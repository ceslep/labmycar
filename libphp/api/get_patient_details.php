<?php
session_start();
header('Content-Type: application/json');

require_once 'cors.php';
require_once '../datos_conexion.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => 'Método no permitido. Use POST.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificacion = $_POST['identificacion'] ?? '';

    if (empty($identificacion)) {
        $response = ['success' => false, 'message' => 'La identificación del paciente es requerida.'];
    } else {
        // Prepare and execute the statement to prevent SQL injection
        $stmt = $mysqli->prepare("SELECT * FROM paciente WHERE identificacion = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $identificacion);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $patientData = $result->fetch_assoc();
                $response = ['success' => true, 'patient' => $patientData];
            } else {
                $response = ['success' => false, 'message' => 'Paciente no encontrado.'];
            }
            $stmt->close();
        } else {
            $response = ['success' => false, 'message' => 'Error interno del servidor al preparar la consulta.'];
        }
    }
}

$mysqli->close();
echo json_encode($response);
exit;
