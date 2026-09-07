<?php
require_once("datos_conexion.php");

$codigo=$datos->codigo;

$stmt=$mysqli->prepare("SELECT * from procedimientos where codigo=? order by nombre");
$stmt->bind_param("s",$codigo);
$stmt->execute();
$result=$stmt->get_result();
if ($result->num_rows>0)
echo json_encode($result->fetch_all(MYSQLI_ASSOC)[0]);
$stmt->close();
$mysqli->close();
