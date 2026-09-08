# Plan de mejoras del backend PHP (libphp)

Auditoría de los endpoints que consume la SPA (Svelte) y del frontend en general.
Prioridad: P0 seguridad → P1 integridad de datos → P2 rendimiento → P3 convenciones/API → P4 limpieza.

## Inventario de endpoints usados
- Configuración: `getConfiguracion.php`, `guardarConfiguracion.php`
- Pacientes: `getPaciente.php`, `getPacientes.php`, `savePaciente.php`, `getPacientesFecha.php`, `panelPacientesPorIds.php`
- Exámenes/asignación: `getExamenesPaciente.php`, `getExamenesPacienteFecha.php`, `getExamenesFecha.php`, `getSeleccionados.php`, `guardarExamenes.php`, `updateExamen.php`, `eliminarExamen.php`
- Resultados: `getTipo1/2.php`, `getParcialOrina.php`, `getCoprologico.php`, `getFrotisVaginal.php`, `getPerfilLipidico.php`, `getHemogramaRayto(New).php`, `guardarExamen.php`
- Catálogo: `getProcedimientos.php`, `getProcedimiento.php`, `guardarProcedimiento.php`, `eliminarProcedimiento.php`, `getUniConst.php`
- Opciones/items: `getItemsExamenes.php`, `guardarItemsExamenes.php`, `getExamenesWithItems.php`
- Entidades: `getEntidades.php` · Búsqueda: `busquedaExamenes.php` · Portal: `getExamenesPaciente2.php`
- Nuevos (SPA Admin/Panel): `setEntidad.php`, `panelPacientes.php`, `adminDatos.php`
- Impresión por URL (WhatsApp/vista servidor): `printphp/print_examen.php`, `printphp/imprimirTodo.php`
- Infraestructura común: `datos_conexion.php` (define `$mysqli` y `$datos`), `cors.php` (solo algunos)

---

## P0 — Seguridad (hacer primero)

1. **Lista blanca de columnas en guardados dinámicos** — `guardarExamen.php` y `guardarProcedimiento.php`
   construyen `REPLACE INTO ... (columnas)` usando las claves JSON del cliente tal cual (solo se
   escapan los *valores*). Una clave maliciosa (p. ej. con comilla de columna) permitiría inyectar SQL.
   - Acción: validar cada `$key` contra la lista real de columnas de la tabla (`SHOW COLUMNS`,
     cacheada) o contra un mapa fijo por `tabla`; usar comillas invertidas.
2. **Autenticación real del API** — hoy el "login" compara la T.P. en el cliente y todos los
   `*.php` de escritura son invocables públicamente.
   - Acción (fase): añadir un secreto compartido opcional en cabecera/firma generado en el login
     (o sesión PHP por cookie) exigido por los endpoints de escritura; al menos en los de
     `guardar*`, `eliminar*`, `setEntidad.php` y `panelPacientes.php`.
3. **Rate-limit en el portal público** — `getExamenesPaciente2.php` (identificación + verificador
   simple) es abierta a internet: limitar intentos por IP (p. ej. 10/min) para evitar fuerza bruta
   del verificador.
4. **No exponer errores SQL** — en los `echo json_encode(["msg"=>false,"error"=>$mysqli->error])`
   evitar devolver el detalle técnico en producción (log interno + mensaje genérico).

## P1 — Integridad de datos

5. **`savePaciente.php`: UPDATE en vez de REPLACE** — REPLACE borra la fila y solo conserva las 8
   columnas del formulario (pierde dirección, teléfonos 2, ocupación, ciudad, etc.).
   - Acción: UPDATE de las 8 columnas; si `affected_rows=0` → INSERT (parche P2 ya documentado).
6. **`guardarExamenes.php`: no perder exámenes Realizados** — hace DELETE+INSERT de todo el
   paciente+fecha, revirtiendo a Pendiente los ya emitidos y dejando huérfanas sus filas si se
   desmarcan.
   - Acción: preservar `realizado='S'` (solo reemplazar los pendientes) — parche P1 documentado.
