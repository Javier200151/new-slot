<?php

namespace App\Services\MissionControl;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Website-side API client. Only the Laravel backend can reach Mission Control.
 * All routes are fixed, never provided by the browser; secret is never sent to Livewire.
 */
final class MissionControlClient
{
    private const SERVER_IDS = ['operativos', 'academia', 'academia_vietnam', 'academia_ww2'];

    public function enabled(): bool
    {
        return (bool) config('mission_control.enabled');
    }

    public function isConfigured(): bool
    {
        return $this->enabled()
            && strlen((string) config('mission_control.secret')) >= 32
            && $this->validBaseUrl()
            && $this->readableCertificate();
    }

    public function configError(): ?string
    {
        if (! $this->enabled()) {
            return 'Conexión con SQA Mission Control desactivada.';
        }
        if (! $this->validBaseUrl()) {
            return 'La dirección de Mission Control debe ser HTTPS y estar en la red privada.';
        }
        if (strlen((string) config('mission_control.secret')) < 32) {
            return 'Falta configurar la clave segura de Mission Control.';
        }
        if (! $this->readableCertificate()) {
            return 'No se encuentra el certificado CA de Mission Control en el contenedor PHP.';
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    public function servers(): array
    {
        return $this->request('GET', '/servidores');
    }

    /** @return list<array<string,mixed>> */
    public function missions(): array
    {
        return $this->request('GET', '/misiones');
    }

    /** @return list<array<string,mixed>> */
    public function campaigns(): array
    {
        return $this->request('GET', '/campanas');
    }

    /** @return list<string> */
    public function mods(): array
    {
        return $this->request('GET', '/mods');
    }

    /** @return array<string,mixed> */
    public function health(): array
    {
        return $this->request('GET', '/salud');
    }

    /** @return array<string,mixed> */
    public function checkLaunch(array $payload): array
    {
        return $this->request('POST', '/servidores/operativos/comprobar', $payload);
    }

    /** @return array<string,mixed> */
    public function launch(array $payload): array
    {
        return $this->request('POST', '/servidores/operativos/lanzar', $payload);
    }

    /** @return array<string,mixed> */
    public function command(string $server, string $action, array $payload): array
    {
        $this->assertServer($server);
        if (! in_array($action, ['arrancar', 'parar', 'reiniciar'], true)) {
            throw new MissionControlException('Acción desconocida.', 'accion_no_valida');
        }
        if ($action === 'arrancar' && $server === 'operativos') {
            throw new MissionControlException('Los operativos deben iniciarse desde una actividad.', 'accion_no_valida');
        }

        return $this->request('POST', '/servidores/'.$server.'/'.$action, $payload);
    }

    /** @return array<string,mixed> */
    public function setDailyRestart(string $server, bool $enabled, string $time, array $actor): array
    {
        $this->assertServer($server);
        if ($server === 'operativos' || ! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/D', $time)) {
            throw new MissionControlException('Hora o servidor no válido.', 'peticion_invalida');
        }

        return $this->request('PUT', '/servidores/'.$server.'/reinicio-diario', [
            'activo' => $enabled, 'hora' => $time, 'actor' => $actor,
        ]);
    }

    public static function signature(string $secret, string $time, string $nonce, string $method, string $path, string $body): string
    {
        $canonical = $time."\n".$nonce."\n".strtoupper($method)."\n".$path."\n".hash('sha256', $body);

        return hash_hmac('sha256', $canonical, $secret);
    }

    private function assertServer(string $id): void
    {
        if (! in_array($id, self::SERVER_IDS, true)) {
            throw new MissionControlException('Servidor no reconocido.', 'servidor_no_existe');
        }
    }

    private function validBaseUrl(): bool
    {
        $url = trim((string) config('mission_control.url'));
        $p = parse_url($url);
        if (! is_array($p) || ($p['scheme'] ?? '') !== 'https' || ($p['path'] ?? '') !== '/api/v1'
            || (isset($p['user']) || isset($p['pass'])) || isset($p['query']) || isset($p['fragment']) || empty($p['host'])) {
            return false;
        }
        // No Internet-facing, loopback, or arbitrary hostnames; the server is on VMware's NAT LAN.
        return ($p['host'] ?? '') === '192.168.37.1' && (int) ($p['port'] ?? 443) === 8443;
    }

    private function readableCertificate(): bool
    {
        $path = (string) config('mission_control.ca_certificate');

        return $path !== '' && is_file($path) && is_readable($path);
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        if ($error = $this->configError()) {
            throw new MissionControlException($error, 'sin_configurar');
        }
        $method = strtoupper($method);
        $path = '/api/v1'.$endpoint;
        if (! preg_match('~\A/api/v1/[a-z0-9/_-]+\z~', $path)) {
            throw new MissionControlException('Ruta no autorizada.', 'ruta_no_valida');
        }
        // GETs have an empty byte body for the HMAC. JSON payload uses the same bytes sent over TLS.
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $time = (string) now()->timestamp;
        $nonce = bin2hex(random_bytes(16));
        $headers = [
            'Accept' => 'application/json',
            'X-SQA-Timestamp' => $time,
            'X-SQA-Nonce' => $nonce,
            'X-SQA-Signature' => self::signature((string) config('mission_control.secret'), $time, $nonce, $method, $path, $body),
        ];
        try {
            $pending = Http::timeout((int) config('mission_control.timeout', 5))
                ->connectTimeout(3)
                ->withOptions([
                    'verify' => (string) config('mission_control.ca_certificate'),
                    'allow_redirects' => false,
                ])
                ->withHeaders($headers);
            if ($method !== 'GET') {
                $pending = $pending->withBody($body, 'application/json');
            }
            $response = $pending->send($method, rtrim((string) config('mission_control.url'), '/').$endpoint);
        } catch (Throwable) {
            throw new MissionControlException('No se puede conectar con SQA Mission Control. Revisa el certificado, la red y el servicio.', 'sin_conexion');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new MissionControlException('Respuesta inesperada de Mission Control.', 'respuesta_invalida', $response->status());
        }
        if (! $response->successful()) {
            $error = $json['error'] ?? [];
            $code = is_string($error['codigo'] ?? null) ? $error['codigo'] : 'error_agente';
            $message = is_string($error['mensaje'] ?? null) ? $error['mensaje'] : 'Mission Control ha rechazado la operación.';
            $detail = is_array($error['detalle'] ?? null) ? $error['detalle'] : [];
            throw new MissionControlException(mb_substr($message, 0, 350), $code, $response->status(), $detail);
        }
        if (! array_key_exists('data', $json) || ! is_array($json['data'])) {
            throw new MissionControlException('Mission Control no ha devuelto los datos esperados.', 'respuesta_invalida');
        }

        return $json['data'];
    }
}
