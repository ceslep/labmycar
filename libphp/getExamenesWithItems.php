<?php
$datos=json_decode("{}");
require_once("datos_conexion.php");

$criterio="";
if(isset($datos->criterio)) 
$criterio=$datos->criterio;
  $sql="select codexamen from opcionesExamenes
  ";
  
 // SQIO($mysqli,$sql);
  
  if ($result=$mysqli->query($sql)){
        echo json_encode($result->fetch_All(MYSQLI_ASSOC));
  }
else
    echo json_encode(["msg"=>false,"sql"=>$sql]);
$result->free();
$mysqli->close();