7. **`guardarConfiguracion.php`: fila única** — REPLACE acumula filas nuevas con cada guardado.
   - Acción: `UPDATE configuracion WHERE id=(última)` y si no existe INSERT; agregar índice único
     para impedir duplicados.
8. **`guardarExamen.php`: conservar metadatos y claves** — REPLACE genérico por columnas enviadas;
   añadir `ind`/clave a la lista de exclusión ya está; falta garantizar por tabla una clave única
   `(identificacion, fecha[, examen])` + UPSERT en vez de insertar duplicados en tablas sin índice
   único (detalle). La protección "realizado no modificable" ya está activa.
9. **Índices/unicidad en BD** (migración SQL):
   - `examenes`: índice `(identificacion, fecha)`, `(fecha)`, y opcional único
     `(codexamen, identificacion, fecha)`.
   - Detalle: único `(identificacion, fecha[, examen])` en parcialOrina/coprologico/frotis/perfil/
     hemogramaRayto/examen_tipo_1/2.
   - `configuracion`: fila única.
   - `paciente`: índice `identificacion` único (nombre natural ya usado como clave lógica).

## P2 — Rendimiento

10. **`getExamenesPaciente.php` sin parámetros** descarga todo el histórico (334 k filas en la
    prueba). - Acción: exigir `criterio`/`fecha`, o devolver solo con `LIMIT` + paginación; hoy la
    SPA ya usa las versiones con parámetros.
11. **`getPacientes.php`**: agregar `ORDER BY apellidos` y revisar el `LIMIT` (la SPA busca por
    servidor con criterio ≥3, pero el listado inicial está truncado a 200 sin orden).
12. **`getConfiguracion.php`** devuelve ~174 KB (logo/firma base64) en cada carga de app/login.
    - Acción: endpoint ligero `configPublica.php` (solo campos texto + hashes) para el arranque, y
      mantener el pesado solo al editar; añadir cabecera `ETag`/cache.
13. **`adminDatos.php` (análisis de salud)** recorre tablas completas de detalle en cada petición
    con `analisis=1`.
    - Acción: filtrar por rango de fechas, agrupar en una sola query por tabla con JOIN y ejecutar
      fuera de horario o con caché de N minutos.
14. **Respuestas consistentes** de arrays vacíos: muchos endpoints no emiten nada cuando no hay
    filas (la SPA ya normaliza a `[]`); mejor que el servidor devuelva siempre `[]`/`{msg:false}`
    para una API predecible.

## P3 — Convenciones de API

15. **Centralizar CORS/OPTIONS y cabeceras** en `datos_conexion.php`: `Access-Control-Allow-*`
    (la SPA evita preflight enviando `text/plain`, pero conviene soportar `application/json`),
    `Content-Type: application/json; charset=UTF-8` en las respuestas y `no-cache`.
16. **`cors.php`**: revisar que se incluya en todos los endpoints públicos o eliminarlo en favor de
    la centralización.
17. **Convención de errores**: `{msg:false, error: código}` uniforme + `error_log`.
18. **Normalizar respuestas de entidades/pacientes** (actualmente cada endpoint tiene su forma:
    arrays planos vs `{msg,data}`): documentar o unificar con wrappers ligeros.
19. **`$datos`/conexión**: en `datos_conexion.php` fijar charset UTF-8, `mysqli_report` moderado y
    cerrar conexión siempre (los nuevos archivos lo hacen; algunos antiguos no).

## P4 — Limpieza/legado

20. **Archivos duplicados/muertos en `printphp/`**: `print_examen3/4/t/old.php`,
    `imprimirTodo2/imprimir_todo2/imprimriTodoOld.php`, `configuracion_bacteriologos*` — identificar
    el que usa producción y archivar el resto.
