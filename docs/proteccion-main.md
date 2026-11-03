# Protección de main — requiere al administrador

**Estado al 02/11/2026: pendiente.** Requiere una cuenta con rol admin en GitHub; el repositorio está en la cuenta de Gerald.

Consulta del 27/10/2026 mediante GitHub CLI: `salvUs122` tiene lectura y push, pero `admin: false`
en `GeraldVargas/proyectotelematicos`. No se modificó la configuración remota.

Después de publicar el workflow y verificar su primera ejecución correcta, un administrador debe
crear una regla para `main` en Settings → Branches/Rulesets:

1. Exigir pull request, una revisión y resolución de conversaciones.
2. Exigir los checks del workflow CI: pruebas/lint de las 3 APIs, contratos, panel web, Flutter e integración.
3. Bloquear force-push y eliminación de la rama.

Las aprobaciones del ADR y de las PR deben registrarlas los integrantes con sus propias cuentas.
