# Squad ALPHA / BookStack — v10: preflight + controlled activation

This ZIP is operations-only: it does not alter PHP/Laravel/BookStack code and does not enable OIDC automatically.

## IMPORTANT SECURITY GATES

1. Confirm the existing backups; retain a tested recovery path. The SQL dump was completed and TAR passed listing, but no isolated restore test has been done.
2. Confirm issuer HTTPS discovery and JWKS work from a BookStack container and from external users.
3. OIDC provider must be enabled in Laravel before running the preflight scripts, but changing `WIKI_OIDC_ENABLED=true` is a real security-exposure step. Review the custom OIDC implementation before enabling. It has not been independently security-audited. It should not be considered production-ready on the strength of route listing or syntax checks.
4. Confirm manual BookStack admin grants are preserved; phase-1 configuration MUST use OIDC_USER_TO_GROUPS=false and OIDC_REMOVE_FROM_GROUPS=false. Enabling group auto-sync is a distinct later task.
5. Ensure the Laravel `WIKI_OIDC_CLIENT_SECRET` and the BookStack `OIDC_CLIENT_SECRET` are **identical**, random, and >= 32 characters. Do not commit either to Git.
6. Verify `WIKI_OIDC_REDIRECT_URI=https://wiki.squadalpha.es/oidc/callback` and CLIENT_ID=bookstack.
7. The current homegrown IdP still needs a browser end-to-end check, a security review of replay/concurrency, subject handling, errors, prompt/consent semantics, and verification of persistent secrets before use for real users. Do not activate based on static preflight alone.

## Controlled BookStack configuration (example fragment ONLY)

Add these variables to the `bookstack:` environment in `/home/gop/samba/Bookstack/docker-compose.yml`:

```yaml
      - AUTH_METHOD=oidc
      - AUTH_AUTO_INITIATE=false
      - OIDC_NAME=Squad ALPHA
      - OIDC_CLIENT_ID=bookstack
      - OIDC_CLIENT_SECRET=${BOOKSTACK_OIDC_CLIENT_SECRET}
      - OIDC_ISSUER=https://www.squadalpha.es
      - OIDC_ISSUER_DISCOVER=true
      - OIDC_DISPLAY_NAME_CLAIMS=name
      - OIDC_USER_TO_GROUPS=false
      - OIDC_REMOVE_FROM_GROUPS=false
      - OIDC_END_SESSION_ENDPOINT=false
```

For `BOOKSTACK_OIDC_CLIENT_SECRET` use a server-side `.env` kept outside Git and matching Laravel's WIKI_OIDC_CLIENT_SECRET. Verify no conflicting `AUTH_METHOD` or OIDC keys in `/config/www/.env`. Check rendered configuration (`docker compose config --no-interpolate` or cautiously `docker compose config` but NEVER publish expanded output with secrets). Docker Compose normally recreates only the BookStack container upon `docker compose up -d bookstack`; do not run it until the activation gate.

## When to start the 40-minute maintenance window

**NOT YET.** Start the 40-minute window only after provider is security-reviewed, confidential client credentials are set and verified, both HTTPS preflights pass, BookStack OIDC configuration is staged, a revert procedure is at hand, and the operator authorizes the switch. **The actual change of `AUTH_METHOD` and recreation of BookStack marks the start of the 40 minutes.**

## Rollback outline (prepare BEFORE activation)

1. Save the original `docker-compose.yml` and `.env` outside Git with mode 600 (do not disclose them).
2. If login fails, revert `AUTH_METHOD` to its prior setting (typically `standard`, but confirm the actual value first), remove temporary OIDC fields, and redeploy `docker compose up -d bookstack`.
3. Run `docker compose ps`, inspect `docker logs --tail 80 bookstack` after redacting secrets, and test normal login in a private/incognito session.
4. Rollback normally does not require restoring the database for a method switch. Restore backed-up DB/files ONLY when data was actually altered and the operator has a tested restoration plan. Record any OIDC-created accounts before a DB restore.

## Acceptance checks during maintenance

- Public wiki pages are accessible anonymously.
- Rylod (`sqa-user-154`) opens BookStack user 38 without duplicate; retains prior roles.
- Plana Mayor (`sqa-user-1`) opens user 51 and can administer.
- A disallowed/unverified SQA account cannot authenticate.
- Manual `Admin`, `API`, `Editor` assignments remain untouched.
- A never-linked user may be blocked until linking; this is explicitly accepted for the maintenance window.
- No login form should solicit the old BookStack password once using OIDC.
