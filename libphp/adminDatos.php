<?php
// API de datos del Módulo Admin (equivalente SPA de libphp/admin.php).
// Devuelve JSON con estadísticas, gráficos, análisis de salud y listado paginado.
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

function fval($n) { return floatval(str_replace(',', '.', $n ?? '0')); }

function clasificarEdadA($edad) {
    $edad = (int)round((float)$edad);
    if ($edad < 18) return '0-17';
    if ($edad <= 35) return '18-35';
    if ($edad <= 55) return '36-55';
    if ($edad <= 75) return '56-75';
    return '75+';
}

function registrarCondicionA(&$hd, &$hg, &$ha, &$ht, $cond, $gen, $edad, $tabla) {
    $hd[$cond]++;
    if (isset($hg[$gen])) $hg[$gen]++;
    $ha[clasificarEdadA($edad)]++;
    $ht[$tabla] = ($ht[$tabla] ?? 0) + 1;
}

function extraerResultadoDinamicoA($mysqli, $tabla, $identificacion, $codexamen, $fecha) {
    if (empty($tabla)) return 'Sin tabla';
    $tabla_safe = $mysqli->real_escape_string($tabla);
    if ($mysqli->query("SHOW TABLES LIKE '$tabla_safe'")->num_rows === 0) return 'Tabla no encontrada';

    $estructura = $mysqli->query("DESCRIBE `$tabla_safe`");
    $columnas = [];
    while ($col = $estructura->fetch_assoc()) $columnas[] = $col['Field'];
    $cols_lower = array_map('strtolower', $columnas);

    $wc = []; $wp = []; $wt = "";
    foreach (['identificacion','nrodoc','cedula','documento','id_paciente','idpaciente'] as $alias) {
        $idx = array_search($alias, $cols_lower);
        if ($idx !== false) { $wc[] = "`{$columnas[$idx]}` = ?"; $wp[] = $identificacion; $wt .= "s"; break; }
    }
    foreach (['codexamen','examen','codigo','cod_examen','idexamen'] as $alias) {
        $idx = array_search($alias, $cols_lower);
        if ($idx !== false) { $wc[] = "`{$columnas[$idx]}` = ?"; $wp[] = $codexamen; $wt .= "s"; break; }
    }
    foreach (['fecha','fecharegistro','fecha_registro','fechahora'] as $alias) {
        $idx = array_search($alias, $cols_lower);
        if ($idx !== false) { $wc[] = "`{$columnas[$idx]}` = ?"; $wp[] = $fecha; $wt .= "s"; break; }
    }

    if (empty($wc)) {
        $res = $mysqli->query("SELECT * FROM `$tabla_safe` ORDER BY ind DESC LIMIT 1");
        if (!$res || $res->num_rows === 0) return 'Pendiente';
        $datos = $res->fetch_assoc();
    } else {
        $s = $mysqli->prepare("SELECT * FROM `$tabla_safe` WHERE " . implode(" AND ", $wc) . " LIMIT 1");
        if (!$s) return 'Error';
        $s->bind_param($wt, ...$wp); $s->execute();
        $r = $s->get_result();
        if ($r->num_rows === 0) return 'Pendiente';
        $datos = $r->fetch_assoc();
    }

    $excluir = array_map('strtolower', ['ind','identificacion','nrodoc','cedula','documento','codexamen','examen','codigo','fecha','hora','id','bacteriologo','observaciones','fechahora','fechaResultados','id_paciente','idpaciente','cod_examen','idexamen']);
    $valor = '';
    foreach (['resultado','valor','result','value','valoracion','dato'] as $campo) {
        if (isset($datos[$campo]) && !empty($datos[$campo]) && $datos[$campo] !== 'N/A') { $valor = $datos[$campo]; break; }
    }
    if (empty($valor)) {
        foreach ($datos as $columna => $v) {
            if (!in_array(strtolower($columna), $excluir) && !empty($v) && $v !== '0000-00-00' && $v !== '0' && $v !== 'N/A') {
                $valor .= ($valor ? ', ' : '') . $columna . ': ' . $v;
            }
        }
    }
    return $valor ?: 'Sin valor';
}

