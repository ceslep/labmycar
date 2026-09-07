<?php
require_once("datos_conexion.php");

$tabla=$mysqli->real_escape_string($datos->tabla);

$sql="select * from configuracion order by id desc limit 1"; 
$result=$mysqli->query($sql);
$datas=$result->fetch_assoc();
$bacteriologo=$datas['bacteriologoLaboratorio'].":T.P. ".$datas['tarjetaPLaboratorio'];

$fields="";
$values="";
foreach ($datos as $key=>$value){
    if ($key=='id' || $key=='fechahora' || $key=='tabla' || $key=='ind' || $key=='nombreExamen' || $key=='constant' || $key=='unidades' || $key=='bacteriologo' || $key=='doctor' || $key=='hora' || $key=='exportar')  continue;
   $escapedValue=$mysqli->real_escape_string($value);
   $fields.="$key,";
   $values.="'$escapedValue',";
}


//Eliminar ultimo caracter de comas
    $fields=rtrim($fields, ",");
    $values=rtrim($values, ",");

    $fields.=",bacteriologo";
    $values.=",'$bacteriologo'";

    $sql="REPLACE INTO $tabla ($fields) values ($values)";

  if($mysqli->query($sql))
    echo json_encode(["msg"=>true]);  
else
    echo json_encode(["msg"=>false]);
$mysqli->close();
