<?php
require_once("datos_conexion.php");

$codexamen=$datos->codexamen;
$campo=$datos->campo;

$stmt=$mysqli->prepare("select items from opcionesExamenes where codexamen=? and campo=?");
$stmt->bind_param("ss",$codexamen,$campo);
$stmt->execute();
$result=$stmt->get_result();
if ($result->num_rows>0){
echo $result->fetch_assoc()['items'];
}
$stmt->close();
$mysqli->close();
