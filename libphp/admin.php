<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (file_exists(__DIR__ . '/datos_conexion.php')) {
    require_once __DIR__ . '/datos_conexion.php';
} elseif (file_exists(__DIR__ . '/../datos_conexion.php')) {
    require_once __DIR__ . '/../datos_conexion.php';
} else {
    die("Error: No se encontró datos_conexion.php en " . __DIR__);
}

$DEBUG = true;

$res_conf = $mysqli->query("SELECT nombreCorto, nombreLaboratorio, urlLogoLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
$dato_conf = $res_conf->fetch_assoc();
$nombreLab = $dato_conf['nombreLaboratorio'] ?? 'Laboratorio Clínico';
$nombreCorto = $dato_conf['nombreCorto'] ?? 'LAB';
$urlLogo = "data:image/png;base64," . ($dato_conf['urlLogoLaboratorio'] ?? '');

if ($DEBUG === true && !isset($_SESSION['autenticado'])) {
    $_SESSION['autenticado'] = true;
    $_SESSION['usuario_nombre'] = $nombreLab . ' (ADMIN)';
    $_SESSION['lab_id'] = 'debug_lab_id';
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

if (isset($_POST['login'])) {
    $password_ingresado = $_POST['password'] ?? '';
    $stmt = $mysqli->prepare("SELECT nombreLaboratorio FROM configuracion WHERE tarjetaPlaboratorio = ? LIMIT 1");
    $stmt->bind_param("s", $password_ingresado);
    $stmt->execute();
    $res_login = $stmt->get_result();
    if ($res_login->num_rows > 0) {
        $datos_u = $res_login->fetch_assoc();
        $_SESSION['autenticado'] = true;
        $_SESSION['usuario_nombre'] = $datos_u['nombreLaboratorio'];
        $_SESSION['lab_id'] = md5($password_ingresado);
    } else {
        $error = "Acceso denegado. Verifique sus datos.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

if (!isset($_SESSION['autenticado'])):
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin - Acceso</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            @keyframes gradientShift { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }
            @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
            @keyframes fadeInUp { from{opacity:0;transform:translateY(30px) scale(0.96)} to{opacity:1;transform:translateY(0) scale(1)} }
            .login-bg { background:linear-gradient(-45deg,#0f172a,#1e1b4b,#172554,#0c0a09); background-size:400% 400%; animation:gradientShift 15s ease infinite; }
            .glass-card { background:rgba(255,255,255,0.95); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); }
            .icon-float { animation:float 4s ease-in-out infinite; }
            .login-enter { animation:fadeInUp 0.7s cubic-bezier(0.16,1,0.3,1) both; }
        </style>
    </head>
    <body class="login-bg h-screen flex items-center justify-center p-4">
        <div class="glass-card p-8 sm:p-10 rounded-3xl shadow-2xl w-full max-w-md login-enter border border-white/20">
            <div class="text-center mb-8">
                <div class="mb-5 icon-float">
                    <?php if (!empty($urlLogo) && $urlLogo !== 'data:image/png;base64,'): ?>
                        <img src="<?= $urlLogo ?>" alt="Logo" class="w-20 h-20 mx-auto object-contain drop-shadow-lg rounded-2xl">
                    <?php else: ?>
                        <img src="icons/thiings/shield.png" alt="Admin" class="w-20 h-20 mx-auto object-contain drop-shadow-lg rounded-2xl">
                    <?php endif; ?>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Panel Administrativo</h1>
                <p class="text-slate-500 text-sm mt-1"><?= htmlspecialchars($nombreLab) ?></p>
            </div>
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-xl mb-5 text-sm flex items-center gap-2 border border-red-100">
                    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Contraseña</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400"><i class="bi bi-key"></i></span>
                        <input type="password" name="password" id="loginPassword" placeholder="Ingrese contraseña" required
                            class="w-full pl-11 pr-12 py-3.5 border-2 border-slate-200 rounded-2xl focus:border-indigo-500 outline-none transition text-sm focus:ring-2 focus:ring-indigo-500/20">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-indigo-500 transition">
                            <i class="bi bi-eye-slash" id="pwIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" name="login"
                    class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white py-3.5 rounded-2xl font-bold text-sm tracking-wide shadow-lg shadow-indigo-200 hover:shadow-xl hover:-translate-y-0.5 transition-all">
                    INICIAR SESIÓN
                </button>
            </form>
            <p class="text-center text-[11px] text-slate-400 mt-6">Módulo de Administración</p>
        </div>
        <script>
            function togglePassword() {
                const pw = document.getElementById('loginPassword');
                const icon = document.getElementById('pwIcon');
                if (pw.type === 'password') { pw.type = 'text'; icon.className = 'bi bi-eye'; }
                else { pw.type = 'password'; icon.className = 'bi bi-eye-slash'; }
            }
        </script>
    </body>
    </html>
    <?php exit;
endif;

// --- PAGINATION & FILTERS ---
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$filter_fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$filter_fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$filter_entidad = $_GET['entidad'] ?? '';
$filter_busqueda = $_GET['buscar'] ?? '';
$filter_estado = $_GET['estado'] ?? '';
$filter_tabla = $_GET['tabla'] ?? '';

// Procedimientos check (antes de filtros)
$procedimientos_exists = $mysqli->query("SHOW TABLES LIKE 'procedimientos'")->num_rows > 0;
$examenes_map = [];
$tablas_disponibles = [];
if ($procedimientos_exists) {
    $res_proc = $mysqli->query("SELECT codigo, nombre, tipo, abreviatura, tabla FROM procedimientos ORDER BY nombre");
    while ($proc = $res_proc->fetch_assoc()) {
        $examenes_map[$proc['codigo']] = $proc;
        if (!empty($proc['tabla']) && !isset($tablas_disponibles[$proc['tabla']])) {
            $tablas_disponibles[$proc['tabla']] = $proc['nombre'] ?? $proc['tabla'];
        }
    }
}
$tabla_labels = [
    'examen_tipo_1' => 'General',
    'examen_tipo_2' => 'Química',
    'examen_tipo_3' => 'Parcial de Orina',
    'examen_tipo_5' => 'Hemograma',
    'examen_tipo_7' => 'Tiempo de Protrombina',
    'perfilLipidico' => 'Perfil Lipídico',
    'hemogramaRayto' => 'Hemograma Rayto',
];

$where = ["1=1"];
$params = [];
$types = "";

if (!empty($filter_fecha_inicio)) {
    $where[] = "e.fecha >= ?";
    $params[] = $filter_fecha_inicio;
    $types .= "s";
}
if (!empty($filter_fecha_fin)) {
    $where[] = "e.fecha <= ?";
    $params[] = $filter_fecha_fin;
    $types .= "s";
}
if (!empty($filter_entidad)) {
    $where[] = "TRIM(e.entidad) = TRIM(?)";
    $params[] = $filter_entidad;
    $types .= "s";
}
if (!empty($filter_busqueda)) {
    $_like = "%" . $filter_busqueda . "%";
    $where[] = "(e.identificacion LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR p.correo LIKE ? OR p.telefono LIKE ?)";
    $params = array_merge($params, [$_like, $_like, $_like, $_like, $_like]);
    $types .= "sssss";
}
if ($filter_estado === 'realizado') {
    $where[] = "e.realizado = 'S'";
} elseif ($filter_estado === 'pendiente') {
    $where[] = "e.realizado != 'S'";
}
if (!empty($filter_tabla) && $procedimientos_exists) {
    $where[] = "pr.tabla = ?";
    $params[] = $filter_tabla;
    $types .= "s";
}

$where_sql = implode(" AND ", $where);

// Count total
$count_sql = "SELECT COUNT(*) as total FROM examenes e INNER JOIN paciente p ON e.identificacion = p.identificacion";
if ($procedimientos_exists) $count_sql .= " LEFT JOIN procedimientos pr ON e.codexamen = pr.codigo";
$count_sql .= " WHERE $where_sql";
$count_stmt = $mysqli->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_registros = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_registros / $per_page));
if ($page > $total_pages) $page = $total_pages;

// Fetch data - JOIN condicional con procedimientos
$sql = "SELECT e.identificacion, e.fecha, e.entidad, e.codexamen, e.realizado,
        p.nombres, p.apellidos, p.edad, p.genero, p.telefono, p.correo";
if ($procedimientos_exists) {
    $sql .= ", pr.tabla as examen_tabla, pr.nombre as examen_nombre_proc";
}
$sql .= " FROM examenes e
        INNER JOIN paciente p ON e.identificacion = p.identificacion";
if ($procedimientos_exists) {
    $sql .= " LEFT JOIN procedimientos pr ON e.codexamen = pr.codigo";
}
$sql .= " WHERE $where_sql
        ORDER BY e.fecha DESC, p.apellidos ASC, p.nombres ASC
        LIMIT $per_page OFFSET $offset";
$stmt = $mysqli->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$resultados = $stmt->get_result();

// Funcion para extraer resultado de tabla dinamica
function extraerResultadoDinamico($mysqli, $tabla, $identificacion, $codexamen, $fecha) {
    if (empty($tabla)) return 'Sin tabla';
    $tabla_safe = $mysqli->real_escape_string($tabla);
    $tabla_existe = $mysqli->query("SHOW TABLES LIKE '$tabla_safe'")->num_rows > 0;
    if (!$tabla_existe) return 'Tabla no encontrada';

    $estructura = $mysqli->query("DESCRIBE `$tabla_safe`");
    $columnas = [];
    while ($col = $estructura->fetch_assoc()) $columnas[] = $col['Field'];
    $cols_lower = array_map('strtolower', $columnas);

    $where_clauses = [];
    $where_params = [];
    $where_types = "";

    // Buscar columna de identificacion con varios nombres posibles
    foreach (['identificacion','nrodoc','cedula','documento','id_paciente','idpaciente'] as $alias) {
        $idx = array_search($alias, $cols_lower);
        if ($idx !== false) { $where_clauses[] = "`{$columnas[$idx]}` = ?"; $where_params[] = $identificacion; $where_types .= "s"; break; }
    }
    // Buscar columna de examen
    foreach (['codexamen','examen','codigo','cod_examen','idexamen'] as $alias) {
        $idx = array_search($alias, $cols_lower);
        if ($idx !== false) { $where_clauses[] = "`{$columnas[$idx]}` = ?"; $where_params[] = $codexamen; $where_types .= "s"; break; }
    }
    // Buscar columna de fecha
    foreach (['fecha','fecharegistro','fecha_registro','fechahora'] as $alias) {
        $idx = array_search($alias, $cols_lower);
        if ($idx !== false) { $where_clauses[] = "`{$columnas[$idx]}` = ?"; $where_params[] = $fecha; $where_types .= "s"; break; }
    }

    // Si no hay WHERE, intentar sin WHERE con LIMIT
    if (empty($where_clauses)) {
        $sql_res = "SELECT * FROM `$tabla_safe` ORDER BY ind DESC LIMIT 1";
        try {
            $stmt_res = $mysqli->prepare($sql_res);
            $stmt_res->execute();
            $res_res = $stmt_res->get_result();
            if ($res_res->num_rows === 0) return 'Pendiente';
            $datos = $res_res->fetch_assoc();
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    } else {
        $where_sql = implode(" AND ", $where_clauses);
        $sql_res = "SELECT * FROM `$tabla_safe` WHERE $where_sql LIMIT 1";
        try {
            $stmt_res = $mysqli->prepare($sql_res);
            $stmt_res->bind_param($where_types, ...$where_params);
            $stmt_res->execute();
            $res_res = $stmt_res->get_result();
            if ($res_res->num_rows === 0) return 'Pendiente';
            $datos = $res_res->fetch_assoc();
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    // Columnas que nunca se muestran como resultado
    $excluir = array_map('strtolower', ['ind','identificacion','nrodoc','cedula','documento','codexamen','examen','codigo','fecha','hora','id','bacteriologo','observaciones','fechahora','fechaResultados','id_paciente','idpaciente','cod_examen','idexamen']);

    // Buscar campo de valor directo
    $valor = '';
    foreach (['resultado','valor','result','value','valoracion','dato'] as $campo) {
        if (isset($datos[$campo]) && !empty($datos[$campo]) && $datos[$campo] !== 'N/A') { $valor = $datos[$campo]; break; }
    }

    // Si no encontro campo directo, concatenar todos los no excluidos
    if (empty($valor)) {
        foreach ($datos as $columna => $v) {
            if (!in_array(strtolower($columna), $excluir) && !empty($v) && $v !== '0000-00-00' && $v !== '0' && $v !== 'N/A') {
                $valor .= ($valor ? ', ' : '') . $columna . ': ' . $v;
            }
        }
    }

    return $valor ?: 'Sin valor';
}

// Entidades for filter
$res_entidades = $mysqli->query("SELECT id, nombre FROM entidades ORDER BY nombre ASC");
$entidades = [];
if ($res_entidades) {
    while ($row = $res_entidades->fetch_assoc()) $entidades[] = $row;
}

// Stats
$stats_total = $mysqli->query("SELECT COUNT(*) as t FROM examenes")->fetch_assoc()['t'];
$stats_realizados = $mysqli->query("SELECT COUNT(*) as t FROM examenes WHERE realizado = 'S'")->fetch_assoc()['t'];
$stats_pendientes = $stats_total - $stats_realizados;
$stats_hoy = $mysqli->query("SELECT COUNT(*) as t FROM examenes WHERE fecha = CURDATE()")->fetch_assoc()['t'];

// Chart data: exams by entity (top 8)
$res_entity = $mysqli->query("SELECT e.entidad, COUNT(*) as total FROM examenes e WHERE e.entidad IS NOT NULL AND TRIM(e.entidad) != '' GROUP BY e.entidad ORDER BY total DESC LIMIT 8");
$chart_entidad_labels = []; $chart_entidad_data = [];
if ($res_entity) { while ($r = $res_entity->fetch_assoc()) { $chart_entidad_labels[] = $r['entidad']; $chart_entidad_data[] = (int)$r['total']; } }

// Chart data: exams by type/table
$chart_tipo_labels = []; $chart_tipo_data = [];
if ($procedimientos_exists) {
    $res_tipo = $mysqli->query("SELECT pr.tabla, COUNT(*) as total FROM examenes e INNER JOIN procedimientos pr ON e.codexamen = pr.codigo WHERE pr.tabla IS NOT NULL GROUP BY pr.tabla ORDER BY total DESC");
    if ($res_tipo) { while ($r = $res_tipo->fetch_assoc()) { $label = $tabla_labels[$r['tabla']] ?? $r['tabla']; $chart_tipo_labels[] = $label; $chart_tipo_data[] = (int)$r['total']; } }
}

// Chart data: gender distribution
$res_gen = $mysqli->query("SELECT p.genero, COUNT(*) as total FROM examenes e INNER JOIN paciente p ON e.identificacion = p.identificacion GROUP BY p.genero");
$chart_gen_labels = []; $chart_gen_data = [];
if ($res_gen) { while ($r = $res_gen->fetch_assoc()) { $chart_gen_labels[] = $r['genero'] === 'F' ? 'Femenino' : ($r['genero'] === 'M' ? 'Masculino' : 'Otro'); $chart_gen_data[] = (int)$r['total']; } }

// Chart data: daily trend (last 14 days)
$res_trend = $mysqli->query("SELECT e.fecha, COUNT(*) as total FROM examenes e WHERE e.fecha >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY e.fecha ORDER BY e.fecha ASC");
$chart_trend_labels = []; $chart_trend_data = [];
if ($res_trend) { while ($r = $res_trend->fetch_assoc()) { $chart_trend_labels[] = date('d/m', strtotime($r['fecha'])); $chart_trend_data[] = (int)$r['total']; } }

// Chart data: realized vs pending by entity (top 5)
$res_rp = $mysqli->query("SELECT e.entidad, SUM(CASE WHEN e.realizado='S' THEN 1 ELSE 0 END) as realizados, SUM(CASE WHEN e.realizado!='S' THEN 1 ELSE 0 END) as pendientes FROM examenes e WHERE e.entidad IS NOT NULL AND TRIM(e.entidad) != '' GROUP BY e.entidad ORDER BY COUNT(*) DESC LIMIT 5");
$chart_rp_labels = []; $chart_rp_realizados = []; $chart_rp_pendientes = [];
if ($res_rp) { while ($r = $res_rp->fetch_assoc()) { $chart_rp_labels[] = $r['entidad']; $chart_rp_realizados[] = (int)$r['realizados']; $chart_rp_pendientes[] = (int)$r['pendientes']; } }

$script_actual = basename($_SERVER['PHP_SELF']);

// --- HEALTH STATISTICS ---
$health_data = [
    'anemia' => 0, 'infecciones' => 0, 'trombocitopenia' => 0, 'trombocitosis' => 0,
    'diabetes_indicio' => 0, 'infeccion_urinaria' => 0, 'problemas_renales' => 0,
    'bilirrubina_alterada' => 0, 'colesterol_alto' => 0, 'ldl_alto' => 0,
    'hdl_bajo' => 0, 'trigliceridos_altos' => 0, 'coagulacion' => 0
];
$health_by_gender = ['F' => 0, 'M' => 0];
$health_by_age = ['0-17' => 0, '18-35' => 0, '36-55' => 0, '56-75' => 0, '75+' => 0];
$health_by_table = [];

function clasificarEdad($edad) {
    $edad = (int)round($edad);
    if ($edad < 18) return '0-17';
    if ($edad <= 35) return '18-35';
    if ($edad <= 55) return '36-55';
    if ($edad <= 75) return '56-75';
    return '75+';
}

function registrarCondicion(&$hd, &$hg, &$ha, &$ht, $cond, $gen, $edad, $tabla) {
    $hd[$cond]++;
    if (isset($hg[$gen])) $hg[$gen]++;
    $ha[clasificarEdad($edad)]++;
    $ht[$tabla] = ($ht[$tabla] ?? 0) + 1;
}

if ($mysqli->query("SHOW TABLES LIKE 'hemogramaRayto'")->num_rows > 0) {
    $res_h = $mysqli->query("SELECT h.*, p.genero, p.edad FROM hemogramaRayto h INNER JOIN paciente p ON h.identificacion = p.identificacion");
    if ($res_h) while ($rh = $res_h->fetch_assoc()) {
        $WBC = floatval(str_replace(',','.', $rh['WBC'] ?? '0'));
        $PLT = floatval(str_replace(',','.', $rh['PLT'] ?? '0'));
        $HGB = floatval(str_replace(',','.', $rh['HGB'] ?? $rh['hemoglobina'] ?? '0'));
        $gen = $rh['genero'] ?? ''; $edad = $rh['edad'] ?? 0;
        if ($HGB > 0 && (($gen === 'F' && $HGB < 12) || ($gen === 'M' && $HGB < 13) || ($gen !== 'F' && $gen !== 'M' && $HGB < 12)))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'anemia', $gen, $edad, 'Hemograma');
        if ($WBC > 0 && ($WBC > 11000 || $WBC < 4000))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'infecciones', $gen, $edad, 'Hemograma');
        if ($PLT > 0 && $PLT < 150000)
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'trombocitopenia', $gen, $edad, 'Hemograma');
        if ($PLT > 450000)
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'trombocitosis', $gen, $edad, 'Hemograma');
    }
}

if ($mysqli->query("SHOW TABLES LIKE 'examen_tipo_5'")->num_rows > 0) {
    $res_h5 = $mysqli->query("SELECT t.*, p.genero, p.edad FROM examen_tipo_5 t INNER JOIN paciente p ON t.identificacion = p.identificacion");
    if ($res_h5) while ($rh = $res_h5->fetch_assoc()) {
        $WBC = floatval(str_replace(',','.', $rh['WBC'] ?? $rh['leucocitos'] ?? '0'));
        $PLT = floatval(str_replace(',','.', $rh['PLT'] ?? '0'));
        $HGB = floatval(str_replace(',','.', $rh['hemoglobina'] ?? '0'));
        $gen = $rh['genero'] ?? ''; $edad = $rh['edad'] ?? 0;
        if ($HGB > 0 && (($gen === 'F' && $HGB < 12) || ($gen === 'M' && $HGB < 13) || ($gen !== 'F' && $gen !== 'M' && $HGB < 12)))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'anemia', $gen, $edad, 'Hemograma');
        if ($WBC > 0 && ($WBC > 11000 || $WBC < 4000))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'infecciones', $gen, $edad, 'Hemograma');
        if ($PLT > 0 && $PLT < 150000)
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'trombocitopenia', $gen, $edad, 'Hemograma');
        if ($PLT > 450000)
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'trombocitosis', $gen, $edad, 'Hemograma');
    }
}

