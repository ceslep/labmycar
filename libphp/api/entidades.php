<?php
session_start();
header('Content-Type: application/json');

require_once 'cors.php';
require_once '../datos_conexion.php';

$response = ['success' => false, 'message' => 'Método no permitido. Use POST.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Consulta para obtener todas las entidades ordenadas por nombre
        $query = "SELECT id, nombre FROM entidades ORDER BY nombre ASC";
        
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            
            $entidades = [];
            while ($row = $result->fetch_assoc()) {
                $entidades[] = [
                    'id' => (int)$row['id'],
                    'nombre' => $row['nombre']
                ];
            }
            
            $stmt->close();
            
            $response = [
                'success' => true,
                'data' => $entidades,
                'total' => count($entidades),
                'message' => 'Entidades cargadas correctamente'
            ];
            
        } else {
            $response = ['success' => false, 'message' => 'Error interno del servidor al preparar la consulta.'];
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        $response = [
            'success' => false, 
            'error' => 'Error al obtener las entidades',
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