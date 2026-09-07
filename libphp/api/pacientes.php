<?php
session_start();
header('Content-Type: application/json');

require_once 'cors.php';
require_once '../datos_conexion.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => 'Método no permitido. Use POST.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check for specific action
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'consulta_pacientes_resultados') {
            // Handle patient results search
            $identificacion = trim($_POST['identificacion'] ?? '');
            $nombres = trim($_POST['nombres'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $ciudad = trim($_POST['ciudad'] ?? '');
            $entidad = trim($_POST['entidad'] ?? '');
            $include_examenes = $_POST['include_examenes'] ?? '1';
            $solo_con_resultados = $_POST['solo_con_resultados'] ?? '0';
            $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 50;
            
            // Validate that at least one criterion is present
            if (empty($identificacion) && empty($nombres) && empty($telefono) && empty($ciudad) && empty($entidad)) {
                $response = ['success' => false, 'message' => 'Debe ingresar al menos un criterio de búsqueda.'];
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Build SQL base for patients
            $sql_pacientes = "
                SELECT
                    p.identificacion,
                    CONCAT_WS(' ', p.apellidos, p.nombres) as nombre_completo,
                    p.apellidos,
                    p.nombres,
                    p.edad,
                    p.genero,
                    p.fecnac,
                    p.telefono,
                    p.telefono_movil,
                    p.telefono_residencia2,
                    p.correo,
                    p.ciudad_residencia,
                    p.direccion_residencia,
                    p.entidad,
                    COUNT(DISTINCT e.fecha) as total_visitas,
                    MAX(e.fecha) as ultima_visita
                FROM paciente p
                LEFT JOIN examenes e ON p.identificacion = e.identificacion
                WHERE 1=1";
            $params = [];
            $param_types = "";
            
            // Filter by identification
            if (!empty($identificacion)) {
                $sql_pacientes .= " AND p.identificacion LIKE ?";
                $params[] = "%" . $identificacion . "%";
                $param_types .= "s";
            }
            
            // Filter by names (search in all name fields)
            if (!empty($nombres)) {
                $nombres_like = "%" . $nombres . "%";
                $sql_pacientes .= " AND (p.nombres LIKE ? OR p.apellidos LIKE ? OR p.nombre1 LIKE ? OR p.nombre2 LIKE ? OR p.apellido1 LIKE ? OR p.apellido2 LIKE ?)";
                $params = array_merge($params, [$nombres_like, $nombres_like, $nombres_like, $nombres_like, $nombres_like, $nombres_like]);
                $param_types .= "ssssss";
            }
            
            // Filter by phone (search in all phone fields)
            if (!empty($telefono)) {
                $telefono_like = "%" . $telefono . "%";
                $sql_pacientes .= " AND (p.telefono LIKE ? OR p.telefono_movil LIKE ? OR p.telefono_residencia2 LIKE ?)";
                $params = array_merge($params, [$telefono_like, $telefono_like, $telefono_like]);
                $param_types .= "sss";
            }
            
            // Filter by city
            if (!empty($ciudad)) {
                $sql_pacientes .= " AND p.ciudad_residencia LIKE ?";
                $params[] = "%" . $ciudad . "%";
                $param_types .= "s";
            }
            
            // Filter by entity
            if (!empty($entidad)) {
                $sql_pacientes .= " AND p.entidad LIKE ?";
                $params[] = "%" . $entidad . "%";
                $param_types .= "s";
            }
            
            $sql_pacientes .= " GROUP BY p.identificacion ORDER BY p.apellidos ASC, p.nombres ASC LIMIT " . $limit;
            
            // Execute patient query
            $stmt_pacientes = $mysqli->prepare($sql_pacientes);
            if (!empty($params)) {
                $stmt_pacientes->bind_param($param_types, ...$params);
            }
            $stmt_pacientes->execute();
            $result_pacientes = $stmt_pacientes->get_result();
            $pacientes = [];
            
            // Check if procedures table exists
            $procedimientos_exists = $mysqli->query("SHOW TABLES LIKE 'procedimientos'")->num_rows > 0;
            
            while ($paciente_row = $result_pacientes->fetch_assoc()) {
                $identificacion_p = $paciente_row['identificacion'];
                
                // Format main phone
                $telefono_principal = $paciente_row['telefono'] ?: $paciente_row['telefono_movil'] ?: $paciente_row['telefono_residencia2'] ?: '';
                
                // Calculate age if not in database
                $edad_formateada = $paciente_row['edad'];
                if (empty($edad_formateada) && !empty($paciente_row['fecnac']) && $paciente_row['fecnac'] !== '0000-00-00') {
                    $fecha_nac = new DateTime($paciente_row['fecnac']);
                    $hoy = new DateTime();
                    $edad_formateada = $hoy->diff($fecha_nac)->y;
                }
                
                $paciente_data = [
                    'identificacion' => $identificacion_p,
                    'nombre_completo' => $paciente_row['nombre_completo'],
                    'apellidos' => $paciente_row['apellidos'],
                    'nombres' => $paciente_row['nombres'],
                    'edad' => $edad_formateada,
                    'genero' => $paciente_row['genero'],
                    'telefono' => $telefono_principal,
                    'telefono_fijo' => $paciente_row['telefono'],
                    'telefono_movil' => $paciente_row['telefono_movil'],
                    'telefono_residencia2' => $paciente_row['telefono_residencia2'],
                    'correo' => $paciente_row['correo'],
                    'ciudad_residencia' => $paciente_row['ciudad_residencia'],
                    'direccion_residencia' => $paciente_row['direccion_residencia'],
                    'entidad' => $paciente_row['entidad'],
                    'total_visitas' => $paciente_row['total_visitas'] ?? 0,
                    'ultima_visita' => $paciente_row['ultima_visita'] ?? '',
                    'examenes' => []
                ];
                
                // Get patient exams if requested
                if ($include_examenes == '1') {
                    $sql_examenes = "SELECT
                        e.fecha, e.codexamen, e.realizado, e.entidad";
                    if ($procedimientos_exists) {
                        $sql_examenes .= ",
                            pr.nombre as nombre_examen,
                            pr.codigo as codigo_examen,
                            pr.tabla as examen_tabla,
                            pr.tipo as tipo_examen,
                            pr.tipoprocedimiento as tipo_procedimiento,
                            pr.abreviatura";
                    }
                    $sql_examenes .= " FROM examenes e
                        INNER JOIN paciente p ON e.identificacion = p.identificacion";
                    if ($procedimientos_exists) {
                        $sql_examenes .= " LEFT JOIN procedimientos pr ON e.codexamen = pr.codigo";
                    }
                    $sql_examenes .= " WHERE e.identificacion = ? ORDER BY e.fecha DESC";
                    $stmt_examenes = $mysqli->prepare($sql_examenes);
                    $stmt_examenes->bind_param("s", $identificacion_p);
                    $stmt_examenes->execute();
                    $result_examenes = $stmt_examenes->get_result();
                    
                    while ($examen_row = $result_examenes->fetch_assoc()) {
                        $examen_data = [
                            'fecha' => $examen_row['fecha'],
                            'fecha_examen' => $examen_row['fecha'],
                            'codigo' => $examen_row['codexamen'],
                            'codexamen' => $examen_row['codexamen'],
                            'entidad' => $examen_row['entidad'],
                            'realizado' => $examen_row['realizado']
                        ];
                        
                        if ($procedimientos_exists) {
                            $examen_data['nombre'] = $examen_row['nombre_examen'] ?? $examen_row['abreviatura'] ?? 'Examen #' . $examen_row['codexamen'];
                            $examen_data['tipo'] = $examen_row['tipo_examen'] ?? 'No especificado';
                            $examen_data['tabla'] = $examen_row['examen_tabla'] ?? '';
                            $examen_data['procedimiento'] = $examen_row['tipo_procedimiento'] ?? '';
                            $examen_data['abreviatura'] = $examen_row['abreviatura'] ?? '';
                            $examen_data['tipo_procedimiento'] = $examen_row['tipo_procedimiento'] ?? '';
                            
                            // Get actual exam result from dynamic table
                            if (!empty($examen_row['examen_tabla'])) {
                                $tabla_resultado = $examen_row['examen_tabla'];
                                $tabla_existe = $mysqli->query("SHOW TABLES LIKE '$tabla_resultado'")->num_rows > 0;
                                
                                if ($tabla_existe) {
                                    // Get table structure
                                    $estructura = $mysqli->query("DESCRIBE `$tabla_resultado`");
                                    $columnas = [];
                                    while ($col = $estructura->fetch_assoc()) {
                                        $columnas[] = $col['Field'];
                                    }
                                    
                                    // Build dynamic WHERE
                                    $where_clauses = [];
                                    $where_params = [];
                                    $where_types = "";
                                    
                                    if (in_array('identificacion', $columnas)) {
                                        $where_clauses[] = "identificacion = ?";
                                        $where_params[] = $identificacion_p;
                                        $where_types .= "s";
                                    }
                                    
                                    if (in_array('codexamen', $columnas)) {
                                        $where_clauses[] = "codexamen = ?";
                                        $where_params[] = $examen_row['codexamen'];
                                        $where_types .= "s";
                                    } elseif (in_array('examen', $columnas)) {
                                        $where_clauses[] = "examen = ?";
                                        $where_params[] = $examen_row['codexamen'];
                                        $where_types .= "s";
                                    }
                                    
                                    if (in_array('fecha', $columnas)) {
                                        $where_clauses[] = "fecha = ?";
                                        $where_params[] = $examen_row['fecha'];
                                        $where_types .= "s";
                                    }
                                    
                                    if (!empty($where_clauses)) {
                                        $where_sql = implode(" AND ", $where_clauses);
                                        $sql_resultado = "SELECT * FROM `$tabla_resultado` WHERE $where_sql LIMIT 1";
                                        
                                        try {
                                            $stmt_resultado = $mysqli->prepare($sql_resultado);
                                            $stmt_resultado->bind_param($where_types, ...$where_params);
                                            $stmt_resultado->execute();
                                            $result_resultado = $stmt_resultado->get_result();
                                            
                                            if ($result_resultado->num_rows > 0) {
                                                $datos_resultado = $result_resultado->fetch_assoc();
                                                
                                                // Extract main value according to table type
                                                $valor_resultado = '';
                                                $referencia = '';
                                                
                                                switch ($tabla_resultado) {
                                                    case 'examen_tipo_1':
                                                        $valor_resultado = $datos_resultado['valoracion'] ?? '';
                                                        break;
                                                    case 'examen_tipo_2':
                                                        $valor_resultado = $datos_resultado['valoracion'] ?? '';
                                                        break;
                                                    case 'examen_tipo_3':
                                                        $valores_clave = ['densidad', 'color', 'ph', 'proteinas', 'glucosa', 'bilirrubina', 'nitritos', 'leucocitos'];
                                                        foreach ($valores_clave as $campo) {
                                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                            }
                                                        }
                                                        break;
                                                    case 'examen_tipo_5':
                                                        $hemograma_clave = ['hemoglobina', 'hematocrito', 'leucocitos'];
                                                        foreach ($hemograma_clave as $campo) {
                                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                            }
                                                        }
                                                        break;
                                                    case 'perfilLipidico':
                                                        $lipidos_clave = ['colesterol_total', 'colesterol_hdl', 'trigliceridos'];
                                                        foreach ($lipidos_clave as $campo) {
                                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                            }
                                                        }
                                                        break;
                                                    case 'hemogramaRayto':
                                                        $auto_clave = ['WBC', 'RBC', 'HGB', 'HCT', 'PLT'];
                                                        foreach ($auto_clave as $campo) {
                                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                            }
                                                        }
                                                        break;
                                                    default:
                                                        foreach ($datos_resultado as $columna => $valor) {
                                                            if (in_array(strtolower($columna), ['resultado', 'valor', 'result', 'value']) && !empty($valor)) {
                                                                $valor_resultado = $valor;
                                                                break;
                                                            }
                                                        }
                                                        
                                                        if (empty($valor_resultado)) {
                                                            $excluir = ['ind', 'identificacion', 'codexamen', 'examen', 'fecha', 'hora', 'id', 'bacteriologo', 'observaciones'];
                                                            foreach ($datos_resultado as $columna => $valor) {
                                                                if (!in_array(strtolower($columna), $excluir) && !empty($valor) && $valor !== '0000-00-00' && $valor !== '0' && $valor !== 'N/A') {
                                                                    $valor_resultado = $valor;
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                }
                                                
                                                // Extract reference if exists
                                                $ref_campos = ['referencia', 'rango', 'valor_de_referencia'];
                                                foreach ($ref_campos as $campo) {
                                                    if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                        $referencia = $datos_resultado[$campo];
                                                        break;
                                                    }
                                                }
                                                
                                                $examen_data['resultado'] = $valor_resultado ?: 'Sin valor';
                                                $examen_data['referencia'] = $referencia ?: 'N/A';
                                                $examen_data['estado'] = 'Completado';
                                                $examen_data['resultado_completo'] = $datos_resultado;
                                            } else {
                                                $examen_data['resultado'] = 'Pendiente';
                                                $examen_data['referencia'] = 'N/A';
                                                $examen_data['estado'] = 'Pendiente';
                                            }
                                        } catch (Exception $e) {
                                            $examen_data['resultado'] = 'Error en consulta';
                                            $examen_data['referencia'] = 'N/A';
                                            $examen_data['estado'] = 'Error';
                                        }
                                    } else {
                                        $examen_data['resultado'] = 'Sin datos';
                                        $examen_data['referencia'] = 'N/A';
                                        $examen_data['estado'] = 'Sin datos';
                                    }
                                } else {
                                    $examen_data['resultado'] = 'Tabla no encontrada';
                                    $examen_data['referencia'] = 'N/A';
                                    $examen_data['estado'] = 'Error';
                                }
                            } else {
                                $examen_data['nombre'] = 'Examen #' . $examen_row['codexamen'];
                                $examen_data['tipo'] = 'No especificado';
                                $examen_data['tabla'] = '';
                                $examen_data['procedimiento'] = '';
                                $examen_data['resultado'] = 'No disponible';
                                $examen_data['referencia'] = 'N/A';
                                $examen_data['estado'] = 'N/A';
                            }
                        } else {
                            $examen_data['nombre'] = 'Examen #' . $examen_row['codexamen'];
                            $examen_data['resultado'] = 'No disponible';
                            $examen_data['estado'] = 'N/A';
                        }
                        
                        // If only concrete results are requested, filter
                        if ($solo_con_resultados == '1' && ($examen_data['estado'] == 'Pendiente' || $examen_data['resultado'] == 'No disponible')) {
                            continue;
                        }
                        
                        $paciente_data['examenes'][] = $examen_data;
                    }
                    
                    $paciente_data['total_examenes'] = count($paciente_data['examenes']);
                    $paciente_data['examenes_con_resultados'] = count(array_filter($paciente_data['examenes'], function ($ex) {
                        return isset($ex['estado']) && $ex['estado'] == 'Completado';
                    }));
                }
                
                $pacientes[] = $paciente_data;
            }
            
            $response = [
                'success' => true,
                'pacientes' => $pacientes,
                'total_pacientes' => count($pacientes),
                'criterios' => [
                    'identificacion' => $identificacion,
                    'nombres' => $nombres,
                    'telefono' => $telefono,
                    'ciudad' => $ciudad,
                    'entidad' => $entidad,
                    'include_examenes' => $include_examenes,
                    'solo_con_resultados' => $solo_con_resultados,
                    'limit' => $limit
                ]
            ];
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Get POST parameters for original functionality
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