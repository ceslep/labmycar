<?php
//exit(0);
require_once "datos_conexion.php";
$datos = json_decode(file_get_contents("php://input"));
$mysqli = new mysqli($host, $user, $pass, $database);

$sql = "";



//$destinatario = "ingeleandro@gmail.com";

$asunto = "Resultados Laboratorio";




$mensaje = "<p style='color:green;font-size:1.5rem;'>laboratorio</p><p style='color:tomato;'>Resultados de Exámen para: <span style='color:violet;'>$datos->nombres</span></p><p> Identificado con $datos->identificacion </p>
<br/>

<br/>
<p>

";

// Cabeceras para especificar el remitente y el tipo de contenido (puedes personalizarlas según tus necesidades)
$cabeceras = "From: secretaria@iedeoccidente.com\r\n";
$cabeceras .= "Content-type: text/plain; charset=utf-8\r\n";

$email = $datos->correo;
$primer_caracter = substr($email, 0, 1);

// Encuentra la posición del símbolo "@" en el correo electrónico
$posicion_arroba = strpos($email, '@');

// Obtén el dominio del correo electrónico
$dominio = substr($email, $posicion_arroba);

// Construye el correo oculto
$correo_oculto = $primer_caracter . str_repeat('*', $posicion_arroba - 1) . $dominio;
/*
if (mail($destinatario, $asunto, $mensaje, $cabeceras)) {
echo json_encode(array("msg" => "El codigo ha sido enviado correctamente a $correo_oculto"));
} else {
echo json_encode(array("msg" => "error"));
} */
// Envía el correo
require "phpmailer/src/PHPMailer.php";
require "phpmailer/src/SMTP.php";
$mail = new PHPMailer\PHPMailer\PHPMailer();
$mail->IsSMTP(); // enable SMTP
$mail->SMTPDebug = 0; // debugging: 1 = errors and messages, 2 = messages only
$mail->SMTPAuth = true; // authentication enabled
$mail->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED for Gmail
$mail->Host = "mail.iedeoccidente.com";
$mail->Port = 465; // or 587
$mail->IsHTML(true);
$mail->Username = "webmaster@app.iedeoccidente.com";
$mail->Password = "colsecre001*";
$mail->SetFrom("webmaster@app.iedeoccidente.com");
$mail->Subject = $asunto;
$mail->AddEmbeddedImage('logo.png', 'mylogo', 'Logo');
$mail->Body = "<html><body>
<div style='display:flex;justify-content:center;'>
<img src='cid:mylogo' alt='logo' width=120>
</div>
<p style='color:red'>$mensaje </p>
<h5>Haga click en el siguiente enlace para visualizar el</h5>
<a href='$datos->content' alt='resultado'>Resultados $datos->info</a>

</body></html>";
$mail->AddAddress($datos->correo);
$mail->addCC('ingeleandro@gmail.com');
if (!$mail->Send()) {
    echo json_encode(array("msg" => "error"));
} else {
    echo json_encode(array("msg" => "El resultado ha sido enviado correctamente a $datos->correo"));
}

$mysqli->close();
