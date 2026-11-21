<?php

namespace Tests\Unit;

use App\Services\MissionControl\MissionControlClient;
use PHPUnit\Framework\TestCase;

class MissionControlSignatureTest extends TestCase
{
    public function test_hmac_includes_method_path_nonce_and_exact_body(): void
    {
        $secret = str_repeat('s', 32);
        $time = '1795284000';
        $nonce = '0123456789abcdef0123456789abcdef';
        $path = '/api/v1/servidores/operativos/lanzar';
        $body = '{"actividad":{"id":215,"nombre":"DEATH VALLEY"}}';
        $canonical = $time."\n".$nonce."\nPOST\n".$path."\n".hash('sha256', $body);
        $expected = hash_hmac('sha256', $canonical, $secret);

        $this->assertSame($expected, MissionControlClient::signature($secret, $time, $nonce, 'post', $path, $body));
        $this->assertNotSame($expected, MissionControlClient::signature($secret, $time, $nonce, 'GET', $path, $body));
        $this->assertNotSame($expected, MissionControlClient::signature($secret, $time, $nonce, 'POST', '/api/v1/servidores/operativos/parar', $body));
    }
}
