<?php
// Configuración pública ligera (nombre, corto y logo) para arranque/login/portal.
require_once("datos_conexion.php");

$r = $mysqli->query("SELECT nombreLaboratorio, nombreCorto, urlLogoLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
if ($r && $row = $r->fetch_assoc()) {
    echo json_encode(["msg" => true, "data" => $row]);
} else {
    echo json_encode(["msg" => false]);
}
$mysqli->close();
