<?php
session_start();
header('Content-Type: application/json');

require_once 'cors.php';
require_once '../datos_conexion.php';

$response = ['success' => false, 'message' => 'Método no permitido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificacion = $_POST['identificacion'] ?? '';
    $codexamen = $_POST['codexamen'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    
    if (empty($identificacion) || empty($codexamen) || empty($fecha)) {
        $response = ['success' => false, 'message' => 'Faltan parámetros obligatorios: identificación, código de examen y fecha.'];
    } else {
        try {
            // Primero obtener la tabla donde se guardan los resultados de este examen
            $sql_procedimiento = "SELECT tabla FROM procedimientos WHERE codigo = ? LIMIT 1";
            $stmt_proc = $mysqli->prepare($sql_procedimiento);
            
            if ($stmt_proc) {
                $stmt_proc->bind_param("s", $codexamen);
                $stmt_proc->execute();
                $result_proc = $stmt_proc->get_result();
                
                $tabla_resultados = '';
                if ($row_proc = $result_proc->fetch_assoc()) {
                    $tabla_resultados = $row_proc['tabla'];
                }
                $stmt_proc->close();
                
                if (!empty($tabla_resultados)) {
                    // Verificar si la tabla existe
                    $check_table = $mysqli->query("SHOW TABLES LIKE '$tabla_resultados'");
                    
                    if ($check_table->num_rows > 0) {
                        // Obtener la estructura de la tabla para construir la consulta
                        $describe = $mysqli->query("DESCRIBE `$tabla_resultados`");
                        $columnas = [];
                        
                        while ($col = $describe->fetch_assoc()) {
                            $columnas[] = $col['Field'];
                        }
                        
                        // Construir WHERE con las columnas disponibles
                        $where_clauses = [];
                        $params = [];
                        $types = '';
                        
                        if (in_array('identificacion', $columnas)) {
                            $where_clauses[] = "identificacion = ?";
                            $params[] = $identificacion;
                            $types .= 's';
                        }
                        
                        if (in_array('codexamen', $columnas)) {
                            $where_clauses[] = "codexamen = ?";
                            $params[] = $codexamen;
                            $types .= 's';
                        } elseif (in_array('examen', $columnas)) {
                            $where_clauses[] = "examen = ?";
                            $params[] = $codexamen;
                            $types .= 's';
                        }
                        
                        if (in_array('fecha', $columnas)) {
                            $where_clauses[] = "fecha = ?";
                            $params[] = $fecha;
                            $types .= 's';
                        }
                        
                        if (!empty($where_clauses)) {
                            $where_sql = "WHERE " . implode(" AND ", $where_clauses);
                            
                            $sql_resultados = "SELECT * FROM `$tabla_resultados` $where_sql LIMIT 1";
                            $stmt_resultados = $mysqli->prepare($sql_resultados);
                            
                            if ($stmt_resultados) {
                                $stmt_resultados->bind_param($types, ...$params);
                                $stmt_resultados->execute();
                                $result = $stmt_resultados->get_result();
                                
                                if ($row = $result->fetch_assoc()) {
                                    // Formatear los resultados para mejor visualización
                                    $resultados_formateados = [];
                                    
                                    foreach ($row as $columna => $valor) {
                                        // Excluir columnas no informativas
                                        $columnas_excluir = ['ind', 'id', 'fechahora'];
                                        
                                        if (!in_array(strtolower($columna), $columnas_excluir) && !empty($valor)) {
                                            // Capitalizar el nombre de la columna
                                            $nombre_columna = ucwords(str_replace('_', ' ', strtolower($columna)));
                                            $resultados_formateados[$nombre_columna] = $valor;
                                        }
                                    }
                                    
                                    $response = [
                                        'success' => true,
                                        'resultados' => $resultados_formateados,
                                        'tabla' => $tabla_resultados,
                                        'mensaje' => 'Resultados encontrados'
                                    ];
                                } else {
                                    $response = [
                                        'success' => false,
                                        'message' => 'No se encontraron resultados para este examen.',
                                        'codigo' => 'NO_RESULTS'
                                    ];
                                }
                                
                                $stmt_resultados->close();
                            } else {
                                $response = ['success' => false, 'message' => 'Error al preparar consulta de resultados.'];
                            }
                        } else {
                            $response = ['success' => false, 'message' => 'No se encontraron columnas válidas para la consulta.'];
                        }
                    } else {
                        $response = ['success' => false, 'message' => "La tabla de resultados '$tabla_resultados' no existe."];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'No se encontró la tabla de resultados para este tipo de examen.'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Error al consultar procedimientos.'];
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Error al obtener los resultados del examen',
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