<?php
require_once "datos_conexion.php";

$fecha1  = $datos->rfecha1;
$fecha2  = $datos->rfecha2;
$rentidad = $datos->rentidad;

function getExamenTipo1($mysqli,$identificacion, $fecha, $codexamen)
{
    $stmt=$mysqli->prepare("SELECT concat_ws(' ',valoracion,unidades) as valoracion FROM examen_tipo_1 inner join procedimientos on examen_tipo_1.examen=procedimientos.codigo WHERE identificacion=? AND fecha=? AND examen=?");
    $stmt->bind_param("ssi",$identificacion,$fecha,$codexamen);
    $stmt->execute();
    return $stmt->get_result();
}

function getExamenTipo2($mysqli,$identificacion, $fecha, $codexamen)
{
    $stmt=$mysqli->prepare("SELECT concat_ws(' ',valoracion,unidades) as valoracion FROM examen_tipo_2 inner join procedimientos on examen_tipo_2.examen=procedimientos.codigo WHERE identificacion=? AND fecha=? AND examen=?");
    $stmt->bind_param("ssi",$identificacion,$fecha,$codexamen);
    $stmt->execute();
    return $stmt->get_result();
}

function getCoprologico($mysqli,$identificacion, $fecha)
{
    $stmt=$mysqli->prepare("SELECT CONCAT('consistencia: ',IFNULL(consistencia,''),', ','color: ',IFNULL(color,''),', ','sangre: ',IFNULL(sangre,''),', ','moco: ',IFNULL(moco,''),', ','otros_macroscopicos: ',IFNULL(otrosMacroscopicos,''),', ','ph: ',IFNULL(ph,'')) AS valoracion FROM coprologico WHERE identificacion=? AND fecha=?");
    $stmt->bind_param("ss",$identificacion,$fecha);
    $stmt->execute();
    return $stmt->get_result();
}

function getFrotisVaginal($mysqli,$identificacion, $fecha)
{
    $stmt=$mysqli->prepare("SELECT CONCAT('ph: ',IFNULL(ph,''),', ','trichonomas_vaginales: ',IFNULL(trichonomas_vaginales,''),', ','pmn: ',IFNULL(pmn,'')) AS valoracion FROM frotisVaginal WHERE identificacion=? AND fecha=?");
    $stmt->bind_param("ss",$identificacion,$fecha);
    $stmt->execute();
    return $stmt->get_result();
}

function getHemogramaRayto($mysqli,$identificacion, $fecha)
{
    $stmt=$mysqli->prepare("SELECT CONCAT('WBC: ',IFNULL(WBC,''),', ','RBC: ',IFNULL(RBC,''),', ','HGB: ',IFNULL(HGB,''),', ','PLT: ',IFNULL(PLT,'')) AS valoracion FROM hemogramaRayto WHERE identificacion=? AND fecha=?");
    $stmt->bind_param("ss",$identificacion,$fecha);
    $stmt->execute();
    return $stmt->get_result();
}

function getParcialOrina($mysqli,$identificacion, $fecha)
{
    $stmt=$mysqli->prepare("SELECT CONCAT('densidad: ',IFNULL(densidad,''),', ','color: ',IFNULL(color,''),', ','ph: ',IFNULL(ph,'')) AS valoracion FROM parcialOrina WHERE identificacion=? AND fecha=?");
    $stmt->bind_param("ss",$identificacion,$fecha);
    $stmt->execute();
    return $stmt->get_result();
}

function getPerfilLipidico($mysqli,$identificacion, $fecha)
{
    $stmt=$mysqli->prepare("SELECT CONCAT('colesterol_total: ',IFNULL(colesterol_total,''),', ','colesterol_hdl: ',IFNULL(colesterol_hdl,''),', ','trigliceridos: ',IFNULL(trigliceridos,'')) AS valoracion FROM perfilLipidico WHERE identificacion=? AND fecha=?");
    $stmt->bind_param("ss",$identificacion,$fecha);
    $stmt->execute();
    return $stmt->get_result();
}

function getAllExamsQuery($mysqli,$identificacion, $fecha, $codexamen,$tabla)
{
    $result=null;
    if ($tabla == 'examen_tipo_1') $result=getExamenTipo1($mysqli,$identificacion,$fecha,$codexamen);
    else if ($tabla == 'examen_tipo_2') $result=getExamenTipo2($mysqli,$identificacion,$fecha,$codexamen);
    else if ($tabla == 'coprologico') $result=getCoprologico($mysqli,$identificacion,$fecha);
    else if ($tabla == 'frotisVaginal') $result=getFrotisVaginal($mysqli,$identificacion,$fecha);
    else if ($tabla == 'hemogramaRayto') $result=getHemogramaRayto($mysqli,$identificacion,$fecha);
    else if ($tabla == 'parcialOrina') $result=getParcialOrina($mysqli,$identificacion,$fecha);
    else if ($tabla == 'perfilLipidico') $result=getPerfilLipidico($mysqli,$identificacion,$fecha);
    return $result;
}

if($rentidad!=""){
$stmt=$mysqli->prepare("Select procedimientos.tabla,examenes.codexamen,paciente.entidad,paciente.identificacion,concat_ws(' ',apellidos,nombres) as nombres,paciente.genero,paciente.edad,procedimientos.nombre as examen,examenes.fecha from examenes inner join paciente on examenes.identificacion=paciente.identificacion inner join procedimientos on examenes.codexamen=procedimientos.codigo where examenes.fecha between ? and ? and paciente.entidad=?");
$stmt->bind_param("sss",$fecha1,$fecha2,$rentidad);
}else{
$stmt=$mysqli->prepare("Select procedimientos.tabla,examenes.codexamen,paciente.entidad,paciente.identificacion,concat_ws(' ',apellidos,nombres) as nombres,paciente.genero,paciente.edad,procedimientos.nombre as examen,examenes.fecha from examenes inner join paciente on examenes.identificacion=paciente.identificacion inner join procedimientos on examenes.codexamen=procedimientos.codigo where examenes.fecha between ? and ?");
$stmt->bind_param("ss",$fecha1,$fecha2);
}
$stmt->execute();
$result = $stmt->get_result();
$data   = [];
while ($dato = $result->fetch_assoc()) {
    $id = $dato['identificacion'];
    $fec = $dato['fecha'];
    $cod = $dato['codexamen'];
    $tabla= $dato['tabla'];
    $res=getAllExamsQuery($mysqli,$id,$fec,$cod,$tabla);
    if($res && $res->num_rows>0)
    $dato['valoracion'] = $res->fetch_assoc()['valoracion'];
    else
    $dato['valoracion'] = '';
    $data[] = $dato;
}

echo json_encode(data);

$stmt->close();
$mysqli->close();