21. **`admin.php`, `prt.php`, `prt_mejorado.php` y assets JS antiguos**: ya hay equivalentes SPA
    (Panel/Admin); decidir conservarlos como respaldo o retirarlos del flujo principal.
22. **`eliminarExamen.php`**: usa `DELETE FROM ?` inválido; corregir a tabla fija `examenes`
    (parche P5) si se necesita la función de borrado.
23. **Pruebas**: añadir un script de humo (GET/POST de solo lectura) y `php -l` sobre todos los
    `.php` en CI/local.

## Sugerencia de orden de ejecución
1. P0.1 (whitelist de columnas) y P0.2 (token de escritura) — crítico si el dominio es público.
2. P1.5–P1.7 (UPDATE paciente, preservar realizado, config única) y P1.9 (índices).
3. P2.10–P2.13 (límites, config ligera, salud acotada) y P3.15–P3.19.
4. P4 (limpieza) en ventanas de bajo uso, validando con el checklist QA.

---

## Impacto sobre el funcionamiento actual (si se implementa todo)

> Clave: estos PHP los usan **también** la app Flutter antigua, el portal compilado
> (mycarweb), `prt.php`/`admin.php` y `paciente.html`. El plan debe aplicarse **de forma
> retrocompatible o con corte coordinado**, nunca asumiendo que solo la SPA consume la API.

### P0 Seguridad
| Cambio | Qué sigue igual | Qué cambia | Riesgo si no se cuida |
|---|---|---|---|
| Whitelist de columnas (guardarExamen/procedimiento) | Los guardados legítimos (SPA y Flutter mandan las mismas claves) | Se rechazan claves no esperadas (antes: error o inserto raro) | Si la whitelist no incluye alguna clave real que manda un cliente antiguo → `msg:false` al guardar. Mitigación: whitelist = columnas reales + lista de exclusión actual; probar guardando 1 examen de cada tipo con la app vieja |
| Token de escritura | Lecturas (get*) abiertas | `guardar*/eliminar*/setEntidad` exigen token | **Rompía la app Flutter/portal si se exige sin que ellos envíen token.** Mitigación: fase opcional “si no hay token se permite” mientras conviven; endurecer solo tras retirar legado |
| Rate-limit portal | Consulta legítima 1 a 1 | Límite de intentos por IP | Paciente real bloqueado si comparte IP (oficina). Mitigación: límite generoso y ventana corta |
| Ocultar errores SQL | OK | Detalle técnico solo en log | Ninguno funcional |

### P1 Integridad
| Cambio | Qué sigue igual | Qué cambia | Riesgo |
|---|---|---|---|
| savePaciente UPDATE+INSERT | Alta y edición visibles igual | Editar ya **no** borra dirección/tel 2/ocupación | Duplicados previos de `paciente` (por REPLACE sin único): la migración de índice único exige **deduplicar primero** o el INSERT fallará |
| guardarExamenes preserva Realizados | Reasignar una fecha con exámenes nuevos | Los ya **emitidos siguen emitidos** (antes volvían Pendiente) | Mejora; solo cambia la expectativa de quien reasignaba a propósito para “reabrir” un resultado (eso ya lo impide la normativa) |
| Configuración fila única | Se lee igual (siempre la última) | Se actualiza la misma fila | Ninguno funcional; verificar firma/logo sigan escribiéndose |
| UPSERT por clave + índices únicos en detalle | Guardados normales iguales | Ya no se acumulan filas duplicadas por paciente+fecha | Si existen duplicados históricos → crear el índice único **falla**; requiere script de dedupe (conservar la más reciente) antes. El bloqueo “realizado no modificable” ya cambió el comportamiento de la app vieja que intentara re-editar un emitido (le responderá `msg:false`) |
| Índices | Nada visible | Consultas más rápidas | Solo requiere ventana de mantenimiento y backup |

