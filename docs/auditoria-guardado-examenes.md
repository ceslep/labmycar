# Auditoría: guardado de exámenes

> Objetivo: verificar por qué usuarios reportan que "no se guardan todos los exámenes"
> (o que el guardado falla sin aviso) y qué corregir antes de escribir los tests.

Fecha: auditoría de código (cliente SPA + endpoints PHP). La BD real no se tocó.

---

## Flujo auditado

| Paso | Cliente (SPA) | Endpoint PHP | Efecto |
|---|---|---|---|
| Asignar varios exámenes a paciente+fecha | `CrearExamen.svelte` → `guardarExamenes()` | `guardarExamenes.php` | reemplaza los exámenes pendientes de esa fecha |
| Guardar el resultado de UN examen | `RegistroExamen.svelte` → `guardarDetalle()` | `guardarExamen.php` + `updateExamen.php` | escribe el detalle en su tabla y marca `realizado='S'` |
| Cargar resultado previo para editar/ver | esquemas → `getTipo1/getTipo2/getParcialOrina/...` | `get*.php` | devuelve el detalle existente |

---

## Hallazgos

### A. El cliente da por guardado sin comprobar la respuesta `msg` (CRÍTICO)

`src/lib/api/laboratorio.ts`:

- `guardarExamenes()` (≈línea 506): `await http(...)` y devuelve `true`
  **sin mirar** `msg`. El backend `guardarExamenes.php` responde siempre
  `{"msg":"si"}` aunque un `execute()` haya fallado (no comprueba errores por
  sentencia ni usa transacción).
- `guardarDetalle()` (≈línea 406): hace `await http('guardarExamen.php')`,
  **ignora** su `msg`, y luego llama `updateExamen()`, devolviendo `true`.
- `updateExamen()`: igual, no valida la respuesta.

Consecuencia: si el servidor responde `{"msg":false}` con HTTP 200 (error SQL,
bloqueo normativo "ya realizado", etc.), `http()` **no lanza** (solo lanza con
`!res.ok`) y la SPA muestra "guardados correctamente". → El usuario cree que se
guardó y no se guardó nada. Es la queja típica de "no guarda bien".

Además `guardarDetalle()` llama a `updateExamen()` **aunque** `guardarExamen.php`
haya devuelto `msg:false`: un examen puede quedar marcado `realizado='S'`
**sin** tener datos de resultado (reporte "emitido" vacío).

### B. `codexamen` enlazado como entero cuando la columna es VARCHAR (ALTO)

`updateExamen.php` línea 11 y `getTipo1.php`/`getTipo2.php` línea 14 usan
`bind_param("ssi", ..., $codexamen)`. La columna `codexamen`/`examen` es
`varchar(12)` y **contiene códigos alfanuméricos** (p. ej. `0042A` CREATININA EN
ORINA, `906130`, etc. — verificados en `bd.sql`).

- Con tipo `i`, mysqli convierte `'0042A'` a `42` → el `UPDATE`/`SELECT` no
  encuentra el registro → el examen **no se marca realizado** o **no se
  precarga** su resultado, sin error visible.
- Incluso en códigos numéricos MySQL compara varchar contra int con coerción de
  tipos, que puede matchear mal.

Debe ser `"sss"` en todos los endpoints que reciban `codexamen`.

### C. `guardarExamenes.php` puede duplicar exámenes ya realizados (ALTO)

`libphp/guardarExamenes.php`:

1. `UPDATE examenes SET realizado='S' WHERE ... AND realizado='S'` (no-op).
2. Por cada codexamen de la selección: borra el pendiente con ese codexamen y
   **`INSERT` incondicional** de una fila nueva.
3. Al final borra pendientes no seleccionados.

Problema: `getSeleccionados.php` devuelve **todos** los exámenes de la fecha
(incluidos los `realizado='S'`), y el paso 2 del cliente los preselecciona.
Si un examen ya está `'S'` y sigue marcado al re-guardar, el paso 2 **no** borra
el `'S'` (solo borra pendientes) pero el `INSERT` crea una fila pendiente
**duplicada** del mismo codexamen. Resultado: el mismo examen aparece dos veces
(uno realizado y otro pendiente).

El guardado debe ser idempotente: solo insertar si **no existe** ninguna fila
para `(identificacion, fecha, codexamen)`.

### D. `REPLACE INTO` depende de la clave de la tabla destino (MEDIO/ALTO)

`libphp/guardarExamen.php` construye `REPLACE INTO {tabla} ({cols}) VALUES (...)`
excluyendo de la whitelist `id`, `ind`, `fechahora`, `hora`, etc.

