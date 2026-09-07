<?php
session_start();
header('Content-Type: application/json');

require_once 'cors.php';
require_once '../datos_conexion.php'; // Adjust path as necessary

$response = ['success' => false, 'message' => 'Método no permitido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_ingresado = $_POST['usuario'] ?? '';
    $password_ingresado = $_POST['password'] ?? '';

    // Validate input
    if (empty($usuario_ingresado) || empty($password_ingresado)) {
        $response = ['success' => false, 'message' => 'El usuario y la contraseña no pueden estar vacíos.'];
    } else {
        // Using prepared statements to prevent SQL injection
        // Volvemos a la consulta original ya que la tabla configuracion solo tiene tarjetaPlaboratorio
        $stmt = $mysqli->prepare("SELECT nombreLaboratorio FROM configuracion WHERE usuario = ? AND tarjetaPlaboratorio = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $usuario_ingresado, $password_ingresado);
            $stmt->execute();
            $res_login = $stmt->get_result();

            if ($res_login->num_rows > 0) {
                $datos_u = $res_login->fetch_assoc();
                // For a true SPA, session management might involve tokens.
                // For now, mirroring existing session logic.
                $_SESSION['autenticado'] = true;
                $_SESSION['usuario_nombre'] = $datos_u['nombreLaboratorio'];
                $_SESSION['usuario'] = $usuario_ingresado; // Guardamos el usuario ingresado
                $_SESSION['lab_id'] = md5($password_ingresado); // Using md5 as in original code, but advise against for actual passwords.

                $response = [
                    'success' => true,
                    'user' => [
                        'nombre' => $datos_u['nombreLaboratorio'],
                        'usuario' => $usuario_ingresado,
                        'lab_id' => $_SESSION['lab_id']
                    ]
                ];
            } else {
                $response = ['success' => false, 'message' => 'Acceso denegado. Verifique sus datos.'];
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