if ($mysqli->query("SHOW TABLES LIKE 'examen_tipo_3'")->num_rows > 0) {
    $res_u = $mysqli->query("SELECT t.*, p.genero, p.edad FROM examen_tipo_3 t INNER JOIN paciente p ON t.identificacion = p.identificacion");
    if ($res_u) while ($ru = $res_u->fetch_assoc()) {
        $gen = $ru['genero'] ?? ''; $edad = $ru['edad'] ?? 0;
        $glucosa = strtolower(trim($ru['glucosa'] ?? ''));
        $nitritos = strtolower(trim($ru['nitritos'] ?? ''));
        $leucocitos = strtolower(trim($ru['leucocitos'] ?? ''));
        $proteinas = strtolower(trim($ru['proteinas'] ?? ''));
        $bilirrubina = strtolower(trim($ru['bilirrubina'] ?? ''));
        if (!empty($glucosa) && !in_array($glucosa, ['n/a','negativo','neg','ausente','']))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'diabetes_indicio', $gen, $edad, 'Parcial Orina');
        if ((!empty($nitritos) && !in_array($nitritos, ['n/a','negativo','neg',''])) || (!empty($leucocitos) && !in_array($leucocitos, ['n/a','negativo','neg',''])))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'infeccion_urinaria', $gen, $edad, 'Parcial Orina');
        if (!empty($proteinas) && !in_array($proteinas, ['n/a','negativo','neg','']))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'problemas_renales', $gen, $edad, 'Parcial Orina');
        if (!empty($bilirrubina) && !in_array($bilirrubina, ['n/a','negativo','neg','']))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'bilirrubina_alterada', $gen, $edad, 'Parcial Orina');
    }
}

