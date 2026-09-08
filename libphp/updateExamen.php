<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$identificacion=$datos->identificacion;
$fecha=$datos->fecha;
$codexamen=$datos->codexamen;

// codexamen es VARCHAR (admite códigos alfanuméricos como '0042A'); siempre 's'.
$stmt=$mysqli->prepare("UPDATE examenes set realizado='S' where identificacion=? and fecha=? and codexamen=?");
$stmt->bind_param("sss",$identificacion,$fecha,$codexamen);
if($stmt->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false,"error"=>$stmt->error]);
$stmt->close();
$mysqli->close();
