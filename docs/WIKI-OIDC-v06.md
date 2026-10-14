# Squad ALPHA / BookStack — v06 (vinculación asistida)

## Alcance y limitación

- **Este paquete NO activa OIDC**, NO añade formulario web de autoservicio y NO modifica el método de autenticación de BookStack.
- Introduce una **vinculación real pero asistida por operador**: una prueba RSA firmada tras verificar la contraseña de Squad ALPHA y una herramienta de ejecución local dentro del contenedor BookStack que comprueba la contraseña histórica de BookStack y asigna `external_auth_id`.
- Solo se utiliza para usuarios RECLUTA/ACTIVO/RESERVA con email verificado. Plana Mayor (administrador protegido) sigue reservada a un procedimiento de migración administrativa aparte.
- Roles y contenido de BookStack no se modifican. Las cuentas especiales con IDs 1, 2, 40, 41 se excluyen. Los roles Admin, API y Editor siguen siendo manuales.
- Requiere acceso interactivo autorizado a la terminal de BookStack. **No dar acceso a Docker/SSH a miembros ordinarios.** Este flujo es para un piloto administrado, NO para habilitar 16 vinculaciones sin supervisión.

## Preparación (solo cuando se autorice piloto)

1. Instalar el PHP `app/Console/Commands/WikiIssueLinkProof.php` en Laravel y comprobar `php artisan list --raw | grep wiki:issue-link-proof`.
2. Generar claves OIDC RSA en **producción** con `php artisan wiki:oidc-key-generate` si aún no existen; guardar copia segura. No generar de nuevo si ya hay claves; no poner privadas en Git.
3. Copiar **solo la clave pública** de Laravel al host BookStack mediante canal seguro y pasarla al contenedor, por ejemplo bajo `/tmp/sqa-public.pem` (temporal). **Nunca copiar la privada a BookStack.**
4. Transferir `bookstack-tools/link-existing-account.php` directamente a `/tmp/link-existing-account.php` dentro del contenedor BookStack. No colocarlo en `/app/www/public` ni exponerlo por HTTP.
5. Hacer copia de seguridad de la base de datos de BookStack antes de cualquier vinculación.

## Piloto de vinculación, una sola cuenta real no administrativa

En el servidor Laravel (comando interactivo):

```
docker exec -it web-php-1 php artisan wiki:issue-link-proof ID_SQA
```

El comando pide, ocultándola, la contraseña del usuario en Squad ALPHA. Devuelve un JWT válido **cinco minutos**, firmado RS256. **No publicarlo en el chat, logs, historial de shell, GitHub o tickets**.

En el servidor BookStack (contenedor ya preparado):

```
sudo docker exec -it bookstack php /tmp/link-existing-account.php /tmp/sqa-public.pem
```

El script solicita el JWT y luego la **contraseña actual de BookStack**, ocultándola, verifica firma/issuer/audiencia/expiración, email único y contraseña, y escribe exclusivamente `users.external_auth_id` para la cuenta correcta. Usa transacción y bloquea cuentas técnicas.

Es imprescindible que el operador y el titular coordinen las pruebas sin divulgar contraseñas; si no se dispone de un mecanismo privado seguro para la introducción de credenciales, **no ejecutar** el piloto.

## Validación posterior

- En BookStack, verificar mediante una consulta de lectura que el `external_auth_id` quedó como `sqa-user-ID` y que `users.id` no cambió.
- Verificar manualmente que el usuario conserva sus roles existentes, sus páginas y su historial.
- Con OIDC aún desactivado, la autenticación tradicional BookStack continúa funcionando como antes.
- Al completar el piloto, eliminar `/tmp/link-existing-account.php` y `/tmp/sqa-public.pem` del contenedor si no se van a realizar más enlaces.

## Riesgos y próximos hitos

- La fase de vinculación de Plana Mayor y asignación exclusiva de `Admin` requiere su procedimiento especial antes de cambiar `AUTH_METHOD`.
- La herramienta asistida debe sustituirse por autoservicio con prueba de propiedad directamente en BookStack, CSRF, limitación de intentos, control de sesión, protección antifuerza bruta y reautenticación. No exponer jamás un endpoint ad hoc que permita comprobar contraseñas libremente.
- Antes de activar BookStack OIDC: auditar proveedor OIDC, rotación/almacenamiento de claves, copia de seguridad, rollback, permisos manuales, cookies y revocación de sesiones.
