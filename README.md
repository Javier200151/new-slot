# NewSlot test suite hotfix 24

Corrige exclusivamente el fixture `activity_types` de `PublicEventsPageTest`.

En el insert múltiple, ambas filas deben declarar las mismas columnas. Se deja:
- Oficial: `uses_event_result = false`
- Prácticas: `uses_event_result = true`

No modifica código de producción, base de datos ni migraciones.
