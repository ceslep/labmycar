<?php
require_once("datos_conexion.php");

// Límite de intentos por IP para el portal público (evita fuerza bruta del verificador).
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$archivo = sys_get_temp_dir() . '/labmy_portal_' . md5($ip) . '.rl';
$ahora = time();
$datosLim = ['t' => $ahora, 'n' => 0];
if (file_exists($archivo)) {
    $j = json_decode((string)file_get_contents($archivo), true);
    if (is_array($j) && isset($j['t']) && ($ahora - (int)$j['t']) < 60) {
        $datosLim = $j;
    } else {
        $datosLim = ['t' => $ahora, 'n' => 0];
    }
}
$datosLim['n']++;
@file_put_contents($archivo, json_encode($datosLim), LOCK_EX);
if ((int)$datosLim['n'] > 30) {
    http_response_code(429);
    echo json_encode([]);
    $mysqli->close();
    exit;
}

$identificacion = trim((string)($datos->identificacion ?? ''));
$numero = trim((string)($datos->numero ?? ''));
if ($identificacion === '' || $numero === '' || strlen($identificacion) > 20 || strlen($numero) > 10) {
    echo json_encode([]);
    $mysqli->close();
    exit;
}

// Todas las columnas van calificadas con su tabla: al unir examenes + procedimientos
// + paciente hay columnas repetidas (fecha, entidad, identificacion...); sin
// calificarlas MySQL lanza "column ambiguous" y el prepare() falla -> 500.
$sql = "SELECT examenes.*,
    procedimientos.nombre AS examen,
    procedimientos.tipo AS tipo,
    procedimientos.tabla AS tabla,
    procedimientos.info AS info,
    concat_ws(' ', paciente.nombres, paciente.apellidos) AS nombres,
    paciente.fecnac AS fecnac,
    round((to_days(curdate()) - to_days(paciente.fecnac)) / 365.242199, 2) AS edad
  FROM examenes
  INNER JOIN procedimientos ON examenes.codexamen = procedimientos.codigo
  INNER JOIN paciente ON examenes.identificacion = paciente.identificacion
  WHERE examenes.identificacion = ?
    AND (? = year(paciente.fecnac) OR ? = right(paciente.telefono, 4))
  ORDER BY examenes.fecha DESC, procedimientos.nombre ASC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    // Evita el fatal 500: devuelve el error del motor para poder diagnosticarlo.
    echo json_encode(["msg" => false, "error" => $mysqli->error]);
    $mysqli->close();
    exit;
}
$stmt->bind_param("sss", $identificacion, $numero, $numero);
if (!$stmt->execute()) {
    echo json_encode(["msg" => false, "error" => $stmt->error]);
    $stmt->close();
    $mysqli->close();
    exit;
}
$result = $stmt->get_result();
echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$stmt->close();
$mysqli->close();
