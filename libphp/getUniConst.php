<?php
require_once("datos_conexion.php");

$codigo='';
if (isset($datos->codexamen)){
$codigo=$datos->codexamen;
}

$stmt=$mysqli->prepare("SELECT unidades,constante from procedimientos where codigo=?");
$stmt->bind_param("s",$codigo);
$stmt->execute();
$result=$stmt->get_result();
if ($result->num_rows>0)
echo json_encode(["msg"=>true,"data"=>$result->fetch_all(MYSQLI_ASSOC)[0]]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
