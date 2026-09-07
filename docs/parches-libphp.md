# Parches propuestos para libphp (backend)

> Propuestas **solo documentadas** aquí; NO se ha modificado `libphp/`.
> Revisa cada parche y, si lo apruebas, aplícalo en tu copia local de `libphp/`
> (se sincroniza automáticamente a `https://mycar.iedeoccidente.com/libphp` por WinSCP).

---

## P1 — guardarExamenes.php: no perder exámenes "Realizado" al reasignar (A2)

**Problema**: hoy hace `DELETE` de todos los exámenes del paciente+fecha y reinserta
`solo los marcados`, con `realizado=NULL`. Si un día ya tiene resultados y se reasigna
(se marca otro examen), los ya realizados vuelven a "Pendiente", y desmarcar uno
realizado deja huérfano su resultado.

**Cambio propuesto** (reemplazar el cuerpo del archivo):

```php
<?php
require_once("datos_conexion.php");

$identificacion=$datos->identificacion;
$fecha=$datos->fecha;

// Conserva los ya realizados (no se deben reasignar ni borrar).
$stmt=$mysqli->prepare("UPDATE examenes SET realizado='S' WHERE identificacion=? AND fecha=? AND realizado='S'");
$stmt->bind_param("ss",$identificacion,$fecha);
$stmt->execute();
$stmt->close();

// Solo se reemplazan las asignaciones NO realizadas.
$stmt=$mysqli->prepare("DELETE FROM examenes WHERE identificacion=? AND fecha=? AND (realizado IS NULL OR realizado<>'S')");
$stmt->bind_param("ss",$identificacion,$fecha);
$stmt->execute();
$stmt->close();

$Examenes=json_decode($datos->examenes);
$stmt2=$mysqli->prepare("REPLACE INTO examenes (codexamen,identificacion,fecha) VALUES (?,?,?)");
foreach($Examenes as $examen){
    $codexamen=$examen->codigo;
    $stmt2->bind_param("sss",$codexamen,$identificacion,$fecha);
    $stmt2->execute();
}
echo json_encode(["msg"=>"si"]);
$stmt2->close();
$mysqli->close();
```

**Nota**: la SPA debe además impedir desmarcar exámenes ya realizados al reasignar
(se añadirá cuando se active el parche).

---

## P2 — savePaciente.php: editar paciente sin borrar sus demás datos (A3)

**Problema**: `REPLACE INTO paciente` elimina la fila original y solo conserva las 8
columnas del formulario (se pierden dirección, barrio, teléfono 2, ocupación,
estado civil, correo 2, lugar de nacimiento, fecha/hora de registro, etc.).

**Cambio propuesto** (reemplazar la sentencia por UPDATE+INSERT seguro):

```php
$stmt=$mysqli->prepare("UPDATE paciente SET nombres=?,apellidos=?,fecnac=?,genero=?,telefono=?,correo=?,entidad=? WHERE identificacion=?");
$stmt->bind_param("ssssssss",$nombres,$apellidos,$fecnac,$genero,$telefono,$correo,$entidad,$identificacion);
$stmt->execute();
if($mysqli->affected_rows===0){
    $stmt=$mysqli->prepare("INSERT INTO paciente (identificacion,nombres,apellidos,fecnac,genero,telefono,correo,entidad) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssss",$identificacion,$nombres,$apellidos,$fecnac,$genero,$telefono,$correo,$entidad);
    $stmt->execute();
}
echo json_encode(["msg"=>true]);
```

---

## P3 — guardarExamen.php: conservar metadatos al re-guardar un resultado (A4)

**Problema**: `REPLACE INTO {tabla}` con solo las columnas enviadas; el resto de la
fila (metadatos previos) se pierde. El cliente SPA ya envía todas las columnas de
cada tabla (verificadas), así que en la práctica el riesgo es bajo; como refuerzo:

**Opción recomendada**: sustituir el final por dos pasos: si ya existe fila con la
misma clave (`identificacion+fecha` [+`examen` cuando aplique]) hacer `UPDATE` de
solo las columnas recibidas, y si no, `INSERT`. (Requiere conocer la clave por tabla;
dejar este parche pendiente de un inventario de PK por tabla antes de aplicarlo.)

---

## P4 — getPacientes.php: orden estable + búsqueda por servidor (M2)

**Problema**: `SELECT ... LIMIT 200` sin `ORDER BY` → lista inestable y solo 200
pacientes alcanzables desde el cliente.

**Cambio propuesto** (añadir al final de la query):

```sql
ORDER BY apellidos ASC, nombres ASC
```

(Si la respuesta con `criterio` ya filtra por LIKE, el buscador de la SPA con ≥3
caracteres llama a este endpoint para buscar en toda la base.)

---

## P5 — eliminarExamen.php: SQL con placeholder inválido (B1)

**Problema**: usa `DELETE FROM ?` (placeholder de tabla no válido en MySQL).

**Recomendación**: si se necesita eliminar exámenes de un paciente+fecha, corregir
borrando solo desde la tabla `examenes` (las tablas de detalle se conservan o se
borran explícitamente con nombres fijos, nunca con placeholder):

```php
$stmt=$mysqli->prepare("DELETE FROM examenes WHERE identificacion=? AND fecha=?");
```

---

## P6 — setEntidad.php: cambiar la entidad de un examen (acción `actualizar_entidad` de prt.php)

**Problema**: el panel web (prt.php) permite cambiar la entidad de un examen con el
POST interno `actualizar_entidad`, pero no hay un endpoint REST para que la SPA lo haga.

**Cambio propuesto**: nuevo archivo `libphp/setEntidad.php`:

```php
<?php
require_once("cors.php");
require_once("datos_conexion.php");

$identificacion=$datos->identificacion ?? '';
$fecha=$datos->fecha ?? '';
$codexamen=$datos->codexamen ?? '';
$entidad=$datos->entidad ?? '';

$stmt=$mysqli->prepare("UPDATE examenes SET entidad=? WHERE identificacion=? AND fecha=? AND codexamen=?");
$stmt->bind_param("ssss",$entidad,$identificacion,$fecha,$codexamen);
$ok=$stmt->execute();
echo json_encode(["msg"=>$ok]);
$stmt->close();
$mysqli->close();
```

Con este endpoint la SPA habilitaría el selector de entidad por examen en el Panel.
