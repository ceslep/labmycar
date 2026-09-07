<?php
require_once("datos_conexion.php");

$fecha="";
if(isset($datos->fecha))
$fecha=$datos->fecha;

$stmt=$mysqli->prepare("SELECT distinct paciente.* from paciente inner join examenes on paciente.identificacion=examenes.identificacion where examenes.fecha=? order by examenes.ind");
$stmt->bind_param("s",$fecha);
$stmt->execute();
$result=$stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
