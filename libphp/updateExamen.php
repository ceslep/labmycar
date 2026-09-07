<?php
require_once("datos_conexion.php");

$identificacion=$datos->identificacion;
$fecha=$datos->fecha;
$codexamen=$datos->codexamen;

$stmt=$mysqli->prepare("UPDATE examenes set realizado='S' where identificacion=? and fecha=? and codexamen=?");
$stmt->bind_param("ssi",$identificacion,$fecha,$codexamen);
if($stmt->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
