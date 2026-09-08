<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$identificacion = $datos->identificacion ?? '';
$fecha = $datos->fecha ?? '';
if (empty($identificacion) || empty($fecha)) {
    echo json_encode(["msg" => false, "error" => "faltan datos"]);
    $mysqli->close();
    exit;
}

// Lista de códigos seleccionados (sin duplicados ni vacíos).
$cods = [];
$Examenes = json_decode($datos->examenes ?? '[]');
if ($Examenes && is_array($Examenes)) {
    foreach ($Examenes as $examen) {
        $code = trim((string)($examen->codigo ?? ''));
        if ($code !== '' && !in_array($code, $cods, true)) $cods[] = $code;
    }
}

// Nota: la tabla `examenes` es MyISAM (sin transacciones), por eso el orden
// importa: primero se limpian los pendientes y luego se reinsertan los
// seleccionados, de forma que el resultado sea idempotente y nunca duplique
// los exámenes ya emitidos ('S').

// 1) Exámenes ya emitidos en esa fecha: se conservan siempre (no modificables).
$hayS = [];
$stSel = $mysqli->prepare("SELECT codexamen FROM examenes WHERE identificacion=? AND fecha=? AND realizado='S'");
if (!$stSel) {
    echo json_encode(["msg" => false, "error" => $mysqli->error]);
    $mysqli->close();
    exit;
}
$stSel->bind_param("ss", $identificacion, $fecha);
$stSel->execute();
$resSel = $stSel->get_result();
while ($r = $resSel->fetch_assoc()) $hayS[(string)$r['codexamen']] = true;
$stSel->close();

// 2) Limpia TODOS los pendientes de la fecha (los no seleccionados deben
//    desaparecer; los seleccionados se reinsertan en el paso 3).
$stDel = $mysqli->prepare("DELETE FROM examenes WHERE identificacion=? AND fecha=? AND (realizado IS NULL OR realizado<>'S')");
if (!$stDel) {
    echo json_encode(["msg" => false, "error" => $mysqli->error]);
    $mysqli->close();
    exit;
}
$stDel->bind_param("ss", $identificacion, $fecha);
if (!$stDel->execute()) {
    echo json_encode(["msg" => false, "error" => $stDel->error]);
    $stDel->close();
    $mysqli->close();
    exit;
}
$stDel->close();

// 3) Inserta un pendiente por cada examen seleccionado que no esté ya emitido.
$stIns = $mysqli->prepare("INSERT INTO examenes (codexamen, identificacion, fecha) VALUES (?,?,?)");
if (!$stIns) {
    echo json_encode(["msg" => false, "error" => $mysqli->error]);
    $mysqli->close();
    exit;
}
$insertados = 0;
foreach ($cods as $code) {
    if (isset($hayS[$code])) continue; // ya emitido: se conserva tal cual
    $stIns->bind_param("sss", $code, $identificacion, $fecha);
    if (!$stIns->execute()) {
        echo json_encode(["msg" => false, "error" => $stIns->error]);
        $stIns->close();
        $mysqli->close();
        exit;
    }
    $insertados++;
}
$stIns->close();

echo json_encode(["msg" => true, "guardados" => $insertados]);
$mysqli->close();
