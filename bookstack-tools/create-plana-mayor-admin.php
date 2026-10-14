<?php
/**
 * Squad ALPHA: One-time BookStack 25.07.x Plana Mayor administrative identity.
 * CLI ONLY. Run inside BookStack container, with --dry-run first.
 * Intentionally creates an OIDC-only credential (random unknown password hash).
 * This never changes existing users or grants Admin to an existing account.
 */
declare(strict_types=1);

function stop(string $message, int $code = 1): never { fwrite(STDERR, "STOP: {$message}\n"); exit($code); }
if (PHP_SAPI !== 'cli') stop('CLI only.');
if (count($argv) !== 2 || !in_array($argv[1], ['--dry-run', '--create'], true)) {
    stop('Usage: php create-plana-mayor-admin.php --dry-run | --create');
}
$dry = $argv[1] === '--dry-run';
const TARGET_EMAIL = 'planamayorsquadalpha@gmail.com';
const TARGET_SUB = 'sqa-user-1';
const TARGET_NAME = 'Plana Mayor Squad ALPHA';
const TARGET_SLUG = 'plana-mayor-squad-alpha';

chdir('/app/www');
require '/app/www/vendor/autoload.php';
$app = require '/app/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection();
$schema = $db->getSchemaBuilder();
foreach (['users', 'roles', 'role_user'] as $table) {
    if (!$schema->hasTable($table)) stop('Missing BookStack table: '.$table);
}
foreach (['name','email','password','email_confirmed','image_id','external_auth_id','slug'] as $field) {
    if (!$schema->hasColumn('users', $field)) stop('Unexpected users schema: missing '.$field);
}
$roleColumn = $schema->hasColumn('roles', 'display_name') ? 'display_name' : 'name';
if (!$schema->hasColumn('roles', $roleColumn)) stop('Role name column not found.');
$adminRoles = $db->table('roles')->whereRaw('LOWER(TRIM('.$roleColumn.')) = ?', ['admin'])->get(['id']);
if ($adminRoles->count() !== 1) stop('Expected exactly one Admin role.');
$roleId = (int)$adminRoles->first()->id;

function inspect($db, int $roleId): array {
    $byEmail = $db->table('users')->whereRaw('LOWER(TRIM(email)) = ?', [TARGET_EMAIL])->get(['id','name','email','external_auth_id']);
    $bySub = $db->table('users')->where('external_auth_id', TARGET_SUB)->get(['id','email']);
    $bySlug = $db->table('users')->where('slug', TARGET_SLUG)->get(['id']);
    if ($byEmail->count() > 1 || $bySub->count() > 1 || $bySlug->count() > 1) stop('Ambiguous existing identity or slug.');
    if ($byEmail->count() || $bySub->count() || $bySlug->count()) {
        if ($byEmail->count() !== 1 || $bySub->count() !== 1 || (int)$byEmail->first()->id !== (int)$bySub->first()->id) {
            stop('Email / external_auth_id mismatch, or reserved slug. Refusing changes.');
        }
        $user = $byEmail->first();
        if ((string)$user->external_auth_id !== TARGET_SUB) stop('Existing user not linked to expected subject.');
        if ($bySlug->count() && (int)$bySlug->first()->id !== (int)$user->id) stop('Slug belongs to another user.');
        $hasRole = $db->table('role_user')->where('user_id', $user->id)->where('role_id', $roleId)->exists();
        if (!$hasRole) stop('Existing Plana Mayor identity lacks Admin. Manual review needed; no auto-escalation.');
        return ['exists' => true, 'id' => (int)$user->id];
    }
    return ['exists' => false, 'id' => null];
}

$existing = inspect($db, $roleId);
$adminCountBefore = $db->table('role_user')->where('role_id', $roleId)->distinct()->count('user_id');
$originalAdmin = $db->table('role_user')->where('role_id', $roleId)->where('user_id', 1)->exists();
if (!$originalAdmin) stop('Original BookStack Admin ID 1 no longer has Admin role. Investigate first.');
echo "Admin role ID: {$roleId}\nExisting Admin accounts: {$adminCountBefore}\n";
if ($existing['exists']) {
    echo "ALREADY READY: protected BookStack user ID {$existing['id']} is linked to ".TARGET_SUB." and has Admin. No changes made.\n";
    exit(0);
}
if ($dry) {
    echo "DRY RUN OK: would create a new OIDC-only BookStack account with the protected Plana Mayor email and subject; assign only Admin. No database changes.\n";
    exit(0);
}
// Shared MariaDB advisory lock also coordinates multiple simultaneous CLI invocations.
$lockName = 'sqa_bookstack_plana_mayor_v09';
$lock = $db->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
if ((int)($lock->acquired ?? 0) !== 1) stop('Could not acquire migration lock.');
try {
    $newId = $db->transaction(function () use ($db, $roleId) {
        // Recheck under the advisory lock immediately before writing.
        $state = inspect($db, $roleId);
        if ($state['exists']) return $state['id'];
        // The random password is never disclosed or stored in application code.
        // Users authenticate via OIDC once BookStack AUTH_METHOD is switched.
        $unrecoverablePassword = bin2hex(random_bytes(48));
        $hash = password_hash($unrecoverablePassword, PASSWORD_BCRYPT);
        unset($unrecoverablePassword);
        if (!is_string($hash) || strlen($hash) > 60) stop('Cannot generate password hash.');
        $now = date('Y-m-d H:i:s');
        $id = $db->table('users')->insertGetId([
            'name' => TARGET_NAME,
            'email' => TARGET_EMAIL,
            'password' => $hash,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'email_confirmed' => 1,
            'image_id' => 0,
            'external_auth_id' => TARGET_SUB,
            'system_name' => null,
            'slug' => TARGET_SLUG,
        ]);
        $db->table('role_user')->insert(['user_id' => $id, 'role_id' => $roleId]);
        return (int)$id;
    });
    $verified = inspect($db, $roleId);
    if (!$verified['exists'] || $verified['id'] !== $newId) stop('Post-write verification failed. Stop and investigate.');
    $adminCountAfter = $db->table('role_user')->where('role_id', $roleId)->distinct()->count('user_id');
    if ($adminCountAfter !== $adminCountBefore + 1) stop('Unexpected Admin account count. Stop and investigate.');
    if (!$db->table('role_user')->where('role_id', $roleId)->where('user_id', 1)->exists()) stop('Original Admin role unexpectedly changed.');
    echo "CREATED: Protected Plana Mayor BookStack ID {$newId} -> ".TARGET_SUB."; assigned Admin. Previous administrators unchanged.\n";
} finally {
    $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
}
