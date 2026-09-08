<?php
// Migración puntual: deduplica filas repetidas y agrega índices únicos.
// Ejecutar UNA vez con token (Sincronizar libphp primero). Puede borrarse tras ejecutar.
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$resumen = [];
$errores = [];

function columnaPk($mysqli, $tabla) {
    $cols = [];
    $r = $mysqli->query("DESCRIBE `" . $mysqli->real_escape_string($tabla) . "`");
    if (!$r) return null;
    while ($c = $r->fetch_assoc()) {
        if ($c['Key'] === 'PRI') return $c['Field'];
        $cols[] = $c['Field'];
    }
    return in_array('id', $cols, true) ? 'id' : (in_array('ind', $cols, true) ? 'ind' : null);
}

function tieneIndice($mysqli, $tabla, $nombre) {
    $st = $mysqli->prepare("SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
    $st->bind_param('ss', $tabla, $nombre);
    $st->execute();
    return (int)$st->get_result()->fetch_assoc()['n'] > 0;
}

$candidatos = [
    ['tabla' => 'parcialOrina', 'cols' => ['identificacion', 'fecha']],
    ['tabla' => 'coprologico', 'cols' => ['identificacion', 'fecha']],
    ['tabla' => 'frotisVaginal', 'cols' => ['identificacion', 'fecha']],
    ['tabla' => 'perfilLipidico', 'cols' => ['identificacion', 'fecha']],
    ['tabla' => 'hemogramaRayto', 'cols' => ['identificacion', 'fecha']],
    ['tabla' => 'examen_tipo_1', 'cols' => ['identificacion', 'fecha', 'examen']],
    ['tabla' => 'examen_tipo_2', 'cols' => ['identificacion', 'fecha', 'examen']],
    ['tabla' => 'examen_tipo_5', 'cols' => ['identificacion', 'fecha', 'examen']],
    ['tabla' => 'examen_tipo_7', 'cols' => ['identificacion', 'fecha', 'examen']],
    ['tabla' => 'examenes', 'cols' => ['codexamen', 'identificacion', 'fecha']],
    ['tabla' => 'paciente', 'cols' => ['identificacion']],
    ['tabla' => 'opcionesExamenes', 'cols' => ['codexamen', 'campo']]
];

foreach ($candidatos as $cand) {
    $tabla = $cand['tabla'];
    $cols = $cand['cols'];
    $pk = columnaPk($mysqli, $tabla);
    if (!$pk) {
        $errores[] = "$tabla: sin PK detectable";
        continue;
    }
    // Verificar que todas las columnas clave existan
    $des = $mysqli->query("DESCRIBE `" . $mysqli->real_escape_string($tabla) . "`");
    $existentes = [];
    if ($des) while ($d = $des->fetch_assoc()) $existentes[] = $d['Field'];
    $faltan = array_values(array_diff($cols, $existentes));
    if (count($faltan) > 0) {
        $errores[] = "$tabla: faltan columnas " . implode(',', $faltan);
        continue;
    }
    $cond = implode(' AND ', array_map(function ($c) { return "d.`$c`=k.`$c`"; }, $cols));
    $dup = 0;
    // examenes: si cualquier fila de la clave estaba realizada, marcarlas todas antes de deduplicar
    if ($tabla === 'examenes') {
        $mysqli->query("UPDATE examenes e JOIN (SELECT codexamen, identificacion, fecha FROM examenes WHERE realizado='S' GROUP BY codexamen, identificacion, fecha) s ON e.codexamen=s.codexamen AND e.identificacion=s.identificacion AND e.fecha=s.fecha SET e.realizado='S'");
    }
    $sql = "DELETE d FROM `$tabla` d INNER JOIN `$tabla` k ON $cond AND k.`$pk` > d.`$pk`";
    if ($mysqli->query($sql)) {
        $dup = $mysqli->affected_rows;
    } else {
        $errores[] = "$tabla: dedupe falló -> " . $mysqli->error;
        continue;
    }
    $nombre = 'uq_' . $tabla;
    $creado = false;
    if (!tieneIndice($mysqli, $tabla, $nombre)) {
        $colsSql = implode(',', array_map(function ($c) { return "`$c`"; }, $cols));
        if ($mysqli->query("ALTER TABLE `$tabla` ADD UNIQUE KEY `$nombre` ($colsSql)")) {
            $creado = true;
        } else {
            $errores[] = "$tabla: índice falló -> " . $mysqli->error;
        }
    }
    $resumen[] = ['tabla' => $tabla, 'duplicados_eliminados' => $dup, 'indice_unico' => $creado ? 'creado' : (tieneIndice($mysqli, $tabla, $nombre) ? 'ya_existia' : 'pendiente')];
}

echo json_encode(['msg' => true, 'resumen' => $resumen, 'errores' => $errores]);
$mysqli->close();
