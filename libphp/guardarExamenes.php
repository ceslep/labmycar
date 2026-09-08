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

// 1) Conserva los exámenes ya emitidos (no se reasignan ni se borran).
$u = $mysqli->prepare("UPDATE examenes SET realizado='S' WHERE identificacion=? AND fecha=? AND realizado='S'");
$u->bind_param("ss", $identificacion, $fecha);
$u->execute();
$u->close();

$Examenes = json_decode($datos->examenes ?? '[]');
$cods = [];

$stmtDel = $mysqli->prepare("DELETE FROM examenes WHERE identificacion=? AND fecha=? AND codexamen=? AND (realizado IS NULL OR realizado<>'S')");
$stmtIns = $mysqli->prepare("INSERT INTO examenes (codexamen, identificacion, fecha) VALUES (?,?,?)");
if ($Examenes && is_array($Examenes)) {
    foreach ($Examenes as $examen) {
        $code = $examen->codigo ?? '';
        if ($code === '') continue;
        $cods[] = $code;
        $stmtDel->bind_param("sss", $identificacion, $fecha, $code);
        $stmtDel->execute();
        $stmtIns->bind_param("sss", $code, $identificacion, $fecha);
        $stmtIns->execute();
    }
}
$stmtDel->close();
$stmtIns->close();

// 2) Elimina pendientes de esa fecha que ya no están en la selección.
$types = "ss";
$params = [$identificacion, $fecha];
$sql = "DELETE FROM examenes WHERE identificacion=? AND fecha=? AND (realizado IS NULL OR realizado<>'S')";
if (count($cods) > 0) {
    $sql .= " AND codexamen NOT IN (" . implode(',', array_fill(0, count($cods), '?')) . ")";
    $types .= str_repeat('s', count($cods));
    foreach ($cods as $c) $params[] = $c;
}
$st = $mysqli->prepare($sql);
if ($st) {
    $refs = [];
    foreach ($params as $i => &$v) $refs[$i] = &$v;
    array_unshift($refs, $types);
    call_user_func_array([$st, 'bind_param'], $refs);
    $st->execute();
    $st->close();
}

echo json_encode(["msg" => "si"]);
$mysqli->close();
