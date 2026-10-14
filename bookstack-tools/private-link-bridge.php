<?php
/** Private BookStack endpoint. MUST be deployed outside webroot and reached only through
 * an exact-match FastCGI route restricted to the SQA server, over authenticated HTTPS.
 * Never use PHP built-in server in public production. Never log request bodies.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $code, string $status): never { http_response_code($code); echo json_encode(['status'=>$status]); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405,'method_not_allowed');
if (($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json') reply(415,'unsupported_media_type');
$secret = trim((string)@file_get_contents('/config/wiki-oidc-link/bridge-secret'));
if (strlen($secret) < 32) reply(503,'not_configured');
$body = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($body) || strlen($body) > 4096) reply(413,'invalid_request');
$ts = (string)($_SERVER['HTTP_X_SQA_TIMESTAMP'] ?? '');
$nonce = (string)($_SERVER['HTTP_X_SQA_NONCE'] ?? '');
$sig = (string)($_SERVER['HTTP_X_SQA_SIGNATURE'] ?? '');
if (!ctype_digit($ts) || abs(time() - (int)$ts) > 60 || !preg_match('/^[a-f0-9]{32}$/D',$nonce) || !preg_match('/^[a-f0-9]{64}$/D',$sig)) reply(401,'unauthorized');
$expected = hash_hmac('sha256',$ts."\n".$nonce."\n".hash('sha256',$body),$secret);
if (!hash_equals($expected,$sig)) reply(401,'unauthorized');
$data = json_decode($body,true);
if (!is_array($data) || !in_array($data['action'] ?? '', ['link','status'],true) || !preg_match('/^sqa-user-[1-9][0-9]*$/D',(string)($data['sub']??'')) || !filter_var($data['email']??null,FILTER_VALIDATE_EMAIL)) reply(400,'invalid_request');
chdir('/app/www');
require '/app/www/vendor/autoload.php';
$app=require '/app/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
// atomic replay marker; cache must support add() with global shared storage
if (!Cache::add('sqa_link_nonce:'.$nonce,1,now()->addMinutes(2))) reply(409,'replayed');
$sub=$data['sub']; $email=mb_strtolower(trim($data['email']));
if ($sub === 'sqa-user-1') reply(403,'protected');
$matches=DB::table('users')->whereRaw('LOWER(TRIM(email)) = ?',[$email])->get(['id','name','password','external_auth_id']);
if ($data['action']==='status') {
    $linked=DB::table('users')->where('external_auth_id',$sub)->whereRaw('LOWER(TRIM(email)) = ?',[$email])->exists();
    reply(200,$linked?'linked':'not_linked');
}
if (count($matches)!==1) reply(200,'not_eligible');
$user=$matches->first();
if (in_array((int)$user->id,[1,2,40,41],true) || in_array(mb_strtolower((string)$user->name),['admin','guest','api','miembro sqa'],true)) reply(200,'not_eligible');
if (trim((string)$user->external_auth_id)===$sub) reply(200,'already_linked');
if (trim((string)$user->external_auth_id)!=='') reply(200,'not_eligible');
$password=(string)($data['password']??'');
if ($password==='' || strlen($password)>1024 || !password_verify($password,(string)$user->password)) reply(200,'not_eligible');
unset($password);
$ok=DB::transaction(function() use($email,$sub,$user): bool {
    $row=DB::table('users')->where('id',$user->id)->lockForUpdate()->first(['id','email','external_auth_id']);
    if (!$row || mb_strtolower(trim((string)$row->email))!==$email || trim((string)$row->external_auth_id)!=='' || DB::table('users')->where('external_auth_id',$sub)->exists()) return false;
    return DB::table('users')->where('id',$row->id)->where(function($q) { $q->whereNull('external_auth_id')->orWhere('external_auth_id',''); })->update(['external_auth_id'=>$sub])===1;
});
reply(200,$ok?'linked':'not_eligible');