// --- Filtros (JSON del cliente) ---
$fecha_hoy = date('Y-m-d');
$fecha_inicio = $datos->fecha_inicio ?? $fecha_hoy;
$fecha_fin = $datos->fecha_fin ?? $fecha_hoy;
$entidad = $datos->entidad ?? '';
$buscar = $datos->buscar ?? '';
$estado = $datos->estado ?? '';
$tabla = $datos->tabla ?? '';
$page = max(1, intval($datos->page ?? 1));
$per_page = min(max(1, intval($datos->per_page ?? 25)), 50);
$offset = ($page - 1) * $per_page;
$analisis = ($datos->analisis ?? '0') === '1' ? '1' : '0';
$analisis_desde = $datos->analisis_desde ?? '';
$analisis_hasta = $datos->analisis_hasta ?? '';

function wFechaAnalisis($alias) {
    global $mysqli, $analisis_desde, $analisis_hasta;
    if (empty($analisis_desde) && empty($analisis_hasta)) return '';
    $w = [];
    if (!empty($analisis_desde)) $w[] = "$alias.fecha >= '" . $mysqli->real_escape_string($analisis_desde) . "'";
    if (!empty($analisis_hasta)) $w[] = "$alias.fecha <= '" . $mysqli->real_escape_string($analisis_hasta) . "'";
    return ' WHERE ' . implode(' AND ', $w);
}

$procedimientos_exists = $mysqli->query("SHOW TABLES LIKE 'procedimientos'")->num_rows > 0;
$tabla_labels = [
    'examen_tipo_1' => 'General', 'examen_tipo_2' => 'Química', 'examen_tipo_3' => 'Parcial de Orina',
    'examen_tipo_5' => 'Hemograma', 'examen_tipo_7' => 'Tiempo de Protrombina',
    'perfilLipidico' => 'Perfil Lipídico', 'hemogramaRayto' => 'Hemograma Rayto'
];
$tablas = [];
if ($procedimientos_exists) {
    $res_proc = $mysqli->query("SELECT DISTINCT tabla, nombre FROM procedimientos WHERE tabla IS NOT NULL AND tabla != '' ORDER BY nombre");
    if ($res_proc) while ($rp = $res_proc->fetch_assoc()) $tablas[] = $rp;
}
$entidades = [];
$res_ent = $mysqli->query("SELECT DISTINCT entidad AS nombre FROM examenes WHERE entidad IS NOT NULL AND TRIM(entidad) != '' ORDER BY entidad ASC");
if ($res_ent) while ($re = $res_ent->fetch_assoc()) $entidades[] = $re['nombre'];

