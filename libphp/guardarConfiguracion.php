<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$stmt=$mysqli->prepare("select * from configuracion order by id desc limit 1");
$stmt->execute();
$result=$stmt->get_result();
$datas=$result->fetch_assoc();
$bacteriologo=$datas['bacteriologoLaboratorio'].":T.P. ".$datas['tarjetaPLaboratorio'];

$allowed=['id','fechahora','tabla','ind','nombreExamen','constant','unidades','bacteriologo','doctor','hora','exportar'];
$colsAllow=[];
$resCols=$mysqli->query("DESCRIBE configuracion");
if($resCols){ while($col=$resCols->fetch_assoc()){ $colsAllow[]=strtolower($col['Field']); } }
$fields=[];
$values=[];
$types="";
$params=[];

foreach ($datos as $key=>$value){
if(in_array($key,$allowed)) continue;
if(!in_array(strtolower((string)$key),$colsAllow,true)) continue;
$fields[]=$key;
$values[]="?";
$types.="s";
$params[]=$value;
}

// Solo añadir la columna bacteriologo si existe en la tabla.
if (in_array('bacteriologo', $colsAllow, true)) {
    $fields[]='bacteriologo';
    $values[]="?";
    $types.="s";
    $params[]=$bacteriologo;
}

$fieldStr=implode(",",$fields);
$valueStr=implode(",",$values);
$tabla=$datos->tabla;

$stmt2=$mysqli->prepare("REPLACE INTO $tabla ($fieldStr) VALUES ($valueStr)");
$stmt2->bind_param($types,...$params);

$imageData=base64_decode($datos->urFirmaLaboratorio);
file_put_contents("./printphp/firma.png",$imageData);
$imageDataP=base64_decode($datos->urlLogoLaboratorio);
file_put_contents("./printphp/logo.png",$imageDataP);
file_put_contents("./firma.png",$imageData);

if($stmt2->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$stmt2->close();
$mysqli->close();
