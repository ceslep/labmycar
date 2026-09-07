<?php
require_once 'datos_conexion.php';

$criterio=$datos->criterio;
$criterio="%".$criterio."%";

$stmt=$mysqli->prepare("select paciente.identificacion,concat_ws(' ',paciente.nombres,paciente.apellidos) as nombres,edad from paciente inner join examenes on paciente.identificacion=examenes.identificacion where paciente.identificacion like ? or concat_ws(' ',paciente.nombres,paciente.apellidos) like ? group by paciente.identificacion order by nombres");
$stmt->bind_param("ss",$criterio,$criterio);
$stmt->execute();
$result=$stmt->get_result();
echo json_encode(["msg"=>true,"data"=>$result->fetch_all(MYSQLI_ASSOC)]);
$stmt->close();
$mysqli->close();
