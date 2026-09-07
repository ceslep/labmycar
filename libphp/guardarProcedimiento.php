<?php
require_once("cors.php");
require_once("datos_conexion.php");

$allowed=['id'];
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

$stmt=$mysqli->prepare("REPLACE INTO procedimientos ($fieldStr) VALUES ($valueStr)");
$stmt->bind_param($types,...$params);

if($stmt->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
