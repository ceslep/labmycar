<?php
session_start();
header('Content-Type: application/json');

require_once 'cors.php';
require_once '../datos_conexion.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => 'Método no permitido. Use POST.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get POST parameters
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
        
        // Validate that fecha is required
        if (empty($fecha)) {
            $response = ['success' => false, 'message' => 'El parámetro fecha es obligatorio.'];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
    // Primero obtener pacientes con sus exámenes para la fecha
    $baseQuery = "SELECT DISTINCT p.id, p.identificacion, p.nombres, p.apellidos, p.telefono, 
                         p.correo, p.genero, p.fecnac, p.estado, p.entidad,
                         GROUP_CONCAT(DISTINCT e.codexamen ORDER BY e.codexamen) as examenes_codigos
                   FROM paciente p
                   INNER JOIN examenes e ON p.identificacion = e.identificacion
                   WHERE e.fecha = ?";
        
        $params = [$fecha];
        $types = 's';
        
        // Add search filter - matching actual field names
    if (!empty($search)) {
        $baseQuery .= " AND (p.nombres LIKE ? OR p.apellidos LIKE ? OR p.telefono LIKE ? OR p.correo LIKE ? OR p.identificacion LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= str_repeat('s', 5);
    }
        
        // Group and order
        $baseQuery .= " GROUP BY p.id ORDER BY p.apellidos, p.nombres";
        
        // Prepare and execute query following login.php pattern
        $stmt = $mysqli->prepare($baseQuery);
        if ($stmt) {
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Preparar cache de procedimientos para optimizar consultas
            $procedimientos_cache = [];
            $check_table = $mysqli->query("SHOW TABLES LIKE 'procedimientos'");
            if ($check_table->num_rows > 0) {
                $result_proc = $mysqli->query("SELECT codigo, nombre, abreviatura FROM procedimientos");
                while ($proc = $result_proc->fetch_assoc()) {
                    $procedimientos_cache[$proc['codigo']] = !empty($proc['abreviatura']) 
                        ? $proc['abreviatura'] 
                        : $proc['nombre'];
                }
            }
            
            // Prepare patients array
            $pacientes = [];
            
            while ($row = $result->fetch_assoc()) {
                // Calculate age from fecnac
                $fecha_nacimiento = new DateTime($row['fecnac']);
                $hoy = new DateTime();
                $edad = $hoy->diff($fecha_nacimiento)->y;
                
                // Process gender
                $genero = '';
                $color_genero = 'bg-gray-100 text-gray-600';
                if (strtolower($row['genero']) === 'masculino') {
                    $genero = 'M';
                    $color_genero = 'bg-blue-100 text-blue-700';
                } elseif (strtolower($row['genero']) === 'femenino') {
                    $genero = 'F';
                    $color_genero = 'bg-pink-100 text-pink-700';
                }
                
                // Process exams - obtener nombres usando cache de procedimientos
                $examenes = [];
                $examenes_detalle = [];
                
                if (!empty($row['examenes_codigos'])) {
                    $examenes_codigos_array = explode(',', $row['examenes_codigos']);
                    
                    // Obtener nombres de exámenes usando el cache de procedimientos
                    foreach ($examenes_codigos_array as $codigo_examen) {
                        $codigo_examen = trim($codigo_examen);
                        if (!empty($codigo_examen)) {
                            // Buscar en cache de procedimientos
                            if (isset($procedimientos_cache[$codigo_examen])) {
                                $examenes_detalle[] = $procedimientos_cache[$codigo_examen];
                            } else {
                                // Fallback basado en códigos comunes
                                switch ($codigo_examen) {
                                    case '1':
                                        $examenes_detalle[] = 'Hemograma';
                                        break;
                                    case '2':
                                        $examenes_detalle[] = 'Orina';
                                        break;
                                    case '3':
                                        $examenes_detalle[] = 'Coprológico';
                                        break;
                                    case '4':
                                        $examenes_detalle[] = 'Química Sanguínea';
                                        break;
                                    case '5':
                                        $examenes_detalle[] = 'Hemocultivo';
                                        break;
                                    case '6':
                                        $examenes_detalle[] = 'Urocultivo';
                                        break;
                                    case '7':
                                        $examenes_detalle[] = 'Perfil Lipídico';
                                        break;
                                    case '8':
                                        $examenes_detalle[] = 'Glicemia';
                                        break;
                                    default:
                                        $examenes_detalle[] = "Examen " . $codigo_examen;
                                        break;
                                }
                            }
                        }
                    }
                    
                    $examenes = $examenes_detalle;
                }
                
                // Determine status color
                $estado = $row['estado'] ?: 'Activo'; // Default to Activo if null
                $color_estado = 'bg-gray-100 text-gray-700';
                if (strtolower($estado) === 'activo') {
                    $color_estado = 'bg-green-100 text-green-700';
                } elseif (strtolower($estado) === 'inactivo') {
                    $color_estado = 'bg-red-100 text-red-700';
                }
                
                // Build full name
                $nombre_completo = trim($row['nombres'] . ' ' . $row['apellidos']);
                
                $paciente = [
                    'id_paciente' => (int)$row['id'],
                    'identificacion' => $row['identificacion'],
                    'nombre' => $row['nombres'] ?: '',
                    'apellido' => $row['apellidos'] ?: '',
                    'nombre_completo' => $nombre_completo,
                    'telefono' => $row['telefono'] ?: '',
                    'email' => $row['correo'] ?: '',
                    'genero' => $genero,
                    'genero_completo' => ucfirst($row['genero'] ?: ''),
                    'color_genero' => $color_genero,
                    'fecha_nacimiento' => $row['fecnac'],
                    'edad' => $edad,
                    'edad_texto' => $edad . ' años',
                    'estado' => ucfirst($estado),
                    'color_estado' => $color_estado,
                    'entidad' => $row['entidad'] ?: '',
                    'usuario_registro' => 'Sistema',
                    'fecha_registro' => $row['fecnac'],
                    'examenes' => $examenes,
                    'examenes_codigos' => explode(',', $row['examenes_codigos'] ?? ''),
                    'total_examenes' => count($examenes)
                ];
                
                $pacientes[] = $paciente;
            }
            
            $stmt->close();
            
            // Success response
            $total_examenes_count = array_sum(array_column($pacientes, 'total_examenes'));
            $response = [
                'success' => true,
                'data' => $pacientes,
                'total' => count($pacientes),
                'total_examenes' => $total_examenes_count,
                'fecha_filtro' => $fecha,
                'fecha_consulta' => $fecha,
                'message' => "Pacientes con exámenes en fecha: $fecha" . (!empty($search) ? " (filtrado por: $search)" : ""),
                'examenes_disponibles' => !empty($procedimientos_cache) ? count($procedimientos_cache) : 0
            ];
            
        } else {
            $response = ['success' => false, 'message' => 'Error interno del servidor al preparar la consulta.'];
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        $response = [
            'success' => false, 
            'error' => 'Error al obtener los pacientes',
            'message' => $e->getMessage()
        ];
    }
    
}

if (isset($mysqli)) {
    $mysqli->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>