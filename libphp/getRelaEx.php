<?php
require_once("datos_conexion.php");

$fecha1=$datos->fecha1;
$fecha2=$datos->fecha2;

$stmt=$mysqli->prepare("select procedimientos.codigo,procedimientos.nombre,count(procedimientos.codigo) as cantidad from procedimientos inner join examenes on procedimientos.codigo=examenes.codexamen where examenes.fecha between ? and ? group by examenes.codexamen order by procedimientos.nombre");
$stmt->bind_param("ss",$fecha1,$fecha2);
$stmt->execute();
$result=$stmt->get_result();

$dataEx=[];
if ($result->num_rows>0){
while($dato=$result->fetch_assoc()){
$codexamen=$dato['codigo'];
$stmt2=$mysqli->prepare("Select * from paciente inner join examenes on paciente.identificacion=examenes.identificacion where examenes.codexamen=? AND examenes.fecha between ? and ?");
$stmt2->bind_param("sss",$codexamen,$fecha1,$fecha2);
$stmt2->execute();
$resultPacientes=$stmt2->get_result();
$dataEx[]=array("dataExamen"=>$dato,"datosPacientes"=>($resultPacientes->fetch_all(MYSQLI_ASSOC)));
$stmt2->close();
}
}
echo json_encode($dataEx);
$stmt->close();
$mysqli->close();
