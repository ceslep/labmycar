# LabMyCar — SPA de Laboratorio Clínico (Svelte 5 + TypeScript + Tailwind)

Migración de la aplicación Flutter **"mycar"** (gestión de laboratorio clínico) a una SPA
de escritorio-first responsive, consumiendo el backend PHP existente en
`https://mycar.iedeoccidente.com/libphp/`.

## Comandos

| Tarea | Comando |
|---|---|
| Desarrollo | `npm run dev` |
| Verificación (tipos) | `npm run check` |
| Build producción (raíz) | `npm run build` |
| Build para servirse bajo `/libphp/app/` | `npm run build:app` |
| Vista previa del build | `npm run preview` |

## Módulos (rutas con hash)

- `/login` — acceso del laboratorio (clave T.P. de `configuracion`).
- `/inicio` · `/pacientes` · `/pacientes/nuevo` · `/paciente/:id/editar` · `/paciente/:id/examenes`
- `/crear-examen` — asistente 3 pasos (paciente+fecha → catálogo → guardar `guardarExamenes.php`).
- `/registro/:id/:codexamen/:fecha/:tipo` — registro de resultados (tipos 1,2,3,4,5,6,8) con esquema declarativo.
- `/resultados` · `/configuracion` (Laboratorio + Procedimientos + opciones) · `/procedimiento/:codigo`
- `/panel` — gestión por fecha y búsqueda de pacientes con exámenes.
- `/portal` — consulta pública de resultados (identificación + número verificador).

## Arquitectura

```
src/
├─ App.svelte            # Sesión, banner de conexión y despacho de rutas
├─ lib/
│  ├─ api/laboratorio.ts # Cliente HTTP tipado de los endpoints PHP
│  ├─ core/              # config (URL base), http (fetch + serverDown + CORS text/plain)
│  ├─ models/            # Tipos 1:1 con bd.sql y respuestas del servidor
│  ├─ schemas/resultados.ts  # Esquemas declarativos de los formularios de examen
│  ├─ reportes/print.ts  # Generación de reportes HTML imprimibles (PDF en cliente)
│  ├─ stores/            # Sesión y toasts (runes)
│  └─ ui/                # Iconos, campos, estados, tarjetas, toasts…
└─ routes/               # Páginas (una por módulo)
```

### Notas técnicas
- **CORS**: el backend responde `Access-Control-Allow-Origin: *` pero no completa
  preflight OPTIONS; el cliente envía el JSON sin `Content-Type` explícito
  (`text/plain`, exento de preflight), igual que el cliente Dart original.
- **PDF**: los reportes se generan en el navegador (HTML + CSS de impresión) y se
  imprimen/guardan como PDF con el diálogo nativo. No dependen de `printphp/`.
- **Despliegue**: `npm run build:app` genera `dist/` con base `/libphp/app/`;
  súbalo a `https://mycar.iedeoccidente.com/libphp/app/` (mismo origen que la API,
  sin CORS) mediante tu sincronización WinSCP.
- La carpeta `libphp/` es el espejo del backend; **no** es parte del build de la SPA.
