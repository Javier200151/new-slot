# Squad ALPHA / BookStack: etapa de preparación v02

Este ZIP se aplica **encima de v01** y reemplaza `WikiAccessPolicy.php`.

Incluye:
- Autorización por estado RECLUTA/ACTIVO/RESERVA, correo verificado y excepción para la cuenta protegida (`User::isProtectedAdmin()`).
- Resolución de roles acumulativos para el futuro token: `Miembro` + todos los Grupos SQA válidos + `Coordinador de GRUPO` cuando corresponda + claim especial de Plana Mayor.
- Exclusión del grupo paraguas `SQA` para evitar crear permisos privados por accidente.
- Comando de diagnóstico `php artisan wiki:oidc-preview ID` sin cambios de datos.

No incluye todavía endpoints OIDC, comprobación de contraseña antigua de BookStack, vinculación de usuarios ni reconciliación de roles en BookStack. NO configurar AUTH_METHOD=oidc en BookStack.

**Roles que jamás debe modificar el reconciliador futuro:** Admin, API, Editor. El `Admin` manual debe conservarse incluso si el usuario cambia de Grupos SQA.

**Pendiente de diseño:** la sincronización al cambiar estado y la revocación de sesiones existentes; el rol manual Admin por sí solo no debe servir para eludir la regla de estados del SSO ordinario.

Comandos necesarios tras copiar archivos:

LOCAL:
```
php artisan optimize:clear
php artisan wiki:oidc-preview ID
```
PRODUCCIÓN (NO desplegar todavía; solo cuando se autorice una prueba):
```
docker exec -it web-php-1 php artisan optimize:clear
docker exec -it web-php-1 php artisan wiki:oidc-preview ID
```

Nota: no se han añadido dependencias, rutas, cambios SQL o mecanismos de autenticación. La autenticación SSO necesita una implementación OIDC de servidor contrastada antes de probarse.
