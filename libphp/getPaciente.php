<?php
require_once("datos_conexion.php");
$identificacion='';
if (isset($datos->identificacion))
$identificacion=$datos->identificacion;

$stmt=$mysqli->prepare("SELECT * from paciente where identificacion=?");
$stmt->bind_param("s",$identificacion);
$stmt->execute();
$result=$stmt->get_result();
if ($result->num_rows>0)
echo json_encode(["msg"=>true,"data"=>$result->fetch_all(MYSQLI_ASSOC)[0]]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
