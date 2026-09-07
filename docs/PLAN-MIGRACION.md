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
| 5 | Reportes PDF en cliente (individual y todos) | ✅ código (A/B visual pendiente) |
| 6 | Configuración laboratorio + procedimientos + opciones | ✅ código |
| 7 | Panel de gestión (por fecha y búsqueda) | ✅ código |
| 8 | Portal público de pacientes | ✅ código |
| 9 | QA y despliegue | ✅ guía + build:app; **aceptación pendiente** |

Verificación por fase: `npm run check` 0 errores/0 warnings y `npm run build` OK.

## Pendientes de aceptación (usuario)
1. Confirmar el banner "Sin conexión" (detalle que muestra el login tras Ctrl+F5).
2. Recorrer checklist de `docs/QA.md` (registro paciente → asignar → guardar → imprimir → portal).
3. Prueba del reporte Realizado tipo 1/2 tras el arreglo A1 (codexamen).

## Mejoras de backend propuestas (no aplicadas; ver docs/parches-libphp.md)
P1 reasignar sin perder Realizado · P2 savePaciente UPDATE · P4 ORDER BY · P5 eliminarExamen.

## Documentos relacionados
- `docs/QA.md` — checklist de aceptación.
- `docs/parches-libphp.md` — parches propuestos para libphp.
- `README.md` — comandos y arquitectura.
