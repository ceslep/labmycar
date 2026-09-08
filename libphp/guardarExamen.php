<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

// Nombre de tabla seguro (siempre viene del esquema de la SPA).
$tabla = (string)($datos->tabla ?? '');
if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla)) {
    echo json_encode(["msg" => false, "error" => "tabla inválida"]);
    $mysqli->close();
    exit;
}

// Protección normativa: si el examen ya está marcado como realizado (resultado
// emitido), no se permite guardar ni reemplazar sus resultados.
$codexamenGuardar = (string)($datos->codexamen ?? '');
$idNorm = (string)($datos->identificacion ?? '');
$fechaNorm = (string)($datos->fecha ?? '');
if (!empty($codexamenGuardar) && !empty($idNorm) && !empty($fechaNorm)) {
    $stmtChk = $mysqli->prepare("SELECT realizado FROM examenes WHERE identificacion=? AND fecha=? AND codexamen=? LIMIT 1");
    if ($stmtChk) {
        $stmtChk->bind_param("sss", $idNorm, $fechaNorm, $codexamenGuardar);
        $stmtChk->execute();
        $resChk = $stmtChk->get_result();
        if ($resChk->num_rows > 0) {
            $realizado = $resChk->fetch_assoc()['realizado'];
            if ($realizado === 'S') {
                echo json_encode(["msg" => false, "error" => "Resultado ya realizado. Por normativa no es modificable."]);
                $stmtChk->close();
                $mysqli->close();
                exit;
            }
        }
        $stmtChk->close();
    }
}

$result = $mysqli->query("select * from configuracion order by id desc limit 1");
$datas = $result ? $result->fetch_assoc() : [];
$bacteriologo = ($datas['bacteriologoLaboratorio'] ?? '') . ":T.P. " . ($datas['tarjetaPLaboratorio'] ?? '');

// Columnas reales de la tabla destino (lista blanca).
$colsAllow = [];
$resCols = $mysqli->query("DESCRIBE `$tabla`");
if ($resCols) {
    while ($col = $resCols->fetch_assoc()) $colsAllow[] = strtolower($col['Field']);
}
if (!$resCols || count($colsAllow) === 0) {
    echo json_encode(["msg" => false, "error" => "no se pudo leer la tabla $tabla"]);
    $mysqli->close();
    exit;
}
$colSet = array_flip($colsAllow);

// Claves lógicas de unicidad: la misma con la que luego se lee el detalle.
// - examen_tipo_1 / examen_tipo_2: identificacion + fecha + examen(=codexamen);
// - resto de tablas (parcialOrina, coprologico, hemogramaRayto, frotisVaginal,
//   perfilLipidico, ...): identificacion + fecha (aunque tengan columna examen,
//   el backend las lee solo por id+fecha).
$claves = [];
if (isset($colSet['identificacion'])) $claves['identificacion'] = (string)($datos->identificacion ?? '');
if (isset($colSet['fecha'])) $claves['fecha'] = (string)($datos->fecha ?? '');
$conExamen = ($tabla === 'examen_tipo_1' || $tabla === 'examen_tipo_2');
if ($conExamen && isset($colSet['examen'])) {
    $ex = (string)($datos->examen ?? '');
    if ($ex !== '') $claves['examen'] = $ex;
}
if (count($claves) < 2) {
    echo json_encode(["msg" => false, "error" => "faltan datos de identificación/fecha"]);
    $mysqli->close();
    exit;
}

// Columnas a escribir (valores del payload permitidos; excluye metadatos y claves).
$escritura = [];
foreach ($datos as $key => $value) {
    if ($key == 'id' || $key == 'ind' || $key == 'fechahora' || $key == 'tabla' || $key == 'codexamen' ||
        $key == 'nombreExamen' || $key == 'constant' || $key == 'unidades' || $key == 'doctor' ||
        $key == 'hora' || $key == 'exportar') continue;
    if (!isset($colSet[strtolower((string)$key)])) continue;
    if (array_key_exists((string)$key, $claves)) continue; // las claves van en el WHERE
    $escritura[(string)$key] = $value === null ? '' : (string)$value;
}
if (isset($colSet['bacteriologo'])) $escritura['bacteriologo'] = $bacteriologo;
// `fechaResultados` suele ser NOT NULL sin valor por defecto: si no viene en el
// payload (alta nueva), se toma la fecha del examen para que el INSERT no falle.
$fechaVal = (string)($claves['fecha'] ?? '');
if (isset($colSet['fechaResultados']) && !array_key_exists('fechaResultados', $escritura) && $fechaVal !== '') {
    $escritura['fechaResultados'] = $fechaVal;
}
if (count($escritura) === 0) {
    echo json_encode(["msg" => false, "error" => "no hay columnas válidas para guardar"]);
    $mysqli->close();
    exit;
}

// Construye lista de ? en orden estable.
$ordenClaves = array_keys($claves);
$ordenEsc = array_keys($escritura);

// ¿Ya existe la fila? (misma clave lógica con la que se leerá).
$whereSql = implode(' AND ', array_map(fn($c) => "`$c`=?", $ordenClaves));
$typesC = str_repeat('s', count($ordenClaves));
$stmtEx = $mysqli->prepare("SELECT 1 FROM `$tabla` WHERE $whereSql LIMIT 1");
$valsC = array_values($claves);
if ($stmtEx) {
    $refsC = [];
    foreach ($valsC as $i => &$v) $refsC[$i] = &$v;
    array_unshift($refsC, $typesC);
    call_user_func_array([$stmtEx, 'bind_param'], $refsC);
    $stmtEx->execute();
    $existe = $stmtEx->get_result()->num_rows > 0;
    $stmtEx->close();
} else {
    echo json_encode(["msg" => false, "error" => "no se pudo comprobar el registro existente"]);
    $mysqli->close();
    exit;
}

if ($existe) {
    // UPDATE de solo las columnas editadas.
    $setSql = implode(', ', array_map(fn($c) => "`$c`=?", $ordenEsc));
    $sql = "UPDATE `$tabla` SET $setSql WHERE $whereSql";
    $types = $typesC . str_repeat('s', count($ordenEsc));
    $params = array_merge(array_values($claves), array_values($escritura));
} else {
    // INSERT con claves + valores.
    $todas = array_merge($ordenClaves, $ordenEsc);
    $colsSql = implode(',', array_map(fn($c) => "`$c`", $todas));
    $marks = implode(',', array_fill(0, count($todas), '?'));
    $sql = "INSERT INTO `$tabla` ($colsSql) VALUES ($marks)";
    $types = str_repeat('s', count($todas));
    $params = array_merge(array_values($claves), array_values($escritura));
}

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(["msg" => false, "error" => $mysqli->error]);
    $mysqli->close();
    exit;
}
$refs = [];
foreach ($params as $i => &$v) $refs[$i] = &$v;
array_unshift($refs, $types);
call_user_func_array([$stmt, 'bind_param'], $refs);
if ($stmt->execute()) {
    echo json_encode(["msg" => true, "actualizado" => $existe]);
} else {
    echo json_encode(["msg" => false, "error" => $stmt->error]);
}
$stmt->close();
$mysqli->close();
