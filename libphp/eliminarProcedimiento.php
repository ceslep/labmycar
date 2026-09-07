<?php
require_once("cors.php");
require_once("datos_conexion.php");

$ind = intval($datos->ind ?? 0);

if ($ind <= 0) {
    echo json_encode(["msg" => false, "error" => "ind requerido"]);
    $mysqli->close();
    exit;
}

$sql = "DELETE FROM procedimientos WHERE ind = $ind";

if ($mysqli->query($sql))
    echo json_encode(["msg" => true]);
else
    echo json_encode(["msg" => false, "error" => $mysqli->error]);

$mysqli->close();
