<?php
require_once("cors.php");
require_once("datos_conexion.php");

$identificacion=$datos?->identificacion ?? '';
$fecha=$datos?->fecha ?? '';

$tables=["examenes","examen_tipo1","examen_tipo2","examen_tipo3","coprologico","frotisVaginal","parcialOrina","perfilLipidico","hemogramaRayto"];
$stmt=$mysqli->prepare("DELETE FROM ? WHERE identificacion=? AND fecha=?");

foreach($tables as $table){
$stmt->bind_param("sss",$table,$identificacion,$fecha);
$stmt->execute();
}

echo json_encode(["msg"=>true]);
$stmt->close();
$mysqli->close();
