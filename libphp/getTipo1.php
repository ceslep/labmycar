<?php
require_once("datos_conexion.php");
$identificacion='';
$fecha='';
$codexamen='';
if (isset($datos->identificacion))
$identificacion=$datos->identificacion;
if (isset($datos->fecha))
$fecha=$datos->fecha;
if (isset($datos->codexamen))
$codexamen=$datos->codexamen;

$stmt=$mysqli->prepare("SELECT *,procedimientos.nombre as nombreExamen,procedimientos.constante as constant,procedimientos.unidades from examen_tipo_1 inner join procedimientos on examen_tipo_1.examen=procedimientos.codigo where identificacion=? and fecha=? and examen_tipo_1.examen=?");
$stmt->bind_param("ssi",$identificacion,$fecha,$codexamen);
$stmt->execute();
$result=$stmt->get_result();
if ($result->num_rows>0)
echo json_encode(["msg"=>true,"data"=>$result->fetch_all(MYSQLI_ASSOC)[0]]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
