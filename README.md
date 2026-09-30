# NewSlot — Twitch + YouTube automático (v26)

## Qué hace
- Detecta automáticamente directos de Twitch de todos los streamers habilitados mediante la API oficial Helix.
- Detecta automáticamente directos de YouTube sin API Key, usando el Channel ID y la página `/live` del canal.
- Mantiene el sistema manual actual como respaldo: no se elimina ninguna funcionalidad existente.
- Fusiona directos automáticos y manuales sin duplicar la misma plataforma del mismo streamer.
- Si el streamer está asignado a un slot de un evento ACTIVO cercano, el directo se asocia automáticamente a ese evento.
- El indicador `EN DIRECTO` de la página del evento funciona también con directos detectados automáticamente.
- `/directos/estado` incluye tanto emisiones automáticas como manuales.
- La detección externa usa caché para evitar consultas constantes a Twitch/YouTube.
- Los Channel ID/User ID resueltos automáticamente se guardan silenciosamente en `streamers` cuando es posible.
- Añade `php artisan streams:check --refresh` para diagnosticar la integración.
- Añade tests de detección Twitch/YouTube.

## Configuración
YouTube no necesita API Key.

Twitch sí necesita una aplicación registrada en Twitch Developer Console y estas variables:

```env
TWITCH_CLIENT_ID=
TWITCH_CLIENT_SECRET=
```

Opcional:

```env
STREAMS_AUTOMATIC_LIVE=true
STREAMS_LIVE_CACHE_SECONDS=60
STREAMS_EVENT_MATCH_BEFORE_HOURS=12
STREAMS_EVENT_MATCH_AFTER_HOURS=12
```

## Migraciones
No hay migraciones nuevas.

## Verificación
```bash
php artisan optimize:clear
php artisan streams:check --refresh
php artisan test
```
