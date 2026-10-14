# Wiki Squad ALPHA — Fase 1 (base de autorización, NO OIDC aún)

## Estado de esta entrega

Este paquete prepara la política de autorización Laravel para el futuro proveedor OIDC. **No activa SSO ni toca BookStack.** No se deben configurar `AUTH_METHOD=oidc` ni grupos hasta instalar y verificar un proveedor OIDC completo.

- Usuarios autorizados: RECLUTA, ACTIVO, RESERVA con correo verificado, no dados de baja.
- Excepción: cuenta protegida con bandera persistida `is_protected_admin` y correo verificado, independientemente de sus Grupos SQA.
- Seguridad: la excepción NO depende de una simple comparación de email. Se debe comprobar en producción que la cuenta de Plana Mayor tiene bandera `is_protected_admin` y que está protegida por `ProtectedAdminGuard`.
- Grupos SQA: se leen de `sqaGroups()`, nunca de roles Spatie.
- BookStack: 40 usuarios antiguos sin external_auth_id. Migración manual de identidades requiere validación real de contraseña BookStack (sin exportar hashes), no implementada aún.

## Regla crítica para fase de grupos

La sincronización OIDC con `OIDC_REMOVE_FROM_GROUPS=true` puede retirar `Admin` otorgado manualmente. NO activar esta opción sin implementar preservación del rol `Admin`. En la fase inicial se deja la sincronización OIDC de grupos desactivada; el rol de SuperAdmin se configurará cuidadosamente tras la vinculación de la cuenta protegida.

## Activación futura

1. Integrar proveedor OIDC completo y auditado compatible con Laravel 12 (Authorization Code + PKCE, RS256, JWKS, state/nonce, validación redirect_uri).
2. Implementar el flujo de comprobación de contraseña BookStack con un canal seguro, límite de intentos y vinculación transaccional.
3. Vincular y probar cuenta Plana Mayor en BookStack antes del cambio global de autenticación.
4. Respaldar bases de datos y planificar rollback.
5. Solo entonces activar `AUTH_METHOD=oidc` y validar sesiones, visitantes y cuentas técnicas.
