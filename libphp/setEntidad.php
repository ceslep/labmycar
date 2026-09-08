<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$identificacion = $datos->identificacion ?? '';
$fecha = $datos->fecha ?? '';
$codexamen = $datos->codexamen ?? '';
$entidad = $datos->entidad ?? '';

if (empty($identificacion) || empty($fecha) || empty($codexamen)) {
    echo json_encode(["msg" => false, "error" => "identificacion, fecha y codexamen son obligatorios"]);
    $mysqli->close();
    exit;
}

$stmt = $mysqli->prepare("UPDATE examenes SET entidad=? WHERE identificacion=? AND fecha=? AND codexamen=?");
if (!$stmt) {
    echo json_encode(["msg" => false, "error" => $mysqli->error]);
    $mysqli->close();
    exit;
}
$stmt->bind_param("ssss", $entidad, $identificacion, $fecha, $codexamen);
$ok = $stmt->execute();
echo json_encode(["msg" => $ok]);
$stmt->close();
$mysqli->close();
