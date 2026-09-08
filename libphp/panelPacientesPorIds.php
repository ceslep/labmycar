<?php
// Devuelve nombres de pacientes por lista de identificaciones (para tarjetas del Panel).
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$ids = $datos->ids ?? [];
if (!is_array($ids) || count($ids) === 0) {
    echo json_encode([]);
    $mysqli->close();
    exit;
}
$ids = array_slice(array_map('trim', $ids), 0, 600);
$n = count($ids);
$marks = implode(',', array_fill(0, $n, '?'));
$types = str_repeat('s', $n);

$sql = "SELECT identificacion, CONCAT_WS(' ', nombres, apellidos) AS nombre_completo, edad, genero, fecnac, entidad FROM paciente WHERE identificacion IN ($marks) LIMIT 600";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode([]);
    $mysqli->close();
    exit;
}
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$res = $stmt->get_result();
$filas = [];
if ($res) while ($f = $res->fetch_assoc()) $filas[] = $f;
echo json_encode($filas);
$stmt->close();
$mysqli->close();