### P2 Rendimiento
| Cambio | Impacto |
|---|---|
| Límite en `getExamenesPaciente.php` sin parámetros | **Rompía el agregado global de la app Flutter antigua** (Días con resultados descarga todo). Mitigación: no quitar la ruta; añadir paginación/criterio y migrar el legado; la SPA ya no la usa sin criterio |
| ORDER BY en getPacientes | Inofensivo/mejora |
| `configPublica.php` ligera | Aditivo: los clientes viejos siguen usando `getConfiguracion.php` |
| Salud con fechas/caché (adminDatos) | Solo afecta a la SPA Admin; sin cambios visibles en otros clientes |
| Siempre `[]`/`{msg:false}` en vez de cuerpo vacío | La SPA ya normaliza; los clientes Dart capturan excepciones de JSON vacío, así que es compatible, pero **probar** el caso “sin resultados” en el legado |

### P3 Convenciones
- Cambiar `Content-Type` a `application/json` y centralizar CORS: inocuo para `http` de Dart/Vite (parsan por texto); el riesgo real es **tocar la forma de una respuesta** existente (p. ej. envolver arrays en `{data}`) → **no hacerlo**: solo unificar cabeceras/errores y aplicar wrappers a endpoints nuevos.

### P4 Limpieza
- Borrar variantes `printphp/*` solo si producción no las usa (verificar referencias); archivar `admin.php/prt*` **después** de confirmar que nadie más los abre (hay enlaces y flujos antiguos).
- Corregir `eliminarExamen.php` (hoy inválido): activaría una función de borrado que quizá se daba por muerta → decidir si se quiere expuesta.

### Resumen
- **No cambian**: nombres de tablas/columnas, campos de las respuestas existentes, la SPA (salvo que incorporemos el token en el login), el portal, los reportes en cliente.
- **Cambian para mejor**: integridad (no se pierden datos al editar), reasignación (no revierte emitidos), sin duplicados, más rápido.
- **Donde hay que tener cuidado**: (1) token → legado; (2) límite en listado global → legado; (3) índices únicos → deduplicar antes; (4) whitelist de columnas → probar guardado con la app vieja.

**Recomendación**: aplicar por tandas con copia de seguridad y pruebas del checklist QA + una pasada rápida de la app Flutter antigua, empezando por los cambios aditivos/seguros (P1.5, P1.6, P1.7, índices tras dedupe) y dejando el token y los límites del listado global para el corte final con el legado retirado.

---

## Estado de ejecución (SPA principal, legado retirado)
- ✅ P0.1 whitelist de columnas (guardarExamen/procedimiento/configuración) · P0.2 token HMAC en 13+ endpoints (`api_guard.php`, `login.php`) · P0.4 errores genéricos en guardados.
- ✅ P1.5 savePaciente pendiente de cambio → ver nota; P1.6 guardarExamenes preserva Realizados · P1.7 config única pendiente de cambio (ver nota) · P1.9 utilidad `migracion_indices.php` (dedupe + índices únicos, idempotente, botón Admin).
- ✅ P2 límites: global deshabilitado, ORDER BY pacientes, `configPublica.php`, salud con ventana de fechas, `[]` en getExamenesFecha/getSeleccionados/busquedaExamenes.
- ✅ P3 cabeceras CORS/OPTIONS centralizadas en api_guard; errores `no_autorizado` uniformes.
- ✅ P4 rate-limit portal (30/min/IP) y `eliminarExamen.php` reescrito (tablas fijas, sin `DELETE FROM ?`).

**Pendiente/notas**
- P1.5 y P1.7 (UPDATE paciente / config fila única): aún con REPLACE en producción; la SPA conserva columnas al editar porque el servidor solo recibe las 8… aplicar cuando se decida (requiere probar con la SPA).
- Retiro del legado web (prt.php, admin.php, mycarweb, paciente.html) y limpieza de variantes muertas en `printphp/`: decidir y ejecutar tras validar la SPA en producción.
- `printphp/*` sigue usándose para URLs compartidas por WhatsApp y la vista “PDF servidor” (opcional migrar después).
