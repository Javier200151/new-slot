<?php
namespace App\Services\Wiki;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikiLinkClient
{
    public function enabled(): bool
    {
        return config('wiki_link.enabled') && strlen((string) config('wiki_link.shared_secret')) >= 32;
    }

    public function call(string $action, string $subject, string $email, ?string $password = null, ?array $groups = null): array
    {
        if (! $this->enabled()) throw new RuntimeException('La vinculación de Wiki todavía no está disponible.');
        $url = (string) config('wiki_link.endpoint');
        if (! str_starts_with($url, 'https://')) throw new RuntimeException('Wiki link requires HTTPS.');
        $body = json_encode(array_filter([
            'action' => $action, 'sub' => $subject, 'email' => mb_strtolower(trim($email)),
            'password' => $password, 'groups' => $groups,
        ], fn ($v) => $v !== null), JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $timestamp."\n".$nonce."\n".hash('sha256', $body), (string) config('wiki_link.shared_secret'));
        $caCertificate = (string) config('wiki_link.ca_certificate');

		if (! is_file($caCertificate) || ! is_readable($caCertificate)) {
			throw new RuntimeException('No se encuentra el certificado CA de Wiki Link.');
		}

		$response = Http::timeout(8)
			->withOptions([
				'verify' => $caCertificate,
			])
			->withHeaders([
				'Content-Type' => 'application/json', 'X-SQA-Timestamp' => $timestamp,
				'X-SQA-Nonce' => $nonce, 'X-SQA-Signature' => $signature,
			])->withBody($body, 'application/json')->post($url);
        if (! $response->ok() || ! is_array($response->json())) {
            throw new RuntimeException('No se ha podido verificar la cuenta de la Wiki. Inténtalo después.');
        }
        return $response->json();
    }
}
