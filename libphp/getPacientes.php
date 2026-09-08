<?php
require_once("datos_conexion.php");

$criterio = "";
if (isset($datos->criterio)) $criterio = $datos->criterio;

if ($criterio !== "") {
    $criterio = "%" . $criterio . "%";
    $stmt = $mysqli->prepare("SELECT * FROM paciente WHERE identificacion LIKE ? OR nombres LIKE ? OR apellidos LIKE ? OR fecnac LIKE ? ORDER BY apellidos ASC, nombres ASC LIMIT 200");
    $stmt->bind_param("ssss", $criterio, $criterio, $criterio, $criterio);
} else {
    $stmt = $mysqli->prepare("SELECT * FROM paciente ORDER BY apellidos ASC, nombres ASC LIMIT 200");
}
$stmt->execute();
$result = $stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
