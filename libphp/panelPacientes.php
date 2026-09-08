<?php
// Búsqueda avanzada de pacientes con exámenes (equivalente SPA de la acción
// "consulta_pacientes_resultados" de prt.php). Respuesta: array JSON plano.
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$identificacion = trim($datos->identificacion ?? '');
$nombres = trim($datos->nombres ?? '');
$telefono = trim($datos->telefono ?? '');
$ciudad = trim($datos->ciudad ?? '');
$entidad = trim($datos->entidad ?? '');
$solo_con_resultados = ($datos->solo_con_resultados ?? '0') === '1' ? '1' : '0';
$limit = min(intval($datos->limit ?? 50), 200);

if (empty($identificacion) && empty($nombres) && empty($telefono) && empty($ciudad) && empty($entidad)) {
    echo json_encode([]);
    $mysqli->close();
    exit;
}

$sql = "SELECT pa.identificacion,
               CONCAT_WS(' ', pa.apellidos, pa.nombres) AS nombre_completo,
               pa.fecnac, pa.genero, pa.telefono, pa.telefono_movil, pa.correo,
               pa.ciudad_residencia, MAX(e.entidad) AS entidad,
               COUNT(DISTINCT e.fecha) AS total_visitas,
               MAX(e.fecha) AS ultima_visita,
               COUNT(DISTINCT e.codexamen) AS total_examenes,
               COUNT(DISTINCT IF(e.realizado='S', e.codexamen, NULL)) AS con_resultados
        FROM paciente pa
        INNER JOIN examenes e ON pa.identificacion = e.identificacion
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($identificacion)) { $sql .= " AND pa.identificacion LIKE ?"; $params[] = "%$identificacion%"; $types .= "s"; }
if (!empty($nombres)) { $sql .= " AND CONCAT_WS(' ', pa.apellidos, pa.nombres) LIKE ?"; $params[] = "%$nombres%"; $types .= "s"; }
if (!empty($telefono)) { $sql .= " AND (pa.telefono LIKE ? OR pa.telefono_movil LIKE ?)"; $params[] = "%$telefono%"; $params[] = "%$telefono%"; $types .= "ss"; }
if (!empty($ciudad)) { $sql .= " AND pa.ciudad_residencia LIKE ?"; $params[] = "%$ciudad%"; $types .= "s"; }
if (!empty($entidad)) { $sql .= " AND e.entidad LIKE ?"; $params[] = "%$entidad%"; $types .= "s"; }
if ($solo_con_resultados === '1') { $sql .= " AND e.realizado = 'S'"; }

$sql .= " GROUP BY pa.identificacion ORDER BY ultima_visita DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode([]);
    $mysqli->close();
    exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
} else {
    echo json_encode([]);
}
$stmt->close();
$mysqli->close();