// --- Estadísticas ---
$stats = [
    'total' => (int)($mysqli->query("SELECT COUNT(*) t FROM examenes")->fetch_assoc()['t']),
    'realizados' => (int)($mysqli->query("SELECT COUNT(*) t FROM examenes WHERE realizado='S'")->fetch_assoc()['t']),
    'hoy' => (int)($mysqli->query("SELECT COUNT(*) t FROM examenes WHERE fecha = CURDATE()")->fetch_assoc()['t']),
    'ayer' => (int)($mysqli->query("SELECT COUNT(*) t FROM examenes WHERE fecha = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetch_assoc()['t']),
    'ultimos14' => (int)($mysqli->query("SELECT COUNT(*) t FROM examenes WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)")->fetch_assoc()['t']),
    'pacientes' => (int)($mysqli->query("SELECT COUNT(DISTINCT identificacion) t FROM examenes")->fetch_assoc()['t']),
    'visitas' => (int)($mysqli->query("SELECT COUNT(DISTINCT identificacion, fecha) t FROM examenes")->fetch_assoc()['t'])
];
$stats['pendientes'] = $stats['total'] - $stats['realizados'];
$stats['tasa_realizacion'] = $stats['total'] > 0 ? round($stats['realizados'] * 100 / $stats['total'], 1) : 0;
$stats['promedio_diario'] = round($stats['ultimos14'] / 14, 1);
$stats['hoy_vs_ayer'] = $stats['ayer'] > 0 ? round(($stats['hoy'] - $stats['ayer']) * 100 / $stats['ayer'], 1) : ($stats['hoy'] > 0 ? 100 : 0);

// --- Gráficos ---
function colData($mysqli, $sql) { $l = []; $d = []; $r = $mysqli->query($sql); if ($r) while ($x = $r->fetch_assoc()) { $l[] = $x['label']; $d[] = (int)$x['val']; } return [$l, $d]; }
$charts = [];
list($charts['tendencia_labels'], $charts['tendencia_data']) = colData($mysqli, "SELECT DATE_FORMAT(e.fecha,'%d/%m') label, COUNT(*) val FROM examenes e WHERE e.fecha >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY e.fecha ORDER BY e.fecha ASC");
list($charts['entidad_labels'], $charts['entidad_data']) = colData($mysqli, "SELECT entidad label, COUNT(*) val FROM examenes e WHERE e.entidad IS NOT NULL AND TRIM(e.entidad) != '' GROUP BY entidad ORDER BY val DESC LIMIT 8");
$charts['genero_labels'] = []; $charts['genero_data'] = [];
$res_gen = $mysqli->query("SELECT p.genero, COUNT(*) total FROM examenes e INNER JOIN paciente p ON e.identificacion=p.identificacion GROUP BY p.genero");
if ($res_gen) while ($rg = $res_gen->fetch_assoc()) { $charts['genero_labels'][] = $rg['genero'] === 'F' ? 'Femenino' : ($rg['genero'] === 'M' ? 'Masculino' : 'Otro'); $charts['genero_data'][] = (int)$rg['total']; }
$charts['tipo_labels'] = []; $charts['tipo_data'] = [];
if ($procedimientos_exists) {
    $res_t = $mysqli->query("SELECT pr.tabla, COUNT(*) total FROM examenes e INNER JOIN procedimientos pr ON e.codexamen=pr.codigo WHERE pr.tabla IS NOT NULL GROUP BY pr.tabla ORDER BY total DESC");
    if ($res_t) while ($rt = $res_t->fetch_assoc()) { $charts['tipo_labels'][] = $tabla_labels[$rt['tabla']] ?? $rt['tabla']; $charts['tipo_data'][] = (int)$rt['total']; }
}
$charts['estadoEntidad_labels'] = []; $charts['estadoEntidad_realizados'] = []; $charts['estadoEntidad_pendientes'] = [];
$res_rp = $mysqli->query("SELECT e.entidad, SUM(CASE WHEN e.realizado='S' THEN 1 ELSE 0 END) realizados, SUM(CASE WHEN e.realizado!='S' THEN 1 ELSE 0 END) pendientes FROM examenes e WHERE e.entidad IS NOT NULL AND TRIM(e.entidad) != '' GROUP BY e.entidad ORDER BY COUNT(*) DESC LIMIT 5");
if ($res_rp) while ($rr = $res_rp->fetch_assoc()) { $charts['estadoEntidad_labels'][] = $rr['entidad']; $charts['estadoEntidad_realizados'][] = (int)$rr['realizados']; $charts['estadoEntidad_pendientes'][] = (int)$rr['pendientes']; }

// --- Salud (solo si analisis=1) ---
$health = null;
if ($analisis === '1') {
    $health_data = ['anemia'=>0,'infecciones'=>0,'trombocitopenia'=>0,'trombocitosis'=>0,'diabetes_indicio'=>0,'infeccion_urinaria'=>0,'problemas_renales'=>0,'bilirrubina_alterada'=>0,'colesterol_alto'=>0,'ldl_alto'=>0,'hdl_bajo'=>0,'trigliceridos_altos'=>0,'coagulacion'=>0];
    $health_by_gender = ['F'=>0,'M'=>0];
    $health_by_age = ['0-17'=>0,'18-35'=>0,'36-55'=>0,'56-75'=>0,'75+'=>0];
    $health_by_table = [];
    $cond_labels = ['anemia'=>'Anemia','infecciones'=>'Infecciones','trombocitopenia'=>'Trombocitopenia','trombocitosis'=>'Trombocitosis','diabetes_indicio'=>'Diabetes (indicio)','infeccion_urinaria'=>'Infección Urinaria','problemas_renales'=>'Problemas Renales','bilirrubina_alterada'=>'Bilirrubina Alta','colesterol_alto'=>'Colesterol Alto','ldl_alto'=>'LDL Alto','hdl_bajo'=>'HDL Bajo','trigliceridos_altos'=>'Triglicéridos Altos','coagulacion'=>'Coagulación'];

    $tbl = function ($name) use ($mysqli) { return $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($name) . "'")->num_rows > 0; };
    if ($tbl('hemogramaRayto')) {
        $r = $mysqli->query("SELECT h.*, p.genero, p.edad FROM hemogramaRayto h INNER JOIN paciente p ON h.identificacion=p.identificacion" . wFechaAnalisis('h'));
        if ($r) while ($x = $r->fetch_assoc()) {
            $WBC=fval($x['WBC']);$PLT=fval($x['PLT']);$HGB=fval($x['HGB'] ?? $x['hemoglobina'] ?? '0');
            $gen=$x['genero']??'';$edad=$x['edad']??0;
            if ($HGB>0 && (($gen==='F'&&$HGB<12)||($gen==='M'&&$HGB<13)||($gen!=='F'&&$gen!=='M'&&$HGB<12))) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'anemia',$gen,$edad,'Hemograma');
            if ($WBC>0 && ($WBC>11000||$WBC<4000)) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'infecciones',$gen,$edad,'Hemograma');
            if ($PLT>0 && $PLT<150000) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'trombocitopenia',$gen,$edad,'Hemograma');
            if ($PLT>450000) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'trombocitosis',$gen,$edad,'Hemograma');
        }
    }
    if ($tbl('examen_tipo_5')) {
        $r = $mysqli->query("SELECT t.*, p.genero, p.edad FROM examen_tipo_5 t INNER JOIN paciente p ON t.identificacion=p.identificacion" . wFechaAnalisis('t'));
        if ($r) while ($x = $r->fetch_assoc()) {
            $WBC=fval($x['WBC'] ?? $x['leucocitos'] ?? '0');$PLT=fval($x['PLT']);$HGB=fval($x['hemoglobina']??'0');
            $gen=$x['genero']??'';$edad=$x['edad']??0;
            if ($HGB>0 && (($gen==='F'&&$HGB<12)||($gen==='M'&&$HGB<13)||($gen!=='F'&&$gen!=='M'&&$HGB<12))) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'anemia',$gen,$edad,'Hemograma');
            if ($WBC>0 && ($WBC>11000||$WBC<4000)) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'infecciones',$gen,$edad,'Hemograma');
            if ($PLT>0 && $PLT<150000) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'trombocitopenia',$gen,$edad,'Hemograma');
            if ($PLT>450000) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'trombocitosis',$gen,$edad,'Hemograma');
        }
    }
    if ($tbl('examen_tipo_3')) {
        $r = $mysqli->query("SELECT t.*, p.genero, p.edad FROM examen_tipo_3 t INNER JOIN paciente p ON t.identificacion=p.identificacion" . wFechaAnalisis('t'));
        if ($r) while ($x = $r->fetch_assoc()) {
            $gen=$x['genero']??'';$edad=$x['edad']??0;
            $glucosa=strtolower(trim($x['glucosa']??''));
            $nitritos=strtolower(trim($x['nitritos']??''));
            $leucocitos=strtolower(trim($x['leucocitos']??''));
            $proteinas=strtolower(trim($x['proteinas']??''));
            $bilirrubina=strtolower(trim($x['bilirrubina']??''));
            if (!empty($glucosa)&&!in_array($glucosa,['n/a','negativo','neg','ausente',''])) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'diabetes_indicio',$gen,$edad,'Parcial Orina');
            if ((!empty($nitritos)&&!in_array($nitritos,['n/a','negativo','neg','']))||(!empty($leucocitos)&&!in_array($leucocitos,['n/a','negativo','neg','']))) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'infeccion_urinaria',$gen,$edad,'Parcial Orina');
            if (!empty($proteinas)&&!in_array($proteinas,['n/a','negativo','neg',''])) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'problemas_renales',$gen,$edad,'Parcial Orina');
            if (!empty($bilirrubina)&&!in_array($bilirrubina,['n/a','negativo','neg',''])) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'bilirrubina_alterada',$gen,$edad,'Parcial Orina');
        }
    }
    // Parcial de orina actual (tabla `parcialOrina`)
    if ($tbl('parcialOrina')) {
        $r = $mysqli->query("SELECT t.*, p.genero, p.edad FROM parcialOrina t INNER JOIN paciente p ON t.identificacion=p.identificacion" . wFechaAnalisis('t'));
        if ($r) while ($x = $r->fetch_assoc()) {
            $gen=$x['genero']??'';$edad=$x['edad']??0;
            $glucosa=strtolower(trim($x['glucosa']??''));
            $nitritos=strtolower(trim($x['nitritos']??''));
            $leucocitos=strtolower(trim($x['leucocitos']??''));
            $proteinas=strtolower(trim($x['proteinas']??''));
            $bilirrubina=strtolower(trim($x['bilirrubina']??''));
            if (!empty($glucosa)&&!in_array($glucosa,['n/a','negativo','neg','ausente',''])) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'diabetes_indicio',$gen,$edad,'Parcial Orina');
            if ((!empty($nitritos)&&!in_array($nitritos,['n/a','negativo','neg','']))||(!empty($leucocitos)&&!in_array($leucocitos,['n/a','negativo','neg','']))) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'infeccion_urinaria',$gen,$edad,'Parcial Orina');
            if (!empty($proteinas)&&!in_array($proteinas,['n/a','negativo','neg',''])) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'problemas_renales',$gen,$edad,'Parcial Orina');
            if (!empty($bilirrubina)&&!in_array($bilirrubina,['n/a','negativo','neg',''])) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'bilirrubina_alterada',$gen,$edad,'Parcial Orina');
        }
    }
    if ($tbl('perfilLipidico')) {
        $r = $mysqli->query("SELECT t.*, p.genero, p.edad FROM perfilLipidico t INNER JOIN paciente p ON t.identificacion=p.identificacion" . wFechaAnalisis('t'));
        if ($r) while ($x = $r->fetch_assoc()) {
            $gen=$x['genero']??'';$edad=$x['edad']??0;
            $ct=fval($x['colesterol_total']);$cl=fval($x['colesterol_hdl']);$ldl=fval($x['colesterol_ldl']);$trig=fval($x['trigliceridos']);
            if ($ct>200) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'colesterol_alto',$gen,$edad,'Perfil Lipídico');
            if ($ldl>130) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'ldl_alto',$gen,$edad,'Perfil Lipídico');
            if ($cl>0 && (($gen==='M'&&$cl<40)||($gen==='F'&&$cl<50)||($gen!=='F'&&$gen!=='M'&&$cl<40))) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'hdl_bajo',$gen,$edad,'Perfil Lipídico');
            if ($trig>150) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'trigliceridos_altos',$gen,$edad,'Perfil Lipídico');
        }
    }
    if ($tbl('examen_tipo_7')) {
        $r = $mysqli->query("SELECT t.*, p.genero, p.edad FROM examen_tipo_7 t INNER JOIN paciente p ON t.identificacion=p.identificacion" . wFechaAnalisis('t'));
        if ($r) while ($x = $r->fetch_assoc()) {
            $gen=$x['genero']??'';$edad=$x['edad']??0;
            $pt=fval($x['tiempo_de_protrombina']);
            if ($pt>0 && ($pt<11||$pt>13.5)) registrarCondicionA($health_data,$health_by_gender,$health_by_age,$health_by_table,'coagulacion',$gen,$edad,'Protrombina');
        }
    }
    $health = ['condiciones'=>[], 'genero'=>['Femenino'=>$health_by_gender['F'],'Masculino'=>$health_by_gender['M']], 'edad'=>$health_by_age, 'tabla'=>$health_by_table, 'total'=>array_sum($health_data)];
    foreach ($cond_labels as $k => $lab) if ($health_data[$k] > 0) $health['condiciones'][] = ['label'=>$lab, 'total'=>$health_data[$k]];
    usort($health['condiciones'], function ($a, $b) { return $b['total'] - $a['total']; });
}

