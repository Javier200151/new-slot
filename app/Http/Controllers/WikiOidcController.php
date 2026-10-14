<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Wiki\WikiAccessPolicy;
use App\Services\Wiki\WikiOidcJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Single confidential OIDC client: BookStack. Disabled by default. */
class WikiOidcController extends Controller
{
    private function active(): void
    {
        abort_unless(config('wiki_oidc.enabled'), 404);
    }

    public function discovery(): JsonResponse
    {
        $this->active();
        $issuer = config('wiki_oidc.issuer');
        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/oauth/jwks',
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'claims_supported' => ['sub', 'name', 'preferred_username', 'email', 'email_verified', 'groups'],
            'code_challenge_methods_supported' => ['S256'],
        ])->header('Cache-Control', 'no-store');
    }

    public function jwks(WikiOidcJwt $jwt): JsonResponse
    {
        $this->active();
        return response()->json(['keys' => [$jwt->jwk()]])->header('Cache-Control', 'public, max-age=300');
    }

    public function authorize(Request $request, WikiAccessPolicy $policy): Response
    {
        $this->active();
        // Do not redirect on invalid client or URI: avoids open redirects.
        abort_unless(config('wiki_oidc.client_secret') !== '', 503);
        abort_unless(hash_equals(config('wiki_oidc.client_id'), (string) $request->query('client_id')), 400);
        abort_unless(hash_equals(config('wiki_oidc.redirect_uri'), (string) $request->query('redirect_uri')), 400);
        abort_unless($request->query('response_type') === 'code', 400);
        $scopes = explode(' ', (string) $request->query('scope', ''));
        abort_unless(in_array('openid', $scopes, true), 400);
        abort_unless($request->query('code_challenge_method') === 'S256', 400);
        $challenge = (string) $request->query('code_challenge');
        abort_unless((bool) preg_match('/^[A-Za-z0-9_-]{43}$/D', $challenge), 400);
		$state = (string) $request->query('state');
		$nonce = $request->query('nonce');

		abort_unless(
			strlen($state) >= 16 && strlen($state) <= 512,
			400
		);

		abort_unless(
			$nonce === null ||
			(is_string($nonce) && strlen($nonce) >= 16 && strlen($nonce) <= 512),
			400
		);

        if (! auth()->check()) {
            // Preserve validated authorization query through normal local login.
            $request->session()->put('url.intended', $request->fullUrl());
            return redirect()->route('login');
        }
        $user = auth()->user();
        abort_unless($user instanceof User && $policy->canAuthenticate($user), 403);
        $code = WikiOidcJwt::b64(random_bytes(32));
        Cache::put('wiki_oidc:code:'.hash('sha256', $code), [
            'user_id' => $user->getKey(), 'nonce' => $nonce, 'challenge' => $challenge,
            'redirect_uri' => config('wiki_oidc.redirect_uri'), 'client_id' => config('wiki_oidc.client_id'),
            'auth_time' => time(),
        ], now()->addSeconds(90));
        $query = http_build_query(['code' => $code, 'state' => $state], '', '&', PHP_QUERY_RFC3986);
        return redirect()->away(config('wiki_oidc.redirect_uri').'?'.$query)->header('Cache-Control', 'no-store');
    }

    public function token(Request $request, WikiAccessPolicy $policy, WikiOidcJwt $jwt): JsonResponse
    {
        $this->active();
        $clientId = (string) ($request->getUser() ?: $request->input('client_id', ''));
        $secret = (string) ($request->getPassword() ?: $request->input('client_secret', ''));
        if (config('wiki_oidc.client_secret') === '' || ! hash_equals(config('wiki_oidc.client_id'), $clientId) || ! hash_equals(config('wiki_oidc.client_secret'), $secret)) {
            return $this->error('invalid_client', 401);
        }
        if ($request->input('grant_type') !== 'authorization_code' || $request->input('redirect_uri') !== config('wiki_oidc.redirect_uri')) {
            return $this->error('invalid_grant');
        }
        $code = (string) $request->input('code', '');
        if (! preg_match('/^[A-Za-z0-9_-]{43}$/D', $code)) return $this->error('invalid_grant');
        $key = 'wiki_oidc:code:'.hash('sha256', $code);
        // Serialize redemption, including requests arriving at the same instant.
        // Requires a shared cache/lock backend across all PHP workers.
        $lock = Cache::lock('wiki_oidc:redeem:'.hash('sha256', $code), 10);
        try {
            if (! $lock->get()) return $this->error('invalid_grant');
            $data = Cache::pull($key);
        } finally {
            optional($lock)->release();
        }
        if (! is_array($data) || $data['client_id'] !== $clientId || $data['redirect_uri'] !== $request->input('redirect_uri')) return $this->error('invalid_grant');
        $verifier = (string) $request->input('code_verifier', '');
        if (! preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier) || ! hash_equals($data['challenge'], WikiOidcJwt::b64(hash('sha256', $verifier, true)))) return $this->error('invalid_grant');
        $user = User::find($data['user_id']);
        if (! $user || ! $policy->canAuthenticate($user)) return $this->error('access_denied', 403);
        $now = time();
        $claims = array_merge($this->claims($user, $policy), [
			'iss' => config('wiki_oidc.issuer'),
			'aud' => $clientId,
			'iat' => $now,
			'exp' => $now + 300,
			'auth_time' => min($now, (int) ($data['auth_time'] ?? $now)),
		]);

		if (isset($data['nonce']) && $data['nonce'] !== '') {
			$claims['nonce'] = $data['nonce'];
		}

		$idToken = $jwt->encode($claims);
        $accessToken = WikiOidcJwt::b64(random_bytes(32));
        Cache::put('wiki_oidc:access:'.hash('sha256', $accessToken), $user->getKey(), now()->addMinutes(5));
        return response()->json(['access_token' => $accessToken, 'token_type' => 'Bearer', 'expires_in' => 300, 'id_token' => $idToken, 'scope' => 'openid profile email'])->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }

    public function userinfo(Request $request, WikiAccessPolicy $policy): JsonResponse
    {
        $this->active();
        $header = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer ([A-Za-z0-9_-]+)$/D', $header, $m)) return $this->error('invalid_token', 401);
        $id = Cache::get('wiki_oidc:access:'.hash('sha256', $m[1]));
        $user = $id ? User::find($id) : null;
        if (! $user || ! $policy->canAuthenticate($user)) return $this->error('invalid_token', 401);
        return response()->json($this->claims($user, $policy))->header('Cache-Control', 'no-store');
    }

    private function claims(User $user, WikiAccessPolicy $policy): array
    {
        return [
            'sub' => 'sqa-user-'.$user->getKey(),
            'name' => (string) ($user->nick ?: $user->email),
            'preferred_username' => (string) ($user->nick ?: $user->email),
            'email' => (string) $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'groups' => $policy->oidcGroups($user),
        ];
    }

    private function error(string $error, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $error], $status)->header('Cache-Control', 'no-store');
    }
}
