<?php
require_once("datos_conexion.php");

$fecha='';
if (isset($datos->fecha))
$fecha=$datos->fecha;

$stmt=$mysqli->prepare("SELECT * from procedimientos inner join examenes on procedimientos.codigo=examenes.codexamen where examenes.fecha=?");
$stmt->bind_param("s",$fecha);
$stmt->execute();
$result=$stmt->get_result();
if ($result->num_rows>0)
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
else echo json_encode([]);
$stmt->close();
$mysqli->close();
