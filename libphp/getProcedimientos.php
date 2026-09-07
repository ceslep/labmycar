<?php
require_once("datos_conexion.php");

  $sql="SELECT * from procedimientos
        order by nombre
  ";
  $result=$mysqli->query($sql);
  if ($result->num_rows>0)
  echo json_encode($result->fetch_all(MYSQLI_ASSOC));  

$result->free();
$mysqli->close();
