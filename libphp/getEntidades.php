<?php
require_once("datos_conexion.php");


  $sql="SELECT distinct entidad from paciente order by entidad
  ";
  $result=$mysqli->query($sql);
  if ($result->num_rows>0)
  echo json_encode(["msg"=>true,"data"=>$result->fetch_all(MYSQLI_ASSOC)]);  
else
    echo json_encode(["msg"=>false]);
$result->free();
$mysqli->close();
