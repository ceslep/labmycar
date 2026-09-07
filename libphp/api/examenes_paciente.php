<?php
session_start();
header('Content-Type: application/json');

require_once 'cors.php';
require_once '../datos_conexion.php';

$response = ['success' => false, 'message' => 'Método no permitido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificacion = $_POST['identificacion'] ?? '';
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    
    if (empty($identificacion)) {
        $response = ['success' => false, 'message' => 'La identificación del paciente es obligatoria.'];
    } else {
        try {
            // Obtener exámenes del paciente para la fecha específica
            $sql = "SELECT 
                        e.codexamen, 
                        e.fecha, 
                        e.realizado, 
                        e.entidad,
                        p.nombre,
                        p.abreviatura,
                        p.tipo,
                        p.tabla
                    FROM examenes e
                    LEFT JOIN procedimientos p ON e.codexamen = p.codigo
                    WHERE e.identificacion = ? AND e.fecha = ?
                    ORDER BY e.fecha DESC, e.codexamen ASC";
            
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $identificacion, $fecha);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $examenes = [];
                
                while ($row = $result->fetch_assoc()) {
                    // Determinar nombre del examen
                    $nombre_examen = !empty($row['abreviatura']) 
                        ? $row['abreviatura'] 
                        : (!empty($row['nombre']) ? $row['nombre'] : "Examen " . $row['codexamen']);
                    
                    // Determinar estado
                    $estado = 'Pendiente';
                    $realizado = false;
                    
                    if (strtolower($row['realizado']) === 's' || $row['realizado'] === '1') {
                        $estado = 'Realizado';
                        $realizado = true;
                    }
                    
                    $examen = [
                        'codigo' => $row['codexamen'],
                        'nombre' => $nombre_examen,
                        'fecha' => $row['fecha'],
                        'estado' => $estado,
                        'realizado' => $realizado,
                        'entidad' => $row['entidad'],
                        'tipo' => $row['tipo'],
                        'tabla' => $row['tabla']
                    ];
                    
                    $examenes[] = $examen;
                }
                
                $stmt->close();
                
                $response = [
                    'success' => true,
                    'examenes' => $examenes,
                    'total' => count($examenes),
                    'paciente_identificacion' => $identificacion,
                    'fecha_consulta' => $fecha
                ];
                
            } else {
                $response = ['success' => false, 'message' => 'Error al preparar la consulta de exámenes.'];
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Error al obtener los exámenes del paciente',
                'error' => $e->getMessage()
            ];
        }
    }
}

if (isset($mysqli)) {
    $mysqli->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>