- `REPLACE` solo reemplaza si la fila nueva choca con una **PK/UNIQUE** de la
  tabla. El payload del cliente **no incluye la PK** (`ind`/`id`) de la fila de
  detalle, así que el reemplazo depende de que la tabla tenga un índice único
  natural (`(identificacion, fecha, examen)` o similar) o de columnas como
  `examenind`/`citasind` que sí viajan en `preservar`.
- **Pendiente de verificar los índices reales de la BD** (phpMyAdmin →
  estructura de `examen_tipo_1/2`, `parcialOrina`, `coprologico`,
  `hemogramaRayto`, `frotisVaginal`, `perfilLipidico`). Si no hay UNIQUE útil,
  cada guardado **inserta una fila nueva** (duplicados de detalle) o el REPLACE
  falla en silencio.
- Si todas las columnas enviadas son rechazadas por la whitelist, `$fields`
  queda vacío y se genera SQL inválido (`REPLACE INTO x () VALUES ()`) →
  `msg:false` silencioso.

### E. `updateExamen.php` marca `'S'` incluso si el detalle no se guardó

Derivado de A: como el cliente no comprueba `msg` de `guardarExamen.php`, el
flujo marca el examen como realizado aunque el `REPLACE` fallara. (Ver A.)

### F. Sin transacción ni control de errores por sentencia

`guardarExamenes.php` ejecuta DELETE + varios INSERT sin `BEGIN/COMMIT` y sin
revisar `$stmt->execute()` por cada uno; si algo falla a mitad, la fecha queda
en un estado parcial mientras la respuesta sigue diciendo `"msg":"si"`.

---

## Correcciones propuestas (antes de los tests)

1. **Cliente** (`laboratorio.ts`):
   - `guardarExamenes()`: leer la respuesta y devolver `resp?.msg !== false`.
   - `guardarDetalle()`: si `guardarExamen.php` responde `msg:false`, **no**
     llamar `updateExamen()` y devolver `false` con el motivo.
   - `updateExamen()`: devolver booleano según `msg`.
   - `http()` podría lanzar cuando el JSON traiga `msg:false` explícito solo en
     endpoints de escritura (o validarlo en la capa API).
2. **Backend**:
   - `updateExamen.php`, `getTipo1.php`, `getTipo2.php`, `relaexentidad.php`:
     `"sss"` en lugar de `"ssi"` para `codexamen`.
   - `guardarExamenes.php`: INSERT solo si no existe fila para
     `(identificacion,fecha,codexamen)`; envolver en transacción y reportar
     errores reales.
   - `guardarExamen.php`: verificar índices/UNIQUE de las tablas destino; si la
     tabla no tiene clave única natural, hacer `UPDATE` si existe fila
     (identificacion+fecha [+examen]) y `INSERT` si no, en vez de `REPLACE`
     ciego; validar que `$fields` no quede vacío.

---

## Correcciones aplicadas (2026)

| Archivo | Cambio |
|---|---|
| `src/lib/api/laboratorio.ts` | `guardarExamenes()`, `guardarDetalle()`, `updateExamen()` ahora **validan `msg`**; `guardarDetalle()` no llama a `updateExamen()` si el detalle no se guardó (evita marcar `realizado='S'` sin datos). |
| `src/lib/api/guardado.ts` (nuevo) | Lógica pura reutilizada por la API y testeada (payload completo, `msgEsOk`, `codigosUnicos`). |
| `libphp/updateExamen.php` | `bind_param` `"sss"` (codexamen VARCHAR alfanumérico). |
| `libphp/getTipo1.php`, `getTipo2.php` | `"sss"` en el bind de `codexamen`. |
| `libphp/guardarExamenes.php` | Reescribir: conserva `'S'`, limpia pendientes y reinserta solo los seleccionados no emitidos → **idempotente, sin duplicados**; responde errores reales (`msg:false` + `error`). Nota: `examenes` es MyISAM (sin transacciones), por eso el orden importa. |
| `libphp/guardarExamen.php` | En vez de `REPLACE` ciego: **UPDATE si existe / INSERT si no** con la clave lógica de lectura (id+fecha, y +examen en tipo 1/2); solo columnas reales (DESCRIBE); `bacteriologo`/`fechaResultados` se manejan según existan; errores devueltos con `msg:false`. |

### Pendiente
- Sincronizar `libphp/` por WinSCP a producción.
- Verificar en phpMyAdmin que las tablas de detalle tengan índice único razonable
  (el nuevo `guardarExamen.php` ya no depende de él, pero conviene saberlo para
  limpieza de duplicados históricos).

---

## Tests que se escribirán después de corregir

Ver apartado de planificación en `docs/PLAN-TESTS-GUARDADO.md`.
