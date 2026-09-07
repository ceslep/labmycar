<?php
require_once("datos_conexion.php");

$identificacion=$datos->identificacion;
$numero=$datos->numero;

$stmt=$mysqli->prepare("SELECT examenes.*,procedimientos.nombre as examen,procedimientos.tipo as tipo,tabla,info, concat_ws(' ',nombres,apellidos) as nombres,fecnac,round((to_days(curdate())-(to_days(fecnac)))/365.242199,2) as edad from examenes inner join procedimientos on examenes.codexamen=procedimientos.codigo inner join paciente on examenes.identificacion=paciente.identificacion where examenes.identificacion=? and (?=year(fecnac) or ?=right(telefono,4)) order by fecha desc,nombre");
$stmt->bind_param("ssi",$identificacion,$numero,$numero);
$stmt->execute();
$result=$stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
