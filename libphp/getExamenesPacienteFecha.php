<?php
require_once("datos_conexion.php");

$fecha="";
$identificacion="";
if(isset($datos->fecha)) 
$fecha=$mysqli->real_escape_string($datos->fecha);
if(isset($datos->identificacion)) 
$identificacion=$mysqli->real_escape_string($datos->identificacion);
  $sql="SELECT examenes.*,procedimientos.nombre as examen,procedimientos.tipo as tipo from examenes 
  inner join procedimientos on examenes.codexamen=procedimientos.codigo 
  ";
  if($fecha!=""){
  $sql.=" where 1=1";
  $sql.=" and (";
  $sql.="identificacion = '$identificacion' and ";
  $sql.="fecha = '$fecha'";
    $sql.=")";  
  $sql.=" order by fecha desc,nombre";  
  }
  //SQIO($mysqli,$sql);
  
  if ($result=$mysqli->query($sql))
  echo json_encode($result->fetch_all(MYSQLI_ASSOC));  
else
    echo json_encode(["msg"=>false,"sql"=>$sql]);
$result->free();
$mysqli->close();
