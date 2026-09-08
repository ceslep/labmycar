<?php
require_once("cors.php");
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$allowed=['id'];
$colsAllow=[];
$resCols=$mysqli->query("DESCRIBE procedimientos");
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

$fieldStr=implode(",",$fields);
$valueStr=implode(",",$values);

$stmt=$mysqli->prepare("REPLACE INTO procedimientos ($fieldStr) VALUES ($valueStr)");
$stmt->bind_param($types,...$params);

if($stmt->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
