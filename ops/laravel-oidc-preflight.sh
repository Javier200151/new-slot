#!/usr/bin/env bash
set -euo pipefail
CONTAINER="${1:-web-php-1}"
ISSUER='https://www.squadalpha.es'
echo '== Laravel OIDC readiness =='
docker exec "$CONTAINER" php artisan wiki:oidc-readiness
printf '\n== Public HTTPS discovery ==\n'
curl -fsS --max-time 12 "$ISSUER/.well-known/openid-configuration" | docker exec -i "$CONTAINER" php -r '
$d=json_decode(stream_get_contents(STDIN),true);
if (!is_array($d)) {fwrite(STDERR,"Invalid discovery JSON\n");exit(1);}
foreach (["issuer","authorization_endpoint","token_endpoint","jwks_uri"] as $k) {if (empty($d[$k])) {fwrite(STDERR,"Missing $k\n");exit(1);}}
if ($d["issuer"]!=="https://www.squadalpha.es") {fwrite(STDERR,"Issuer mismatch\n");exit(1);}
echo "Discovery OK\n";
'
printf '\n== Public JWKS ==\n'
curl -fsS --max-time 12 "$ISSUER/oauth/jwks" | docker exec -i "$CONTAINER" php -r '
$d=json_decode(stream_get_contents(STDIN),true);
if (!is_array($d)||empty($d["keys"][0]["n"])||empty($d["keys"][0]["e"])||($d["keys"][0]["alg"]??null)!=="RS256") {fwrite(STDERR,"Bad JWKS\n");exit(1);} echo "JWKS OK\n";
'
printf '\n== Invalid client must fail ==\n'
status=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 12 -X POST "$ISSUER/oauth/token" --data-urlencode 'grant_type=authorization_code' --data-urlencode 'client_id=bookstack' --data-urlencode 'client_secret=invalid' || true)
if [[ "$status" != '401' ]]; then echo "Unexpected token response: HTTP $status" >&2; exit 1; fi
echo 'Invalid client rejected: OK'
echo 'PASS: Initial checks only; NO real browser-based OIDC exchange has been tested.'
