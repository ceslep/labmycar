<?php
require_once("datos_conexion.php");

$identificacion=$datos->identificacion;
$fecha=$datos->fecha;

$stmt=$mysqli->prepare("DELETE FROM examenes WHERE identificacion=? AND fecha=?");
$stmt->bind_param("ss",$identificacion,$fecha);
$stmt->execute();

$Examenes=json_decode($datos->examenes);
$stmt2=$mysqli->prepare("REPLACE INTO examenes (codexamen,identificacion,fecha) VALUES (?,?,?)");
foreach($Examenes as $examen){
$codexamen=$examen->codigo;
$stmt2->bind_param("sss",$codexamen,$identificacion,$fecha);
$stmt2->execute();
}

echo json_encode(["msg"=>"si"]);
$stmt->close();
$stmt2->close();
$mysqli->close();