// --- Listado paginado ---
$where = ['1=1']; $params = []; $types = "";
if (!empty($fecha_inicio)) { $where[] = "e.fecha >= ?"; $params[] = $fecha_inicio; $types .= "s"; }
if (!empty($fecha_fin)) { $where[] = "e.fecha <= ?"; $params[] = $fecha_fin; $types .= "s"; }
if (!empty($entidad)) { $where[] = "TRIM(e.entidad) = TRIM(?)"; $params[] = $entidad; $types .= "s"; }
if (!empty($buscar)) {
    $like = "%" . $buscar . "%";
    $where[] = "(e.identificacion LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR p.correo LIKE ? OR p.telefono LIKE ?)";
    $params = array_merge($params, [$like, $like, $like, $like, $like]); $types .= "sssss";
}
if ($estado === 'realizado') $where[] = "e.realizado='S'";
elseif ($estado === 'pendiente') $where[] = "e.realizado!='S'";
if (!empty($tabla) && $procedimientos_exists) { $where[] = "pr.tabla = ?"; $params[] = $tabla; $types .= "s"; }
$where_sql = implode(" AND ", $where);

$count_sql = "SELECT COUNT(*) t FROM examenes e INNER JOIN paciente p ON e.identificacion=p.identificacion";
if ($procedimientos_exists) $count_sql .= " LEFT JOIN procedimientos pr ON e.codexamen=pr.codigo";
$count_sql .= " WHERE $where_sql";
$cst = $mysqli->prepare($count_sql);
if ($cst) { if (!empty($params)) $cst->bind_param($types, ...$params); $cst->execute(); $total = (int)$cst->get_result()->fetch_assoc()['t']; } else $total = 0;
$pages = max(1, (int)ceil($total / $per_page));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $per_page;

