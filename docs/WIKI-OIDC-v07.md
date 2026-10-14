# Squad ALPHA / BookStack — v07 (piloto reforzado)

## Alcance

**Actualización incremental sobre v06**, únicamente cambia `bookstack-tools/link-existing-account.php`. No cambia Laravel ni sus rutas, no añade ninguna pantalla de usuario y **NO activa OIDC**. La utilidad se ejecuta exclusivamente mediante CLI dentro del contenedor BookStack; **nunca alojarla en un directorio web**.

### Cambios respecto a v06

- `--dry-run`: valida la prueba firmada, coincidencia única de correo, contraseña antigua, exclusiones y colisiones de identificadores **sin modificar usuarios y sin consumir el justificante**.
- Ledger de `jti` persistente en `/config/wiki-oidc-link/consumed-jti.tsv` con permisos restrictivos y `flock` exclusivo para evitar reutilizaciones y serializar intentos con la misma utilidad.
- Segunda comprobación de vinculación y bloqueo `FOR UPDATE` durante la escritura; solo actualiza `users.external_auth_id`.
- Conserva exclusión de IDs técnicos 1, 2, 40, 41 y nombres Admin, Guest, API y Miembro SQA; **Plana Mayor no se vincula por el flujo ordinario**.
- Si fallase escribir el ledger después de actualizar la base de datos, el comando declara un estado de atención manual; **no repetir ciegamente**. El mismo `sub` no podrá vincularse a una segunda cuenta mediante este script.

## Limitaciones explícitas

- La herramienta sigue siendo una **prueba asistida** para una cuenta propia de ensayo, no un autoservicio para miembros. No dar acceso SSH ni Docker a otros usuarios para pedirles sus contraseñas. No compartir pruebas JWT, contraseñas o claves privadas.
- Solo está preparada para BookStack 25.07.2 identificado en la conversación; revisar funcionamiento real en entorno controlado antes de hacer escrituras.
- El bloqueo de ledger serializa ejecuciones de **esta utilidad**; no coordina cambios hechos por otros sistemas ni sustituye una restricción única a nivel BD. Exige conservar `/config/wiki-oidc-link/` en respaldos.
- `--dry-run` solo garantiza ausencia de escrituras de **usuarios** por parte de esta utilidad; crea el ledger vacío si no existía.
- La comprobación de la contraseña utiliza `password_verify` con el hash de BookStack; si el formato histórico no fuera compatible, fallará de forma segura.
- El backup creado previamente no se ha restaurado en un entorno aislado; verificar su recuperación antes de una migración masiva.

## Preparación (en servidor Ubuntu BookStack, no Laravel)

1. Tener un backup válido antes de operar. No cambiar `AUTH_METHOD`.
2. Copiar el archivo PHP de `bookstack-tools/` al host, y de allí a `/tmp/link-existing-account.php` dentro del contenedor BookStack. No dejarlo accesible desde web.
3. Transferir exclusivamente `public.pem` de Laravel por canal seguro, nunca `private.pem`. Copiarlo dentro de BookStack a `/tmp/sqa-public.pem`.
4. Realizar primero el piloto **solo** con una cuenta ordinaria propia, con correo verificado y estado autorizado, que NO tenga roles de administración.
5. En Laravel, emitir prueba mediante `docker exec -it web-php-1 php artisan wiki:issue-link-proof ID` (requiere contraseña de Squad ALPHA, no compartir el JWT).
6. En BookStack, ejecutar **simulación**:

```sh
sudo docker exec -it bookstack php /tmp/link-existing-account.php /tmp/sqa-public.pem --dry-run
```

El programa pide el JWT y la contraseña antigua de BookStack. Si resulta `DRY RUN OK`, emitir **una prueba nueva** desde Laravel para la ejecución real (el plazo es 5 minutos), y ejecutar **solo con autorización explícita**:

```sh
sudo docker exec -it bookstack php /tmp/link-existing-account.php /tmp/sqa-public.pem
```

Luego verificar el `external_auth_id`, los roles, el ID y la autoría por consultas de solo lectura. El método de login de BookStack permanece intacto.

## Comprobaciones básicas tras copiar v07

```sh
sudo docker exec bookstack php -l /tmp/link-existing-account.php
sudo docker compose ps
```

El cambio no lleva comandos `php artisan` nuevos ni migraciones para Laravel.

## Recuperación

Si se modifica una vinculación equivocada, **no borrar la cuenta BookStack**. Con la vinculación registrada y una copia de seguridad, preparar un cambio inverso específico revisado para ese ID. No utilizar un restore completo sin evaluar los cambios posteriores al backup.
