<?php
require_once("cors.php");
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

// Borrado explícito por tabla fija (nunca con placeholder de tabla).
$tables = ["examenes", "parcialOrina", "coprologico", "frotisVaginal", "perfilLipidico", "hemogramaRayto", "examen_tipo_1", "examen_tipo_2", "examen_tipo_5", "examen_tipo_7"];
$eliminadas = [];
$errores = [];

foreach ($tables as $table) {
    $cols = [];
    $res = $mysqli->query("DESCRIBE `" . $mysqli->real_escape_string($table) . "`");
    if (!$res) continue;
    while ($c = $res->fetch_assoc()) $cols[] = $c['Field'];
    if (!in_array('identificacion', $cols, true) || !in_array('fecha', $cols, true)) continue;
    $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE identificacion = ? AND fecha = ?");
    if (!$stmt) { $errores[] = $table; continue; }
    $stmt->bind_param("ss", $identificacion, $fecha);
    $stmt->execute();
    $stmt->close();
    $eliminadas[] = $table;
}

echo json_encode(["msg" => true, "tablas" => $eliminadas, "errores" => $errores]);
$mysqli->close();
