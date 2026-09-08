<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$identificacion=$datos->identificacion;
$nombres=$datos->nombres;
$apellidos=$datos->apellidos;
$fecnac=$datos->fecnac;
$genero=$datos->genero;
$telefono=$datos->telefono;
$correo=$datos->correo;
$entidad=$datos->entidad;

$stmt=$mysqli->prepare("REPLACE INTO paciente (identificacion,nombres,apellidos,fecnac,genero,telefono,correo,entidad) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param("ssssssss",$identificacion,$nombres,$apellidos,$fecnac,$genero,$telefono,$correo,$entidad);
if($stmt->execute())
echo json_encode(["msg"=>true]);
else
echo json_encode(["msg"=>false]);
$stmt->close();
$mysqli->close();
