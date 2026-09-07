<?php
require_once("datos_conexion.php");

$criterio="";
if(isset($datos->criterio))
$criterio=$datos->criterio;

if($criterio!=""){
$stmt=$mysqli->prepare("SELECT examenes.*,procedimientos.nombre as examen,procedimientos.tipo as tipo,tabla,info from examenes inner join procedimientos on examenes.codexamen=procedimientos.codigo where identificacion=? order by fecha desc,nombre");
$stmt->bind_param("s",$criterio);
}else{
$stmt=$mysqli->prepare("SELECT examenes.*,procedimientos.nombre as examen,procedimientos.tipo as tipo,tabla,info from examenes inner join procedimientos on examenes.codexamen=procedimientos.codigo order by fecha desc,nombre");
}
$stmt->execute();
$result=$stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