$sql = "SELECT e.identificacion, e.fecha, e.entidad, e.codexamen, e.realizado, p.nombres, p.apellidos, p.edad, p.genero";
if ($procedimientos_exists) $sql .= ", pr.tabla AS examen_tabla, pr.nombre AS examen_nombre, pr.tipo AS examen_tipo";
$sql .= " FROM examenes e INNER JOIN paciente p ON e.identificacion=p.identificacion";
if ($procedimientos_exists) $sql .= " LEFT JOIN procedimientos pr ON e.codexamen=pr.codigo";
$sql .= " WHERE $where_sql ORDER BY e.fecha DESC, p.apellidos ASC, p.nombres ASC LIMIT $per_page OFFSET $offset";
$st = $mysqli->prepare($sql);
$filas = [];
if ($st) {
    if (!empty($params)) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($f = $res->fetch_assoc()) {
        $f['paciente'] = trim(($f['apellidos'] ?? '') . ' ' . ($f['nombres'] ?? ''));
        $f['examen'] = $f['examen_nombre'] ?? '';
        $f['resumen'] = extraerResultadoDinamicoA($mysqli, $f['examen_tabla'] ?? '', $f['identificacion'], $f['codexamen'], $f['fecha']);
        unset($f['nombres'], $f['apellidos'], $f['examen_nombre']);
        $filas[] = $f;
    }
}

echo json_encode(['success' => true, 'stats' => $stats, 'charts' => $charts, 'health' => $health, 'tablas' => $tablas, 'entidades' => $entidades, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filas' => $filas]);
$mysqli->close();
