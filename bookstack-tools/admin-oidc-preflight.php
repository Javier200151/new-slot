<?php
/** Read-only BookStack 25.07.x admin/role inventory, run from CLI inside BookStack container.
 * php /tmp/admin-oidc-preflight.php
 * Does not output emails, passwords, hashes, tokens or IDs of ordinary users.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
chdir('/app/www');
require '/app/www/vendor/autoload.php';
$app = require '/app/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection();
$schema = $db->getSchemaBuilder();
foreach (['users', 'roles', 'role_user'] as $table) {
    if (! $schema->hasTable($table)) { fwrite(STDERR, "Missing table: $table\n"); exit(1); }
}
$roleNameColumn = $schema->hasColumn('roles', 'display_name') ? 'display_name' : 'name';
$roles = $db->table('roles')->select('id', $roleNameColumn.' as label')->orderBy('id')->get();
$admins = $roles->filter(fn($role) => mb_strtolower(trim((string)$role->label)) === 'admin');
if ($admins->count() !== 1) { fwrite(STDERR, "Expected exactly one Admin role; found ".$admins->count().".\n"); exit(2); }
$adminId = $admins->first()->id;
$adminUserIds = $db->table('role_user')->where('role_id', $adminId)->pluck('user_id')->unique()->values();
echo "BookStack Admin role ID: $adminId\n";
echo "Current accounts with Admin role: ".$adminUserIds->count()."\n";
echo "Original local Admin (ID 1) retains role: ".($adminUserIds->contains(1) ? 'YES' : 'NO')."\n";
echo "Configured BookStack accounts with an external_auth_id: ".$db->table('users')->whereNotNull('external_auth_id')->where('external_auth_id','!=','')->count()."\n";
$protected = $db->table('users')->where('external_auth_id', 'sqa-user-1')->get(['id']);
echo "Protected SQA Plana Mayor linked identities: ".$protected->count()."\n";
if ($protected->count() === 1) {
    echo "Protected Plana Mayor has Admin role: ".($adminUserIds->contains($protected->first()->id) ? 'YES' : 'NO')."\n";
} else {
    echo "Protected Plana Mayor Admin linkage: NOT READY (expected exactly one)\n";
}
foreach (['Admin', 'API', 'Editor', 'Miembro', 'Public'] as $name) {
    echo "Role {$name}: ".($roles->contains(fn($role) => mb_strtolower(trim((string)$role->label)) === mb_strtolower($name)) ? 'PRESENT' : 'MISSING')."\n";
}
echo "READ ONLY: no user, role or permission changed.\n";
