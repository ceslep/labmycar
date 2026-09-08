<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$codexamen=$datos->codexamen;
$campo=$datos->campo;
$items=$datos->items;

$stmt=$mysqli->prepare("REPLACE INTO opcionesExamenes (codexamen,campo,items) VALUES (?,?,?)");
$stmt->bind_param("sss",$codexamen,$campo,$items);
if($stmt->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
