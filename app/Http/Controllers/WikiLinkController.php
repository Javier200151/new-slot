<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Wiki\WikiAccessPolicy;
use App\Services\Wiki\WikiLinkClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class WikiLinkController extends Controller
{
    public function status(Request $request, WikiAccessPolicy $policy, WikiLinkClient $client): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $policy->canAuthenticate($user)) abort(403);
        try {
            $result = $client->call('status', 'sqa-user-'.$user->getKey(), (string) $user->email);
            if ($request->expectsJson()) {
                return response()->json(['status' => match ($result['status'] ?? '') {
                    'linked' => 'linked',
                    'pending_link' => 'pending_link',
                    default => 'unlinked',
                }])
                    ->header('Cache-Control', 'no-store, private');
            }
            return back()->with('wiki_link_notice', ($result['status'] ?? '') === 'linked'
                ? 'Tu cuenta de Wiki ya está vinculada.'
                : 'Si tenías una cuenta antigua en la Wiki, vincúlala antes de entrar.')
                ->withFragment('profile-linked-accounts');
        } catch (RuntimeException $e) {
            report($e);
            if ($request->expectsJson()) {
                return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store, private');
            }
            return back()->withErrors(['wiki' => 'No se pudo comprobar la vinculación.'])->withFragment('profile-linked-accounts');
        }
    }

    public function link(Request $request, WikiAccessPolicy $policy, WikiLinkClient $client): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $policy->canAuthenticate($user) || $policy->isSuperAdmin($user)) abort(403);
        $validated = $request->validate([
            'sqa_password' => ['required', 'string', 'max:1024'],
            'bookstack_password' => ['required', 'string', 'max:1024'],
        ]);
        if (! Hash::check($validated['sqa_password'], (string) $user->password)) {
            return back()->withErrors(['wiki' => 'No se pudo verificar la identidad.'])->withFragment('profile-linked-accounts');
        }
        try {
            $result = $client->call('link', 'sqa-user-'.$user->getKey(), (string) $user->email, $validated['bookstack_password']);
            if (($result['status'] ?? '') === 'linked' || ($result['status'] ?? '') === 'already_linked') {
                Cache::forget('wiki_link:pending_profile:'.$user->getKey());
                return back()->with('wiki_link_notice', 'Cuenta de Wiki vinculada correctamente.')->withFragment('profile-linked-accounts');
            }
            return back()->withErrors(['wiki' => 'No se pudo vincular. Revisa las credenciales de la Wiki.'])->withFragment('profile-linked-accounts');
        } catch (RuntimeException $e) {
            report($e);
            return back()->withErrors(['wiki' => 'El servicio de vinculación no está disponible temporalmente.'])->withFragment('profile-linked-accounts');
        }
    }
}
