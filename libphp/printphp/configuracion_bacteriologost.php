<?php
/**
 * config_bacteriologos.php
 * Configuración temporal de bacteriólogos por fecha de reporte
 * 
 * 📝 Instrucciones:
 * - Para cambiar de bacteriólogo en el futuro, solo actualiza las constantes abaixo
 * - Formato de fecha: 'YYYY-MM-DD'
 */

// === CONFIGURACIÓN ACTUAL ===
define('FECHA_CAMBIO_BACTERIOLOGO', '2026-01-20');
define('BACTERIOLOGO_ANTERIOR', 'Meiser Pedroza Ditta:T.P. 1065643292');

/**
 * Determina qué bacteriólogo mostrar según la fecha del reporte
 * 
 * @param string $fecha_reporte Fecha del examen (YYYY-MM-DD)
 * @param string $bacteriologo_actual Valor proveniente de la BD
 * @return string Nombre completo del bacteriólogo con T.P.
 */
function obtenerBacteriologoPorFecha($fecha_reporte, $bacteriologo_actual) {
    // Si la fecha es anterior al cambio, retornar el bacteriólogo histórico
    if ($fecha_reporte < FECHA_CAMBIO_BACTERIOLOGO) {
        return BACTERIOLOGO_ANTERIOR;
    }
    // De lo contrario, usar el bacteriólogo configurado en la BD
    return $bacteriologo_actual;
}
?>