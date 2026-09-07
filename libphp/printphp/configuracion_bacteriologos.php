<?php
/**
 * configuracion_bacteriologos.php
 * Configuración de bacteriólogos por fecha de reporte
 * 
 * Lee firmas.json y determina la firma activa según la fecha actual
 */

// === CONFIGURACIÓN ===
define('FIRMAS_JSON_URL', 'https://mycar.iedeoccidente.com/printphp/firmas.json');

/**
 * Obtiene las firmas desde el JSON del servidor
 * 
 * @return array|null Array de firmas o null si hay error
 */
function obtenerFirmas() {
    $json = @file_get_contents(FIRMAS_JSON_URL);
    if (!$json) {
        return null;
    }
    $firmas = json_decode($json, true);
    return is_array($firmas) ? $firmas : null;
}

/**
 * Determina si una firma está activa en la fecha dada
 * 
 * @param array $periodos Array de periodos con 'desde' y 'hasta'
 * @param string $fecha Fecha a verificar (Y-m-d)
 * @return bool True si la firma está activa
 */
function firmaActivaEnFecha($periodos, $fecha) {
    if (empty($periodos)) {
        return true; // Sin períodos = siempre activo
    }
    
    $fecha = strtotime($fecha);
    if ($fecha === false) {
        return true;
    }
    
    foreach ($periodos as $periodo) {
        $desde = isset($periodo['desde']) ? strtotime($periodo['desde']) : 0;
        $hasta = isset($periodo['hasta']) && $periodo['hasta'] ? strtotime($periodo['hasta']) : PHP_INT_MAX;
        
        if ($fecha >= $desde && $fecha <= $hasta) {
            return true;
        }
    }
    return false;
}

/**
 * Obtiene la firma activa según la fecha del reporte
 * 
 * @param string $fecha_reporte Fecha del examen (Y-m-d)
 * @param string|null $bacteriologo_actual Valor actual de la BD (no se usa ya que se determina por fecha)
 * @return array Datos de la firma activa: ['nombre', 'cargo', 'imagen_firma', 'archivo']
 */
function obtenerFirmaActiva($fecha_reporte, $bacteriologo_actual = null) {
    $firmas = obtenerFirmas();
    
    if (!$firmas || empty($firmas)) {
        return [
            'nombre' => $bacteriologo_actual ?: 'Bacteriólogo',
            'cargo' => '',
            'imagen_firma' => '',
            'archivo' => ''
        ];
    }
    
    // Buscar firma activa según la fecha
    foreach ($firmas as $firma) {
        $periodos = isset($firma['periodos']) ? $firma['periodos'] : [];
        
        if (firmaActivaEnFecha($periodos, $fecha_reporte)) {
            return [
                'nombre' => $firma['nombre'] ?? '',
                'cargo' => $firma['cargo'] ?? '',
                'imagen_firma' => $firma['imagen_firma'] ?? '',
                'archivo' => $firma['archivo'] ?? ''
            ];
        }
    }
    
    // Si no encuentra firma activa, retornar la primera o la actual de BD
    return [
        'nombre' => $bacteriologo_actual ?: ($firmas[0]['nombre'] ?? 'Bacteriólogo'),
        'cargo' => $firmas[0]['cargo'] ?? '',
        'imagen_firma' => $firmas[0]['imagen_firma'] ?? '',
        'archivo' => $firmas[0]['archivo'] ?? ''
    ];
}

/**
 * Obtiene la imagen de firma en base64 para usar en el PDF
 * 
 * @param string $fecha_reporte Fecha del examen (Y-m-d)
 * @param string|null $bacteriologo_actual Valor actual de la BD
 * @return string Imagen en base64 o URL de imagen por defecto
 */
function obtenerImagenFirma($fecha_reporte, $bacteriologo_actual = null) {
    $firma = obtenerFirmaActiva($fecha_reporte, $bacteriologo_actual);
    
    // Si tiene imagen base64, retornarla
    if (!empty($firma['imagen_firma'])) {
        return $firma['imagen_firma'];
    }
    
    // Si tiene archivo, construir URL
    if (!empty($firma['archivo'])) {
        return 'https://mycar.iedeoccidente.com/printphp/' . $firma['archivo'];
    }
    
    // Por defecto, imagen legacy
    return 'https://mycar.iedeoccidente.com/printphp/firma1.png';
}

/**
 * Obtiene el nombre completo del bacteriólogo activo
 * 
 * @param string $fecha_reporte Fecha del examen (Y-m-d)
 * @param string|null $bacteriologo_actual Valor actual de la BD
 * @return string Nombre del bacteriólogo
 */
function obtenerBacteriologoPorFecha($fecha_reporte, $bacteriologo_actual = null) {
    $firma = obtenerFirmaActiva($fecha_reporte, $bacteriologo_actual);
    return $firma['nombre'];
}

/**
 * Compatibilidad con código anterior
 * Determina qué bacteriólogo mostrar según la fecha del reporte
 * 
 * @param string $fecha_reporte Fecha del examen (YYYY-MM-DD)
 * @param string $bacteriologo_actual Valor proveniente de la BD
 * @return string Nombre completo del bacteriólogo con T.P.
 */
function obtenerBacteriologoPorFechaLegacy($fecha_reporte, $bacteriologo_actual) {
    return obtenerBacteriologoPorFecha($fecha_reporte, $bacteriologo_actual);
}
?>