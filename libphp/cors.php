<?php
/*$allowed_origins = [
    "http://localhost:5173",
    "http://localhost:4173",
    "http://localhost:5174",
    "http://localhost:5175",
    "https://app.iedeoccidente.com",
    "https://ceslep.github.io"
];

// Verifica si la cabecera Origin existe y est谩 permitida
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    header('Content-Type: application/json; charset=utf-8');
}

// Manejo de preflight (OPTIONS) para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}*/
$allowed_origins = [
    "http://localhost:5173",
    "https://app.iedeoccidente.com",
    "https://ceslep.github.io"
];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    // NO incluir Access-Control-Allow-Credentials si usas origin específico
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}