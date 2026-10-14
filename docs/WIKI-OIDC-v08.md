# Squad ALPHA / BookStack — v08 (incremental sobre v07)

## Alcance real

- Añade tarjeta informativa **Wiki Squad ALPHA** a *Mi perfil → Cuentas vinculadas*, con enlace a `https://wiki.squadalpha.es`.
- La tarjeta **no afirma** que la cuenta está vinculada: Laravel todavía no tiene un canal de consulta autenticado de BookStack.
- Añade `bookstack-tools/admin-oidc-preflight.php`, diagnóstico de solo lectura para inventariar el rol Admin y los identificadores OIDC existentes.
- No cambia el proveedor OIDC de Laravel, las tablas, los roles de BookStack, los hashes o los métodos de autenticación.

## Instalación

En Laravel, copiar `resources/views/profile/linked-accounts.blade.php` al mismo destino. Preservar los cambios locales realizados después de newslot25 antes de sobrescribir, porque esta vista se ha basado en ese ZIP original.

LOCAL:

    php artisan view:clear

PRODUCCIÓN:

    docker exec -it web-php-1 php artisan view:clear

En Ubuntu, copiar `bookstack-tools/admin-oidc-preflight.php` a una carpeta privada y luego al contenedor:

    sudo docker cp /home/gop/samba/Bookstack/wiki-oidc/bookstack-tools/admin-oidc-preflight.php bookstack:/tmp/admin-oidc-preflight.php
    sudo docker exec bookstack php -l /tmp/admin-oidc-preflight.php
    sudo docker exec bookstack php /tmp/admin-oidc-preflight.php

No requiere `migrate`, `seed`, ni cambiar `AUTH_METHOD`.

## Administración antes de activar SSO

- `Admin`, `API` y `Editor` son roles locales manuales. No deben sincronizarse ni borrarse automáticamente al iniciar sesión.
- Debe existir exactamente una identidad OIDC con `external_auth_id=sqa-user-1` para la cuenta protegida de Plana Mayor, y esa cuenta debe tener el rol BookStack `Admin` **antes** de activar OIDC.
- El diagnóstico no crea dicha identidad ni concede privilegios. La asociación administrativa y su procedimiento de recuperación se prepararán en una entrega posterior con verificación de conflictos, backup y autorización explícita.
- No activar `OIDC_REMOVE_FROM_GROUPS` sin un diseño y pruebas de sincronización selectiva que protejan todos los roles manuales.
- No activar `AUTH_METHOD=oidc` aún. Las cuentas técnicas que dependan de contraseña tradicional no podrán iniciar sesión por ese método una vez activado OIDC. Evaluar sus dependencias y el plan de recuperación.

## Bloqueos restantes

1. Autoservicio real: autenticación segura del titular contra BookStack sin publicar la verificación de contraseña en una ruta pública del contenedor y sin exponer passwords al admin.
2. Proveedor OIDC: pruebas de interoperabilidad, manejo de sesiones y revocación.
3. Cuenta protegida Plana Mayor con rol Admin, preservación de roles manuales y plan de recuperación.
4. Sincronización selectiva de grupos SQA y coordinadores, permitiendo roles acumulativos.

Esta entrega no resuelve esos bloqueos ni constituye autorización para activar SSO.
