<?php
require_once("datos_conexion.php");

$criterio = "";
if (isset($datos->criterio)) $criterio = $datos->criterio;

// Listado global deshabilitado por rendimiento (histórico completo).
if ($criterio === "") {
    echo json_encode([]);
    $mysqli->close();
    exit;
}

$stmt = $mysqli->prepare("SELECT examenes.*, procedimientos.nombre as examen, procedimientos.tipo as tipo, tabla, info FROM examenes INNER JOIN procedimientos ON examenes.codexamen=procedimientos.codigo WHERE identificacion=? ORDER BY fecha DESC, nombre");
$stmt->bind_param("s", $criterio);
$stmt->execute();
$result = $stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
