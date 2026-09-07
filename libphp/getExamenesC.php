<?php
require_once("datos_conexion.php");

$fecha1=$datos->fecha1;
$fecha2=$datos->fecha2;
$genero=$datos->genero;
$codigo=$datos->codigo;

$stmt=$mysqli->prepare("SELECT examenes.*,concat_ws(' ',paciente.apellidos,paciente.nombres) as nombres,round((to_days(curdate())-to_days(fecnac))/365.242199,2) as edad,procedimientos.nombre,procedimientos.tabla,procedimientos.info,procedimientos.tipo from examenes inner join paciente on examenes.identificacion=paciente.identificacion inner join procedimientos on examenes.codexamen=procedimientos.codigo where examenes.fecha between ? and ? and paciente.genero=? and examenes.codexamen=? order by fecha desc");
$stmt->bind_param("ssss",$fecha1,$fecha2,$genero,$codigo);
$stmt->execute();
$result=$stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
