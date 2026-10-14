<?php
/**
 * Run ONLY inside BookStack's CLI container: php /tmp/link-existing-account.php /secure/path/public.pem
 * No web route, no network service, no password in process argv or environment.
 * Operator assisted: prompted for JWT and existing BookStack password.
 */

declare(strict_types=1);

function fail(string $message): never { fwrite(STDERR, $message."\n"); exit(1); }
function decodeUrl(string $value): string|false {
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $value)) return false;
    return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
}
function promptHidden(string $message): string {
    fwrite(STDERR, $message);
    $tty = is_resource(STDIN) && function_exists('stream_isatty') && stream_isatty(STDIN);
    if (!$tty) fail('Interactive terminal required for hidden password input.');
    $state = shell_exec('stty -g < /dev/tty');
    if (!is_string($state) || trim($state) === '') fail('Cannot configure hidden input.');
    try {
        shell_exec('stty -echo < /dev/tty');
        $line = fgets(STDIN);
    } finally {
        shell_exec('stty '.escapeshellarg(trim($state)).' < /dev/tty');
        fwrite(STDERR, "\n");
    }
    return rtrim((string)$line, "\r\n");
}

if (PHP_SAPI !== 'cli') fail('CLI only.');
if (count($argv) !== 2) fail('Usage: php link-existing-account.php /secure/path/sqa-public.pem');
$keyPath = $argv[1];
if (!is_file($keyPath) || !is_readable($keyPath)) fail('Missing public verification key.');
$public = openssl_pkey_get_public(file_get_contents($keyPath));
if ($public === false) fail('Invalid RSA public key.');
fwrite(STDERR, "Paste SQA linking proof (expires after five minutes): ");
$jwt = trim((string)fgets(STDIN));
$parts = explode('.', $jwt);
if (count($parts) !== 3 || strlen($jwt) > 8192) fail('Invalid proof structure.');
[$h, $p, $s] = $parts;
$header = json_decode(decodeUrl($h) ?: '', true);
$claims = json_decode(decodeUrl($p) ?: '', true);
$signature = decodeUrl($s);
if (!is_array($header) || $header['alg'] !== 'RS256' || !is_array($claims) || !is_string($signature) || openssl_verify($h.'.'.$p, $signature, $public, OPENSSL_ALGO_SHA256) !== 1) fail('Invalid proof signature.');
$now = time();
if (($claims['iss'] ?? null) !== 'https://www.squadalpha.es' || ($claims['aud'] ?? null) !== 'sqa-bookstack-manual-link-v1'
    || !is_int($claims['iat'] ?? null) || !is_int($claims['nbf'] ?? null) || !is_int($claims['exp'] ?? null)
    || $claims['iat'] > $now + 30 || $claims['nbf'] > $now + 30 || $claims['exp'] < $now || $claims['exp'] > $claims['iat'] + 300
    || !preg_match('/^sqa-user-[1-9][0-9]*$/D', (string)($claims['sub'] ?? ''))
    || !filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-f0-9]{32}$/D', (string)($claims['jti'] ?? ''))) {
    fail('Proof expired or invalid claims.');
}
$email = mb_strtolower(trim((string)$claims['email']));
$password = promptHidden('Existing BookStack password (hidden): ');
if ($password === '') fail('Missing password.');

chdir('/app/www');
require '/app/www/vendor/autoload.php';
$app = require '/app/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Built-in system identities are NEVER eligible for ordinary linking.
$specialIds = [1, 2, 40, 41];
$matches = DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->get(['id', 'name', 'email', 'password', 'external_auth_id']);
if ($matches->count() !== 1) fail('No unique existing BookStack account with this email.');
$row = $matches->first();
if (in_array((int)$row->id, $specialIds, true) || in_array(mb_strtolower(trim((string)$row->name)), ['admin','guest','api','miembro sqa'], true)) fail('Protected BookStack service account.');
if ($row->external_auth_id !== null && trim((string)$row->external_auth_id) !== '') fail('BookStack account already linked.');
if (!password_verify($password, (string)$row->password)) fail('Existing BookStack password not valid.');
unset($password);
$sub = (string)$claims['sub'];
$linked = DB::transaction(function () use ($email, $sub, $row, $specialIds) {
    $already = DB::table('users')->where('external_auth_id', $sub)->exists();
    if ($already) return false;
    $account = DB::table('users')->where('id', $row->id)->lockForUpdate()->first(['id','email','external_auth_id']);
    if (!$account || in_array((int)$account->id, $specialIds, true)
        || mb_strtolower(trim((string)$account->email)) !== $email
        || trim((string)$account->external_auth_id) !== '') return false;
    return DB::table('users')->where('id', $row->id)->where(function ($q) {
        $q->whereNull('external_auth_id')->orWhere('external_auth_id', '');
    })->update(['external_auth_id' => $sub]) === 1;
});
if (!$linked) fail('Account changed or subject already linked. No changes made.');
fwrite(STDOUT, 'LINKED: BookStack ID '.$row->id.' -> '.$sub.'. Existing roles and content preserved.'."\n");
