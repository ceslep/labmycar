<?php
/**
 * login_configlab.php
 * Autenticación para ConfigLab MyCar
 * 
 * Métodos:
 *   POST {id: "9695141"} - Login por ID
 *   POST {googleToken: "..."} - Login por Google
 *   GET - Verificar sesión
 *   GET?logout=1 - Cerrar sesión
 */

require_once 'datos_conexion.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cargar usuarios permitidos
$usuariosFile = __DIR__ . '/usuarios_permitidos.json';
$usuarios = json_decode(file_get_contents($usuariosFile), true);
$usuarios = $usuarios['usuarios'] ?? [];

// Función para generar token
function generarToken($usuario) {
    $payload = [
        'id' => $usuario['id'],
        'email' => $usuario['email'],
        'nombre' => $usuario['nombre'] ?? '',
        'exp' => time() + (24 * 60 * 60) // 24 horas
    ];
    $token = base64_encode(json_encode($payload));
    return $token;
}

// Función para validar token
function validarToken($token) {
    try {
        $payload = json_decode(base64_decode($token), true);
        if (!$payload || !isset($payload['exp'])) return null;
        if ($payload['exp'] < time()) return null;
        return $payload;
    } catch (Exception $e) {
        return null;
    }
}

// Cerrar sesión
if (isset($_GET['logout'])) {
    setcookie('configlab_token', '', time() - 3600, '/', '', '', true);
    echo json_encode(['success' => true]);
    exit(0);
}

// Verificar sesión activa
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_COOKIE['configlab_token'] ?? '';
    if ($token) {
        $payload = validarToken($token);
        if ($payload) {
            echo json_encode([
                'success' => true,
                'usuario' => [
                    'id' => $payload['id'],
                    'email' => $payload['email'],
                    'nombre' => $payload['nombre']
                ]
            ]);
            exit(0);
        }
    }
    echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
    exit(0);
}

// Login por POST
$datos = json_decode(file_get_contents('php://input'), true);

if (isset($datos['googleToken']) && $datos['googleToken'] !== '') {
    // Login con Google - validar JWT
    $googleToken = $datos['googleToken'];
    $clientId = '162861936617-aato9lpla08g5edots6fegvbkq7d6rti.apps.googleusercontent.com';
    
    // Dividir el token en partes
    $parts = explode('.', $googleToken);
    if (count($parts) !== 3) {
        echo json_encode(['success' => false, 'mensaje' => 'Token de Google inválido']);
        exit(0);
    }
    
    // Decodificar payload (parte central)
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!$payload) {
        echo json_encode(['success' => false, 'mensaje' => 'Token de Google inválido']);
        exit(0);
    }
    
    // Verificar audience coincides con nuestro client ID
    if (!isset($payload['aud']) || $payload['aud'] !== $clientId) {
        echo json_encode(['success' => false, 'mensaje' => 'Token de Google no autorizado para esta app']);
        exit(0);
    }
    
    $email = $payload['email'] ?? '';
    $foto = $payload['picture'] ?? '';
    
    // Buscar usuario por email
    foreach ($usuarios as $usuario) {
        if (strtolower($usuario['email']) === strtolower($email)) {
            $token = generarToken($usuario);
            setcookie('configlab_token', $token, time() + (24 * 60 * 60), '/', '', '', true);
            echo json_encode([
                'success' => true,
                'usuario' => [
                    'id' => $usuario['id'],
                    'email' => $usuario['email'],
                    'nombre' => $usuario['nombre'] ?? '',
                    'foto' => $foto
                ]
            ]);
            exit(0);
        }
    }
    
    echo json_encode(['success' => false, 'mensaje' => 'Email no autorizado']);
    exit(0);
}

if (isset($datos['id']) && $datos['id'] !== '') {
    $id = $datos['id'];
    
    // Buscar usuario por ID
    foreach ($usuarios as $usuario) {
        if ($usuario['id'] === $id) {
            $token = generarToken($usuario);
            setcookie('configlab_token', $token, time() + (24 * 60 * 60), '/', '', '', true);
            echo json_encode([
                'success' => true,
                'usuario' => [
                    'id' => $usuario['id'],
                    'email' => $usuario['email'],
                    'nombre' => $usuario['nombre'] ?? ''
                ]
            ]);
            exit(0);
        }
    }
    
    echo json_encode(['success' => false, 'mensaje' => 'ID no autorizado']);
    exit(0);
}

echo json_encode(['success' => false, 'mensaje' => 'Datos inválidos']);