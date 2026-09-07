<?php
require_once("datos_conexion.php");

$identificacion='';
$fecha='';
if (isset($datos->identificacion))
$identificacion=$datos->identificacion;
if (isset($datos->fecha))
$fecha=$datos->fecha;

$stmt=$mysqli->prepare("SELECT * from frotisVaginal where identificacion=? and fecha=?");
$stmt->bind_param("ss",$identificacion,$fecha);
$stmt->execute();
$result=$stmt->get_result();
if ($result->num_rows>0)
echo json_encode(["msg"=>true,"data"=>$result->fetch_all(MYSQLI_ASSOC)[0]]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