if ($mysqli->query("SHOW TABLES LIKE 'perfilLipidico'")->num_rows > 0) {
    $res_l = $mysqli->query("SELECT t.*, p.genero, p.edad FROM perfilLipidico t INNER JOIN paciente p ON t.identificacion = p.identificacion");
    if ($res_l) while ($rl = $res_l->fetch_assoc()) {
        $gen = $rl['genero'] ?? ''; $edad = $rl['edad'] ?? 0;
        $col_total = floatval(str_replace(',','.', $rl['colesterol_total'] ?? '0'));
        $col_hdl = floatval(str_replace(',','.', $rl['colesterol_hdl'] ?? '0'));
        $col_ldl = floatval(str_replace(',','.', $rl['colesterol_ldl'] ?? '0'));
        $trig = floatval(str_replace(',','.', $rl['trigliceridos'] ?? '0'));
        if ($col_total > 200) registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'colesterol_alto', $gen, $edad, 'Perfil Lipídico');
        if ($col_ldl > 130) registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'ldl_alto', $gen, $edad, 'Perfil Lipídico');
        if ($col_hdl > 0 && (($gen === 'M' && $col_hdl < 40) || ($gen === 'F' && $col_hdl < 50) || ($gen !== 'F' && $gen !== 'M' && $col_hdl < 40)))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'hdl_bajo', $gen, $edad, 'Perfil Lipídico');
        if ($trig > 150) registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'trigliceridos_altos', $gen, $edad, 'Perfil Lipídico');
    }
}

