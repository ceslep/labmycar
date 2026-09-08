# Plan de migración — Flutter "mycar" → SPA Svelte 5 + TS + Tailwind

Documento informativo del plan aprobado y su estado de ejecución (actualizado por fase).

## Decisiones adoptadas
- PDF/impresión **en cliente** (HTML imprimible → diálogo de impresión/PDF del navegador).
- Alcance: **todo el ecosistema** (Laboratorio + Panel de gestión + Portal de pacientes).
- **Desktop-first responsive** (≥900 px barra lateral, <900 px barra inferior).
- Backend único: **`https://mycar.iedeoccidente.com/libphp`** (base configurable en
  `localStorage` `labmycar:base_url`). La carpeta local `libphp/` es el espejo de
  sincronización WinSCP y **no forma parte del build**.
- CORS: se envían POST sin `Content-Type` explícito (text/plain safelisted → sin preflight).

## Arquitectura
```
src/
├─ App.svelte                  # sesión + banner conexión + despacho de rutas
├─ lib/
│  ├─ api/laboratorio.ts       # cliente HTTP tipado de endpoints PHP
│  ├─ core/{config,http}.svelte.ts  # URL base, fetch+timeout, serverDown
│  ├─ models/models.ts         # tipos 1:1 con bd.sql/respuestas
│  ├─ schemas/resultados.ts    # esquemas declarativos por tipo de examen
│  ├─ reportes/print.ts        # reportes HTML imprimibles (PDF cliente)
│  ├─ router.svelte.ts         # router hash con runes
│  ├─ stores/                  # sesión y toasts
│  ├─ ui/                      # componentes base
└─ routes/                     # Login, Shell, Inicio, Pacientes(+Form/Examenes),
                               # CrearExamen, RegistroExamen, Resultados,
                               # Configuracion(+ProcedimientoForm), Panel, Portal
```

## Fases y estado

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Conectividad backend /libphp (CORS, contratos) | ✅ verificada |
| 1 | Fundaciones, login T.P., shell 5 secciones | ✅ (banner pendiente confirmación usuario) |
| 2 | Pacientes (listado, alta, edición) | ✅ |
| 3 | Asignación de exámenes (asistente 3 pasos) | ✅ |
| 4 | Registro de resultados (tipos 1,2,3,4,5,6,8) + LAN + aviso esquema antiguo | ✅ código |
| 5 | Reportes PDF en cliente (individual y todos) | ✅ visor modal con vista previa (escritorio) / resultado con botón imprimir (móvil); A/B visual pendiente |
| 6 | Configuración laboratorio + procedimientos + opciones | ✅ código |
| 7 | Panel de gestión | ✅ integrado estilo prt.php: rango de fechas, filtros (entidad/examen/solo realizados), impresión por examen y por paciente, historial |
| 8 | Portal público de pacientes | ✅ código |
| 9 | QA y despliegue | ✅ guía + build:app; **aceptación final pendiente** |

Verificación por fase: `npm run check` 0 errores/0 warnings y `npm run build` OK.
Últimas correcciones: codexamen en tipo 1/2 (reportes vacíos), listas vacías (respuestas de 0 bytes), modal de vista previa, búsqueda de pacientes por servidor.

## Pendientes de aceptación (usuario)
1. Confirmar en navegador que el Panel consulta rangos sin errores y que los reportes (modal) muestran los valores.
2. Bloqueo normativo de resultados emitidos (probarlo: guardar un pendiente → se vuelve solo lectura; no se puede re-guardar).
3. Opcionales: parche P6 ya aplicado (selector de entidad); admin.php: decidir reimplementarlo como sección del Panel o mantener enlace externo.

## Extras incorporados tras el plan
- Visor de reportes en **modal** (desktop) / pantalla completa con imprimir (móvil).
- Panel integrado estilo `prt.php`: rango de fechas, filtros, resumen por entidad, tarjetas expandibles con valor, impresión por examen/paciente/fecha, WhatsApp, CSV, Hoy/Ayer, "Mostrar más", historial con resumen, selector de entidad por examen (`libphp/setEntidad.php`), búsqueda avanzada (`libphp/panelPacientes.php`).
- **Resultados emitidos inmodificables**: solo lectura en la SPA + rechazo en `libphp/guardarExamen.php` (realizado='S').
- Logo del laboratorio visible en login, barra lateral, portal y visor (store compartido `stores/configapp.svelte.ts`).

## Mejoras de backend propuestas (no aplicadas; ver docs/parches-libphp.md)
P1 reasignar sin perder Realizado · P2 savePaciente UPDATE · P4 ORDER BY · P5 eliminarExamen.

## Documentos relacionados
- `docs/QA.md` — checklist de aceptación.
- `docs/parches-libphp.md` — parches propuestos para libphp.
- `README.md` — comandos y arquitectura.
