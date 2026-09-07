<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['filename']) || empty($input['data'])) {
    echo json_encode(["msg" => false, "error" => "filename y data requeridos"]);
    exit;
}

$filename = basename($input['filename']);
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

if (!preg_match('/\.(png|jpg|jpeg|webp)$/i', $filename)) {
    echo json_encode(["msg" => false, "error" => "Extensión no permitida"]);
    exit;
}

$data = $input['data'];
if (preg_match('/^data:image\/\w+;base64,/', $data)) {
    $data = preg_replace('/^data:image\/\w+;base64,/', '', $data);
}
$decoded = base64_decode($data);

if ($decoded === false) {
    echo json_encode(["msg" => false, "error" => "Base64 inválido"]);
    exit;
}

if (file_put_contents(__DIR__ . '/' . $filename, $decoded)) {
    echo json_encode(["msg" => true, "archivo" => $filename]);
} else {
    echo json_encode(["msg" => false, "error" => "Error al guardar archivo"]);
}
