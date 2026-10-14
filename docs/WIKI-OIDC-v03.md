# Squad ALPHA / BookStack — v03 (OIDC provider groundwork)

## Scope
- One confidential OIDC client (`bookstack`) with fixed callback.
- OIDC discovery, RS256 JWK, authorization code + S256 PKCE, 90s one-time code, 5-minute ID/access tokens, userinfo.
- State and nonce checking, stable `sub=sqa-user-{id}`, verified email and status authorization via WikiAccessPolicy.
- Protected Plana Mayor authorization and SQA group claims (for *later* mapping).
- No changes to BookStack, no migrations, no account linking, no BookStack role mutations.

## Important limitations / DO NOT ENABLE IN PRODUCTION
1. There is **no manual account linking** yet. Enabling BookStack's AUTH_METHOD=oidc now risks duplicate accounts and wrong roles.
2. BookStack requires native OIDC in place of standard login; Admin/API/Editor handling and recovery have not been integrated end-to-end.
3. The Laravel public login now uses `redirect()->intended('/')` to resume OIDC browser authorization after login. Test modal and standard login regression.
4. End-to-end Laravel/OIDC protocol tests, live BookStack interoperability and security audit are still required. PHP syntax validation alone is not those tests.
5. The authorization-code store requires a shared cache backend across all PHP instances and strong single-consumption semantics. Before enabling, implement/use atomic one-time code consumption and verify cluster behavior.
6. Group claims are informational in v03. Keep BookStack OIDC_USER_TO_GROUPS disabled until managed-role synchronization with protected manual-role behavior is implemented.
7. Keep WIKI_OIDC_ENABLED=false. Do not add AUTH_METHOD=oidc to BookStack.

## Local steps (from Laravel project directory)
1. Overlay ZIP paths, replacing v02 policy and two existing project files (`routes/web.php`, `bootstrap/app.php`, `PublicLoginController.php`). Review any intervening changes first.
2. `php artisan optimize:clear`
3. `php artisan route:list --path=oauth`
4. `php artisan wiki:oidc-preview 154`
5. Optionally create keys *locally* using `php artisan wiki:oidc-key-generate` but never commit generated private keys.

## Future configuration (NOT ACTIVE NOW)
```
WIKI_OIDC_ENABLED=false
WIKI_OIDC_ISSUER=https://www.squadalpha.es
WIKI_OIDC_CLIENT_ID=bookstack
WIKI_OIDC_CLIENT_SECRET=<random secret stored only server-side>
WIKI_OIDC_REDIRECT_URI=https://wiki.squadalpha.es/oidc/callback
WIKI_OIDC_PRIVATE_KEY_PATH=<absolute path outside public web root>
WIKI_OIDC_PUBLIC_KEY_PATH=<absolute path outside public web root>
WIKI_OIDC_KEY_ID=wiki-key-v1
```

Do not store production secrets, user passwords, or RSA private keys in repository/ZIP. The app must be reachable at the exact issuer domain (including www) over HTTPS. BookStack domain remains https://wiki.squadalpha.es.

## Next block
- Verify BookStack legacy password securely using a temporary least-privilege remote verification flow; do not export password hashes or transmit passwords to the Laravel server for storage.
- Link identities to existing BookStack `users.external_auth_id` after explicit manual dual-password verification and email equality.
- Preserve old accounts' local roles; ensure Admin/API/Editor not removed by role synchronization; separately make protected Plana Mayor full Admin.
- Implement missing and negative integration tests, then controlled deployment and rollback.