if ($mysqli->query("SHOW TABLES LIKE 'examen_tipo_7'")->num_rows > 0) {
    $res_c = $mysqli->query("SELECT t.*, p.genero, p.edad FROM examen_tipo_7 t INNER JOIN paciente p ON t.identificacion = p.identificacion");
    if ($res_c) while ($rc = $res_c->fetch_assoc()) {
        $gen = $rc['genero'] ?? ''; $edad = $rc['edad'] ?? 0;
        $pt = floatval(str_replace(',','.', $rc['tiempo_de_protrombina'] ?? '0'));
        if ($pt > 0 && ($pt < 11 || $pt > 13.5))
            registrarCondicion($health_data, $health_by_gender, $health_by_age, $health_by_table, 'coagulacion', $gen, $edad, 'Protrombina');
    }
}

$health_condiciones_labels = [
    'anemia'=>'Anemia','infecciones'=>'Infecciones','trombocitopenia'=>'Trombocitopenia',
    'trombocitosis'=>'Trombocitosis','diabetes_indicio'=>'Diabetes (indicio)',
    'infeccion_urinaria'=>'Infección Urinaria','problemas_renales'=>'Problemas Renales',
    'bilirrubina_alterada'=>'Bilirrubina Alta','colesterol_alto'=>'Colesterol Alto',
    'ldl_alto'=>'LDL Alto','hdl_bajo'=>'HDL Bajo','trigliceridos_altos'=>'Triglicéridos Altos',
    'coagulacion'=>'Coagulación'
];
$health_condiciones_colores = ['#ef4444','#f97316','#eab308','#a855f7','#ec4899','#06b6d4','#14b8a6','#f43f5e','#8b5cf6','#6366f1','#3b82f6','#10b981','#64748b'];
$chart_health_labels = []; $chart_health_data = []; $chart_health_colors = [];
foreach ($health_condiciones_labels as $key => $label) {
    if ($health_data[$key] > 0) {
        $chart_health_labels[] = $label;
        $chart_health_data[] = $health_data[$key];
        $chart_health_colors[] = $health_condiciones_colores[array_search($key, array_keys($health_condiciones_labels))];
    }
}
$chart_health_gen_labels = ['Femenino','Masculino'];
$chart_health_gen_data = [$health_by_gender['F'], $health_by_gender['M']];
$chart_health_age_labels = array_keys($health_by_age);
$chart_health_age_data = array_values($health_by_age);
$chart_health_table_labels = array_keys($health_by_table);
$chart_health_table_data = array_values($health_by_table);
$health_total_condiciones = array_sum($health_data);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Resultados | <?= htmlspecialchars($nombreLab) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root { --primary:#4f46e5; --primary-light:#818cf8; --primary-dark:#3730a3; --success:#059669; --warning:#d97706; --danger:#dc2626; }
        [x-cloak]{display:none!important}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
        @keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
        .animate-fade-in{animation:fadeInUp .4s cubic-bezier(0.16,1,0.3,1) both}
        .skeleton{background:linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:.5rem}
        .glass{background:rgba(255,255,255,.7);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
        .card-hover{transition:all .25s cubic-bezier(0.16,1,0.3,1)}
        .card-hover:hover{transform:translateY(-2px);box-shadow:0 12px 40px -12px rgba(0,0,0,.12)}
        .select-modern{-webkit-appearance:none;-moz-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");background-position:right .6rem center;background-repeat:no-repeat;background-size:1.3em 1.3em;padding-right:2.2rem}
        .btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));transition:all .2s}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px -4px rgba(79,70,229,.4)}
        .thiings-icon{image-rendering:-webkit-optimize-contrast;image-rendering:crisp-edges}
        .table-row:hover{background:#f8fafc}
        .pagination-btn{transition:all .15s}
        .pagination-btn:hover:not(:disabled){background:#4f46e5;color:#fff;transform:translateY(-1px)}
        .pagination-btn.active{background:#4f46e5;color:#fff;box-shadow:0 4px 12px -2px rgba(79,70,229,.3)}
    </style>
</head>
<body class="bg-slate-50 min-h-screen" x-data="adminApp()">

    <!-- HEADER -->
    <header class="glass border-b border-slate-200/60 px-4 py-3 sticky top-0 z-30">
        <div class="max-w-[1600px] mx-auto flex justify-between items-center gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <?php if (!empty($urlLogo) && $urlLogo !== 'data:image/png;base64,'): ?>
                    <img src="<?= $urlLogo ?>" alt="Logo" class="h-9 w-9 object-contain rounded-lg flex-shrink-0">
                <?php else: ?>
                    <img src="icons/thiings/shield.png" alt="Admin" class="h-9 w-9 object-contain rounded-lg flex-shrink-0 thiings-icon">
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="font-bold text-sm text-slate-800 truncate leading-tight">Panel Administrativo</div>
                    <div class="text-[11px] text-slate-400 leading-tight"><?= htmlspecialchars($nombreLab) ?></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="prt.php"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <i class="bi bi-arrow-left text-sm"></i> <span class="hidden sm:inline">RESULTADOS</span>
                </a>
                <div class="hidden md:flex items-center gap-1.5 bg-slate-100 px-2.5 py-1 rounded-lg text-[11px] text-slate-600">
                    <i class="bi bi-person-fill text-slate-400"></i>
                    <span class="font-medium truncate max-w-[140px]"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
                </div>
                <a href="?logout=1"
                    class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
                    title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right text-sm"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-[1600px] mx-auto p-4 sm:p-6 space-y-6">

        <!-- STATS CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-fade-in">
            <div class="bg-white rounded-2xl p-5 border border-slate-200/60 card-hover">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-clipboard-data text-indigo-500 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold text-slate-800"><?= number_format($stats_total) ?></div>
                        <div class="text-[11px] text-slate-400 uppercase font-medium">Total Exámenes</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-slate-200/60 card-hover">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-emerald-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-check-circle-fill text-emerald-500 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold text-emerald-600"><?= number_format($stats_realizados) ?></div>
                        <div class="text-[11px] text-slate-400 uppercase font-medium">Realizados</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-slate-200/60 card-hover">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-clock-fill text-amber-500 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold text-amber-600"><?= number_format($stats_pendientes) ?></div>
                        <div class="text-[11px] text-slate-400 uppercase font-medium">Pendientes</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 border border-slate-200/60 card-hover">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-calendar-check text-blue-500 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-extrabold text-blue-600"><?= number_format($stats_hoy) ?></div>
                        <div class="text-[11px] text-slate-400 uppercase font-medium">Hoy</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BUTTON: Gráficas -->
        <div class="animate-fade-in" style="animation-delay:.15s">
            <button onclick="document.getElementById('chartsModal').classList.remove('hidden')" class="bg-white rounded-2xl border border-slate-200/60 px-5 py-3 w-full flex items-center gap-3 hover:border-indigo-200 hover:shadow-sm transition-all text-left">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-bar-chart-line-fill text-white text-lg"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-slate-700">Gráficas y Estadísticas</div>
                    <div class="text-[11px] text-slate-400">Tendencia, distribución por entidad, género, tipo de examen y más</div>
                </div>
                <i class="bi bi-chevron-right text-slate-300 ml-auto"></i>
            </button>
        </div>

        <!-- BUTTON: Estadísticas de Salud -->
        <div class="animate-fade-in" style="animation-delay:.2s">
            <button onclick="document.getElementById('healthModal').classList.remove('hidden'); setTimeout(renderHealthCharts, 100)" class="bg-white rounded-2xl border border-slate-200/60 px-5 py-3 w-full flex items-center gap-3 hover:border-rose-200 hover:shadow-sm transition-all text-left">
                <div class="w-10 h-10 bg-gradient-to-br from-rose-500 to-red-600 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-heart-pulse-fill text-white text-lg"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-slate-700">Estadísticas de Salud</div>
                    <div class="text-[11px] text-slate-400">Condiciones clínicas, enfermedades detectadas y problemáticas</div>
                </div>
                <?php if ($health_total_condiciones > 0): ?>
                    <span class="bg-rose-100 text-rose-700 text-[10px] font-bold px-2 py-0.5 rounded-full flex-shrink-0"><?= number_format($health_total_condiciones) ?> alertas</span>
                <?php endif; ?>
                <i class="bi bi-chevron-right text-slate-300 ml-auto"></i>
            </button>
        </div>

        <!-- FILTERS -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/60 animate-fade-in" style="animation-delay:.1s">
            <form action="<?= $script_actual ?>" method="GET" id="filterForm">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <i class="bi bi-funnel text-indigo-500 text-sm"></i>
                    </div>
                    <h2 class="text-sm font-bold text-slate-700">Filtros de Búsqueda</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($filter_fecha_inicio) ?>"
                            class="w-full px-3 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Fecha Fin</label>
                        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($filter_fecha_fin) ?>"
                            class="w-full px-3 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Entidad</label>
                        <select name="entidad" class="select-modern w-full px-3 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm font-medium">
                            <option value="">Todas</option>
                            <?php foreach ($entidades as $ent): ?>
                                <option value="<?= htmlspecialchars($ent['nombre']) ?>" <?= $filter_entidad === $ent['nombre'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ent['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Estado</label>
                        <select name="estado" class="select-modern w-full px-3 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm font-medium">
                            <option value="">Todos</option>
                            <option value="realizado" <?= $filter_estado === 'realizado' ? 'selected' : '' ?>>Realizados</option>
                            <option value="pendiente" <?= $filter_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Buscar</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="bi bi-search text-slate-400 text-sm"></i></span>
                            <input type="text" name="buscar" value="<?= htmlspecialchars($filter_busqueda) ?>" placeholder="ID, nombre, email..."
                                class="w-full pl-9 pr-3 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm">
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-4">
                    <button type="submit" class="btn-primary text-white px-5 py-2.5 rounded-xl text-xs font-bold flex items-center gap-1.5">
                        <i class="bi bi-search"></i> FILTRAR
                    </button>
                    <a href="<?= $script_actual ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 rounded-xl text-xs font-bold transition">
                        LIMPIAR
                    </a>
                    <div class="flex-1"></div>
                    <button type="button" onclick="exportarAdminExcel()" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                        <i class="bi bi-file-earmark-excel"></i> EXPORTAR
                    </button>
                </div>
            </form>
        </div>

        <!-- TABLE TABS -->
        <?php if (!empty($tablas_disponibles)): ?>
        <div class="bg-white rounded-2xl border border-slate-200/60 px-4 py-2.5 animate-fade-in overflow-x-auto" style="animation-delay:.15s">
            <div class="flex items-center gap-1 min-w-max">
                <a href="<?= $script_actual . '?' . http_build_query(array_merge($_GET, ['tabla' => ''])) ?>"
                   class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition whitespace-nowrap <?= empty($filter_tabla) ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' ?>">
                    Todos
                </a>
                <?php foreach ($tablas_disponibles as $tabla_key => $tabla_nombre):
                    $label = $tabla_labels[$tabla_key] ?? $tabla_nombre;
                    $count_sql_t = "SELECT COUNT(*) as t FROM examenes e INNER JOIN procedimientos pr ON e.codexamen = pr.codigo WHERE pr.tabla = ?";
                    $count_stmt_t = $mysqli->prepare($count_sql_t);
                    $count_stmt_t->bind_param("s", $tabla_key);
                    $count_stmt_t->execute();
                    $count_t = $count_stmt_t->get_result()->fetch_assoc()['t'];
                ?>
                    <a href="<?= $script_actual . '?' . http_build_query(array_merge($_GET, ['tabla' => $tabla_key])) ?>"
                       class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition whitespace-nowrap <?= $filter_tabla === $tabla_key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' ?>">
                        <?= htmlspecialchars($label) ?>
                        <span class="ml-1 text-[9px] opacity-70"><?= number_format($count_t) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- RESULTS CARDS -->
        <div class="space-y-2 animate-fade-in" style="animation-delay:.2s">
            <!-- Header -->
            <div class="bg-white rounded-2xl border border-slate-200/60 px-5 py-3 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <img src="icons/thiings/medical-report.png" alt="" class="w-6 h-6 object-contain thiings-icon">
                    <h2 class="text-sm font-bold text-slate-700">Resultados</h2>
                    <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-0.5 rounded-full">
                        <?= number_format($total_registros) ?>
                    </span>
                </div>
                <div class="text-[11px] text-slate-400">
                    Página <?= $page ?> de <?= $total_pages ?>
                </div>
            </div>

            <?php if ($resultados->num_rows > 0): ?>
                <?php while ($row = $resultados->fetch_assoc()):
                    $nom = htmlspecialchars(trim(($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? '')));
                    $edad = round($row['edad'] ?? 0);
                    $gen = $row['genero'] ?? '';
                    $gen_icon = $gen === 'F' ? 'bi-gender-female text-pink-400' : ($gen === 'M' ? 'bi-gender-male text-blue-400' : 'bi-gender-ambiguous text-slate-400');
                    $gen_class = $gen === 'F' ? 'bg-pink-50 text-pink-600' : ($gen === 'M' ? 'bg-blue-50 text-blue-600' : 'bg-slate-50 text-slate-500');
                    $realizado = $row['realizado'] === 'S';
                    $estado_class = $realizado ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                    $estado_text = $realizado ? 'Realizado' : 'Pendiente';
                    $proc = $examenes_map[$row['codexamen']] ?? null;
                    $examen_nombre = $proc ? htmlspecialchars($proc['nombre'] ?? $proc['abreviatura'] ?? $row['codexamen']) : htmlspecialchars($row['codexamen']);
                    $examen_tipo = $proc ? htmlspecialchars($proc['tipo'] ?? '') : '';
                    $tabla_res = $row['examen_tabla'] ?? ($proc['tabla'] ?? '');
                    $resultado_valor = '';
                    if ($realizado && !empty($tabla_res)) {
                        $resultado_valor = extraerResultadoDinamico($mysqli, $tabla_res, $row['identificacion'], $row['codexamen'], $row['fecha']);
                    } elseif (!$realizado) {
                        $resultado_valor = '—';
                    } else {
                        $resultado_valor = 'Sin resultado';
                    }
                    $resultado_corto = mb_strlen($resultado_valor) > 80 ? mb_substr($resultado_valor, 0, 80) . '...' : $resultado_valor;
                ?>
                    <div class="bg-white rounded-xl border border-slate-200/60 px-4 py-3 hover:border-indigo-200 hover:shadow-sm transition-all"
                         data-card
                         data-id="<?= htmlspecialchars($row['identificacion']) ?>"
                         data-nom="<?= $nom ?>"
                         data-edad="<?= $edad ?>"
                         data-gen="<?= $gen ?>"
                         data-fecha="<?= date('d/m/Y', strtotime($row['fecha'])) ?>"
                         data-entidad="<?= htmlspecialchars($row['entidad'] ?? '') ?>"
                         data-examen="<?= $examen_nombre ?>"
                         data-resultado="<?= htmlspecialchars($resultado_valor) ?>"
                         data-estado="<?= $estado_text ?>">
                        <!-- Row 1: Name + Status + Action -->
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="font-mono text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded shrink-0">
                                    <?= htmlspecialchars($row['identificacion']) ?>
                                </span>
                                <span class="text-sm font-bold text-slate-800 truncate"><?= $nom ?></span>
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold <?= $gen_class ?>">
                                    <i class="bi <?= $gen_icon ?>"></i> <?= $gen ?>
                                </span>
                                <span class="text-[10px] text-slate-400 shrink-0"><?= $edad ?>a</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold border <?= $estado_class ?>">
                                    <i class="bi <?= $realizado ? 'bi-check-circle-fill' : 'bi-clock-fill' ?> mr-0.5"></i>
                                    <?= $estado_text ?>
                                </span>
                                <button onclick="verDetalle(this)"
                                    data-id="<?= htmlspecialchars($row['identificacion']) ?>"
                                    data-nom="<?= $nom ?>"
                                    data-fecha="<?= $row['fecha'] ?>"
                                    data-entidad="<?= htmlspecialchars($row['entidad'] ?? '') ?>"
                                    data-examen="<?= $examen_nombre ?>"
                                    data-codexamen="<?= htmlspecialchars($row['codexamen']) ?>"
                                    data-realizado="<?= $realizado ? 'S' : 'N' ?>"
                                    class="p-1.5 rounded-lg bg-indigo-50 text-indigo-500 hover:bg-indigo-100 transition"
                                    title="Ver detalle">
                                    <i class="bi bi-eye-fill text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <!-- Row 2: Details -->
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px]">
                            <span class="text-slate-500"><i class="bi bi-calendar3 mr-1"></i><?= date('d/m/Y', strtotime($row['fecha'])) ?></span>
                            <span class="text-slate-500 truncate max-w-[180px]" title="<?= htmlspecialchars($row['entidad'] ?? '') ?>"><i class="bi bi-building mr-1"></i><?= htmlspecialchars($row['entidad'] ?? '—') ?></span>
                            <span class="text-slate-700 font-medium truncate max-w-[200px]" title="<?= $examen_nombre ?>"><i class="bi bi-flask mr-1"></i><?= $examen_nombre ?></span>
                        </div>
                        <!-- Row 3: Result -->
                        <?php if ($resultado_valor !== '—' && $resultado_valor !== 'Sin resultado'): ?>
                            <div class="mt-2 bg-slate-50 rounded-lg px-3 py-1.5 text-[11px] text-slate-600 break-words" title="<?= htmlspecialchars($resultado_valor) ?>">
                                <i class="bi bi-clipboard2-pulse mr-1 text-indigo-400"></i><?= htmlspecialchars($resultado_valor) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-slate-200/60 px-5 py-12 text-center">
                    <img src="icons/thiings/patient.png" alt="" class="w-16 h-16 object-contain mx-auto mb-3 thiings-icon opacity-60">
                    <div class="text-sm font-bold text-slate-600 mb-1">No se encontraron resultados</div>
                    <div class="text-xs text-slate-400">Ajuste los filtros de búsqueda</div>
                </div>
            <?php endif; ?>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3">
                    <div class="text-[11px] text-slate-400">
                        Mostrando <?= $offset + 1 ?>-<?= min($offset + $per_page, $total_registros) ?> de <?= number_format($total_registros) ?>
                    </div>
                    <div class="flex items-center gap-1" id="pagination">
                        <?php
                        $buildUrl = function($p) use ($script_actual, $filter_fecha_inicio, $filter_fecha_fin, $filter_entidad, $filter_busqueda, $filter_estado, $filter_tabla) {
                            $params = ['page' => $p];
                            if ($filter_fecha_inicio) $params['fecha_inicio'] = $filter_fecha_inicio;
                            if ($filter_fecha_fin) $params['fecha_fin'] = $filter_fecha_fin;
                            if ($filter_entidad) $params['entidad'] = $filter_entidad;
                            if ($filter_busqueda) $params['buscar'] = $filter_busqueda;
                            if ($filter_estado) $params['estado'] = $filter_estado;
                            if ($filter_tabla) $params['tabla'] = $filter_tabla;
                            return $script_actual . '?' . http_build_query($params);
                        };
                        ?>
                        <a href="<?= $buildUrl(max(1, $page - 1)) ?>"
                            class="pagination-btn w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 text-xs font-bold <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1): ?>
                            <a href="<?= $buildUrl(1) ?>" class="pagination-btn w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 text-xs font-bold">1</a>
                            <?php if ($start > 2): ?><span class="text-slate-300 text-xs px-1">...</span><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="<?= $buildUrl($i) ?>"
                                class="pagination-btn w-9 h-9 flex items-center justify-center rounded-xl border text-xs font-bold <?= $i === $page ? 'active border-indigo-500' : 'border-slate-200 text-slate-500' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><span class="text-slate-300 text-xs px-1">...</span><?php endif; ?>
                            <a href="<?= $buildUrl($total_pages) ?>" class="pagination-btn w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 text-xs font-bold"><?= $total_pages ?></a>
                        <?php endif; ?>
                        <a href="<?= $buildUrl(min($total_pages, $page + 1)) ?>"
                            class="pagination-btn w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 text-xs font-bold <?= $page >= $total_pages ? 'opacity-40 pointer-events-none' : '' ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CHARTS MODAL -->
    <div id="chartsModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex items-start justify-center p-4 pt-10 z-50 overflow-y-auto" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden mb-10">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-5 flex justify-between items-center sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <i class="bi bi-bar-chart-line-fill text-2xl"></i>
                    <div>
                        <h3 class="font-bold text-lg">Gráficas y Estadísticas</h3>
                        <p class="text-indigo-200 text-xs">Resumen visual de resultados</p>
                    </div>
                </div>
                <button onclick="document.getElementById('chartsModal').classList.add('hidden')" class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <!-- Stats cards mini -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-indigo-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-extrabold text-indigo-600"><?= number_format($stats_total) ?></div>
                        <div class="text-[10px] text-indigo-400 uppercase font-bold">Total</div>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-extrabold text-emerald-600"><?= number_format($stats_realizados) ?></div>
                        <div class="text-[10px] text-emerald-400 uppercase font-bold">Realizados</div>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-extrabold text-amber-600"><?= number_format($stats_pendientes) ?></div>
                        <div class="text-[10px] text-amber-400 uppercase font-bold">Pendientes</div>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-extrabold text-blue-600"><?= number_format($stats_hoy) ?></div>
                        <div class="text-[10px] text-blue-400 uppercase font-bold">Hoy</div>
                    </div>
                </div>

                <!-- Charts grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-graph-up text-blue-500 mr-1"></i>Tendencia Diaria</h4>
                        <div style="height:150px"><canvas id="chartTrend"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-bar-chart-fill text-emerald-500 mr-1"></i>Estado por Entidad</h4>
                        <div style="height:150px"><canvas id="chartEstado"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-pie-chart-fill text-purple-500 mr-1"></i>Exámenes por Tipo</h4>
                        <div style="height:150px"><canvas id="chartTipo"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-buildings text-amber-500 mr-1"></i>Distribución por Entidad</h4>
                        <div style="height:150px"><canvas id="chartEntidad"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4 flex flex-col items-center">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-gender-ambiguous text-pink-500 mr-1"></i>Género</h4>
                        <div style="height:130px;width:180px"><canvas id="chartGenero"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4 flex flex-col items-center">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-clipboard2-pulse text-indigo-500 mr-1"></i>Resumen</h4>
                        <div style="height:130px;width:180px"><canvas id="chartResumen"></canvas></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HEALTH STATISTICS MODAL -->
    <div id="healthModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex items-start justify-center p-4 pt-10 z-50 overflow-y-auto" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden mb-10">
            <div class="bg-gradient-to-r from-rose-600 to-red-600 text-white p-5 flex justify-between items-center sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <i class="bi bi-heart-pulse-fill text-2xl"></i>
                    <div>
                        <h3 class="font-bold text-lg">Estadísticas de Salud</h3>
                        <p class="text-rose-200 text-xs">Condiciones clínicas detectadas en resultados de laboratorio</p>
                    </div>
                </div>
                <button onclick="document.getElementById('healthModal').classList.add('hidden')" class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <!-- Summary cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-rose-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-extrabold text-rose-600"><?= number_format($health_total_condiciones) ?></div>
                        <div class="text-[10px] text-rose-400 uppercase font-bold">Total Alertas</div>
                    </div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-extrabold text-amber-600"><?= count(array_filter($health_data)) ?></div>
                        <div class="text-[10px] text-amber-400 uppercase font-bold">Condiciones</div>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <div class="text-xl font-extrabold text-blue-600"><?= ($health_by_gender['F'] + $health_by_gender['M']) ?></div>
                        <div class="text-[10px] text-blue-400 uppercase font-bold">Pacientes Afectados</div>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-3 text-center">
                        <?php
                        $max_cond = ''; $max_val = 0;
                        foreach ($health_data as $k => $v) { if ($v > $max_val) { $max_val = $v; $max_cond = $health_condiciones_labels[$k]; } }
                        ?>
                        <div class="text-lg font-extrabold text-emerald-600 truncate"><?= htmlspecialchars($max_cond ?: 'N/A') ?></div>
                        <div class="text-[10px] text-emerald-400 uppercase font-bold">Más Frecuente</div>
                    </div>
                </div>

                <?php if ($health_total_condiciones > 0): ?>
                <!-- Charts grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-pie-chart-fill text-rose-500 mr-1"></i>Condiciones por Tipo</h4>
                        <div style="height:200px"><canvas id="chartHealthTipo"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-bar-chart-fill text-blue-500 mr-1"></i>Top Condiciones</h4>
                        <div style="height:200px"><canvas id="chartHealthBar"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-gender-ambiguous text-pink-500 mr-1"></i>Por Género</h4>
                        <div style="height:160px;width:200px;margin:0 auto"><canvas id="chartHealthGenero"></canvas></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <h4 class="text-xs font-bold text-slate-600 mb-2"><i class="bi bi-bar-chart-steps text-amber-500 mr-1"></i>Por Rango de Edad</h4>
                        <div style="height:160px"><canvas id="chartHealthEdad"></canvas></div>
                    </div>
                </div>

                <!-- Detail table -->
                <div class="bg-white rounded-xl border border-slate-200/60 overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200/60">
                        <h4 class="text-xs font-bold text-slate-600"><i class="bi bi-list-ul text-rose-500 mr-1"></i>Detalle de Condiciones Detectadas</h4>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($health_condiciones_labels as $key => $label):
                            if ($health_data[$key] > 0):
                                $pct = round(($health_data[$key] / max($health_total_condiciones, 1)) * 100, 1);
                                $color = $health_condiciones_colores[array_search($key, array_keys($health_condiciones_labels))];
                        ?>
                        <div class="px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50 transition">
                            <div class="w-2 h-8 rounded-full flex-shrink-0" style="background:<?= $color ?>"></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-slate-700"><?= htmlspecialchars($label) ?></div>
                                <div class="text-[10px] text-slate-400"><?= number_format($health_data[$key]) ?> casos detectados</div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-bold" style="color:<?= $color ?>"><?= $pct ?>%</div>
                                <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden mt-0.5">
                                    <div class="h-full rounded-full" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>

                <?php else: ?>
                <div class="text-center py-10">
                    <img src="icons/thiings/heart.png" alt="" class="w-20 h-20 object-contain mx-auto mb-4 thiings-icon opacity-60">
                    <h3 class="text-lg font-bold text-slate-700 mb-1">Sin condiciones detectadas</h3>
                    <p class="text-slate-500 text-sm">No se encontraron valores anormales en los resultados analizados</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- DETAIL MODAL -->
    <div x-show="showModal" x-cloak
        class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4 z-50"
        @click.self="showModal = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden"
            @click.stop
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0">
            <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white p-5">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <img src="icons/thiings/medical-report.png" alt="" class="w-9 h-9 object-contain thiings-icon">
                        <div>
                            <h3 class="font-bold">Detalle del Examen</h3>
                            <p class="text-indigo-200 text-xs" x-text="modalData.fecha"></p>
                        </div>
                    </div>
                    <button @click="showModal = false" class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Identificación</div>
                        <div class="text-sm font-bold text-slate-800 font-mono" x-text="modalData.id"></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Paciente</div>
                        <div class="text-sm font-bold text-slate-800" x-text="modalData.paciente"></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Fecha</div>
                        <div class="text-sm font-medium text-slate-700" x-text="modalData.fecha"></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Entidad</div>
                        <div class="text-sm font-medium text-slate-700" x-text="modalData.entidad || '—'"></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Examen</div>
                        <div class="text-sm font-medium text-slate-700" x-text="modalData.examen"></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1">Estado</div>
                        <div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold"
                                :class="modalData.realizado === 'S' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                x-text="modalData.realizado === 'S' ? 'Realizado' : 'Pendiente'"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function adminApp() {
            return {
                showModal: false,
                modalData: { id:'', paciente:'', fecha:'', entidad:'', examen:'', codexamen:'', realizado:'' },
                init() {
                    window.verDetalle = (btn) => {
                        this.modalData = {
                            id: btn.dataset.id,
                            paciente: btn.dataset.nom,
                            fecha: btn.dataset.fecha,
                            entidad: btn.dataset.entidad,
                            examen: btn.dataset.examen,
                            codexamen: btn.dataset.codexamen,
                            realizado: btn.dataset.realizado
                        };
                        this.showModal = true;
                    };
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape' && this.showModal) this.showModal = false;
                    });
                }
            };
        }

        function exportarAdminExcel() {
            const cards = document.querySelectorAll('[data-card]');
            if (!cards.length) { Swal.fire('Info','No hay datos para exportar','info'); return; }
            const rows = [];
            const headers = ['ID','Paciente','Edad','Género','Fecha','Entidad','Examen','Resultado','Estado'];
            rows.push(headers);
            cards.forEach(card => {
                rows.push([
                    card.dataset.id || '',
                    card.dataset.nom || '',
                    card.dataset.edad || '',
                    card.dataset.gen || '',
                    card.dataset.fecha || '',
                    card.dataset.entidad || '',
                    card.dataset.examen || '',
                    card.dataset.resultado || '',
                    card.dataset.estado || ''
                ]);
            });
            if (rows.length <= 1) { Swal.fire('Info','No hay datos para exportar','info'); return; }
            const ws = XLSX.utils.aoa_to_sheet(rows);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Resultados Admin');
            XLSX.writeFile(wb, `admin_resultados_${new Date().toISOString().split('T')[0]}.xlsx`);
            Swal.fire({icon:'success',title:'Exportado',text:'Archivo Excel generado',confirmButtonColor:'#4f46e5',timer:2000,showConfirmButton:false});
        }

        // Charts - rendered when modal opens
        let chartsRendered = false;
        const chartColors = ['#6366f1','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];

        function renderCharts() {
            if (chartsRendered) return;
            chartsRendered = true;

            Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
            Chart.defaults.font.size = 10;
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.pointStyleWidth = 6;

            // 1. Tendencia diaria
            new Chart(document.getElementById('chartTrend'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_trend_labels) ?>,
                    datasets: [{
                        label: 'Exámenes',
                        data: <?= json_encode($chart_trend_data) ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.1)',
                        fill: true,
                        tension: .4,
                        pointRadius: 2,
                        pointBackgroundColor: '#6366f1',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 0, font: { size: 9 } } },
                        y: { type: 'logarithmic', grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } }
                    }
                }
            });

            // 2. Estado por entidad
            new Chart(document.getElementById('chartEstado'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_rp_labels) ?>,
                    datasets: [
                        { label: 'Realizados', data: <?= json_encode($chart_rp_realizados) ?>, backgroundColor: '#10b981', borderRadius: 4 },
                        { label: 'Pendientes', data: <?= json_encode($chart_rp_pendientes) ?>, backgroundColor: '#f59e0b', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { font: { size: 9 }, boxWidth: 8 } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 45, font: { size: 8 } } },
                        y: { type: 'logarithmic', grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } }
                    }
                }
            });

            // 3. Exámenes por tipo
            new Chart(document.getElementById('chartTipo'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_tipo_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_tipo_data) ?>,
                        backgroundColor: chartColors.slice(0, <?= count($chart_tipo_labels) ?>),
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: { legend: { position: 'right', labels: { padding: 8, font: { size: 9 }, boxWidth: 8 } } }
                }
            });

            // 4. Distribución por entidad
            new Chart(document.getElementById('chartEntidad'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_entidad_labels) ?>,
                    datasets: [{
                        label: 'Exámenes',
                        data: <?= json_encode($chart_entidad_data) ?>,
                        backgroundColor: chartColors.slice(0, <?= count($chart_entidad_labels) ?>),
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { type: 'logarithmic', grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                    }
                }
            });

            // 5. Distribución por género
            new Chart(document.getElementById('chartGenero'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_gen_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_gen_data) ?>,
                        backgroundColor: ['#ec4899','#3b82f6','#94a3b8'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: { legend: { position: 'bottom', labels: { padding: 8, font: { size: 9 }, boxWidth: 8 } } }
                }
            });

            // 6. Resumen general
            new Chart(document.getElementById('chartResumen'), {
                type: 'doughnut',
                data: {
                    labels: ['Realizados','Pendientes'],
                    datasets: [{
                        data: [<?= (int)$stats_realizados ?>, <?= (int)$stats_pendientes ?>],
                        backgroundColor: ['#10b981','#f59e0b'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: { legend: { position: 'bottom', labels: { padding: 8, font: { size: 9 }, boxWidth: 8 } } }
                }
            });
        }

        // Render charts when modal opens
        document.getElementById('chartsModal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
        const origOpen = document.querySelector('[onclick*="chartsModal"]').onclick;
        document.querySelector('[onclick*="chartsModal"]').addEventListener('click', function() {
            setTimeout(renderCharts, 100);
        });

        // Health Statistics Charts
        let healthChartsRendered = false;
        const healthColors = <?= json_encode($chart_health_colors) ?>;

        function renderHealthCharts() {
            if (healthChartsRendered) return;
            if (typeof <?= json_encode($chart_health_data) ?> === 'undefined' || <?= json_encode($chart_health_data) ?>.length === 0) return;
            healthChartsRendered = true;

            // 1. Condiciones por tipo (Doughnut)
            new Chart(document.getElementById('chartHealthTipo'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_health_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_health_data) ?>,
                        backgroundColor: healthColors,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '50%',
                    plugins: { legend: { position: 'right', labels: { padding: 6, font: { size: 9 }, boxWidth: 8 } } }
                }
            });

            // 2. Top condiciones (Horizontal Bar)
            new Chart(document.getElementById('chartHealthBar'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_health_labels) ?>,
                    datasets: [{
                        label: 'Casos',
                        data: <?= json_encode($chart_health_data) ?>,
                        backgroundColor: healthColors,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                    }
                }
            });

            // 3. Por género (Doughnut)
            new Chart(document.getElementById('chartHealthGenero'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($chart_health_gen_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_health_gen_data) ?>,
                        backgroundColor: ['#ec4899', '#3b82f6'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: { legend: { position: 'bottom', labels: { padding: 8, font: { size: 9 }, boxWidth: 8 } } }
                }
            });

            // 4. Por rango de edad (Bar)
            new Chart(document.getElementById('chartHealthEdad'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_health_age_labels) ?>,
                    datasets: [{
                        label: 'Casos',
                        data: <?= json_encode($chart_health_age_data) ?>,
                        backgroundColor: ['#06b6d4','#10b981','#eab308','#f97316','#ef4444'],
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                        y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } }
                    }
                }
            });
        }
    </script>
</body>
</html>
