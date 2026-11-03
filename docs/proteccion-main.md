# Protección de main — requiere al administrador

**Estado al 03/11/2026: pendiente.** Requiere una cuenta con rol administrador en GitHub;
el repositorio está en la cuenta de Gerald.

La consulta de solo lectura mediante GitHub CLI del 03/11 devolvió `viewerPermission: WRITE`:
la cuenta conectada puede enviar código, pero no administrar la protección. La consulta de
protección de `main` respondió 404, la rama reportó `protected: false` y la lista de rulesets estaba vacía. No se modificó la
configuración remota ni se considera configurada la protección obligatoria.

El workflow **ya tiene una ejecución remota correcta**: los siete jobs de
[CI 41564765479](https://github.com/GeraldVargas/proyectotelematicos/actions/runs/41564765479)
pasaron sobre el commit `e9e9bf91589b517a70926133b21b57f94df1f58e`, coincidente con el HEAD
local consultado. Fue creada el **02/11/2026 a las 22:13 (Bolivia)**. Los cambios locales
del 03/11 todavía necesitan publicarse y pasar su propia ejecución de CI.

Un administrador debe crear una regla para `main` en Settings → Branches/Rulesets:

1. Exigir pull request, una revisión y resolución de conversaciones.
2. Exigir los checks del workflow CI: pruebas/lint de las 3 APIs, contratos, panel web, Flutter e integración.
3. Bloquear force-push y eliminación de la rama.

Las aprobaciones del ADR y de las PR deben registrarlas los integrantes con sus propias cuentas.
