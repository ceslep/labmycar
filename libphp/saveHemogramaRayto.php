<?php
require_once("datos_conexion.php");

$tabla=$datos->tabla;

$stmt=$mysqli->prepare("select * from configuracion order by id desc limit 1");
$stmt->execute();
$result=$stmt->get_result();
$datas=$result->fetch_assoc();
$bacteriologo=$datas['bacteriologoLaboratorio'].":T.P. ".$datas['tarjetaPLaboratorio'];

$allowed=['id','fechahora','tabla','ind','nombreExamen','constant','unidades','bacteriologo','doctor','hora','exportar'];
$fields=[];
$values=[];
$types="";
$params=[];

foreach ($datos as $key=>$value){
if(in_array($key,$allowed)) continue;
$fields[]=$key;
$values[]="?";
$types.="s";
$params[]=$value;
}

$fieldStr=implode(",",$fields);
$valueStr=implode(",",$values);

$stmt2=$mysqli->prepare("REPLACE INTO $tabla ($fieldStr) VALUES ($valueStr)");
$stmt2->bind_param($types,...$params);

if($stmt2->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$stmt2->close();
$mysqli->close();
