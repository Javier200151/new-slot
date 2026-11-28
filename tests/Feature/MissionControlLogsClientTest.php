<?php

namespace Tests\Feature;

use App\Services\MissionControl\MissionControlClient;
use App\Services\MissionControl\MissionControlException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MissionControlLogsClientTest extends TestCase
{
    private string $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->certificate = tempnam(sys_get_temp_dir(), 'sqa-ca-');
        file_put_contents($this->certificate, "test CA certificate\n");
        config()->set('mission_control.enabled', true);
        config()->set('mission_control.url', 'https://192.168.37.1:8443/api/v1');
        config()->set('mission_control.secret', str_repeat('s', 40));
        config()->set('mission_control.ca_certificate', $this->certificate);
    }

    protected function tearDown(): void
    {
        @unlink($this->certificate);
        parent::tearDown();
    }

    public function test_catalog_requests_are_signed_and_server_is_whitelisted(): void
    {
        Http::fake(['*' => Http::response(['data' => ['fuentes' => []]], 200)]);
        $data = app(MissionControlClient::class)->logs('academia');
        $this->assertSame(['fuentes' => []], $data);

        Http::assertSent(function (Request $request): bool {
            $path = '/api/v1/servidores/academia/logs';
            $signature = MissionControlClient::signature(
                str_repeat('s', 40),
                (string) $request->header('X-SQA-Timestamp'),
                (string) $request->header('X-SQA-Nonce'),
                'GET', $path, '',
            );

            return $request->url() === 'https://192.168.37.1:8443'.$path
                && $request->method() === 'GET'
                && $signature === $request->header('X-SQA-Signature');
        });
    }

    public function test_log_chunks_allow_uppercase_and_dots_and_sign_path_without_query(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'texto' => 'Error example', 'fin' => 223, 'reiniciado' => false, 'mas' => false,
        ]], 200)]);
        $file = 'arma3server_x64_2026-11-26_17-48-41.rpt';
        $data = app(MissionControlClient::class)->logChunk('academia', 'HC1', $file, 123);
        $this->assertSame(223, $data['fin']);

        Http::assertSent(function (Request $request) use ($file): bool {
            $path = '/api/v1/servidores/academia/logs/HC1/'.$file;
            $signature = MissionControlClient::signature(
                str_repeat('s', 40),
                (string) $request->header('X-SQA-Timestamp'),
                (string) $request->header('X-SQA-Nonce'),
                'GET', $path, '',
            );

            return $request->url() === 'https://192.168.37.1:8443'.$path.'?desde=123&max=204800'
                && $signature === $request->header('X-SQA-Signature');
        });
    }

    public function test_first_log_read_has_no_offset_and_only_the_fixed_chunk_size(): void
    {
        Http::fake(['*' => Http::response(['data' => [
            'texto' => 'Tail', 'fin' => 500, 'mas' => false,
        ]], 200)]);

        $data = app(MissionControlClient::class)->logChunk('academia', 'HC1', 'archivo.rpt');
        $this->assertSame('Tail', $data['texto']);
        Http::assertSent(fn (Request $request): bool =>
            str_ends_with($request->url(), '/api/v1/servidores/academia/logs/HC1/archivo.rpt?max=204800')
            && ! str_contains($request->url(), 'desde='));
    }

    public function test_invalid_server_source_filename_and_byte_positions_do_not_make_requests(): void
    {
        Http::fake();
        $client = app(MissionControlClient::class);
        $bad = [
            fn () => $client->logs('otra_maquina'),
            fn () => $client->logChunk('otro', 'HC1', 'test.rpt'),
            fn () => $client->logChunk('academia', '../HC1', 'test.rpt'),
            fn () => $client->logChunk('academia', 'HC1', '../test.rpt'),
            fn () => $client->logChunk('academia', 'HC1', 'test.log'),
            fn () => $client->logChunk('academia', 'HC1', 'test..rpt'),
            fn () => $client->logChunk('academia', 'HC1', 'test.rpt', -1),
            fn () => $client->downloadLog('academia', 'HC1', '../test.rpt'),
        ];
        foreach ($bad as $call) {
            try {
                $call();
                $this->fail('An invalid Mission Control log path was accepted.');
            } catch (MissionControlException $e) {
                $this->assertContains($e->errorCode, ['servidor_no_existe', 'peticion_invalida']);
            }
        }
        Http::assertNothingSent();
    }

    public function test_missing_file_error_code_is_preserved(): void
    {
        Http::fake(['*' => Http::response([
            'error' => ['codigo' => 'log_no_existe', 'mensaje' => 'Archivo no encontrado'],
        ], 404)]);

        try {
            app(MissionControlClient::class)->logChunk('academia', 'HC1', 'missing.rpt');
            $this->fail('Expected MissionControlException.');
        } catch (MissionControlException $e) {
            $this->assertSame('log_no_existe', $e->errorCode);
            $this->assertSame(404, $e->httpStatus);
        }
    }

    public function test_download_uses_plain_text_stream_not_json(): void
    {
        Http::fake(['*' => Http::response("RPT log\n-password=***\n", 200, ['Content-Type' => 'text/plain'])]);

        $stream = app(MissionControlClient::class)->downloadLog('academia', 'HC1', 'old_2026-11-26.rpt');
        $this->assertSame("RPT log\n-password=***\n", $stream->getContents());
        $stream->close();

        Http::assertSent(fn (Request $request): bool =>
            $request->method() === 'GET'
            && str_ends_with($request->url(), '/api/v1/servidores/academia/logs/HC1/old_2026-11-26.rpt/descargar')
            && $request->header('X-SQA-Signature') === MissionControlClient::signature(
                str_repeat('s', 40),
                (string) $request->header('X-SQA-Timestamp'),
                (string) $request->header('X-SQA-Nonce'),
                'GET',
                '/api/v1/servidores/academia/logs/HC1/old_2026-11-26.rpt/descargar',
                '',
            ),
        );
    }
}
