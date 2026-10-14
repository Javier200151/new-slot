# Squad ALPHA / BookStack — v04 (hardening + readiness)

## Scope
- Uses v03 as starting point; all v03 files remain in this patch.
- Serializes authorization-code redemption with a Laravel Cache atomic lock and consumes each code under the lock. A shared cache/lock backend across all app instances is required. The current single PHP container and file cache must use writable, persistent storage.
- Prevents authorizations when the confidential client has no configured secret.
- Adds `php artisan wiki:oidc-readiness`: issuer, keys, secret, cache locking checks (no secret output).
- The provider remains disabled by default. **Do not enable BookStack OIDC yet.**

## Unimplemented blockers (do not activate)
- Manual account linking proving both passwords requires an authenticated, hardened BookStack-side verification bridge (no native BookStack API endpoint for validating a local password). Never export password hashes or send credentials to an external public endpoint.
- Migration of `external_auth_id` for existing BookStack accounts, without merging IDs or losing ownership.
- Setup of Plana Mayor admin in BookStack, preservation of manual Admin/API/Editor assignments, guest/public behavior, restoration path.
- End-to-end OIDC compliance testing (including BookStack callback, nonce, login, session invalidation) and vulnerability review.
- Existing signed-in BookStack sessions may persist after state changes; revocation mechanism needed.

## LOCAL (once files are copied)
```
php artisan optimize:clear
php artisan route:list --path=oauth
php artisan wiki:oidc-preview 1
php artisan wiki:oidc-readiness
```
Readiness can fail locally due to absent RSA keys and confidential client secret; this is expected until configured. Do not commit private key or secrets.

## PRODUCTION (ONLY in a planned, separate, explicitly approved staging deployment)
```
docker exec -it web-php-1 php artisan optimize:clear
docker exec -it web-php-1 php artisan wiki:oidc-readiness
```
Do not enable `WIKI_OIDC_ENABLED` or change BookStack `AUTH_METHOD` with this package. Ensure the env variables are provided to PHP container, and signing keys are backed up securely.
