# Squad ALPHA — v12 (autoservicio, despliegue por etapas)

## Estado
La interfaz y el controlador Laravel están preparados, **NO activados por defecto**. El script BookStack es un endpoint privado nuevo, NO es el CLI v07. La aplicación BookStack debe servirlo mediante una ruta Nginx `location = /internal/sqa-link` con FastCGI exacto, PHP-FPM, IP ACL que permita únicamente la IP origen de Laravel, y TLS HTTPS válido a través del proxy. No coloques el PHP en el directorio web público. No envíes contraseñas por `http://192.168.37.3:6875`.

## Laravel
1. Instala los archivos PHP y Blade incrementales; inserta la ruta de INSTALL-ROUTES.txt **dentro del grupo auth existente** de routes/web.php (o inserta fuera del grupo, ya tiene middleware auth). Verifica que no está duplicada.
2. En `.env`, mantén `WIKI_LINK_ENABLED=false` hasta terminar el puente.
3. Genera un secreto nuevo específico (distinto del secreto OIDC): `openssl rand -hex 32`, y configura `WIKI_LINK_SHARED_SECRET` en Laravel, y ese mismo valor (sin salto de línea) en `/config/wiki-oidc-link/bridge-secret` dentro del contenedor BookStack. Permisos 600. Nunca lo incluyas en Git.
4. `WIKI_LINK_ENDPOINT=https://wiki.squadalpha.es/internal/sqa-link` con certificado verificable. El puente debe estar accesible solo desde el servidor Laravel mediante ACL del proxy y la ruta FastCGI.
5. Prueba endpoint `status` con autenticación HMAC y la resistencia a replay. Prueba respuestas sin publicar secretos. Después activa `WIKI_LINK_ENABLED=true` y limpia config.

## BookStack
Copia `bookstack-tools/private-link-bridge.php` a `/config/wiki-oidc-link/private-link-bridge.php`, fuera de `/app/www/public`.
**Antes de habilitarlo, inspecciona `/config/nginx/site-confs/default.conf`** para seleccionar el mismo `fastcgi_pass` y parámetros `SCRIPT_FILENAME` usados por la instancia; no inventes el socket. La ubicación exacta de nginx se configura en un cambio específico al servidor. Habilita límite de tamaño 4KB, access control, HTTPS y rate limiting en reverse proxy y Nginx. Revisa que no se registren los cuerpos POST.

No desactives AUTH_METHOD=oidc ni alteres los roles Admin/API/Editor.

## Seguridad
La autenticación del endpoint usa HMAC SHA256 sobre timestamp, nonce aleatorio y SHA256 del JSON. El nonce se consume de modo atómico mediante cache Laravel/BookStack. Una sesión autenticada y la contraseña SQA correcta son necesarias antes de solicitar el enlace. BookStack verifica su contraseña histórica en su propia BD y limita el vínculo a correo único, identidad libre y cuenta no técnica. Esto NO sustituye protección de transporte TLS, ACL, limitación de intentos y monitorización.

## Límites pendientes de validar antes de activar
- Ruta Nginx/FastCGI exacta en tu imagen LinuxServer; necesitamos examinar su `default.conf`.
- Pruebas end-to-end con cuenta ya vinculada y cuenta sin vincular.
- No se han ejecutado pruebas funcionales contra tu instancia de producción. Hasta entonces el interruptor queda OFF.
- Migrar roles SQA es una siguiente entrega distinta.
