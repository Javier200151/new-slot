# Squad ALPHA ↔ BookStack — v05: inventario seguro de vinculación

La v05 añade un comando de auditoría **solo lectura**. No implementa ni simula la comprobación de la contraseña antigua de BookStack; no modifica ninguna base de datos y no habilita OIDC. Conserva todos los archivos de la v04.

## Inventario en Ubuntu BookStack

Exporta las cuentas BookStack a un fichero local, fuera del directorio web, y protégelo. Ejemplo (se ejecuta en el HOST de BookStack):

```bash
sudo docker exec bookstack sh -c 'cd /app/www && php artisan tinker --execute='\''echo DB::table("users")->select("id","name","email","external_auth_id")->orderBy("id")->get()->toJson();'\''' > /root/bookstack-user-inventory.json
sudo chmod 600 /root/bookstack-user-inventory.json
```

**Atención:** el fichero contiene correos personales. No lo publiques en chats, tickets o Git. Copiarlo exclusivamente al servidor Laravel por un canal seguro (ej. `scp`) y borrarlo de ambos hosts al terminar. Evitar compartirlo entre servidores si no se puede custodiar.

## Auditar en Laravel, sin escribir datos

```bash
php artisan wiki:bookstack-link-audit /ruta/segura/bookstack-user-inventory.json
# Opcional: muestra únicamente IDs, sin correos
php artisan wiki:bookstack-link-audit /ruta/segura/bookstack-user-inventory.json --details
```

Clasificación: `special` (Admin/Guest/Miembro SQA/API reservados), `already_linked`, `verified_candidate`, `not_verified`, `not_authorized`, `no_match`, `ambiguous`. La clasificación no equivale a una vinculación: hasta acreditar ambas contraseñas y verificar el usuario de BookStack, **no escribir `external_auth_id`**.

## Administración y roles

- La cuenta protegida Plana Mayor debe recibir el rol **Admin** en BookStack mediante un mapeo especial cuidadosamente verificado antes del cambio de auth.
- Los roles `Admin`, `API` y `Editor` son manuales y no deben retirarse por sincronización OIDC.
- `Miembro` y todos los roles de Grupos SQA (incluidas coordinaciones) serán acumulativos; los cambios de estado deberán revocar acceso según política.
- OIDC estándar de BookStack no ofrece verificación remota de contraseñas locales: se necesita un comprobador seguro **del lado BookStack** o un proceso de vinculación probado con autenticación propia de BookStack. No añadir un endpoint público que reciba contraseñas sin diseño de seguridad.
- La sincronización de roles y la invalidación de sesiones antiguas siguen pendientes. No activar todavía `AUTH_METHOD=oidc`.

## LOCAL

```bash
php artisan optimize:clear
php artisan list --raw | findstr wiki:bookstack-link-audit
```

## PRODUCCIÓN

No desplegar aún. Cuando se apruebe un diagnóstico en producción, sin habilitar OIDC:

```bash
docker exec -it web-php-1 php artisan optimize:clear
docker exec -it web-php-1 php artisan wiki:oidc-readiness
# Solo si se dispone de inventario privado en el host Laravel:
docker exec -it web-php-1 php artisan wiki:bookstack-link-audit /ruta/segura/bookstack-user-inventory.json
```

NOTA: la ruta pasada a `php artisan` debe existir dentro del contenedor `web-php-1`, no solo en el host.
