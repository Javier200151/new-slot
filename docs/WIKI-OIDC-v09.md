# Squad ALPHA BookStack — v09 Plana Mayor Admin

## Scope
One-time BookStack 25.07.x CLI helper. Creates a *new* OIDC-linked Plana Mayor account using the locked Squad ALPHA ID `sqa-user-1` and gives it BookStack's existing `Admin` role. It does not edit existing accounts, roles or document permissions. It does not enable OIDC or change Docker Compose.

Designed for the verified BookStack schema: `users` has mandatory `name,email,password,external_auth_id,slug` and defaults for `email_confirmed,image_id`. A strong random password is hashed, discarded and never supplied to operators. Before OIDC activation, this new account is not intended to log in by BookStack's local password form.

## Preparation
Keep existing backup `/home/gop/bookstack-backups/20261014-180215` and ideally validate by restoring to an isolated test system. Copy `bookstack-tools/create-plana-mayor-admin.php` into a private server directory, then into the container's `/tmp`.

```bash
sudo docker cp /home/gop/samba/Bookstack/wiki-oidc/bookstack-tools/create-plana-mayor-admin.php bookstack:/tmp/create-plana-mayor-admin.php
sudo docker exec bookstack php -l /tmp/create-plana-mayor-admin.php
sudo docker exec bookstack php /tmp/create-plana-mayor-admin.php --dry-run
```

## Real change (only after inspecting dry-run)

```bash
sudo docker exec bookstack php /tmp/create-plana-mayor-admin.php --create
sudo docker exec bookstack php /tmp/admin-oidc-preflight.php
```

Expected: existing six Admin accounts remain; one additional Plana Mayor account, with `external_auth_id = sqa-user-1` and `Admin`; preflight reports protected linkage ready. Repeating `--dry-run` or `--create` should print `ALREADY READY` and not create a duplicate.

## Notes / security limits
- New account creation uses a restricted DB transaction and BookStack's existing `role_user` pivot, not a public web endpoint. It does not send welcome email or trigger all model events. It is for an operator-supervised one-off and must be verified against the installed BookStack version.
- No plaintext password is shared, and no existing account's password is changed.
- Existing Admin, API and Editor role assignments remain unchanged.
- The special `sqa-user-1` ID is only safe if the Squad ALPHA OIDC issuer enforces protected-admin identity server-side. Confirm before enabling OIDC.
- This **does not** make the old local Admin login available after switching BookStack to OIDC. Maintain an explicit rollback plan for authentication and backups.
- Do not run the `--create` step without an approved backup and a working dry-run. No `AUTH_METHOD` change is part of v09.
