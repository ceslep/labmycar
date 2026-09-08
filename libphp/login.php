<?php
require_once("datos_conexion.php");
require_once("api_guard.php");

$clave = $datos->clave ?? '';

$stmt = $mysqli->prepare("SELECT tarjetaPLaboratorio, nombreLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
$ok = false;
$token = '';
$nombre = '';
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (hash_equals((string)$row['tarjetaPLaboratorio'], (string)$clave)) {
            $ok = true;
            $token = apiTokenEsperado();
            $nombre = $row['nombreLaboratorio'] ?? '';
        }
    }
    $stmt->close();
}
echo json_encode(["msg" => $ok, "token" => $token, "nombre" => $nombre]);
$mysqli->close();
