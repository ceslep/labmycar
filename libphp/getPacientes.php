<?php
require_once("datos_conexion.php");

$criterio="";
if(isset($datos->criterio))
$criterio=$datos->criterio;

if($criterio!=""){
$criterio="%".$criterio."%";
$stmt=$mysqli->prepare("SELECT * from paciente where identificacion like ? or nombres like ? or apellidos like ? or fecnac like ? limit 200");
$stmt->bind_param("ssss",$criterio,$criterio,$criterio,$criterio);
}else{
$stmt=$mysqli->prepare("SELECT * from paciente limit 200");
}
$stmt->execute();
$result=$stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
