#!/usr/bin/env bash
set -euo pipefail
CONTAINER="${1:-bookstack}"
ISSUER='https://www.squadalpha.es'
echo '== BookStack container reachability =='
docker exec "$CONTAINER" sh -c 'php -r '\''$u="https://www.squadalpha.es/.well-known/openid-configuration";$c=@file_get_contents($u);if($c===false){fwrite(STDERR,"HTTPS discovery fetch failed\n");exit(1);} $j=json_decode($c,true);if(($j["issuer"]??"")!=="https://www.squadalpha.es"){fwrite(STDERR,"Issuer mismatch\n");exit(1);} echo "Discovery HTTPS OK\n";'\'''
echo '== BookStack protected administrator =='
docker exec "$CONTAINER" php /tmp/admin-oidc-preflight.php
echo 'CHECK manually: Plana Mayor linked identities = 1 and Admin = YES; 7 Admins.'
echo 'No changes made.'
