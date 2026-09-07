<?php
session_start();
require_once 'datos_conexion.php';
$res_conf = $mysqli->query("SELECT nombreCorto, nombreLaboratorio, urlLogoLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
$dato_conf = $res_conf ? $res_conf->fetch_assoc() : null;
$nombreLab = $dato_conf['nombreLaboratorio'] ?? 'Laboratorio Clinico';
$nombreCorto = $dato_conf['nombreCorto'] ?? 'LAB';
$urlLogo = "data:image/png;base64," . ($dato_conf['urlLogoLaboratorio'] ?? '');
$error = "";
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
        <title>Acceso al Sistema</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            @keyframes gradientShift {
                0%, 100% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
            }
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-12px); }
            }
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(30px) scale(0.96); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            .login-bg {
                background: linear-gradient(-45deg, #0f172a, #1e1b4b, #172554, #0c0a09);
                background-size: 400% 400%;
                animation: gradientShift 15s ease infinite;
            }
            .glass-card {
                background: rgba(255,255,255,0.95);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }
            .icon-float { animation: float 4s ease-in-out infinite; }
            .login-enter { animation: fadeInUp 0.7s cubic-bezier(0.16,1,0.3,1) both; }
            .login-input:focus { box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
            .login-btn { transition: all 0.2s ease; }
            .login-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 25px -5px rgba(99,102,241,0.4); }
            .login-btn:active { transform: translateY(0); }
            .pw-toggle { transition: color 0.15s; }
            .pw-toggle:hover { color: #4f46e5; }
        </style>
    </head>
    <body class="login-bg h-screen flex items-center justify-center p-4">
        <div class="glass-card p-8 sm:p-10 rounded-3xl shadow-2xl w-full max-w-md login-enter border border-white/20">
            <div class="text-center mb-8">
                <div class="mb-5 icon-float">
                    <?php if (!empty($urlLogo)): ?>
                        <img src="<?= str_starts_with(trim($urlLogo), 'data:image') ? $urlLogo : htmlspecialchars($urlLogo) ?>"
                            alt="Logo" class="w-20 h-20 mx-auto object-contain drop-shadow-lg rounded-2xl">
                    <?php else: ?>
                        <img src="icons/thiings/microscope.png" alt="Lab" class="w-20 h-20 mx-auto object-contain drop-shadow-lg rounded-2xl">
                    <?php endif; ?>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight"><?= htmlspecialchars($nombreLab) ?></h1>
                <p class="text-slate-500 text-sm mt-1">Ingrese su clave de configuración</p>
            </div>
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-xl mb-5 text-sm flex items-center gap-2 border border-red-100" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Contraseña</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400">
                            <i class="bi bi-key"></i>
                        </span>
                        <input type="password" name="password" id="loginPassword" placeholder="Ingrese contraseña" required
                            class="login-input w-full pl-11 pr-12 py-3.5 border-2 border-slate-200 rounded-2xl focus:border-indigo-500 outline-none transition text-sm">
                        <button type="button" onclick="togglePassword()" class="pw-toggle absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400">
                            <i class="bi bi-eye-slash" id="pwIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" name="login"
                    class="login-btn w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white py-3.5 rounded-2xl font-bold text-sm tracking-wide shadow-lg shadow-indigo-200">
                    INICIAR SESIÓN
                </button>
            </form>
            <p class="text-center text-[11px] text-slate-400 mt-6">Panel de Resultados Clínicos</p>
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

$script_actual = htmlspecialchars(basename($_SERVER['PHP_SELF']));
$mysqli->query("UPDATE paciente SET edad = ROUND((TO_DAYS(CURDATE()) - TO_DAYS(fecnac)) / 365.242199, 2) WHERE fecnac IS NOT NULL");
$fecha = $_GET['fecha'] ?? date("Y-m-d");
$identificacion_inicial = $_GET['identificacion'] ?? '';
$busqueda = $_GET['buscar'] ?? '';
$todos = isset($_GET['todos']) ? true : false;
$params = [];
$param_types = "";
$where_conditions = "1=1";
if (!$todos || empty($busqueda)) {
    $where_conditions .= " AND e.fecha = ?";
    $params[] = $fecha;
    $param_types .= "s";
}
if (!empty($busqueda)) {
    $busqueda_like = "%" . $busqueda . "%";
    $where_conditions .= " AND (e.identificacion LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)";
    $params = array_merge($params, [$busqueda_like, $busqueda_like, $busqueda_like]);
    $param_types .= "sss";
}
$sql = "SELECT e.identificacion, e.fecha as fecha_examen, MAX(e.entidad) as entidad,
CONCAT_WS(' ', p.apellidos, p.nombres) as nombres,
p.edad, p.correo, p.telefono, p.genero, p.fecnac
FROM examenes e
INNER JOIN paciente p ON e.identificacion = p.identificacion
WHERE $where_conditions
GROUP BY e.identificacion, e.fecha
ORDER BY e.fecha DESC, p.apellidos ASC, p.nombres ASC
LIMIT 100";
$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$resultados = $stmt->get_result();
if (!empty($urlLogo) && str_starts_with(trim($urlLogo), 'data:image')) {
} elseif (!empty($urlLogo)) {
    if (!filter_var($urlLogo, FILTER_VALIDATE_URL) && !str_starts_with($urlLogo, '/') && !str_starts_with($urlLogo, 'http')) {
        $urlLogo = "printphp/" . $urlLogo;
    }
}
$total_pacientes = $resultados->num_rows;
$hoy = date("Y-m-d");
$es_hoy = ($fecha == $hoy);
$res_entidades = $mysqli->query("SELECT id, nombre FROM entidades ORDER BY nombre ASC");
$entidades = [];
if ($res_entidades && $res_entidades->num_rows > 0) {
    while ($row = $res_entidades->fetch_assoc()) {
        $entidades[] = $row;
    }
}
if (isset($_POST['action']) && $_POST['action'] == 'actualizar_entidad') {
    $identificacion = $_POST['identificacion'] ?? '';
    $fecha_examen = $_POST['fecha_examen'] ?? '';
    $codexamen = $_POST['codexamen'] ?? '';
    if (!empty($identificacion) && !empty($fecha_examen) && !empty($codexamen)) {
        $stmt_update = $mysqli->prepare("UPDATE examenes SET entidad = ? WHERE identificacion = ? AND fecha = ? AND codexamen = ?");
        $nueva_entidad = $_POST['entidad'] ?? '';
        $stmt_update->bind_param("ssss", $nueva_entidad, $identificacion, $fecha_examen, $codexamen);
        if ($stmt_update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Entidad actualizada correctamente.', 'entidad' => $nueva_entidad]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la entidad: ' . $mysqli->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos para la actualización.']);
    }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'consulta_entidades') {
    $entidad = $_POST['entidad'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $solo_resultados = $_POST['solo_resultados'] ?? '0';
    $agrupar_fecha = $_POST['agrupar_fecha'] ?? '1';
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        echo json_encode(['success' => false, 'message' => 'Las fechas son obligatorias.']);
        exit;
    }
    $procedimientos_exists = $mysqli->query("SHOW TABLES LIKE 'procedimientos'")->num_rows > 0;
    if ($procedimientos_exists) {
        $sql = "SELECT e.identificacion, e.fecha as fecha_examen, e.entidad, e.codexamen, e.realizado,
            p.nombres, p.apellidos, p.edad, p.telefono, p.genero,
            pr.nombre as nombre_examen, pr.codigo as codigo_examen, pr.tabla as examen_tabla,
            pr.tipo as tipo_examen, pr.tipoprocedimiento as tipo_procedimiento, pr.abreviatura
            FROM examenes e
            INNER JOIN paciente p ON e.identificacion = p.identificacion
            LEFT JOIN procedimientos pr ON e.codexamen = pr.codigo
            WHERE e.fecha BETWEEN ? AND ?";
        $params = [$fecha_inicio, $fecha_fin];
        $param_types = "ss";
        if (!empty($entidad)) {
            $sql .= " AND TRIM(e.entidad) = TRIM(?)";
            $params[] = $entidad;
            $param_types .= "s";
        }
    } else {
        $sql = "SELECT e.identificacion, e.fecha as fecha_examen, e.entidad, e.codexamen, e.realizado,
            p.nombres, p.apellidos, p.edad, p.telefono, p.genero
            FROM examenes e
            INNER JOIN paciente p ON e.identificacion = p.identificacion
            WHERE e.fecha BETWEEN ? AND ?";
        $params = [$fecha_inicio, $fecha_fin];
        $param_types = "ss";
        if (!empty($entidad)) {
            $sql .= " AND TRIM(e.entidad) = TRIM(?)";
            $params[] = $entidad;
            $param_types .= "s";
        }
    }
    if ($solo_resultados == '1') {
        $sql .= " AND e.realizado = 'S'";
    }
    $sql .= " ORDER BY e.fecha DESC, p.apellidos ASC, p.nombres ASC, pr.nombre ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $mysqli->error]);
        exit;
    }
    $bind_result = $stmt->bind_param($param_types, ...$params);
    if (!$bind_result) {
        echo json_encode(['success' => false, 'message' => 'Error en parámetros: ' . $stmt->error]);
        exit;
    }
    $execute_result = $stmt->execute();
    if (!$execute_result) {
        echo json_encode(['success' => false, 'message' => 'Error ejecutando: ' . $stmt->error]);
        exit;
    }
    $result = $stmt->get_result();
    $resultados = [];
    while ($row = $result->fetch_assoc()) {
        $nombres_completos = trim(($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? ''));
        if (empty($nombres_completos)) {
            $nombres_completos = trim(($row['apellido1'] ?? '') . ' ' . ($row['apellido2'] ?? '') . ' ' . ($row['nombre1'] ?? '') . ' ' . ($row['nombre2'] ?? ''));
        }
        $examen_data = [
            'identificacion' => $row['identificacion'],
            'paciente' => $nombres_completos,
            'edad' => $row['edad'],
            'genero' => $row['genero'],
            'telefono' => $row['telefono'],
            'fecha_examen' => $row['fecha_examen'],
            'entidad' => $row['entidad'],
            'examen_codigo' => $row['codexamen'],
            'realizado' => $row['realizado']
        ];
        if ($procedimientos_exists) {
            $examen_data['examen_nombre'] = $row['nombre_examen'] ?? $row['abreviatura'] ?? 'Examen #' . $row['codexamen'];
            $examen_data['examen_tipo'] = $row['tipo_examen'] ?? 'No especificado';
            $examen_data['examen_tabla'] = $row['examen_tabla'] ?? '';
            $examen_data['tipo_procedimiento'] = $row['tipo_procedimiento'] ?? '';
            $examen_data['abreviatura'] = $row['abreviatura'] ?? '';
            if (!empty($row['examen_tabla'])) {
                $tabla_resultado = $row['examen_tabla'];
                $tabla_existe = $mysqli->query("SHOW TABLES LIKE '$tabla_resultado'")->num_rows > 0;
                if ($tabla_existe) {
                    $estructura = $mysqli->query("DESCRIBE `$tabla_resultado`");
                    $columnas = [];
                    while ($col = $estructura->fetch_assoc()) { $columnas[] = $col['Field']; }
                    $where_clauses = [];
                    $where_params = [];
                    $where_types = "";
                    if (in_array('identificacion', $columnas)) { $where_clauses[] = "identificacion = ?"; $where_params[] = $row['identificacion']; $where_types .= "s"; }
                    if (in_array('codexamen', $columnas)) { $where_clauses[] = "codexamen = ?"; $where_params[] = $row['codexamen']; $where_types .= "s"; }
                    elseif (in_array('examen', $columnas)) { $where_clauses[] = "examen = ?"; $where_params[] = $row['codexamen']; $where_types .= "s"; }
                    if (in_array('fecha', $columnas)) { $where_clauses[] = "fecha = ?"; $where_params[] = $row['fecha_examen']; $where_types .= "s"; }
                    if (empty($where_clauses)) {
                        $examen_data['resultado'] = 'Sin columnas clave'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Error';
                    } else {
                        $where_sql = implode(" AND ", $where_clauses);
                        $sql_resultado = "SELECT * FROM `$tabla_resultado` WHERE $where_sql LIMIT 1";
                        try {
                            $stmt_resultado = $mysqli->prepare($sql_resultado);
                            $stmt_resultado->bind_param($where_types, ...$where_params);
                            $stmt_resultado->execute();
                            $result_resultado = $stmt_resultado->get_result();
                            if ($result_resultado->num_rows > 0) {
                                $datos_resultado = $result_resultado->fetch_assoc();
                                $valor_resultado = ''; $referencia = ''; $estado = '';
                                switch ($tabla_resultado) {
                                    case 'examen_tipo_1': $valor_resultado = $datos_resultado['valoracion'] ?? ''; break;
                                    case 'examen_tipo_2': $valor_resultado = $datos_resultado['valoracion'] ?? ''; break;
                                    case 'examen_tipo_3':
                                        foreach (['densidad','color','ph','proteinas','glucosa','bilirrubina','nitritos','leucocitos'] as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                        } break;
                                    case 'examen_tipo_5':
                                        foreach (['hemoglobina','hematocrito','leucocitos','WBC','RBC','PLT'] as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                        } break;
                                    case 'examen_tipo_7':
                                        foreach (['tiempo_de_protrombina','tiempo_de_control','tpts'] as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                        } break;
                                    case 'perfilLipidico':
                                        foreach (['colesterol_total','colesterol_hdl','colesterol_ldl','trigliceridos'] as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                        } break;
                                    case 'hemogramaRayto':
                                        foreach (['WBC','RBC','HGB','HCT','PLT'] as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                        } break;
                                    default:
                                        foreach ($datos_resultado as $columna => $valor) {
                                            if (in_array(strtolower($columna), ['resultado','valor','result','value','valoracion']) && !empty($valor)) { $valor_resultado = $valor; break; }
                                        }
                                        if (empty($valor_resultado)) {
                                            $excluir = ['ind','identificacion','codexamen','examen','fecha','hora','id','bacteriologo','observaciones','fechahora','fechaResultados'];
                                            foreach ($datos_resultado as $columna => $valor) {
                                                if (!in_array(strtolower($columna), $excluir) && !empty($valor) && $valor !== '0000-00-00' && $valor !== '0' && $valor !== 'N/A') { $valor_resultado = $valor; break; }
                                            }
                                        }
                                }
                                foreach (['referencia','rango','range','normal','valor_de_referencia'] as $campo) {
                                    if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') { $referencia = $datos_resultado[$campo]; break; }
                                }
                                $examen_data['resultado'] = $valor_resultado ?: 'Sin valor';
                                $examen_data['referencia'] = $referencia ?: 'N/A';
                                $examen_data['estado'] = $estado ?: 'Completado';
                                $examen_data['resultado_completo'] = $datos_resultado;
                            } else {
                                $examen_data['resultado'] = 'Pendiente'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Pendiente';
                            }
                        } catch (Exception $e) {
                            $examen_data['resultado'] = 'Error en consulta'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Error';
                        }
                    }
                } else {
                    $examen_data['resultado'] = 'Tabla no encontrada'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Error';
                }
            } else {
                $examen_data['resultado'] = 'Sin tabla definida'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Pendiente';
            }
        } else {
            $examen_data['examen_nombre'] = 'Examen #' . $row['codexamen'];
            $examen_data['examen_tipo'] = 'No especificado'; $examen_data['examen_tabla'] = '';
            $examen_data['tipo_procedimiento'] = ''; $examen_data['abreviatura'] = '';
            $examen_data['resultado'] = 'No disponible'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'N/A';
        }
        $resultados[] = $examen_data;
    }
    if ($agrupar_fecha == '1') {
        $resultados_agrupados = [];
        foreach ($resultados as $resultado) {
            $fecha = $resultado['fecha_examen'];
            if (!isset($resultados_agrupados[$fecha])) $resultados_agrupados[$fecha] = [];
            $resultados_agrupados[$fecha][] = $resultado;
        }
        $resultado_final = [];
        foreach ($resultados_agrupados as $fecha => $items) {
            $resultado_final[] = ['fecha' => $fecha, 'cantidad' => count($items), 'examenes' => $items];
        }
        echo json_encode(['success' => true, 'resultados' => $resultado_final, 'total_registros' => count($resultados), 'total_fechas' => count($resultado_final)]);
    } else {
        echo json_encode(['success' => true, 'resultados' => $resultados, 'total_registros' => count($resultados)]);
    }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'consulta_pacientes_resultados') {
    $identificacion = trim($_POST['identificacion'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $entidad = trim($_POST['entidad'] ?? '');
    $include_examenes = $_POST['include_examenes'] ?? '1';
    $solo_con_resultados = $_POST['solo_con_resultados'] ?? '0';
    $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 50;
    if (empty($identificacion) && empty($nombres) && empty($telefono) && empty($ciudad) && empty($entidad)) {
        echo json_encode(['success' => false, 'message' => 'Debe ingresar al menos un criterio de búsqueda.']);
        exit;
    }
    $sql_pacientes = "SELECT p.identificacion, CONCAT_WS(' ', p.apellidos, p.nombres) as nombre_completo,
        p.apellidos, p.nombres, p.edad, p.genero, p.fecnac, p.telefono, p.telefono_movil,
        p.telefono_residencia2, p.correo, p.ciudad_residencia, p.direccion_residencia, p.entidad,
        COUNT(DISTINCT e.fecha) as total_visitas, MAX(e.fecha) as ultima_visita
        FROM paciente p LEFT JOIN examenes e ON p.identificacion = e.identificacion WHERE 1=1";
    $params = []; $param_types = "";
    if (!empty($identificacion)) { $sql_pacientes .= " AND p.identificacion LIKE ?"; $params[] = "%".$identificacion."%"; $param_types .= "s"; }
    if (!empty($nombres)) {
        $nombres_like = "%".$nombres."%";
        $sql_pacientes .= " AND (p.nombres LIKE ? OR p.apellidos LIKE ? OR p.nombre1 LIKE ? OR p.nombre2 LIKE ? OR p.apellido1 LIKE ? OR p.apellido2 LIKE ?)";
        $params = array_merge($params, [$nombres_like,$nombres_like,$nombres_like,$nombres_like,$nombres_like,$nombres_like]);
        $param_types .= "ssssss";
    }
    if (!empty($telefono)) {
        $telefono_like = "%".$telefono."%";
        $sql_pacientes .= " AND (p.telefono LIKE ? OR p.telefono_movil LIKE ? OR p.telefono_residencia2 LIKE ?)";
        $params = array_merge($params, [$telefono_like,$telefono_like,$telefono_like]); $param_types .= "sss";
    }
    if (!empty($ciudad)) { $sql_pacientes .= " AND p.ciudad_residencia LIKE ?"; $params[] = "%".$ciudad."%"; $param_types .= "s"; }
    if (!empty($entidad)) { $sql_pacientes .= " AND p.entidad LIKE ?"; $params[] = "%".$entidad."%"; $param_types .= "s"; }
    $sql_pacientes .= " GROUP BY p.identificacion ORDER BY p.apellidos ASC, p.nombres ASC LIMIT " . $limit;
    $stmt_pacientes = $mysqli->prepare($sql_pacientes);
    if (!empty($params)) $stmt_pacientes->bind_param($param_types, ...$params);
    $stmt_pacientes->execute();
    $result_pacientes = $stmt_pacientes->get_result();
    $pacientes = [];
    $procedimientos_exists = $mysqli->query("SHOW TABLES LIKE 'procedimientos'")->num_rows > 0;
    while ($paciente_row = $result_pacientes->fetch_assoc()) {
        $identificacion_p = $paciente_row['identificacion'];
        $telefono_principal = $paciente_row['telefono'] ?: $paciente_row['telefono_movil'] ?: $paciente_row['telefono_residencia2'] ?: '';
        $edad_formateada = $paciente_row['edad'];
        if (empty($edad_formateada) && !empty($paciente_row['fecnac']) && $paciente_row['fecnac'] !== '0000-00-00') {
            $fecha_nac = new DateTime($paciente_row['fecnac']);
            $hoy = new DateTime();
            $edad_formateada = $hoy->diff($fecha_nac)->y;
        }
        $paciente_data = [
            'identificacion' => $identificacion_p, 'nombre_completo' => $paciente_row['nombre_completo'],
            'apellidos' => $paciente_row['apellidos'], 'nombres' => $paciente_row['nombres'],
            'edad' => $edad_formateada, 'genero' => $paciente_row['genero'],
            'telefono' => $telefono_principal, 'telefono_fijo' => $paciente_row['telefono'],
            'telefono_movil' => $paciente_row['telefono_movil'], 'telefono_residencia2' => $paciente_row['telefono_residencia2'],
            'correo' => $paciente_row['correo'], 'ciudad_residencia' => $paciente_row['ciudad_residencia'],
            'direccion_residencia' => $paciente_row['direccion_residencia'], 'entidad' => $paciente_row['entidad'],
            'total_visitas' => $paciente_row['total_visitas'] ?? 0, 'ultima_visita' => $paciente_row['ultima_visita'] ?? '',
            'examenes' => []
        ];
        if ($include_examenes == '1') {
            $sql_examenes = "SELECT e.fecha, e.codexamen, e.realizado, e.entidad";
            if ($procedimientos_exists) $sql_examenes .= ", pr.nombre as nombre_examen, pr.codigo as codigo_examen, pr.tabla as examen_tabla, pr.tipo as tipo_examen, pr.tipoprocedimiento as tipo_procedimiento, pr.abreviatura";
            $sql_examenes .= " FROM examenes e INNER JOIN paciente p ON e.identificacion = p.identificacion";
            if ($procedimientos_exists) $sql_examenes .= " LEFT JOIN procedimientos pr ON e.codexamen = pr.codigo";
            $sql_examenes .= " WHERE e.identificacion = ? ORDER BY e.fecha DESC";
            $stmt_examenes = $mysqli->prepare($sql_examenes);
            $stmt_examenes->bind_param("s", $identificacion_p);
            $stmt_examenes->execute();
            $result_examenes = $stmt_examenes->get_result();
            while ($examen_row = $result_examenes->fetch_assoc()) {
                $examen_data = ['fecha' => $examen_row['fecha'], 'codigo' => $examen_row['codexamen'], 'entidad' => $examen_row['entidad'], 'realizado' => $examen_row['realizado']];
                if ($procedimientos_exists) {
                    $examen_data['nombre'] = $examen_row['nombre_examen'] ?? $examen_row['abreviatura'] ?? 'Examen #' . $examen_row['codexamen'];
                    $examen_data['tipo'] = $examen_row['tipo_examen'] ?? 'No especificado';
                    $examen_data['tabla'] = $examen_row['examen_tabla'] ?? '';
                    $examen_data['procedimiento'] = $examen_row['tipo_procedimiento'] ?? '';
                    $examen_data['abreviatura'] = $examen_row['abreviatura'] ?? '';
                    if (!empty($examen_row['examen_tabla'])) {
                        $tabla_resultado = $examen_row['examen_tabla'];
                        $tabla_existe = $mysqli->query("SHOW TABLES LIKE '$tabla_resultado'")->num_rows > 0;
                        if ($tabla_existe) {
                            $estructura = $mysqli->query("DESCRIBE `$tabla_resultado`");
                            $columnas = [];
                            while ($col = $estructura->fetch_assoc()) $columnas[] = $col['Field'];
                            $where_clauses = []; $where_params = []; $where_types = "";
                            if (in_array('identificacion', $columnas)) { $where_clauses[] = "identificacion = ?"; $where_params[] = $identificacion_p; $where_types .= "s"; }
                            if (in_array('codexamen', $columnas)) { $where_clauses[] = "codexamen = ?"; $where_params[] = $examen_row['codexamen']; $where_types .= "s"; }
                            elseif (in_array('examen', $columnas)) { $where_clauses[] = "examen = ?"; $where_params[] = $examen_row['codexamen']; $where_types .= "s"; }
                            if (in_array('fecha', $columnas)) { $where_clauses[] = "fecha = ?"; $where_params[] = $examen_row['fecha']; $where_types .= "s"; }
                            if (!empty($where_clauses)) {
                                $where_sql = implode(" AND ", $where_clauses);
                                $sql_resultado = "SELECT * FROM `$tabla_resultado` WHERE $where_sql LIMIT 1";
                                try {
                                    $stmt_resultado = $mysqli->prepare($sql_resultado);
                                    $stmt_resultado->bind_param($where_types, ...$where_params);
                                    $stmt_resultado->execute();
                                    $result_resultado = $stmt_resultado->get_result();
                                    if ($result_resultado->num_rows > 0) {
                                        $datos_resultado = $result_resultado->fetch_assoc();
                                        $valor_resultado = ''; $referencia = '';
                                        switch ($tabla_resultado) {
                                            case 'examen_tipo_1': case 'examen_tipo_2': $valor_resultado = $datos_resultado['valoracion'] ?? ''; break;
                                            case 'examen_tipo_3':
                                                foreach (['densidad','color','ph','proteinas','glucosa'] as $campo) {
                                                    if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                } break;
                                            case 'examen_tipo_5':
                                                foreach (['hemoglobina','hematocrito','leucocitos'] as $campo) {
                                                    if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                } break;
                                            case 'perfilLipidico':
                                                foreach (['colesterol_total','colesterol_hdl','trigliceridos'] as $campo) {
                                                    if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                } break;
                                            case 'hemogramaRayto':
                                                foreach (['WBC','RBC','HGB','HCT','PLT'] as $campo) {
                                                    if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                                } break;
                                            default:
                                                foreach ($datos_resultado as $columna => $valor) {
                                                    if (in_array(strtolower($columna), ['resultado','valor','result','value']) && !empty($valor)) { $valor_resultado = $valor; break; }
                                                }
                                                if (empty($valor_resultado)) {
                                                    $excluir = ['ind','identificacion','codexamen','examen','fecha','hora','id','bacteriologo','observaciones'];
                                                    foreach ($datos_resultado as $columna => $valor) {
                                                        if (!in_array(strtolower($columna), $excluir) && !empty($valor) && $valor !== '0000-00-00' && $valor !== '0' && $valor !== 'N/A') { $valor_resultado = $valor; break; }
                                                    }
                                                }
                                        }
                                        foreach (['referencia','rango','valor_de_referencia'] as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') { $referencia = $datos_resultado[$campo]; break; }
                                        }
                                        $examen_data['resultado'] = $valor_resultado ?: 'Sin valor';
                                        $examen_data['referencia'] = $referencia ?: 'N/A';
                                        $examen_data['estado'] = 'Completado';
                                    } else {
                                        $examen_data['resultado'] = 'Pendiente'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Pendiente';
                                    }
                                } catch (Exception $e) {
                                    $examen_data['resultado'] = 'Error en consulta'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Error';
                                }
                            } else {
                                $examen_data['resultado'] = 'Sin datos'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Sin datos';
                            }
                        } else {
                            $examen_data['resultado'] = 'Tabla no encontrada'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'Error';
                        }
                    } else {
                        $examen_data['nombre'] = 'Examen #' . $examen_row['codexamen'];
                        $examen_data['tipo'] = 'No especificado'; $examen_data['tabla'] = ''; $examen_data['procedimiento'] = '';
                        $examen_data['resultado'] = 'No disponible'; $examen_data['referencia'] = 'N/A'; $examen_data['estado'] = 'N/A';
                    }
                } else {
                    $examen_data['nombre'] = 'Examen #' . $examen_row['codexamen'];
                    $examen_data['resultado'] = 'No disponible'; $examen_data['estado'] = 'N/A';
                }
                if ($solo_con_resultados == '1' && ($examen_data['estado'] == 'Pendiente' || $examen_data['resultado'] == 'No disponible')) continue;
                $paciente_data['examenes'][] = $examen_data;
            }
            $paciente_data['total_examenes'] = count($paciente_data['examenes']);
            $paciente_data['examenes_con_resultados'] = count(array_filter($paciente_data['examenes'], function ($ex) {
                return isset($ex['estado']) && $ex['estado'] == 'Completado';
            }));
        }
        $pacientes[] = $paciente_data;
    }
    echo json_encode([
        'success' => true, 'pacientes' => $pacientes, 'total_pacientes' => count($pacientes),
        'criterios' => ['identificacion'=>$identificacion,'nombres'=>$nombres,'telefono'=>$telefono,'ciudad'=>$ciudad,'entidad'=>$entidad,'include_examenes'=>$include_examenes,'solo_con_resultados'=>$solo_con_resultados,'limit'=>$limit]
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nombreLab) ?> - Resultados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5; --primary-light: #818cf8; --primary-dark: #3730a3;
            --success: #059669; --success-light: #34d399;
            --warning: #d97706; --danger: #dc2626;
            --surface: #ffffff; --surface-alt: #f8fafc;
            --text: #0f172a; --text-muted: #64748b;
            --border: #e2e8f0; --radius: 1rem;
        }
        [x-cloak] { display: none !important; }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        @keyframes pulse-ring { 0% { transform: scale(0.9); opacity:1; } 100% { transform: scale(1.3); opacity:0; } }
        .animate-fade-in { animation: fadeInUp 0.4s cubic-bezier(0.16,1,0.3,1) both; }
        .skeleton { background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 0.5rem; }
        .scrollbar-thin::-webkit-scrollbar { width: 5px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .glass { background: rgba(255,255,255,0.7); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        .card-hover { transition: all 0.25s cubic-bezier(0.16,1,0.3,1); }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 12px 40px -12px rgba(0,0,0,0.12); }
        .select-modern {
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.6rem center; background-repeat: no-repeat; background-size: 1.3em 1.3em;
            padding-right: 2.2rem;
        }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); transition: all 0.2s; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px -4px rgba(79,70,229,0.4); }
        .btn-success { background: linear-gradient(135deg, var(--success), #047857); transition: all 0.2s; }
        .btn-success:hover { transform: translateY(-1px); box-shadow: 0 6px 20px -4px rgba(5,150,105,0.4); }
        .thiings-icon { image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges; }
    </style>
</head>
<body class="bg-slate-100 h-screen flex flex-col overflow-hidden" x-data="labApp('<?= $identificacion_inicial ?>')">

    <!-- HEADER -->
    <header class="glass border-b border-slate-200/60 px-4 py-2.5 flex-shrink-0 z-30">
        <div class="max-w-[1600px] mx-auto flex justify-between items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <?php if (!empty($urlLogo)): ?>
                    <img src="<?= str_starts_with(trim($urlLogo), 'data:image') ? $urlLogo : htmlspecialchars($urlLogo) ?>"
                        alt="Logo" class="h-9 w-9 object-contain rounded-lg flex-shrink-0">
                <?php else: ?>
                    <img src="icons/thiings/laboratory.png" alt="Lab" class="h-9 w-9 object-contain rounded-lg flex-shrink-0 thiings-icon">
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="font-bold text-sm text-slate-800 truncate leading-tight"><?= htmlspecialchars($nombreLab) ?></div>
                    <div class="text-[11px] text-slate-400 leading-tight">Panel de Resultados</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="abrirModalEntidades()"
                    class="btn-primary text-white px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                    title="Consultar por entidades">
                    <i class="bi bi-building text-sm"></i>
                    <span class="hidden sm:inline">ENTIDADES</span>
                </button>
                <button @click="abrirModalPacientesBusqueda()"
                    class="btn-success text-white px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                    title="Buscar pacientes con resultados">
                    <i class="bi bi-people-fill text-sm"></i>
                    <span class="hidden sm:inline">PACIENTES</span>
                </button>
                <a href="admin.php"
                    class="bg-slate-700 hover:bg-slate-800 text-white px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                    title="Panel administrativo">
                    <i class="bi bi-shield-lock-fill text-sm"></i>
                    <span class="hidden sm:inline">ADMIN</span>
                </a>
                <div class="hidden lg:flex items-center gap-2 ml-2 pl-2 border-l border-slate-200">
                    <div class="flex items-center gap-1.5 bg-slate-100 px-2.5 py-1 rounded-lg text-[11px] text-slate-600">
                        <i class="bi bi-person-fill text-slate-400"></i>
                        <span class="font-medium truncate max-w-[120px]"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
                    </div>
                    <div class="bg-slate-100 px-2.5 py-1 rounded-lg text-[11px] text-slate-500 font-medium">
                        <?php if ($todos && !empty($busqueda)): ?>
                            Todas las fechas
                        <?php else: ?>
                            <?= date('d/m/Y', strtotime($fecha)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-lg text-[11px] font-bold">
                        <?= $total_pacientes ?> pacientes
                    </div>
                </div>
                <a href="?logout=1"
                    class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ml-1"
                    title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right text-sm"></i>
                    <span class="hidden sm:inline">SALIR</span>
                </a>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- SIDEBAR -->
        <aside class="w-full md:w-[440px] lg:w-[500px] xl:w-[540px] bg-white border-r flex flex-col shadow-sm z-20"
            :class="{'hidden md:flex': vistaReporte}">
            <div class="p-3 border-b border-slate-100 space-y-2" x-show="!idAbierto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-2">
                <form action="<?= $script_actual ?>" method="GET" class="space-y-2">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="relative" id="fecha-container" <?= ($todos && !empty($busqueda)) ? 'style="display:none;"' : '' ?>>
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <i class="bi bi-calendar3 text-slate-400 text-sm"></i>
                            </div>
                            <input type="date" name="fecha" value="<?= $fecha ?>"
                                class="w-full pl-10 pr-4 py-2 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm font-medium">
                        </div>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <i class="bi bi-search text-slate-400 text-sm"></i>
                                </div>
                                <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>"
                                    placeholder="Documento, nombres o apellidos..."
                                    class="w-full pl-10 pr-10 py-2 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-sm"
                                    autocomplete="off" id="buscar-input">
                                <?php if (!empty($busqueda)): ?>
                                    <a href="<?= $script_actual ?>"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-red-500 transition"
                                        title="Limpiar búsqueda">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn-primary px-4 py-2 rounded-xl font-bold text-xs tracking-wide flex items-center gap-1.5 flex-shrink-0">
                                <i class="bi bi-funnel-fill"></i> <span class="hidden sm:inline">FILTRAR</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="buscar-todas" name="todos" value="1" <?= $todos ? 'checked' : '' ?>
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded"
                                @change="toggleFechaInput()">
                            <label for="buscar-todas" class="text-xs text-slate-600 cursor-pointer select-none">
                                Buscar en todas las fechas
                            </label>
                        </div>
                    </div>
                    <?php if (!empty($busqueda)): ?>
                        <div class="bg-amber-50 border border-amber-200/60 rounded-xl p-2">
                            <div class="flex justify-between items-center">
                                <div class="text-xs text-amber-800">
                                    <i class="bi bi-search mr-1"></i>Búsqueda: "<span class="font-bold"><?= htmlspecialchars($busqueda) ?></span>"
                                    <?php if ($todos): ?>
                                        <span class="ml-1 text-[9px] bg-amber-100 text-amber-700 px-1 py-0.5 rounded font-medium">Todas</span>
                                    <?php endif; ?>
                                </div>
                                <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                    <?= $total_pacientes ?> resultado<?= $total_pacientes == 1 ? '' : 's' ?>
                                </span>
                            </div>
                            <?php if ($total_pacientes >= 100): ?>
                                <div class="mt-1 text-[11px] text-amber-600">
                                    <i class="bi bi-info-circle mr-1"></i>Mostrando los primeros 100. Refina tu búsqueda.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>
                <?php if ($es_hoy && !$todos): ?>
                    <div class="bg-indigo-50/80 border border-indigo-100 rounded-xl p-2 flex items-center gap-2">
                        <div class="w-7 h-7 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="bi bi-info-circle-fill text-indigo-500 text-xs"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-indigo-800">Hoy, <?= date('d/m/Y') ?></div>
                            <div class="text-[10px] text-indigo-500">Mostrando resultados del día actual</div>
                        </div>
                    </div>
                <?php elseif ($todos && !empty($busqueda)): ?>
                    <div class="bg-purple-50/80 border border-purple-100 rounded-xl p-2 flex items-center gap-2">
                        <div class="w-7 h-7 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="bi bi-database-fill text-purple-500 text-xs"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-purple-800">Búsqueda completa</div>
                            <div class="text-[10px] text-purple-500">Buscando en todas las fechas disponibles</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lista de pacientes -->
            <div class="flex-1 overflow-y-auto p-3 space-y-2 scrollbar-thin">
                <?php if ($resultados->num_rows > 0): ?>
                    <?php
                    $fecha_actual_grupo = null;
                    while ($row = $resultados->fetch_assoc()):
                        $id_p = $row['identificacion'];
                        $nom_p = $row['nombres'];
                        $edad_p = $row['edad'];
                        $genero_p = $row['genero'];
                        $telefono_p = $row['telefono'];
                        $fecha_examen = $row['fecha_examen'];
                        $entidad_p = $row['entidad'];
                        if ($todos && $fecha_examen != $fecha_actual_grupo) {
                            $fecha_actual_grupo = $fecha_examen;
                            ?>
                            <div class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-sm -mx-3 px-3 py-1.5 rounded-xl border border-slate-200/60">
                                <div class="flex items-center justify-between">
                                    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                                        <i class="bi bi-calendar3 mr-1.5 text-slate-400"></i><?= date('d/m/Y', strtotime($fecha_examen)) ?>
                                    </div>
                                    <a href="<?= $script_actual ?>?fecha=<?= $fecha_examen ?>"
                                        class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium px-2 py-0.5 hover:bg-indigo-50 rounded-lg transition">
                                        Ver solo este día
                                    </a>
                                </div>
                            </div>
                        <?php } ?>
                        <?php
                        if (empty($edad_p) && !empty($row['fecnac'])) {
                            $fecha_nac = new DateTime($row['fecnac']);
                            $hoy_dt = new DateTime();
                            $edad_p = $hoy_dt->diff($fecha_nac)->y;
                        }
                        if (!empty($busqueda)) {
                            $patron = "/" . preg_quote($busqueda, '/') . "/i";
                            $nombres_resaltados = preg_replace($patron, '<mark class="bg-amber-200/70 text-amber-900 rounded px-0.5 font-bold">$0</mark>', $nom_p);
                            $id_resaltado = preg_replace($patron, '<mark class="bg-amber-200/70 text-amber-900 rounded px-0.5 font-bold">$0</mark>', $id_p);
                        } else {
                            $nombres_resaltados = $nom_p;
                            $id_resaltado = $id_p;
                        }
                        $icono_genero = ($genero_p == 'F') ? 'bi-gender-female text-pink-400' :
                            (($genero_p == 'M') ? 'bi-gender-male text-blue-400' : 'bi-gender-ambiguous text-slate-400');
                        ?>
                        <div class="bg-white border border-slate-200/80 rounded-2xl card-hover overflow-hidden"
                            :class="{'!border-indigo-300 !shadow-md ring-2 ring-indigo-500/10': idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>'}">
                            <button
                                @click="idAbierto = (idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>' ? null : '<?= $id_p ?>_<?= $fecha_examen ?>')"
                                class="w-full p-3 text-left flex justify-between items-center gap-3 hover:bg-slate-50/50 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 <?php if ($genero_p == 'F'): ?>bg-pink-50<?php elseif ($genero_p == 'M'): ?>bg-blue-50<?php else: ?>bg-slate-50<?php endif; ?>">
                                            <i class="bi <?= $icono_genero ?> text-sm"></i>
                                        </div>
                                        <div class="text-slate-900 font-bold text-sm truncate">
                                            <?php if ($nombres_resaltados !== $nom_p): ?>
                                                <?= $nombres_resaltados ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($nom_p) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 ml-9">
                                        <span class="font-mono text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md">
                                            <?php if ($id_resaltado !== $id_p): ?>
                                                <?= $id_resaltado ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($id_p) ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($edad_p)): ?>
                                            <span class="text-[10px] text-slate-400"><i class="bi bi-calendar3 mr-0.5"></i><?= round($edad_p) ?>a</span>
                                        <?php endif; ?>
                                        <?php if (!empty($telefono_p) && $telefono_p != '0'): ?>
                                            <span class="text-[10px] text-slate-400"><i class="bi bi-telephone-fill mr-0.5"></i><?= $telefono_p ?></span>
                                        <?php endif; ?>
                                        <?php if ($todos): ?>
                                            <span class="text-[10px] text-purple-500 font-medium"><i class="bi bi-calendar-check mr-0.5"></i><?= date('d/m/Y', strtotime($fecha_examen)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <i class="bi text-lg transition-transform duration-300 flex-shrink-0 mt-1"
                                    :class="idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>' ? 'bi-chevron-up text-indigo-500' : 'bi-chevron-down text-slate-300'"></i>
                            </button>

                            <div x-show="idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>'" x-cloak
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 -translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 -translate-y-2"
                                class="border-t border-slate-100">
                                <div class="p-3 space-y-2 bg-gradient-to-b from-slate-50/80 to-white">
                                    <div class="grid grid-cols-2 gap-2">
                                        <?php $url_todo = "printphp/imprimirTodo.php?idx=" . bin2hex(random_bytes(10)) . "&identificacion=$id_p&fecha=$fecha_examen&nombres=" . urlencode($nom_p) . "&edad=$edad_p&entidad=" . urlencode($entidad_p) . "&info=Resultados&ver=1"; ?>
                                        <button @click="cargarReporte('<?= $url_todo ?>')"
                                            class="text-[10px] uppercase font-bold bg-white border-2 border-slate-200 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 hover:border-orange-300 hover:bg-orange-50/50 transition-all group">
                                            <i class="bi bi-collection-fill text-orange-400 text-sm group-hover:scale-110 transition-transform"></i>
                                            <span>Imprimir Todo</span>
                                        </button>
                                        <button @click="enviarWhatsapp('<?= $telefono_p ?>', '<?= urlencode($url_todo) ?>')"
                                            class="text-[10px] uppercase font-bold bg-white border-2 border-slate-200 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 hover:border-green-300 hover:bg-green-50/50 transition-all group <?= (empty($telefono_p) || $telefono_p == '0') ? 'opacity-40 cursor-not-allowed' : '' ?>"
                                            <?= (empty($telefono_p) || $telefono_p == '0') ? 'disabled title="Sin teléfono"' : '' ?>>
                                            <i class="bi bi-whatsapp text-green-500 text-sm group-hover:scale-110 transition-transform"></i>
                                            <span>WhatsApp</span>
                                        </button>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                            <i class="bi bi-list-check text-indigo-400"></i>
                                            Exámenes
                                            <span class="text-slate-400 font-normal">(<?= date('d/m/Y', strtotime($fecha_examen)) ?>)</span>
                                        </div>
                                        <div class="space-y-1">
                                            <?php
                                            $sql_examenes = "SELECT p.*, e.codexamen, e.entidad
                                                FROM examenes e INNER JOIN procedimientos p ON e.codexamen = p.codigo
                                                WHERE e.identificacion = ? AND e.fecha = ? ORDER BY p.nombre ASC";
                                            $stmt_ex = $mysqli->prepare($sql_examenes);
                                            $stmt_ex->bind_param("ss", $id_p, $fecha_examen);
                                            $stmt_ex->execute();
                                            $res_ex = $stmt_ex->get_result();
                                            if ($res_ex->num_rows > 0):
                                                while ($ex = $res_ex->fetch_assoc()):
                                                    $q = http_build_query([
                                                        'idx' => bin2hex(random_bytes(10)),
                                                        'identificacion' => $id_p, 'fecha' => $fecha_examen,
                                                        'nombres' => $nom_p, 'tabla' => $ex['tabla'],
                                                        'info' => $ex['nombre'], 'tipo' => $ex['tipo'],
                                                        'codexamen' => $ex['codigo'], 'edad' => $edad_p,
                                                        'entidad' => $ex['entidad'], 'embedido' => 1, 'ver' => 1
                                                    ]);
                                                    $url_single = "printphp/print_examen.php?$q";
                                                    ?>
                                                    <div class="p-2 bg-white rounded-xl border border-slate-100 hover:border-indigo-200 hover:shadow-sm transition-all group">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                                                <div class="w-1.5 h-1.5 bg-indigo-400 rounded-full flex-shrink-0"></div>
                                                                <span class="text-xs font-medium text-slate-700 truncate" title="<?= htmlspecialchars($ex['nombre']) ?>">
                                                                    <?= htmlspecialchars($ex['nombre']) ?>
                                                                </span>
                                                            </div>
                                                            <button @click="cargarReporte('<?= $url_single ?>')"
                                                                class="p-1.5 rounded-lg hover:bg-indigo-50 text-indigo-500 transition-colors flex-shrink-0"
                                                                title="Ver examen">
                                                                <i class="bi bi-eye-fill text-sm"></i>
                                                            </button>
                                                        </div>
                                                        <div class="mt-1.5 ml-3.5">
                                                            <select class="select-modern py-1 px-2 text-[11px] font-medium border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none w-full"
                                                                data-identificacion="<?= htmlspecialchars($id_p) ?>"
                                                                data-fecha-examen="<?= htmlspecialchars($fecha_examen) ?>"
                                                                data-codexamen="<?= htmlspecialchars($ex['codexamen']) ?>"
                                                                @change="actualizarEntidad(event.target)">
                                                                <option value="">-- Sin Entidad --</option>
                                                                <?php foreach ($entidades as $entidad): ?>
                                                                    <option value="<?= htmlspecialchars($entidad['nombre']) ?>"
                                                                        <?= (trim($ex['entidad'] ?? '') === trim($entidad['nombre'])) ? 'selected="selected"' : '' ?>>
                                                                        <?= htmlspecialchars($entidad['nombre']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                <?php endwhile;
                                            else: ?>
                                                <div class="text-center py-6 text-slate-300">
                                                    <i class="bi bi-clipboard-x text-2xl mb-1 block"></i>
                                                    <p class="text-[11px]">No hay exámenes registrados</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php
                                    $sql_entidad_actual = "SELECT entidad FROM examenes WHERE identificacion = ? AND fecha = ? ORDER BY entidad DESC LIMIT 1";
                                    $stmt_entidad_actual = $mysqli->prepare($sql_entidad_actual);
                                    $stmt_entidad_actual->bind_param("ss", $id_p, $fecha_examen);
                                    $stmt_entidad_actual->execute();
                                    $res_entidad_actual = $stmt_entidad_actual->get_result();
                                    $entidad_actual = '';
                                    if ($res_entidad_actual && $res_entidad_actual->num_rows > 0) {
                                        $row_entidad = $res_entidad_actual->fetch_assoc();
                                        $entidad_actual = trim($row_entidad['entidad'] ?? '');
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center p-8 text-center animate-fade-in">
                        <img src="icons/thiings/patient.png" alt="Sin resultados" class="w-28 h-28 object-contain mb-5 thiings-icon opacity-80">
                        <h3 class="text-lg font-bold text-slate-700 mb-1.5">No se encontraron resultados</h3>
                        <?php if (!empty($busqueda)): ?>
                            <p class="text-sm text-slate-500 mb-4">para: "<span class="font-mono bg-slate-100 px-2 py-0.5 rounded-lg text-slate-600"><?= htmlspecialchars($busqueda) ?></span>"</p>
                            <a href="<?= $script_actual ?>"
                                class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium px-4 py-2 border border-indigo-200 rounded-xl hover:bg-indigo-50 transition">
                                <i class="bi bi-arrow-left"></i> Ver todos
                            </a>
                        <?php else: ?>
                            <p class="text-sm text-slate-500 mb-4">No hay pacientes para <span class="font-bold"><?= date('d/m/Y', strtotime($fecha)) ?></span></p>
                            <div class="flex gap-2">
                                <a href="<?= $script_actual ?>?fecha=<?= date('Y-m-d') ?>"
                                    class="btn-primary text-white text-sm font-medium px-4 py-2 rounded-xl inline-flex items-center gap-2">
                                    <i class="bi bi-calendar3"></i> Ver hoy
                                </a>
                                <a href="<?= $script_actual ?>?fecha=<?= date('Y-m-d', strtotime('-1 day')) ?>"
                                    class="bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium px-4 py-2 rounded-xl inline-flex items-center gap-2 transition">
                                    <i class="bi bi-arrow-left"></i> Ver ayer
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- AREA PRINCIPAL -->
        <main class="flex-1 relative flex flex-col bg-slate-50">
            <div x-show="!urlReporte" x-cloak class="flex-1 flex flex-col items-center justify-center p-8 animate-fade-in">
                <div class="text-center max-w-lg">
                    <img src="icons/thiings/stethoscope.png" alt="Visor" class="w-32 h-32 object-contain mx-auto mb-6 thiings-icon">
                    <h3 class="text-xl font-extrabold text-slate-800 mb-2">Visor de Resultados</h3>
                    <p class="text-slate-500 text-sm mb-8 leading-relaxed">
                        Seleccione un examen de la lista para visualizar e imprimir los resultados.
                        También puede enviar los resultados por WhatsApp directamente al paciente.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-xl mx-auto">
                        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 card-hover text-center">
                            <img src="icons/thiings/microscope.png" alt="" class="w-12 h-12 mx-auto mb-3 thiings-icon">
                            <div class="text-sm font-bold text-slate-800 mb-0.5">Visualizar</div>
                            <div class="text-[11px] text-slate-400">Ver resultados completos</div>
                        </div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 card-hover text-center">
                            <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                <i class="bi bi-printer-fill text-emerald-500 text-xl"></i>
                            </div>
                            <div class="text-sm font-bold text-slate-800 mb-0.5">Imprimir</div>
                            <div class="text-[11px] text-slate-400">Generar copia física</div>
                        </div>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 card-hover text-center">
                            <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                <i class="bi bi-whatsapp text-green-500 text-xl"></i>
                            </div>
                            <div class="text-sm font-bold text-slate-800 mb-0.5">Compartir</div>
                            <div class="text-[11px] text-slate-400">Enviar por WhatsApp</div>
                        </div>
                    </div>
                </div>
            </div>
            <div x-show="urlReporte" x-cloak class="h-full flex flex-col">
                <div class="glass border-b border-slate-200/60 p-3 flex justify-between items-center flex-shrink-0">
                    <button @click="urlReporte = null; vistaReporte = false"
                        class="md:hidden text-slate-500 hover:text-slate-700 hover:bg-slate-100 p-2 rounded-xl transition">
                        <i class="bi bi-arrow-left text-lg"></i>
                    </button>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <i class="bi bi-file-earmark-text-fill text-indigo-500 text-sm"></i>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider">Reporte</div>
                            <div class="text-[10px] text-slate-400" x-text="nombreReporte || 'Cargando...'"></div>
                        </div>
                    </div>
                    <button @click="imprimirFrame()"
                        class="btn-primary text-white px-4 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5">
                        <i class="bi bi-printer"></i> IMPRIMIR
                    </button>
                </div>
                <iframe :src="urlReporte" id="frameReporte" class="w-full flex-1 border-none bg-white"
                    @load="cargarNombreReporte" title="Vista previa del reporte"></iframe>
            </div>
        </main>
    </div>

    <!-- MODAL ENTIDADES -->
    <div x-show="mostrarModalEntidades" x-cloak
        class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4 z-50"
        @click.self="mostrarModalEntidades = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-3xl shadow-2xl max-w-6xl w-full h-[85vh] flex flex-col overflow-hidden"
            @click.stop
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0">
            <div x-show="!mostrarResultsModal" class="flex flex-col h-full">
                <div class="bg-gradient-to-r from-violet-600 to-indigo-600 text-white p-6 flex-shrink-0">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-3">
                            <img src="icons/thiings/building.png" alt="" class="w-10 h-10 object-contain thiings-icon">
                            <h3 class="text-xl font-bold">Consulta por Entidades</h3>
                        </div>
                        <button @click="mostrarModalEntidades = false" class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="text-violet-200 text-sm">Consulta resultados por entidad y rango de fechas</p>
                </div>
                <form @submit.prevent="consultarPorEntidades()" class="p-6 space-y-5 overflow-y-auto flex-1">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Entidad</label>
                        <select x-model="formularioEntidades.entidad"
                            class="select-modern w-full px-4 py-3 border-2 border-slate-200 rounded-2xl focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition text-sm font-medium">
                            <option value="">-- Todas las entidades --</option>
                            <?php foreach ($entidades as $entidad): ?>
                                <option value="<?= htmlspecialchars($entidad['nombre']) ?>"><?= htmlspecialchars($entidad['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Fecha Inicio</label>
                            <input type="date" x-model="formularioEntidades.fechaInicio" required
                                class="w-full px-4 py-3 border-2 border-slate-200 rounded-2xl focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Fecha Fin</label>
                            <input type="date" x-model="formularioEntidades.fechaFin" required
                                class="w-full px-4 py-3 border-2 border-slate-200 rounded-2xl focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition text-sm font-medium">
                        </div>
                    </div>
                    <div class="bg-violet-50/80 p-4 rounded-2xl border border-violet-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="bi bi-funnel text-violet-500"></i>
                            <span class="text-sm font-semibold text-violet-800">Opciones de filtrado</span>
                        </div>
                        <div class="space-y-2.5">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <div class="relative">
                                    <input type="checkbox" x-model="formularioEntidades.soloConResultados" class="sr-only peer">
                                    <div class="w-10 h-5 bg-slate-200 rounded-full peer-checked:bg-violet-500 transition-colors"></div>
                                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-5"></div>
                                </div>
                                <span class="text-sm text-slate-700 group-hover:text-slate-900 transition">Solo exámenes con resultados</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <div class="relative">
                                    <input type="checkbox" x-model="formularioEntidades.agruparPorFecha" class="sr-only peer">
                                    <div class="w-10 h-5 bg-slate-200 rounded-full peer-checked:bg-violet-500 transition-colors"></div>
                                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-5"></div>
                                </div>
                                <span class="text-sm text-slate-700 group-hover:text-slate-900 transition">Agrupar por fecha</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="mostrarModalEntidades = false"
                            class="flex-1 px-4 py-3 border-2 border-slate-200 text-slate-600 rounded-2xl font-bold hover:bg-slate-50 transition text-sm">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 bg-gradient-to-r from-violet-600 to-indigo-600 text-white px-4 py-3 rounded-2xl font-bold hover:from-violet-700 hover:to-indigo-700 transition shadow-lg shadow-violet-200 text-sm">
                            <i class="bi bi-search mr-2"></i>Consultar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Resultados entidades -->
            <div x-show="mostrarResultsModal" class="flex flex-col h-full">
                <div class="bg-gradient-to-r from-violet-600 to-indigo-600 text-white p-4 flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <button @click="mostrarResultsModal = false" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition">
                                <i class="bi bi-arrow-left"></i>
                            </button>
                            <div>
                                <div class="font-bold text-sm">Resultados por Entidad</div>
                                <div class="text-xs opacity-70">
                                    <span x-text="formularioEntidades?.fechaInicio || ''"></span> - <span x-text="formularioEntidades?.fechaFin || ''"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="exportarExcel()"
                                class="bg-emerald-500 hover:bg-emerald-600 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1">
                                <i class="bi bi-file-earmark-excel"></i> EXCEL
                            </button>
                            <button @click="imprimirResultsModal()"
                                class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1">
                                <i class="bi bi-printer"></i> IMPRIMIR
                            </button>
                            <button @click="mostrarModalEntidades = false" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex-1 overflow-hidden flex flex-col">
                    <div class="max-w-6xl mx-auto w-full p-4 flex flex-col h-full">
                        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4 border border-slate-200/60 flex-shrink-0">
                            <div class="grid grid-cols-4 gap-4">
                                <div class="text-center">
                                    <div class="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-building text-violet-500 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800" x-text="resultadosEntidades?.total_fechas || 0">0</div>
                                    <div class="text-[10px] text-slate-400 uppercase font-medium">Fechas</div>
                                </div>
                                <div class="text-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-people-fill text-blue-500 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800" x-text="resultadosEntidades?.total_registros || 0">0</div>
                                    <div class="text-[10px] text-slate-400 uppercase font-medium">Exámenes</div>
                                </div>
                                <div class="text-center">
                                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-calendar-check text-emerald-500 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800" x-text="resultadosEntidades?.resultados?.length || 0">0</div>
                                    <div class="text-[10px] text-slate-400 uppercase font-medium">Días</div>
                                </div>
                                <div class="text-center">
                                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-funnel text-amber-500 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800 truncate" x-text="formularioEntidades?.entidad || 'Todas'">-</div>
                                    <div class="text-[10px] text-slate-400 uppercase font-medium">Entidad</div>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto space-y-3 scrollbar-thin">
                            <template x-for="grupo in (resultadosEntidades?.resultados || [])" :key="grupo.fecha">
                                <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-slate-200/60">
                                    <div class="bg-gradient-to-r from-violet-50 to-indigo-50 p-3 border-b border-violet-100/60">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center gap-2">
                                                <i class="bi bi-calendar3 text-violet-400"></i>
                                                <div>
                                                    <div class="font-bold text-violet-800 text-sm">
                                                        <span x-text="new Date(grupo.fecha + 'T00:00:00').toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })"></span>
                                                    </div>
                                                    <div class="text-[11px] text-violet-500"><span x-text="grupo.cantidad"></span> exámenes</div>
                                                </div>
                                            </div>
                                            <button @click="imprimirGrupoFechaModal(grupo.fecha)"
                                                class="bg-violet-600 hover:bg-violet-700 text-white px-2.5 py-1 rounded-lg text-[11px] font-bold transition">
                                                <i class="bi bi-printer mr-1"></i>Día
                                            </button>
                                        </div>
                                    </div>
                                    <div class="p-3">
                                        <div class="space-y-2">
                                            <template x-for="(examen, index) in grupo.examenes" :key="examen.identificacion + '_' + examen.examen_codigo">
                                                <div>
                                                    <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl hover:bg-slate-100/80 transition-colors">
                                                        <div class="flex items-center gap-2.5 flex-1 min-w-0">
                                                            <div class="w-2 h-2 bg-violet-400 rounded-full flex-shrink-0"></div>
                                                            <div class="min-w-0">
                                                                <div class="font-medium text-slate-800 text-sm truncate" x-text="examen.paciente"></div>
                                                                <div class="text-[11px] text-slate-400 flex items-center gap-1.5">
                                                                    <span class="font-mono bg-slate-200/80 px-1 py-0.5 rounded text-[10px]" x-text="examen.identificacion"></span>
                                                                    <span><i class="bi bi-calendar3"></i> <span x-text="Math.round(parseFloat(examen.edad)) + 'a'"></span></span>
                                                                    <span x-text="examen.genero"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                                            <div class="text-right mr-1">
                                                                <div class="text-[11px] font-medium text-slate-700 truncate max-w-[140px]" x-text="examen.examen_nombre"></div>
                                                                <div class="text-[10px] text-slate-400" x-text="examen.tipo_procedimiento || examen.examen_tipo"></div>
                                                            </div>
                                                            <button @click="toggleResultadoMini(examen, index)"
                                                                class="p-1.5 rounded-lg transition-colors"
                                                                :class="examen.mostrarResultado ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-200/80 text-slate-500 hover:bg-emerald-50 hover:text-emerald-500'"
                                                                :title="examen.mostrarResultado ? 'Ocultar' : 'Ver resultado'">
                                                                <i class="bi bi-file-text text-xs"></i>
                                                            </button>
                                                            <button @click="verExamenIndividual(examen)"
                                                                class="p-1.5 rounded-lg bg-indigo-100 text-indigo-600 hover:bg-indigo-200 transition-colors"
                                                                title="Ver completo">
                                                                <i class="bi bi-eye-fill text-xs"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div x-show="examen.mostrarResultado"
                                                        x-transition:enter="transition ease-out duration-200"
                                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                                        x-transition:enter-end="opacity-100 translate-y-0"
                                                        x-transition:leave="transition ease-in duration-150"
                                                        class="ml-6 mt-1 p-2.5 bg-gradient-to-br from-emerald-50 to-green-50 border border-emerald-200/60 rounded-xl">
                                                        <div class="flex items-center gap-1.5 mb-1.5">
                                                            <i class="bi bi-file-medical text-emerald-500 text-xs"></i>
                                                            <div class="font-semibold text-emerald-800 text-xs">Resultado:</div>
                                                            <button @click="examen.mostrarResultado = false" class="ml-auto text-emerald-400 hover:text-emerald-600">
                                                                <i class="bi bi-x text-xs"></i>
                                                            </button>
                                                        </div>
                                                        <div class="text-[11px] space-y-1 text-emerald-700">
                                                            <div class="flex justify-between"><span class="font-medium">Valor:</span> <span class="font-bold text-emerald-900" x-text="examen.resultado || 'N/A'"></span></div>
                                                            <div class="flex justify-between" x-show="examen.referencia && examen.referencia !== 'N/A'"><span class="font-medium">Ref:</span> <span x-text="examen.referencia"></span></div>
                                                            <div class="flex justify-between"><span class="font-medium">Estado:</span> <span x-text="examen.estado || 'Pendiente'"></span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL BUSQUEDA PACIENTES -->
    <div x-show="mostrarModalPacientesBusqueda" x-cloak
        class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4 z-[60]"
        @click.self="cerrarModalPacientesBusqueda()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-3xl shadow-2xl max-w-6xl w-full h-[85vh] flex flex-col overflow-hidden"
            @click.stop
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0">
            <!-- Formulario -->
            <div x-show="!mostrarResultsPacientesBusqueda" class="flex flex-col h-full">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-6 flex-shrink-0">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-3">
                            <img src="icons/thiings/stethoscope.png" alt="" class="w-10 h-10 object-contain thiings-icon">
                            <h3 class="text-xl font-bold">Búsqueda de Pacientes</h3>
                        </div>
                        <button @click="cerrarModalPacientesBusqueda()" class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="text-emerald-200 text-sm">Busque por identificación, nombres, teléfono, ciudad o entidad</p>
                </div>
                <form @submit.prevent="buscarPacientes()" class="p-6 space-y-5 overflow-y-auto flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Identificación</label>
                            <input type="text" x-model="formularioPacientesBusqueda.identificacion" placeholder="Documento del paciente"
                                class="w-full px-4 py-3 border-2 border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nombres Completos</label>
                            <input type="text" x-model="formularioPacientesBusqueda.nombres" placeholder="Nombres y apellidos"
                                class="w-full px-4 py-3 border-2 border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition text-sm">
                        </div>
                    </div>
                    <button type="submit"
                        class="btn-success w-full text-white py-3.5 rounded-2xl font-bold text-sm tracking-wide shadow-lg shadow-emerald-200 flex items-center justify-center gap-2">
                        <i class="bi bi-search"></i> BUSCAR PACIENTES
                    </button>
                </form>
            </div>
            <!-- Resultados busqueda -->
            <div x-show="mostrarResultsPacientesBusqueda" class="flex flex-col h-full">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-4 flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="bg-white/20 p-2 rounded-xl">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">Resultados</h3>
                                <p class="text-emerald-200 text-xs" x-text="`${resultadosPacientesBusqueda?.total_pacientes || 0} pacientes encontrados`"></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button @click="exportarPacientesExcel()"
                                class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1">
                                <i class="bi bi-file-earmark-excel"></i> EXCEL
                            </button>
                            <button @click="volverFormularioBusqueda()"
                                class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1">
                                <i class="bi bi-arrow-left"></i> VOLVER
                            </button>
                            <button @click="cerrarModalPacientesBusqueda()" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-4 scrollbar-thin">
                    <div x-show="cargandoPacientesBusqueda" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <div class="w-12 h-12 border-4 border-emerald-200 border-t-emerald-500 rounded-full animate-spin mx-auto"></div>
                            <p class="mt-4 text-slate-500 text-sm">Buscando pacientes...</p>
                        </div>
                    </div>
                    <div x-show="!cargandoPacientesBusqueda && resultadosPacientesBusqueda?.pacientes?.length > 0" class="space-y-4 max-w-6xl mx-auto">
                        <template x-for="(paciente, index) in (resultadosPacientesBusqueda?.pacientes || [])" :key="paciente.identificacion">
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/60 overflow-hidden card-hover"
                                :class="{'!border-emerald-300 ring-2 ring-emerald-500/10': pacienteSeleccionado?.identificacion === paciente.identificacion}">
                                <div class="bg-gradient-to-r from-emerald-50/80 to-teal-50/80 p-4 border-b border-emerald-100/60">
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="flex gap-3">
                                            <div class="w-11 h-11 bg-emerald-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                                                <i class="bi bi-person-fill text-emerald-500 text-lg"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-800" x-text="paciente.nombre_completo"></h4>
                                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-600" x-text="paciente.identificacion"></span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-blue-100 text-blue-600" x-text="`${paciente.edad} años`"></span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-500" x-text="paciente.genero || 'N/A'"></span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-100 text-emerald-600" x-text="paciente.telefono || 'Sin teléfono'"></span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-0.5 text-[11px] text-slate-500">
                                                    <div class="flex items-center gap-1"><i class="bi bi-envelope text-emerald-400"></i> <span x-text="paciente.correo || 'Sin correo'"></span></div>
                                                    <div class="flex items-center gap-1"><i class="bi bi-geo-alt text-emerald-400"></i> <span x-text="`${paciente.ciudad_residencia || 'N/A'} - ${paciente.direccion_residencia || 'N/A'}`"></span></div>
                                                    <div class="flex items-center gap-1"><i class="bi bi-building text-emerald-400"></i> <span x-text="paciente.entidad || 'Sin entidad'"></span></div>
                                                </div>
                                            </div>
                                        </div>
                                        <button @click="seleccionarPaciente(paciente, index)"
                                            class="btn-success text-white px-3 py-1.5 rounded-xl text-xs font-bold flex-shrink-0">
                                            <i class="bi bi-eye mr-1"></i> VER
                                        </button>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                                        <div class="text-center bg-slate-50 rounded-xl py-2"><div class="text-lg font-bold text-slate-800" x-text="paciente.total_visitas || 0"></div><div class="text-[10px] text-slate-400 uppercase font-medium">Visitas</div></div>
                                        <div class="text-center bg-slate-50 rounded-xl py-2"><div class="text-lg font-bold text-slate-800" x-text="paciente.total_examenes || 0"></div><div class="text-[10px] text-slate-400 uppercase font-medium">Exámenes</div></div>
                                        <div class="text-center bg-slate-50 rounded-xl py-2"><div class="text-lg font-bold text-emerald-600" x-text="paciente.examenes_con_resultados || 0"></div><div class="text-[10px] text-slate-400 uppercase font-medium">Con resultados</div></div>
                                        <div class="text-center bg-slate-50 rounded-xl py-2"><div class="text-sm font-bold text-slate-700" x-text="paciente.ultima_visita || 'N/A'"></div><div class="text-[10px] text-slate-400 uppercase font-medium">Última visita</div></div>
                                    </div>
                                    <div x-show="paciente.examenes && paciente.examenes.length > 0">
                                        <button @click="toggleExamenExpandido(index)"
                                            class="w-full bg-slate-50 hover:bg-slate-100 px-3 py-2 rounded-xl text-sm font-medium text-slate-600 transition flex items-center justify-between mb-2">
                                            <span>Exámenes (<span x-text="paciente.examenes.length"></span>)</span>
                                            <i class="bi" :class="examenesExpandidos[index] ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        </button>
                                        <div x-show="examenesExpandidos[index]" x-transition class="space-y-1.5">
                                            <template x-for="(examen, ei) in paciente.examenes" :key="ei">
                                                <div class="bg-slate-50 rounded-xl p-2.5 flex items-center justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-slate-700 text-sm truncate" x-text="examen.nombre"></div>
                                                        <div class="text-[11px] text-slate-400"><span x-text="examen.fecha"></span> · <span x-text="examen.entidad || 'Sin entidad'"></span></div>
                                                    </div>
                                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                                        <span class="text-[10px] px-2 py-0.5 rounded-lg font-bold"
                                                            :class="examen.estado === 'Completado' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                                            x-text="examen.estado || 'Pendiente'"></span>
                                                        <button @click="verExamenPaciente(examen, paciente)" class="p-1.5 rounded-lg bg-indigo-100 text-indigo-600 hover:bg-indigo-200 transition" title="Ver">
                                                            <i class="bi bi-eye-fill text-xs"></i>
                                                        </button>
                                                        <button @click="exportarExamenesPacienteExcel(paciente)" class="p-1.5 rounded-lg bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition" title="Exportar">
                                                            <i class="bi bi-file-earmark-excel text-xs"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="!cargandoPacientesBusqueda && (!resultadosPacientesBusqueda?.pacientes || resultadosPacientesBusqueda.pacientes.length === 0)" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <img src="icons/thiings/patient.png" alt="" class="w-24 h-24 object-contain mx-auto mb-4 thiings-icon opacity-70">
                            <h3 class="text-lg font-bold text-slate-700 mb-1">No se encontraron pacientes</h3>
                            <p class="text-slate-500 text-sm">Intente con otros criterios de búsqueda</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('labApp', (idInicial) => ({
                idAbierto: idInicial || null,
                urlReporte: null,
                vistaReporte: false,
                nombreReporte: '',
                mostrarModalEntidades: false,
                mostrarResultsModal: false,
                formularioEntidades: {
                    entidad: '',
                    fechaInicio: '<?= date('Y-m-d') ?>',
                    fechaFin: '<?= date('Y-m-d') ?>',
                    soloConResultados: false,
                    agruparPorFecha: true
                },
                resultadosEntidades: null,
                mostrarModalPacientesBusqueda: false,
                mostrarResultsPacientesBusqueda: false,
                formularioPacientesBusqueda: {
                    identificacion: '', nombres: '', telefono: '', ciudad: '', entidad: '',
                    includeExamenes: true, soloConResultados: false, limit: 50
                },
                resultadosPacientesBusqueda: null,
                pacienteSeleccionado: null,
                examenesExpandidos: {},
                cargandoPacientesBusqueda: false,

                init() {
                    this.$nextTick(() => this.toggleFechaInput());
                },
                cargarReporte(url) {
                    this.urlReporte = url;
                    this.vistaReporte = true;
                    this.nombreReporte = 'Cargando...';
                    if (window.innerWidth < 768) this.idAbierto = null;
                },
                cargarNombreReporte() {
                    try {
                        const frame = document.getElementById('frameReporte');
                        if (frame && frame.contentDocument) {
                            const title = frame.contentDocument.title || frame.contentDocument.querySelector('h1, h2')?.textContent || 'Reporte';
                            this.nombreReporte = title.substring(0, 50);
                        }
                    } catch (e) { this.nombreReporte = 'Reporte de exámenes'; }
                },
                enviarWhatsapp(tel, urlCodificada) {
                    if (!tel || tel.trim() === '' || tel === '0') {
                        Swal.fire({ icon:'warning', title:'Sin teléfono', text:'Este paciente no tiene celular registrado.', confirmButtonColor:'#4f46e5', background:'#f8fafc', color:'#1e293b' });
                        return;
                    }
                    const fullUrl = `${window.location.origin}/${decodeURIComponent(urlCodificada)}`;
                    navigator.clipboard.writeText(fullUrl).then(() => {
                        Swal.fire({ icon:'success', title:'Enlace copiado', text:'Redirigiendo a WhatsApp...', showConfirmButton:false, timer:2000, background:'#f8fafc', color:'#1e293b' });
                        setTimeout(() => {
                            window.open(`https://wa.me/${tel}?text=${encodeURIComponent('Hola, envío tus resultados de laboratorio. Puedes verlos en: ' + fullUrl)}`, '_blank', 'noopener,noreferrer');
                        }, 2000);
                    }).catch(() => {
                        window.open(`https://wa.me/${tel}?text=${encodeURIComponent('Hola, envío tus resultados de laboratorio. Puedes verlos en: ' + fullUrl)}`, '_blank', 'noopener,noreferrer');
                    });
                },
                imprimirFrame() {
                    const iframe = document.getElementById('frameReporte');
                    if (iframe && iframe.contentWindow) { iframe.contentWindow.focus(); iframe.contentWindow.print(); }
                    else { Swal.fire({ icon:'error', title:'Error', text:'No se puede acceder al contenido para imprimir.', confirmButtonText:'OK' }); }
                },
                abrirModalEntidades() {
                    this.mostrarModalPacientesBusqueda = false;
                    this.mostrarModalEntidades = true;
                    this.mostrarResultsModal = false;
                    this.formularioEntidades = { entidad:'', fechaInicio:'<?= date('Y-m-d') ?>', fechaFin:'<?= date('Y-m-d') ?>', soloConResultados:false, agruparPorFecha:true };
                    this.resultadosEntidades = null;
                },
                async actualizarEntidad(selectElement) {
                    const { identificacion, fechaExamen: fecha_examen, codexamen } = selectElement.dataset;
                    const nueva_entidad = selectElement.value;
                    if (!identificacion || !fecha_examen || !codexamen) {
                        Swal.fire('Error', 'Datos incompletos para la actualización.', 'error'); return;
                    }
                    const result = await Swal.fire({
                        title: '¿Actualizar entidad?',
                        text: `Examen ${codexamen} (${identificacion} - ${fecha_examen})`,
                        icon: 'question', showCancelButton: true, confirmButtonColor:'#4f46e5', cancelButtonColor:'#94a3b8',
                        confirmButtonText: 'Sí, actualizar', cancelButtonText: 'Cancelar'
                    });
                    if (result.isConfirmed) {
                        try {
                            const response = await fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                                body: new URLSearchParams({ action:'actualizar_entidad', identificacion, fecha_examen, codexamen, entidad: nueva_entidad })
                            });
                            const data = await response.json();
                            if (data.success) Swal.fire('Actualizado!', data.message, 'success');
                            else Swal.fire('Error', data.message, 'error');
                        } catch (error) { Swal.fire('Error', 'Error al comunicarse con el servidor.', 'error'); }
                    }
                },
                async consultarPorEntidades() {
                    if (!this.formularioEntidades.fechaInicio || !this.formularioEntidades.fechaFin) {
                        Swal.fire({ icon:'warning', title:'Fechas requeridas', text:'Seleccione el rango de fechas.', confirmButtonColor:'#4f46e5' }); return;
                    }
                    if (new Date(this.formularioEntidades.fechaFin) < new Date(this.formularioEntidades.fechaInicio)) {
                        Swal.fire({ icon:'warning', title:'Fechas inválidas', text:'La fecha fin no puede ser anterior a la inicio.', confirmButtonColor:'#4f46e5' }); return;
                    }
                    Swal.fire({ title:'Consultando...', text:'Obteniendo resultados', allowOutsideClick:false, showConfirmButton:false, willOpen:() => Swal.showLoading() });
                    const formData = new FormData();
                    formData.append('action', 'consulta_entidades');
                    formData.append('entidad', this.formularioEntidades.entidad);
                    formData.append('fecha_inicio', this.formularioEntidades.fechaInicio);
                    formData.append('fecha_fin', this.formularioEntidades.fechaFin);
                    formData.append('solo_resultados', this.formularioEntidades.soloConResultados ? '1' : '0');
                    formData.append('agrupar_fecha', this.formularioEntidades.agruparPorFecha ? '1' : '0');
                    try {
                        const response = await fetch(window.location.href, { method:'POST', body: formData });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        const data = await response.json();
                        Swal.close();
                        if (data.success) { this.resultadosEntidades = data; this.mostrarResultsModal = true; }
                        else Swal.fire('Error', data.message, 'error');
                    } catch (error) { Swal.close(); Swal.fire('Error', 'Error al conectar con el servidor.', 'error'); }
                },
                exportarExcel() {
                    if (!this.resultadosEntidades?.resultados) { Swal.fire({icon:'warning',title:'Sin datos',text:'No hay resultados para exportar.',confirmButtonColor:'#4f46e5'}); return; }
                    let dataForExport = [];
                    if (this.formularioEntidades.agruparPorFecha) {
                        this.resultadosEntidades.resultados.forEach(g => g.examenes.forEach(e => dataForExport.push(e)));
                    } else dataForExport = this.resultadosEntidades.resultados;
                    if (!dataForExport.length) { Swal.fire('Info','No hay datos para exportar.','info'); return; }
                    const headers = ["Identificación","Paciente","Edad","Género","Teléfono","Fecha","Entidad","Examen","Código","Tipo","Resultado","Referencia","Estado"];
                    const rows = dataForExport.map(e => [e.identificacion,e.paciente,e.edad,e.genero,e.telefono,e.fecha_examen,e.entidad,e.examen_nombre,e.examen_codigo,e.examen_tipo,e.resultado,e.referencia,e.estado]);
                    const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Resultados");
                    XLSX.writeFile(wb, "resultados_entidades.xlsx");
                    Swal.fire('Exportado','Exportado a Excel.','success');
                },
                imprimirResultsModal() {
                    let html = '<style>body{font-family:sans-serif;margin:20px}h1{text-align:center;color:#4f46e5;font-size:18px}table{width:100%;border-collapse:collapse;margin:15px 0}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;font-size:11px}th{background:#f8fafc;font-weight:700}.hdr{background:#4f46e5;color:#fff;padding:10px;text-align:center}.grp{background:#eef2ff;font-weight:700}</style>';
                    html += `<div class="hdr"><h1>Consulta por Entidad</h1><p>${this.formularioEntidades.fechaInicio} - ${this.formularioEntidades.fechaFin} | ${this.formularioEntidades.entidad||'Todas'}</p></div><br>`;
                    const addTable = (items) => {
                        html += '<table><thead><tr><th>ID</th><th>Paciente</th><th>Edad</th><th>Género</th><th>Entidad</th><th>Examen</th><th>Resultado</th><th>Estado</th></tr></thead><tbody>';
                        items.forEach(e => html += `<tr><td>${e.identificacion}</td><td>${e.paciente}</td><td>${Math.round(parseFloat(e.edad))}</td><td>${e.genero}</td><td>${e.entidad}</td><td>${e.examen_nombre}</td><td>${e.resultado||'N/A'}</td><td>${e.estado||'N/A'}</td></tr>`);
                        html += '</tbody></table>';
                    };
                    if (this.formularioEntidades.agruparPorFecha) {
                        this.resultadosEntidades.resultados.forEach(g => {
                            html += `<div class="grp">Fecha: ${new Date(g.fecha+'T00:00:00').toLocaleDateString('es-CO',{weekday:'long',year:'numeric',month:'long',day:'numeric'})} (${g.cantidad})</div>`;
                            addTable(g.examenes);
                        });
                    } else addTable(this.resultadosEntidades.resultados);
                    const w = window.open('','_blank'); w.document.write(html); w.document.close(); w.print();
                },
                imprimirGrupoFechaModal(fecha) {
                    const g = this.resultadosEntidades.resultados.find(x => x.fecha === fecha);
                    if (!g) { Swal.fire('Error','No se encontraron exámenes.','error'); return; }
                    let html = '<style>body{font-family:sans-serif;margin:20px}h1{text-align:center;color:#4f46e5;font-size:18px}table{width:100%;border-collapse:collapse;margin:15px 0}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;font-size:11px}th{background:#f8fafc;font-weight:700}.hdr{background:#4f46e5;color:#fff;padding:10px;text-align:center}</style>';
                    html += `<div class="hdr"><h1>${new Date(g.fecha+'T00:00:00').toLocaleDateString('es-CO',{weekday:'long',year:'numeric',month:'long',day:'numeric'})}</h1><p>${this.formularioEntidades.entidad||'Todas'} - ${g.cantidad} exámenes</p></div>`;
                    html += '<table><thead><tr><th>ID</th><th>Paciente</th><th>Edad</th><th>Género</th><th>Entidad</th><th>Examen</th><th>Resultado</th><th>Estado</th></tr></thead><tbody>';
                    g.examenes.forEach(e => html += `<tr><td>${e.identificacion}</td><td>${e.paciente}</td><td>${Math.round(parseFloat(e.edad))}</td><td>${e.genero}</td><td>${e.entidad}</td><td>${e.examen_nombre}</td><td>${e.resultado||'N/A'}</td><td>${e.estado||'N/A'}</td></tr>`);
                    html += '</tbody></table>';
                    const w = window.open('','_blank'); w.document.write(html); w.document.close(); w.print();
                },
                toggleFechaInput() {
                    this.$nextTick(() => {
                        const cb = document.getElementById('buscar-todas');
                        const fc = document.getElementById('fecha-container');
                        if (cb && fc) fc.style.display = cb.checked ? 'none' : 'block';
                    });
                },
                verExamenIndividual(examen) {
                    const q = new URLSearchParams({
                        idx: examen.identificacion+'_'+examen.fecha_examen+'_'+examen.examen_codigo,
                        identificacion: examen.identificacion, fecha: examen.fecha_examen,
                        nombres: examen.paciente, tabla: examen.examen_tabla,
                        info: examen.examen_nombre, tipo: examen.examen_tipo,
                        codexamen: examen.examen_codigo, edad: examen.edad, embedido:1, ver:1
                    }).toString();
                    this.cargarReporte(`printphp/print_examen.php?${q}`);
                    this.mostrarModalEntidades = false;
                },
                abrirModalPacientesBusqueda() {
                    this.mostrarModalEntidades = false;
                    this.formularioPacientesBusqueda = { identificacion:'',nombres:'',telefono:'',ciudad:'',entidad:'',includeExamenes:true,soloConResultados:false,limit:50 };
                    this.resultadosPacientesBusqueda = null;
                    this.pacienteSeleccionado = null;
                    this.examenesExpandidos = {};
                    this.mostrarModalPacientesBusqueda = true;
                    this.mostrarResultsPacientesBusqueda = false;
                },
                cerrarModalPacientesBusqueda() {
                    this.mostrarModalPacientesBusqueda = false;
                    this.resultadosPacientesBusqueda = null;
                    this.pacienteSeleccionado = null;
                    this.examenesExpandidos = {};
                },
                async buscarPacientes() {
                    const f = this.formularioPacientesBusqueda;
                    if (!f.identificacion.trim() && !f.nombres.trim() && !f.telefono.trim() && !f.ciudad.trim() && !f.entidad.trim()) {
                        Swal.fire('Advertencia','Ingrese al menos un criterio de búsqueda.','warning'); return;
                    }
                    this.cargandoPacientesBusqueda = true;
                    this.resultadosPacientesBusqueda = null;
                    this.pacienteSeleccionado = null;
                    const formData = new FormData();
                    formData.append('action', 'consulta_pacientes_resultados');
                    formData.append('identificacion', f.identificacion);
                    formData.append('nombres', f.nombres);
                    formData.append('telefono', f.telefono);
                    formData.append('ciudad', f.ciudad);
                    formData.append('entidad', f.entidad);
                    formData.append('include_examenes', f.includeExamenes ? '1' : '0');
                    formData.append('solo_con_resultados', f.soloConResultados ? '1' : '0');
                    formData.append('limit', f.limit);
                    try {
                        const response = await fetch(window.location.href, { method:'POST', body: formData });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        const data = await response.json();
                        if (data.success) {
                            this.resultadosPacientesBusqueda = data;
                            this.mostrarResultsPacientesBusqueda = true;
                            data.pacientes.forEach((_, i) => this.examenesExpandidos[i] = false);
                        } else Swal.fire('Error', data.message, 'error');
                    } catch (error) { Swal.fire('Error', 'Error al conectar con el servidor.', 'error'); }
                    finally { this.cargandoPacientesBusqueda = false; }
                },
                volverFormularioBusqueda() { this.mostrarResultsPacientesBusqueda = false; },
                seleccionarPaciente(paciente, index) {
                    const esMismo = this.pacienteSeleccionado?.identificacion === paciente.identificacion;
                    this.pacienteSeleccionado = esMismo ? null : paciente;
                    if (index !== undefined) this.examenesExpandidos[index] = !esMismo;
                },
                toggleExamenExpandido(i) { this.examenesExpandidos[i] = !this.examenesExpandidos[i]; },
                verExamenPaciente(examen, paciente) {
                    const params = new URLSearchParams({
                        idx: Math.random().toString(36).substring(2,15),
                        identificacion: paciente.identificacion, fecha: examen.fecha,
                        nombres: paciente.nombre_completo, tabla: examen.tabla,
                        info: examen.nombre, tipo: examen.tipo, codexamen: examen.codigo,
                        edad: paciente.edad, embedido:1, ver:1
                    });
                    this.mostrarModalPacientesBusqueda = false;
                    this.cargarReporte("printphp/print_examen.php?" + params.toString());
                },
                exportarPacientesExcel() {
                    if (!this.resultadosPacientesBusqueda?.pacientes?.length) { Swal.fire('Info','No hay datos para exportar','info'); return; }
                    const ws = XLSX.utils.aoa_to_sheet([
                        ['ID','Nombre','Edad','Género','Teléfono','Email','Ciudad','Entidad','Visitas','Última Visita','Exámenes'],
                        ...this.resultadosPacientesBusqueda.pacientes.map(p => [p.identificacion,p.nombre_completo,p.edad,p.genero,p.telefono,p.correo,p.ciudad_residencia,p.entidad,p.total_visitas,p.ultima_visita,p.total_examenes])
                    ]);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Pacientes');
                    XLSX.writeFile(wb, `pacientes_${new Date().toISOString().split('T')[0]}.xlsx`);
                },
                exportarExamenesPacienteExcel(paciente) {
                    if (!paciente.examenes?.length) { Swal.fire('Info','Sin exámenes para exportar','info'); return; }
                    const ws = XLSX.utils.aoa_to_sheet([
                        ['ID','Paciente','Fecha','Código','Examen','Tipo','Entidad','Estado'],
                        ...paciente.examenes.map(e => [paciente.identificacion,paciente.nombre_completo,e.fecha,e.codigo,e.nombre,e.tipo,e.entidad,e.estado])
                    ]);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Exámenes');
                    XLSX.writeFile(wb, `examenes_${paciente.identificacion}_${new Date().toISOString().split('T')[0]}.xlsx`);
                }
            }));
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const el = document.querySelector('[x-data]');
                if (el && el.__x) {
                    const scope = el.__x.$data;
                    if (scope.mostrarModalEntidades) scope.mostrarModalEntidades = false;
                    if (scope.mostrarModalPacientesBusqueda) scope.cerrarModalPacientesBusqueda();
                }
            }
        });
    </script>
</body>
</html>
