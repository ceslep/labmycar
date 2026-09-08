<?php
// Guard de API: cabeceras CORS/preflight centralizadas + autenticación por token.
// Requiere que datos_conexion.php ya se haya cargado (usa $mysqli para calcular el token).

if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
    header('Content-Type: application/json; charset=UTF-8');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!defined('API_SALT')) {
    define('API_SALT', 'labmycar_apisecret_2025_cambiar');
}

/** Token esperado (HMAC de la T.P. configurada). Cambiar la T.P. invalida todos los tokens. */
function apiTokenEsperado(): string {
    static $t = null;
    if ($t === null) {
        global $mysqli;
        $tp = '';
        if ($mysqli) {
            $r = $mysqli->query("SELECT tarjetaPLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
            if ($r && $row = $r->fetch_assoc()) $tp = $row['tarjetaPLaboratorio'] ?? '';
        }
        $t = hash_hmac('sha256', 'lab:' . $tp, API_SALT);
    }
    return $t;
}

function apiTokenCliente(): string {
    // El token viaja normalmente en el body JSON (evita preflight CORS);
    // también se acepta por cabecera como respaldo.
    global $datos;
    $b = isset($datos->token) ? trim((string)$datos->token) : '';
    if ($b !== '') return $b;
    $h = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($h) return trim($h);
    $a = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $a, $m)) return trim($m[1]);
    return '';
}

/** Exige token en este endpoint (escrituras y datos sensibles). */
function exigirToken(): void {
    if (!hash_equals(apiTokenEsperado(), apiTokenCliente())) {
        http_response_code(403);
        echo json_encode(["msg" => false, "error" => "no_autorizado"]);
        exit;
    }
}
