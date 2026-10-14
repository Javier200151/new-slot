# Wiki SSO v11 — interfaz postactivación

## Alcance

La autenticación OIDC ya funciona en producción para Rylod y Plana Mayor. Esta entrega actualiza únicamente la tarjeta de Wiki de `Mi perfil > Cuentas vinculadas`:

- Corrige el mensaje obsoleto «en preparación» de v08.
- Utiliza `WikiAccessPolicy::canAuthenticate()` para determinar si mostrar «Acceder a la Wiki» o «Ver Wiki pública».
- No presenta como vinculadas cuentas no verificadas contra BookStack.
- Avisa a usuarios históricos que soliciten vinculación **antes** del primer SSO para evitar posibles duplicados.
- No modifica rutas, claves, modelos, contraseñas, grupos, roles ni BookStack.

## Instalación

Aplicar únicamente `resources/views/profile/linked-accounts.blade.php`. Revisar el diff contra las modificaciones realizadas tras `newslot25.zip`.

LOCAL: `php artisan view:clear`

PRODUCCIÓN: `docker exec -it web-php-1 php artisan view:clear`

No ejecutar migraciones ni reinstalar BookStack.

## Siguiente desarrollo: autoservicio de vinculación

La herramienta CLI v07 comprobó ambas contraseñas y vinculó la cuenta 38 con `sqa-user-154`, pero **no** constituye una API de autoservicio. Antes de publicar un formulario, construir un mecanismo remoto autenticado entre Laravel y BookStack que verifique la contraseña de BookStack en su propio entorno; comprobar origen, sesión CSRF, límite de intentos, replay, firmas, auditoría y concurrencia. No exponer el script CLI directamente por HTTP ni transportar hashes a Laravel. El estado «Cuenta vinculada» deberá proceder de una fuente confirmada en BookStack, no de coincidencia de correo o cookie local. Crear una vía de vinculación para usuarios antiguos que no provoque automáticamente cuentas duplicadas. Mantener excluidas cuentas técnicas y conservar roles Admin/API/Editor manuales.

La sincronización de Grupos SQA sigue desactivada (`OIDC_USER_TO_GROUPS=false`).
