<?php
session_start();
require_once 'datos_conexion.php';
// --- 1. LÓGICA DE LOGIN DESDE BASE DE DATOS ---
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
// Lógica de Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}
// --- 2. VISTA DE LOGIN ---
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
    </head>

    <body class="bg-slate-900 h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-md border-t-4 border-indigo-600">
            <div class="text-center mb-8">
                <div class="bg-indigo-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-shield-lock-fill text-3xl text-indigo-600"></i>
                    <img src="printphp/logo.png" alt="Logo" class="w-10 h-10">
                </div>
                <h1 class="text-2xl font-bold text-slate-800">Laboratorio Clínico</h1>
                <p class="text-slate-500 text-sm">Ingrese su clave de configuración</p>
            </div>
            <?php if ($error): ?>
                <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm flex items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Usuario / Clave</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <i class="bi bi-key"></i>
                        </span>
                        <input type="password" name="password" placeholder="Ingrese contraseña" required
                            class="w-full pl-10 p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    </div>
                </div>
                <button type="submit" name="login"
                    class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                    INICIAR SESIÓN
                </button>
            </form>
        </div>
    </body>

    </html>
    <?php
    exit;
endif;
// --- 3. CÓDIGO DEL PANEL DE RESULTADOS ---
$script_actual = htmlspecialchars(basename($_SERVER['PHP_SELF']));
// Actualizar edad de pacientes
$mysqli->query("UPDATE paciente SET edad = ROUND((TO_DAYS(CURDATE()) - TO_DAYS(fecnac)) / 365.242199, 2) WHERE fecnac IS NOT NULL");
$fecha = $_GET['fecha'] ?? date("Y-m-d");
$identificacion_inicial = $_GET['identificacion'] ?? '';
$busqueda = $_GET['buscar'] ?? '';
$todos = isset($_GET['todos']) ? true : false; // Nuevo parámetro para buscar en todas las fechas

function generateRandomString($length = 180)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Preparar la consulta SQL con filtros
$params = [];
$param_types = "";
$where_conditions = "1=1"; // Condición base siempre verdadera

// Si NO estamos buscando en todas las fechas, aplicar filtro de fecha
if (!$todos || empty($busqueda)) {
    $where_conditions .= " AND e.fecha = ?";
    $params[] = $fecha;
    $param_types .= "s";
}

// Aplicar filtro de búsqueda si existe
if (!empty($busqueda)) {
    $busqueda_like = "%" . $busqueda . "%";
    $where_conditions .= " AND (e.identificacion LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)";
    $params = array_merge($params, [$busqueda_like, $busqueda_like, $busqueda_like]);
    $param_types .= "sss";
}

$sql = "SELECT e.identificacion, e.fecha as fecha_examen,
CONCAT_WS(' ', p.apellidos, p.nombres) as nombres,
p.edad, p.correo, p.telefono, p.genero, p.fecnac
FROM examenes e
INNER JOIN paciente p ON e.identificacion = p.identificacion
WHERE $where_conditions
GROUP BY e.identificacion, e.fecha
ORDER BY e.fecha DESC, p.apellidos ASC, p.nombres ASC
LIMIT 100"; // Limitar resultados para evitar sobrecarga

// Usar consulta preparada para seguridad
$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$resultados = $stmt->get_result();

// Obtener configuración del laboratorio
$res_conf = $mysqli->query("SELECT nombreCorto, nombreLaboratorio, urlLogoLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
$dato_conf = $res_conf->fetch_assoc();
$nombreLab = $dato_conf['nombreLaboratorio'] ?? 'Laboratorio Clínico';
$nombreCorto = $dato_conf['nombreCorto'] ?? 'LAB';
$urlLogo = "data:image/png;base64," . $dato_conf['urlLogoLaboratorio'] ?? '';

// Obtener estadísticas
$total_pacientes = $resultados->num_rows;
$hoy = date("Y-m-d");
$es_hoy = ($fecha == $hoy);

// *** NUEVO: Obtener lista de entidades ***
$res_entidades = $mysqli->query("SELECT id, nombre FROM entidades ORDER BY nombre ASC");
$entidades = [];
if ($res_entidades && $res_entidades->num_rows > 0) {
    while ($row = $res_entidades->fetch_assoc()) {
        $entidades[] = $row;
    }
}



// *** NUEVO: Manejo de la actualización de entidad via AJAX ***
if (isset($_POST['action']) && $_POST['action'] == 'actualizar_entidad') {
    $identificacion = $_POST['identificacion'] ?? '';
    $fecha_examen = $_POST['fecha_examen'] ?? '';

    if (!empty($identificacion) && !empty($fecha_examen)) {
        $stmt_update = $mysqli->prepare("UPDATE examenes SET entidad = ? WHERE identificacion = ? AND fecha = ?");
        $nueva_entidad = $_POST['entidad'] ?? '';
        $stmt_update->bind_param("sss", $nueva_entidad, $identificacion, $fecha_examen);
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

// *** NUEVO: Manejo de consulta por entidades ***
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



    // Verificar si existe la tabla procedimientos
    $procedimientos_exists = $mysqli->query("SHOW TABLES LIKE 'procedimientos'")->num_rows > 0;



    // Verificar si existe la tabla procedimientos
    $procedimientos_exists = $mysqli->query("SHOW TABLES LIKE 'procedimientos'")->num_rows > 0;

    // Construir consulta SQL con la estructura real de tablas
    if ($procedimientos_exists) {
        $sql = "SELECT 
            e.identificacion, 
            e.fecha as fecha_examen,
            e.entidad,
            e.codexamen,
            e.realizado,
            p.nombres, 
            p.apellidos, 
            p.edad, 
            p.telefono, 
            p.genero,
            pr.nombre as nombre_examen,
            pr.codigo as codigo_examen,
            pr.tabla as examen_tabla,
            pr.tipo as tipo_examen,
            pr.tipoprocedimiento as tipo_procedimiento,
            pr.abreviatura
        FROM examenes e
        INNER JOIN paciente p ON e.identificacion = p.identificacion
        LEFT JOIN procedimientos pr ON e.codexamen = pr.codigo
        WHERE e.fecha BETWEEN ? AND ?";

        // Agregar filtro de entidad solo si se especificó
        $params = [$fecha_inicio, $fecha_fin];
        $param_types = "ss";

        if (!empty($entidad)) {
            $sql .= " AND TRIM(e.entidad) = TRIM(?)";
            $params[] = $entidad;
            $param_types .= "s";
        }


    } else {
        // Si no existe procedimientos, consulta sinJOIN
        $sql = "SELECT 
            e.identificacion, 
            e.fecha as fecha_examen,
            e.entidad,
            e.codexamen,
            e.realizado,
            p.nombres, 
            p.apellidos, 
            p.edad, 
            p.telefono, 
            p.genero
        FROM examenes e
        INNER JOIN paciente p ON e.identificacion = p.identificacion
        WHERE e.fecha BETWEEN ? AND ?";

        // Agregar filtro de entidad solo si se especificó
        $params = [$fecha_inicio, $fecha_fin];
        $param_types = "ss";

        if (!empty($entidad)) {
            $sql .= " AND TRIM(e.entidad) = TRIM(?)";
            $params[] = $entidad;
            $param_types .= "s";
        }


    }



    // Filtro adicional para solo exámenes con resultados (comentado hasta tener tabla resultados)
    if ($solo_resultados == '1') {
        // $sql .= " AND EXISTS (SELECT 1 FROM resultados r WHERE r.identificacion = e.identificacion AND r.codexamen = e.codexamen AND r.fecha = e.fecha)";
        // Por ahora, filtraremos por exámenes realizados
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
        // Construir nombre completo del paciente
        $nombres_completos = trim(($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? ''));
        if (empty($nombres_completos)) {
            // Si no hay nombres/apellidos, construir con campos separados
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

        // Agregar datos de procedimientos si existen
        if ($procedimientos_exists) {
            $examen_data['examen_nombre'] = $row['nombre_examen'] ?? $row['abreviatura'] ?? 'Examen #' . $row['codexamen'];
            $examen_data['examen_tipo'] = $row['tipo_examen'] ?? 'No especificado';
            $examen_data['examen_tabla'] = $row['examen_tabla'] ?? '';
            $examen_data['tipo_procedimiento'] = $row['tipo_procedimiento'] ?? '';
            $examen_data['abreviatura'] = $row['abreviatura'] ?? '';

            // Obtener resultado real del examen desde la tabla dinámica
            if (!empty($row['examen_tabla'])) {
                $tabla_resultado = $row['examen_tabla'];

                // Verificar si la tabla existe
                $tabla_existe = $mysqli->query("SHOW TABLES LIKE '$tabla_resultado'")->num_rows > 0;

                if ($tabla_existe) {
                    // Primero obtener la estructura de la tabla para saber qué columnas existen
                    $estructura = $mysqli->query("DESCRIBE `$tabla_resultado`");
                    $columnas = [];
                    while ($col = $estructura->fetch_assoc()) {
                        $columnas[] = $col['Field'];
                    }



                    // Construir WHERE dinámico según las columnas disponibles
                    $where_clauses = [];
                    $where_params = [];
                    $where_types = "";

                    if (in_array('identificacion', $columnas)) {
                        $where_clauses[] = "identificacion = ?";
                        $where_params[] = $row['identificacion'];
                        $where_types .= "s";
                    }

                    // Buscar tanto 'codexamen' como 'examen'
                    if (in_array('codexamen', $columnas)) {
                        $where_clauses[] = "codexamen = ?";
                        $where_params[] = $row['codexamen'];
                        $where_types .= "s";
                    } elseif (in_array('examen', $columnas)) {
                        $where_clauses[] = "examen = ?";
                        $where_params[] = $row['codexamen'];
                        $where_types .= "s";
                    }

                    if (in_array('fecha', $columnas)) {
                        $where_clauses[] = "fecha = ?";
                        $where_params[] = $row['fecha_examen'];
                        $where_types .= "s";
                    }

                    if (empty($where_clauses)) {
                        $examen_data['resultado'] = 'Sin columnas clave';
                        $examen_data['referencia'] = 'N/A';
                        $examen_data['estado'] = 'Error';

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

                                // Extraer el valor principal del resultado según el tipo de tabla
                                $valor_resultado = '';
                                $referencia = '';
                                $estado = '';

                                // Para tablas específicas, extraer valores clave
                                switch ($tabla_resultado) {
                                    case 'examen_tipo_1':
                                        // Valores clave: valoracion, constate
                                        $valor_resultado = $datos_resultado['valoracion'] ?? '';
                                        break;

                                    case 'examen_tipo_2':
                                        // Valores clave: valoracion
                                        $valor_resultado = $datos_resultado['valoracion'] ?? '';
                                        break;

                                    case 'examen_tipo_3':
                                        // Múltiples valores posibles: densidad, color, ph, etc.
                                        $valores_clave = ['densidad', 'color', 'ph', 'proteinas', 'glucosa', 'bilirrubina', 'nitritos', 'leucocitos'];
                                        foreach ($valores_clave as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                            }
                                        }
                                        break;

                                    case 'examen_tipo_5':
                                        // Hemograma: tomar valores principales
                                        $hemograma_clave = ['hemoglobina', 'hematocrito', 'leucocitos', 'WBC', 'RBC', 'PLT'];
                                        foreach ($hemograma_clave as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                            }
                                        }
                                        break;

                                    case 'examen_tipo_7':
                                        // Coagulación: tiempo de protrombina, TPT
                                        $coagulacion_clave = ['tiempo_de_protrombina', 'tiempo_de_control', 'tpts'];
                                        foreach ($coagulacion_clave as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                            }
                                        }
                                        break;

                                    case 'perfilLipidico':
                                        // Perfil lipídico: colesterol, triglicéridos
                                        $lipidos_clave = ['colesterol_total', 'colesterol_hdl', 'colesterol_ldl', 'trigliceridos'];
                                        foreach ($lipidos_clave as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                            }
                                        }
                                        break;

                                    case 'hemogramaRayto':
                                        // Hemograma automatizado
                                        $auto_clave = ['WBC', 'RBC', 'HGB', 'HCT', 'PLT'];
                                        foreach ($auto_clave as $campo) {
                                            if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                                $valor_resultado .= ($valor_resultado ? ', ' : '') . $campo . ': ' . $datos_resultado[$campo];
                                            }
                                        }
                                        break;

                                    default:
                                        // Búsqueda genérica para otras tablas
                                        foreach ($datos_resultado as $columna => $valor) {
                                            if (in_array(strtolower($columna), ['resultado', 'valor', 'result', 'value', 'valoracion']) && !empty($valor)) {
                                                $valor_resultado = $valor;
                                                break;
                                            }
                                        }

                                        // Si no se encuentra resultado específico, tomar el primer valor no básico
                                        if (empty($valor_resultado)) {
                                            $excluir = ['ind', 'identificacion', 'codexamen', 'examen', 'fecha', 'hora', 'id', 'bacteriologo', 'observaciones', 'fechahora', 'fechaResultados'];
                                            foreach ($datos_resultado as $columna => $valor) {
                                                if (!in_array(strtolower($columna), $excluir) && !empty($valor) && $valor !== '0000-00-00' && $valor !== '0' && $valor !== 'N/A') {
                                                    $valor_resultado = $valor;
                                                    break;
                                                }
                                            }
                                        }
                                }

                                // Extraer referencia si existe
                                $ref_campos = ['referencia', 'rango', 'range', 'normal', 'valor_de_referencia'];
                                foreach ($ref_campos as $campo) {
                                    if (!empty($datos_resultado[$campo]) && $datos_resultado[$campo] !== 'N/A') {
                                        $referencia = $datos_resultado[$campo];
                                        break;
                                    }
                                }

                                $examen_data['resultado'] = $valor_resultado ?: 'Sin valor';
                                $examen_data['referencia'] = $referencia ?: 'N/A';
                                $examen_data['estado'] = $estado ?: 'Completado';
                                $examen_data['resultado_completo'] = $datos_resultado;


                            } else {
                                $examen_data['resultado'] = 'Pendiente';
                                $examen_data['referencia'] = 'N/A';
                                $examen_data['estado'] = 'Pendiente';

                            }
                        } catch (Exception $e) {
                            $examen_data['resultado'] = 'Error en consulta';
                            $examen_data['referencia'] = 'N/A';
                            $examen_data['estado'] = 'Error';

                        }
                    }
                } else {
                    $examen_data['resultado'] = 'Tabla no encontrada';
                    $examen_data['referencia'] = 'N/A';
                    $examen_data['estado'] = 'Error';

                }
            } else {
                $examen_data['resultado'] = 'Sin tabla definida';
                $examen_data['referencia'] = 'N/A';
                $examen_data['estado'] = 'Pendiente';
            }
        } else {
            $examen_data['examen_nombre'] = 'Examen #' . $row['codexamen'];
            $examen_data['examen_tipo'] = 'No especificado';
            $examen_data['examen_tabla'] = '';
            $examen_data['tipo_procedimiento'] = '';
            $examen_data['abreviatura'] = '';
            $examen_data['resultado'] = 'No disponible';
            $examen_data['referencia'] = 'N/A';
            $examen_data['estado'] = 'N/A';
        }

        $resultados[] = $examen_data;
    }

    // Agrupar por fecha si se solicita
    if ($agrupar_fecha == '1') {
        $resultados_agrupados = [];
        foreach ($resultados as $resultado) {
            $fecha = $resultado['fecha_examen'];
            if (!isset($resultados_agrupados[$fecha])) {
                $resultados_agrupados[$fecha] = [];
            }
            $resultados_agrupados[$fecha][] = $resultado;
        }

        // Convertir a array numerico para JSON
        $resultado_final = [];
        foreach ($resultados_agrupados as $fecha => $items) {
            $resultado_final[] = [
                'fecha' => $fecha,
                'cantidad' => count($items),
                'examenes' => $items
            ];
        }

        echo json_encode([
            'success' => true,
            'resultados' => $resultado_final,
            'total_registros' => count($resultados),
            'total_fechas' => count($resultado_final)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'resultados' => $resultados,
            'total_registros' => count($resultados)
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($nombreLab) ?> - Resultados
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .scrollbar-thin::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Estilos modernos para el select de entidades */
        .select-entidad {
            @apply px-3 py-2.5 text-sm border-2 border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all duration-200 bg-white shadow-sm hover:shadow-md hover:border-slate-300;
            font-family: inherit;
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 500;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.7rem center;
            background-repeat: no-repeat;
            background-size: 1.2em 1.2em;
            padding-right: 2.2rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            max-width: 100%;
            box-sizing: border-box;
        }

        .select-entidad:focus {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234f46e5' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1), 0 2px 4px -1px rgba(79, 70, 229, 0.06);
        }

        .select-entidad:hover {
            transform: translateY(-1px);
        }

        .select-entidad option {
            @apply text-sm py-2 px-3;
            font-weight: 400;
        }

        .select-entidad option:checked {
            @apply bg-indigo-500 text-white font-semibold;
        }
    </style>
</head>

<body class="bg-slate-100 h-screen flex flex-col overflow-hidden" x-data="labApp('<?= $identificacion_inicial ?>')">
    <header class="bg-gradient-to-r from-indigo-800 to-purple-800 text-white p-3 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <?php if (!empty($urlLogo)): ?>
                    <img src="<?= htmlspecialchars($urlLogo) ?>" alt="Logo" class="h-8 w-8 object-contain">
                <?php else: ?>
                    <div class="h-8 w-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="bi bi-droplet text-xl"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="font-bold text-sm uppercase tracking-wider">
                        <?= htmlspecialchars($nombreLab) ?>
                    </div>
                    <div class="text-xs opacity-80">Panel de Resultados</div>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button @click="abrirModalEntidades()"
                    class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-2 shadow-md"
                    title="Consultar por entidades">
                    <i class="bi bi-building"></i>
                    <span class="hidden sm:inline">ENTIDADES</span>
                </button>
                <div class="hidden md:flex items-center gap-3 text-xs">
                    <div class="bg-white/20 px-3 py-1 rounded-full">
                        <i class="bi bi-person-fill mr-1"></i>
                        <?= htmlspecialchars($_SESSION['usuario_nombre']) ?>
                    </div>
                    <div class="bg-white/20 px-3 py-1 rounded-full">
                        <i class="bi bi-calendar3 mr-1"></i>
                        <?php if ($todos && !empty($busqueda)): ?>
                            Todas las fechas
                        <?php else: ?>
                            <?= date('d/m/Y', strtotime($fecha)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="bg-green-500/20 px-3 py-1 rounded-full">
                        <i class="bi bi-people-fill mr-1"></i>
                        <?= $total_pacientes ?> pacientes
                    </div>
                </div>
                <a href="?logout=1"
                    class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-2 shadow-md"
                    title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="hidden sm:inline">SALIR</span>
                </a>
            </div>
        </div>
    </header>
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar de pacientes -->
        <aside class="w-full md:w-96 bg-white border-r flex flex-col shadow-xl z-10"
            :class="{'hidden md:flex': vistaReporte}">
            <div class="p-4 border-b bg-white space-y-4">
                <!-- Filtros -->
                <form action="<?= $script_actual ?>" method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3">
                        <!-- Campo de fecha (se puede ocultar si se activa buscar en todas) -->
                        <div class="relative" id="fecha-container" <?= ($todos && !empty($busqueda)) ? 'style="display:none;"' : '' ?>>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="bi bi-calendar3 text-slate-400"></i>
                            </div>
                            <input type="date" name="fecha" value="<?= $fecha ?>"
                                class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none transition">
                        </div>
                        <!-- Campo de búsqueda -->
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="bi bi-search text-slate-400"></i>
                            </div>
                            <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>"
                                placeholder="Buscar por documento, nombres o apellidos..."
                                class="w-full pl-10 pr-10 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none transition"
                                autocomplete="off" id="buscar-input">
                            <?php if (!empty($busqueda)): ?>
                                <a href="<?= $script_actual ?>"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-red-500 hover:text-red-700 transition"
                                    title="Limpiar búsqueda">
                                    <i class="bi bi-x-circle-fill"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <!-- Checkbox para buscar en todas las fechas -->
                        <div class="flex items-center">
                            <input type="checkbox" id="buscar-todas" name="todos" value="1" <?= $todos ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded"
                                onchange="toggleFechaInput()">
                            <label for="buscar-todas" class="ml-2 text-sm text-slate-700 cursor-pointer">
                                Buscar en todas las fechas
                            </label>
                        </div>
                        <button type="submit"
                            class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold hover:bg-indigo-700 transition shadow-md">
                            <i class="bi bi-funnel-fill mr-2"></i>FILTRAR
                        </button>
                    </div>
                    <?php if (!empty($busqueda)): ?>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="flex justify-between items-center">
                                <div class="text-sm font-medium text-amber-800">
                                    <i class="bi bi-search mr-2"></i>Búsqueda: "<span class="font-bold">
                                        <?= htmlspecialchars($busqueda) ?>
                                    </span>"
                                    <?php if ($todos): ?>
                                        <span class="ml-2 text-xs bg-amber-100 text-amber-800 px-2 py-0.5 rounded">En todas las
                                            fechas</span>
                                    <?php endif; ?>
                                </div>
                                <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-full">
                                    <?= $total_pacientes ?>
                                    <?= $total_pacientes == 1 ? 'resultado' : 'resultados' ?>
                                </span>
                            </div>
                            <?php if ($total_pacientes >= 100): ?>
                                <div class="mt-2 text-xs text-amber-700">
                                    <i class="bi bi-info-circle mr-1"></i>
                                    Mostrando los primeros 100 resultados. Refina tu búsqueda para ver más.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>
                <?php if ($es_hoy && !$todos): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center gap-3">
                        <div class="bg-blue-100 p-2 rounded-lg">
                            <i class="bi bi-info-circle-fill text-blue-600 text-lg"></i>
                        </div>
                        <div class="text-sm text-blue-800">
                            <div class="font-bold">Hoy,
                                <?= date('d/m/Y') ?>
                            </div>
                            <div class="text-xs">Mostrando resultados del día actual</div>
                        </div>
                    </div>
                <?php elseif ($todos && !empty($busqueda)): ?>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 flex items-center gap-3">
                        <div class="bg-purple-100 p-2 rounded-lg">
                            <i class="bi bi-database-fill text-purple-600 text-lg"></i>
                        </div>
                        <div class="text-sm text-purple-800">
                            <div class="font-bold">Búsqueda completa</div>
                            <div class="text-xs">Buscando en todas las fechas disponibles</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Lista de pacientes -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 scrollbar-thin">
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

                        // Mostrar encabezado de grupo por fecha si estamos en modo "todos"
                        if ($todos && $fecha_examen != $fecha_actual_grupo) {
                            $fecha_actual_grupo = $fecha_examen;
                            ?>
                            <div class="sticky top-0 z-10 bg-slate-100 -mx-4 px-4 py-2 border-y border-slate-200">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                        <i class="bi bi-calendar3 mr-2"></i>
                                        <?= date('d/m/Y', strtotime($fecha_examen)) ?>
                                    </div>
                                    <a href="<?= $script_actual ?>?fecha=<?= $fecha_examen ?>"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2 py-1 hover:bg-indigo-50 rounded transition-colors">
                                        Ver solo este día
                                    </a>
                                </div>
                            </div>
                        <?php } ?>

                        <?php
                        // Calcular edad si no está en la base de datos
                        if (empty($edad_p) && !empty($row['fecnac'])) {
                            $fecha_nac = new DateTime($row['fecnac']);
                            $hoy = new DateTime();
                            $edad_p = $hoy->diff($fecha_nac)->y;
                        }
                        // Resaltar coincidencias
                        if (!empty($busqueda)) {
                            $patron = "/" . preg_quote($busqueda, '/') . "/i";
                            $nombres_resaltados = preg_replace($patron, '<mark class="bg-yellow-200 font-bold">$0</mark>', $nom_p);
                            $id_resaltado = preg_replace($patron, '<mark class="bg-yellow-200 font-bold">$0</mark>', $id_p);
                        } else {
                            $nombres_resaltados = $nom_p;
                            $id_resaltado = $id_p;
                        }
                        // Ícono de género
                        $icono_genero = ($genero_p == 'F') ? 'bi-gender-female text-pink-500' :
                            (($genero_p == 'M') ? 'bi-gender-male text-blue-500' : 'bi-gender-ambiguous text-gray-500');
                        ?>
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow"
                            :class="{'ring-2 ring-indigo-500 border-indigo-300': idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>'}">
                            <!-- Encabezado del paciente -->
                            <button
                                @click="idAbierto = (idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>' ? null : '<?= $id_p ?>_<?= $fecha_examen ?>')"
                                class="w-full p-4 text-left flex justify-between items-center rounded-2xl hover:bg-slate-50/50 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="bi <?= $icono_genero ?> text-sm"></i>
                                        <div class="text-slate-900 font-bold text-sm truncate">
                                            <?php if ($nombres_resaltados !== $nom_p): ?>
                                                <?= $nombres_resaltados ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($nom_p) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500 uppercase tracking-wider">
                                        <span class="font-mono bg-slate-100 px-2 py-0.5 rounded">
                                            <?php if ($id_resaltado !== $id_p): ?>
                                                <?= $id_resaltado ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($id_p) ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($edad_p)): ?>
                                            <span><i class="bi bi-calendar3 mr-1"></i>
                                                <?= round($edad_p) ?> años
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($telefono_p) && $telefono_p != '0'): ?>
                                            <span><i class="bi bi-telephone-fill mr-1"></i>
                                                <?= $telefono_p ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($todos): ?>
                                            <span class="text-purple-600 font-medium">
                                                <i class="bi bi-calendar-check mr-1"></i>
                                                <?= date('d/m/Y', strtotime($fecha_examen)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <i class="bi text-xl transition-transform duration-300 ml-2 flex-shrink-0"
                                    :class="idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>' ? 'bi-chevron-up text-indigo-600' : 'bi-chevron-down text-slate-400'"></i>
                            </button>
                            <!-- Contenido expandido -->
                            <div x-show="idAbierto === '<?= $id_p ?>_<?= $fecha_examen ?>'" x-cloak
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform -translate-y-2"
                                x-transition:enter-end="opacity-100 transform translate-y-0"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 transform translate-y-0"
                                x-transition:leave-end="opacity-0 transform -translate-y-2" class="border-t border-slate-100">
                                <div class="p-4 space-y-4 bg-gradient-to-b from-slate-50 to-white rounded-b-2xl">
                                    <!-- Acciones principales -->
                                    <div class="grid grid-cols-2 gap-2">
                                        <?php
                                        $idx_all = generateRandomString();
                                        $url_todo = "printphp/imprimirTodo.php?idx=$idx_all&identificacion=$id_p&fecha=$fecha_examen&nombres=" . urlencode($nom_p) . "&edad=$edad_p&info=Resultados&ver=1";
                                        ?>
                                        <button @click="cargarReporte('<?= $url_todo ?>')"
                                            class="text-xs uppercase font-bold bg-white border border-slate-300 p-3 rounded-xl flex flex-col items-center justify-center gap-1 hover:bg-slate-50 hover:border-orange-300 transition-all group">
                                            <i
                                                class="bi bi-collection-fill text-orange-500 text-lg group-hover:scale-110 transition-transform"></i>
                                            <span>Imprimir Todo</span>
                                        </button>
                                        <button @click="enviarWhatsapp('<?= $telefono_p ?>', '<?= urlencode($url_todo) ?>')"
                                            class="text-xs uppercase font-bold bg-white border border-slate-300 p-3 rounded-xl flex flex-col items-center justify-center gap-1 hover:bg-slate-50 hover:border-green-300 transition-all group <?= (empty($telefono_p) || $telefono_p == '0') ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                            <?= (empty($telefono_p) || $telefono_p == '0') ? 'disabled title="Sin teléfono registrado"' : '' ?>>
                                            <i
                                                class="bi bi-whatsapp text-green-500 text-lg group-hover:scale-110 transition-transform"></i>
                                            <span>WhatsApp</span>
                                        </button>
                                    </div>
                                    <!-- Lista de exámenes individuales -->
                                    <div>
                                        <div
                                            class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                            <i class="bi bi-list-check text-indigo-500"></i>
                                            <span>Exámenes realizados</span>
                                            <span class="text-xs text-slate-500 font-normal">
                                                (
                                                <?= date('d/m/Y', strtotime($fecha_examen)) ?>)
                                            </span>
                                        </div>
                                        <div class="space-y-1.5">
                                            <?php
                                            // Obtener exámenes del paciente para esta fecha específica
                                            $sql_examenes = "SELECT p.*, e.codexamen, e.entidad
FROM examenes e
INNER JOIN procedimientos p ON e.codexamen = p.codigo
WHERE e.identificacion = ? AND e.fecha = ?
ORDER BY p.nombre ASC";
                                            $stmt_ex = $mysqli->prepare($sql_examenes);
                                            $stmt_ex->bind_param("ss", $id_p, $fecha_examen);
                                            $stmt_ex->execute();
                                            $res_ex = $stmt_ex->get_result();
                                            if ($res_ex->num_rows > 0):
                                                while ($ex = $res_ex->fetch_assoc()):
                                                    $idx = generateRandomString();
                                                    $q = http_build_query([
                                                        'idx' => $idx,
                                                        'identificacion' => $id_p,
                                                        'fecha' => $fecha_examen,
                                                        'nombres' => $nom_p,
                                                        'tabla' => $ex['tabla'],
                                                        'info' => $ex['nombre'],
                                                        'tipo' => $ex['tipo'],
                                                        'codexamen' => $ex['codigo'],
                                                        'edad' => $edad_p,
                                                        'embedido' => 1,
                                                        'ver' => 1
                                                    ]);
                                                    $url_single = "printphp/print_examen.php?$q";
                                                    ?>
                                                    <div
                                                        class="flex items-center justify-between p-2.5 bg-white rounded-lg border border-slate-200 hover:border-indigo-300 transition-all group hover:shadow-sm">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <div class="w-2 h-2 bg-indigo-500 rounded-full flex-shrink-0"></div>
                                                            <span class="text-xs font-medium text-slate-700 truncate"
                                                                title="<?= htmlspecialchars($ex['nombre']) ?>">
                                                                <?= htmlspecialchars($ex['nombre']) ?>
                                                            </span>
                                                        </div>
                                                        <button @click="cargarReporte('<?= $url_single ?>')"
                                                            class="text-indigo-600 p-1.5 rounded-lg hover:bg-indigo-50 transition-colors ml-2 flex-shrink-0"
                                                            title="Ver examen">
                                                            <i class="bi bi-eye-fill text-sm"></i>
                                                        </button>
                                                    </div>

                                                <?php endwhile;
                                            else: ?>
                                                <div class="text-center py-4 text-slate-400">
                                                    <i class="bi bi-clipboard-x text-2xl mb-2 opacity-50"></i>
                                                    <p class="text-xs font-medium">No hay exámenes registrados</p>
                                                    <p class="text-[10px] mt-1">para esta fecha</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- *** NUEVO: Menú desplegable para seleccionar entidad para TODOS los exámenes de este paciente en esta fecha *** -->
                                    <?php
                                    // Obtener la entidad actual del paciente para esta fecha
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
                                    <div
                                        class="mt-3 p-3 bg-gradient-to-br from-indigo-50 to-blue-50 rounded-lg border border-indigo-100 shadow-sm overflow-hidden">
                                        <label for="entidad"
                                            class="block text-xs font-bold text-indigo-800 mb-2 flex items-center gap-1.5">
                                            <i class="bi bi-building text-indigo-600"></i>
                                            Asignar Entidad
                                        </label>
                                        <select class="select-entidad" name="entidad" id="entidad"
                                            data-identificacion="<?= htmlspecialchars($id_p) ?>"
                                            data-fecha-examen="<?= htmlspecialchars($fecha_examen) ?>"
                                            onchange="actualizarEntidad(this)">
                                            <option value="">-- Seleccione --</option>
                                            <?php foreach ($entidades as $entidad): ?>
                                                <option value="<?= htmlspecialchars($entidad['nombre']) ?>"
                                                    <?= (trim($entidad_actual) === trim($entidad['nombre'])) ? 'selected="selected"' : '' ?>>
                                                    <?= htmlspecialchars($entidad['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center p-8 text-center">
                        <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                            <i class="bi bi-search text-3xl text-slate-400"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-700 mb-2">No se encontraron resultados</h3>
                        <?php if (!empty($busqueda)): ?>
                            <p class="text-sm text-slate-500 mb-4">para la búsqueda: "<span
                                    class="font-mono bg-slate-100 px-2 py-1 rounded">
                                    <?= htmlspecialchars($busqueda) ?>
                                </span>"</p>
                            <a href="<?= $script_actual ?>"
                                class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium px-4 py-2 border border-indigo-200 rounded-xl hover:bg-indigo-50 transition-colors">
                                <i class="bi bi-arrow-left"></i>
                                Ver todos los pacientes
                            </a>
                        <?php else: ?>
                            <p class="text-sm text-slate-500 mb-4">No hay pacientes con exámenes para la fecha <span
                                    class="font-bold">
                                    <?= date('d/m/Y', strtotime($fecha)) ?>
                                </span></p>
                            <div class="space-x-3">
                                <a href="<?= $script_actual ?>?fecha=<?= date('Y-m-d') ?>"
                                    class="inline-flex items-center gap-2 text-sm bg-indigo-600 text-white hover:bg-indigo-700 font-medium px-4 py-2 rounded-xl transition-colors">
                                    <i class="bi bi-calendar3"></i>
                                    Ver hoy
                                </a>
                                <a href="<?= $script_actual ?>?fecha=<?= date('Y-m-d', strtotime('-1 day')) ?>"
                                    class="inline-flex items-center gap-2 text-sm bg-slate-600 text-white hover:bg-slate-700 font-medium px-4 py-2 rounded-xl transition-colors">
                                    <i class="bi bi-arrow-left"></i>
                                    Ver ayer
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
        <!-- Área principal de visualización -->
        <main class="flex-1 relative flex flex-col bg-slate-200">
            <!-- Vista normal de reportes -->
            <div x-show="!urlReporte" x-cloak class="flex-1 flex flex-col items-center justify-center p-8">
                <div class="text-center">
                    <?php if (!empty($urlLogo)): ?>
                        <img src="<?= htmlspecialchars($urlLogo) ?>" alt="Logo"
                            class="w-24 h-24 object-contain mx-auto mb-4">
                    <?php endif; ?>
                    <div
                        class="w-32 h-32 bg-gradient-to-br from-slate-100 to-slate-200 rounded-3xl flex items-center justify-center mb-6 shadow-inner">
                        <i class="bi bi-file-earmark-pdf text-6xl text-slate-400 text-center"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-700 mb-2">Visor de Resultados</h3>
                    <p class="text-slate-500 max-w-md mx-auto mb-6">
                        Seleccione un examen de la lista para visualizar e imprimir los resultados.
                        También puede enviar los resultados por WhatsApp directamente al paciente.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-2xl mx-auto">
                        <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
                            <div
                                class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                <i class="bi bi-eye-fill text-indigo-600"></i>
                            </div>
                            <div class="text-sm font-bold text-slate-800 mb-1">Visualizar</div>
                            <div class="text-xs text-slate-500">Ver resultados completos</div>
                        </div>
                        <div class="bg-white p=4 rounded-xl border border-slate-200 text-center">
                            <div
                                class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                <i class="bi bi-printer-fill text-green-600"></i>
                            </div>
                            <div class="text-sm font-bold text-slate-800 mb-1">Imprimir</div>
                            <div class="text-xs text-slate-500">Generar copia física</div>
                        </div>
                        <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
                            <div
                                class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center mx-auto mb-3">
                                <i class="bi bi-whatsapp text-amber-600"></i>
                            </div>
                            <div class="text-sm font-bold text-slate-800 mb=1">Compartir</div>
                            <div class="text-xs text-slate-500">Enviar por WhatsApp</div>
                        </div>
                    </div>
                </div>
            </div>
            <div x-show="urlReporte" x-cloak class="h-full flex flex-col">
                <div class="bg-white p-3 border-b shadow-sm flex justify-between items-center">
                    <button @click="urlReporte = null; vistaReporte = false"
                        class="md:hidden text-slate-600 hover:text-slate-800 hover:bg-slate-100 p-2 rounded-lg transition-colors">
                        <i class="bi bi-arrow-left text-lg"></i>
                    </button>
                    <div class="flex items-center gap-3">
                        <div class="bg-indigo-100 p-2 rounded-lg">
                            <i class="bi bi-file-earmark-text-fill text-indigo-600"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Reporte de Exámenes
                            </div>
                            <div class="text-[10px] text-slate-500" x-text="nombreReporte || 'Cargando...'"></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="imprimirFrame()"
                            class="bg-indigo-600 text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-indigo-700 shadow-md transition flex items-center gap-2">
                            <i class="bi bi-printer"></i> IMPRIMIR
                        </button>
                    </div>
                </div>
                <iframe :src="urlReporte" id="frameReporte" class="w-full flex-1 border-none bg-white"
                    @load="cargarNombreReporte" title="Vista previa del reporte"></iframe>
            </div>
        </main>
    </div>

    <!-- Modal de Consulta por Entidades y Fechas -->
    <div x-show="mostrarModalEntidades" x-cloak
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
        @click.self="mostrarModalEntidades = false">
        <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full h-[85vh] flex flex-col" @click.stop>

            <!-- Formulario de consulta -->
            <div x-show="!mostrarResultadosModal" class="flex flex-col h-full">
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-6 rounded-t-2xl flex-shrink-0">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold flex items-center gap-2">
                            <i class="bi bi-building"></i>
                            Consulta por Entidades
                        </h3>
                        <button @click="mostrarModalEntidades = false"
                            class="text-white hover:bg-white/20 p-2 rounded-lg transition">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="text-purple-100 text-sm">
                        Consulta resultados de exámenes por entidad (o todas) y rango de fechas
                    </p>
                </div>

                <form @submit.prevent="consultarPorEntidades()" class="p-6 space-y-5 overflow-y-auto flex-1">
                    <!-- Selección de Entidad -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                            <i class="bi bi-building text-purple-600"></i>
                            Entidad
                        </label>
                        <select x-model="formularioEntidades.entidad"
                            class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all">
                            <option value="">-- Todas las entidades --</option>
                            <?php foreach ($entidades as $entidad): ?>
                                <option value="<?= htmlspecialchars($entidad['nombre']) ?>">
                                    <?= htmlspecialchars($entidad['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Rango de Fechas -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                                <i class="bi bi-calendar-date text-purple-600"></i>
                                Fecha Inicio
                            </label>
                            <input type="date" x-model="formularioEntidades.fechaInicio" required
                                class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                                <i class="bi bi-calendar-date text-purple-600"></i>
                                Fecha Fin
                            </label>
                            <input type="date" x-model="formularioEntidades.fechaFin" required
                                class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition-all">
                        </div>
                    </div>

                    <!-- Opciones Adicionales -->
                    <div class="bg-purple-50 p-4 rounded-xl border border-purple-100">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="bi bi-funnel text-purple-600"></i>
                            <span class="text-sm font-semibold text-purple-800">Opciones de filtrado</span>
                        </div>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" x-model="formularioEntidades.soloConResultados"
                                    class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
                                <span class="text-sm text-slate-700">Mostrar solo exámenes con resultados</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" x-model="formularioEntidades.agruparPorFecha"
                                    class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500">
                                <span class="text-sm text-slate-700">Agrupar resultados por fecha</span>
                            </label>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex gap-3 pt-4 border-t">
                        <button type="button" @click="mostrarModalEntidades = false"
                            class="flex-1 px-4 py-3 border-2 border-slate-300 text-slate-700 rounded-xl font-bold hover:bg-slate-50 transition">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 py-3 rounded-xl font-bold hover:from-purple-700 hover:to-indigo-700 transition shadow-lg">
                            <i class="bi bi-search mr-2"></i>
                            Consultar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Resultados de la consulta -->
            <div x-show="mostrarResultadosModal" class="flex flex-col h-full">
                <!-- Header con botones -->
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-4 flex-shrink-0">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <button @click="mostrarResultadosModal = false"
                                class="bg-white/20 hover:bg-white/30 p-2 rounded-lg transition">
                                <i class="bi bi-arrow-left text-lg"></i>
                            </button>
                            <div>
                                <div class="font-bold text-sm">Resultados por Entidad</div>
                                <div class="text-xs opacity-80">
                                    <span x-text="formularioEntidades?.fechaInicio || ''"></span> - <span
                                        x-text="formularioEntidades?.fechaFin || ''"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="exportarExcel()"
                                class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1">
                                <i class="bi bi-file-earmark-excel"></i>
                                EXCEL
                            </button>
                            <button @click="imprimirResultadosModal()"
                                class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1">
                                <i class="bi bi-printer"></i>
                                IMPRIMIR
                            </button>
                            <button @click="mostrarModalEntidades = false"
                                class="bg-white/20 hover:bg-white/30 p-2 rounded-lg transition">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenido de resultados -->
                <div class="flex-1 overflow-hidden flex flex-col">
                    <div class="max-w-6xl mx-auto w-full p-4 flex flex-col h-full">
                        <!-- Resumen -->
                        <div class="bg-white rounded-xl shadow-lg p-4 mb-4 border flex-shrink-0">
                            <div class="grid grid-cols-4 gap-4">
                                <div class="text-center">
                                    <div
                                        class="bg-purple-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-building text-purple-600 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800"
                                        x-text="resultadosEntidades?.total_fechas || 0">0</div>
                                    <div class="text-xs text-slate-500 uppercase">Fechas</div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="bg-blue-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-people-fill text-blue-600 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800"
                                        x-text="resultadosEntidades?.total_registros || 0">0</div>
                                    <div class="text-xs text-slate-500 uppercase">Exámenes</div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="bg-green-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-calendar-check text-green-600 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800"
                                        x-text="resultadosEntidades?.resultados?.length || 0">0</div>
                                    <div class="text-xs text-slate-500 uppercase">Días</div>
                                </div>
                                <div class="text-center">
                                    <div
                                        class="bg-amber-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <i class="bi bi-funnel text-amber-600 text-sm"></i>
                                    </div>
                                    <div class="text-lg font-bold text-slate-800 truncate"
                                        x-text="formularioEntidades?.entidad || 'Todas'">-</div>
                                    <div class="text-xs text-slate-500 uppercase">Entidad</div>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de resultados -->
                        <div class="flex-1 overflow-y-auto space-y-3">

                            <!-- Lista de resultados -->
                            <div class="space-y-3">
                                <template x-for="grupo in (resultadosEntidades?.resultados || [])" :key="grupo.fecha">
                                    <div class="bg-white rounded-xl shadow-lg overflow-hidden border">
                                        <!-- Header de fecha -->
                                        <div
                                            class="bg-gradient-to-r from-purple-50 to-indigo-50 p-3 border-b border-purple-100">
                                            <div class="flex justify-between items-center">
                                                <div class="flex items-center gap-2">
                                                    <i class="bi bi-calendar3 text-purple-600"></i>
                                                    <div>
                                                        <div class="font-bold text-purple-800 text-sm">
                                                            <span
                                                                x-text="new Date(grupo.fecha + 'T00:00:00').toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })"></span>
                                                        </div>
                                                        <div class="text-xs text-purple-600">
                                                            <span x-text="grupo.cantidad"></span> exámenes
                                                        </div>
                                                    </div>
                                                </div>
                                                <button @click="imprimirGrupoFechaModal(grupo.fecha)"
                                                    class="bg-purple-600 hover:bg-purple-700 text-white px-2 py-1 rounded-lg text-xs font-bold transition">
                                                    <i class="bi bi-printer mr-1"></i>
                                                    Día
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Lista de exámenes -->
                                        <div class="p-3">
                                            <div class="space-y-2">
                                                <template x-for="(examen, index) in grupo.examenes"
                                                    :key="examen.identificacion + '_' + examen.examen_codigo">
                                                    <div>
                                                        <div
                                                            class="flex items-center justify-between p-2 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-2 h-2 bg-purple-500 rounded-full"></div>
                                                                <div class="flex-1">
                                                                    <div class="font-medium text-slate-800 text-sm"
                                                                        x-text="examen.paciente"></div>
                                                                    <div class="text-xs text-slate-500">
                                                                        <span
                                                                            class="font-mono bg-slate-200 px-1 py-0.5 rounded text-xs"
                                                                            x-text="examen.identificacion"></span>
                                                                        <span class="ml-1"><i
                                                                                class="bi bi-calendar3"></i>
                                                                            <span
                                                                                x-text="Math.round(parseFloat(examen.edad)) + ' años'"></span></span>
                                                                        <span class="ml-1"><i class="bi bi-gender-"></i>
                                                                            <span x-text="examen.genero"></span></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="flex items-center gap-1">
                                                                <div class="text-right mr-2">
                                                                    <div class="text-xs font-medium text-slate-700"
                                                                        x-text="examen.examen_nombre"></div>
                                                                    <div class="text-xs text-slate-500"
                                                                        x-text="examen.tipo_procedimiento || examen.examen_tipo">
                                                                    </div>
                                                                </div>
                                                                <button @click="toggleResultadoMini(examen, index)"
                                                                    class="bg-green-600 hover:bg-green-700 text-white p-1 rounded transition-colors"
                                                                    :class="examen.mostrarResultado ? 'bg-green-700' : ''"
                                                                    :title="examen.mostrarResultado ? 'Ocultar' : 'Ver resultado'">
                                                                    <i class="bi bi-file-text text-xs"></i>
                                                                </button>
                                                                <button @click="verExamenIndividual(examen)"
                                                                    class="bg-indigo-600 hover:bg-indigo-700 text-white p-1 rounded transition-colors"
                                                                    title="Ver completo">
                                                                    <i class="bi bi-eye-fill text-xs"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <!-- Vista mini del resultado -->
                                                        <div x-show="examen.mostrarResultado"
                                                            x-transition:enter="transition ease-out duration-200"
                                                            x-transition:enter-start="opacity-0 transform -translate-y-2"
                                                            x-transition:enter-end="opacity-100 transform translate-y-0"
                                                            x-transition:leave="transition ease-in duration-150"
                                                            x-transition:leave-start="opacity-100 transform translate-y-0"
                                                            x-transition:leave-end="opacity-0 transform -translate-y-2"
                                                            class="ml-8 mt-1 p-2 bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg">
                                                            <div class="flex items-center gap-1 mb-1">
                                                                <i
                                                                    class="bi bi-file-medical text-green-600 text-xs"></i>
                                                                <div class="font-semibold text-green-800 text-xs">
                                                                    Resultado:
                                                                </div>
                                                                <button @click="examen.mostrarResultado = false"
                                                                    class="ml-auto text-green-600 hover:text-green-800">
                                                                    <i class="bi bi-x text-xs"></i>
                                                                </button>
                                                            </div>
                                                            <div class="text-xs space-y-1 text-green-700">
                                                                <div class="flex justify-between">
                                                                    <span class="font-medium">Valor:</span>
                                                                    <span class="font-bold text-green-900"
                                                                        x-text="examen.resultado || 'No disponible'"></span>
                                                                </div>
                                                                <div class="flex justify-between"
                                                                    x-show="examen.referencia && examen.referencia !== 'N/A'">
                                                                    <span class="font-medium">Referencia:</span>
                                                                    <span x-text="examen.referencia"></span>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="font-medium">Estado:</span>
                                                                    <span x-text="examen.estado || 'Pendiente'"></span>
                                                                </div>
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

        <script>
            function labApp(idInicial) {
                return {
                    idAbierto: idInicial || null,
                    urlReporte: null,
                    vistaReporte: false,
                    nombreReporte: '',
                    mostrarModalEntidades: false,
                    mostrarResultadosModal: false,
                    formularioEntidades: {
                        entidad: '',
                        fechaInicio: '<?= date('Y-m-d') ?>',
                        fechaFin: '<?= date('Y-m-d') ?>',
                        soloConResultados: false,
                        agruparPorFecha: true
                    },
                    resultadosEntidades: null,
                    cargarReporte(url) {
                        this.urlReporte = url;
                        this.vistaReporte = true;
                        this.nombreReporte = 'Cargando reporte...';
                        // Cerrar menú en móviles
                        if (window.innerWidth < 768) {
                            this.idAbierto = null;
                        }
                    },
                    cargarNombreReporte() {
                        try {
                            const frame = document.getElementById('frameReporte');
                            if (frame && frame.contentDocument) {
                                const title = frame.contentDocument.title ||
                                    frame.contentDocument.querySelector('h1, h2, .titulo')?.textContent ||
                                    'Reporte';
                                this.nombreReporte = title.substring(0, 50) + (title.length > 50 ? '...' : '');
                            }
                        } catch (e) {
                            console.log('No se pudo obtener el nombre del reporte');
                            this.nombreReporte = 'Reporte de exámenes';
                        }
                    },
                    enviarWhatsapp(tel, urlCodificada) {
                        if (!tel || tel.trim() === '' || tel === '0') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Teléfono no disponible',
                                text: 'Este paciente no tiene número de celular registrado.',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#4f46e5',
                                background: '#f8fafc',
                                color: '#1e293b'
                            });
                            return;
                        }
                        const baseUrl = window.location.origin;
                        const fullUrl = `${baseUrl}/${decodeURIComponent(urlCodificada)}`;
                        // Copiar al portapapeles
                        navigator.clipboard.writeText(fullUrl).then(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Enlace copiado',
                                text: 'El enlace se ha copiado al portapapeles. Redirigiendo a WhatsApp...',
                                showConfirmButton: false,
                                timer: 2000,
                                background: '#f8fafc',
                                color: '#1e293b'
                            });
                            // Abrir WhatsApp después de 2 segundos
                            setTimeout(() => {
                                const msg = encodeURIComponent(`Hola, envío tus resultados de laboratorio clínico. Puedes verlos en el siguiente enlace: ${fullUrl}`);
                                window.open(`https://wa.me/${tel}?text=${msg}`, '_blank', 'noopener,noreferrer');
                            }, 2000);
                        }).catch(() => {
                            // Fallback si no se puede copiar
                            const msg = encodeURIComponent(`Hola, envío tus resultados de laboratorio clínico. Puedes verlos en el siguiente enlace: ${fullUrl}`);
                            window.open(`https://wa.me/${tel}?text=${msg}`, '_blank', 'noopener,noreferrer');
                        });
                    },
                    imprimirFrame() {
                        const frame = document.getElementById('frameReporte');
                        if (frame && frame.contentWindow) {
                            frame.contentWindow.focus();
                            frame.contentWindow.print();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se puede acceder al contenido para imprimir.',
                                confirmButtonText: 'Entendido'
                            });
                        }
                    },
                    consultarPorEntidades() {
                        if (!this.formularioEntidades.fechaInicio || !this.formularioEntidades.fechaFin) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Fechas requeridas',
                                text: 'Por favor seleccione el rango de fechas.',
                                confirmButtonText: 'Entendido'
                            });
                            return;
                        }

                        // Debug temporal
                        console.log('Enviando consulta:', {
                            entidad: this.formularioEntidades?.entidad || '',
                            fechaInicio: this.formularioEntidades.fechaInicio,
                            fechaFin: this.formularioEntidades.fechaFin,
                            soloResultados: this.formularioEntidades.soloConResultados,
                            agruparFecha: this.formularioEntidades.agruparPorFecha
                        });

                        // Validar que fecha fin no sea anterior a fecha inicio
                        if (new Date(this.formularioEntidades.fechaFin) < new Date(this.formularioEntidades.fechaInicio)) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Fechas inválidas',
                                text: 'La fecha final no puede ser anterior a la fecha inicial.',
                                confirmButtonText: 'Entendido'
                            });
                            return;
                        }

                        // Mostrar loading
                        Swal.fire({
                            title: 'Consultando...',
                            text: 'Obteniendo resultados de la entidad',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Realizar consulta
                        fetch('<?= $script_actual ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                'action': 'consulta_entidades',
                                'entidad': this.formularioEntidades.entidad,
                                'fecha_inicio': this.formularioEntidades?.fechaInicio || '',
                                'fecha_fin': this.formularioEntidades?.fechaFin || '',
                                'solo_resultados': this.formularioEntidades.soloConResultados ? '1' : '0',
                                'agrupar_fecha': this.formularioEntidades.agruparPorFecha ? '1' : '0'
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                Swal.close();

                                if (data.success) {
                                    this.resultadosEntidades = data;
                                    this.mostrarResultadosModal = true;
                                    console.log('Resultados cargados en modal:', this.resultadosEntidades);
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error en consulta',
                                        text: data.message,
                                        confirmButtonText: 'Entendido'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.close();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de conexión',
                                    text: 'No se pudo realizar la consulta. Intente nuevamente.',
                                    confirmButtonText: 'Entendido'
                                });
                            });
                    },
                    imprimirGrupoFechaModal(fecha) {
                        // Lógica para imprimir un día específico en modal
                        Swal.fire({
                            icon: 'info',
                            title: 'Imprimiendo día',
                            text: 'Preparando impresión para ' + new Date(fecha).toLocaleDateString(),
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(() => {
                            window.print();
                        }, 1600);
                    },
                    imprimirResultadosModal() {
                        window.print();
                    },
                    abrirModalEntidades() {
                        // Establecer fechas actuales por defecto
                        const today = new Date();
                        const todayStr = today.toISOString().split('T')[0];
                        this.formularioEntidades.fechaInicio = todayStr;
                        this.formularioEntidades.fechaFin = todayStr;
                        this.mostrarModalEntidades = true;
                    },
                    imprimirResultadosEntidades() {
                        window.print();
                    },
                    imprimirGrupoFecha(fecha) {
                        // Lógica para imprimir un día específico
                        Swal.fire({
                            icon: 'info',
                            title: 'Imprimiendo día',
                            text: 'Preparando impresión para ' + new Date(fecha).toLocaleDateString(),
                            timer: 1500,
                            showConfirmButton: false
                        });
                        // Aquí se podría implementar la impresión específica del día
                        setTimeout(() => {
                            window.print();
                        }, 1600);
                    },
                    verExamenIndividual(examen) {
                        // Generar URL para ver el examen individual
                        const idx = Math.random().toString(36).substring(2, 15);
                        const params = new URLSearchParams({
                            'idx': idx,
                            'identificacion': examen.identificacion,
                            'fecha': examen.fecha_examen,
                            'tabla': examen.examen_tabla || examen.examen_tipo,
                            'info': examen.examen_nombre,
                            'tipo': examen.examen_tipo,
                            'codexamen': examen.examen_codigo,
                            'edad': examen.edad,
                            'embedido': 1,
                            'ver': 1
                        });
                        const url = "printphp/print_examen.php?" + params.toString();

                        this.urlReporte = url;
                        this.vistaReporte = true;
                        this.nombreReporte = examen.examen_nombre + ' - ' + examen.paciente;
                    },
                    toggleResultadoMini(examen, index) {
                        // Toggle para mostrar/ocultar resultado mini
                        this.$set(examen, 'mostrarResultado', !examen.mostrarResultado);
                    },
                    exportarExcel() {
                        if (!this.resultadosEntidades || !this.resultadosEntidades.resultados) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Sin datos',
                                text: 'No hay resultados para exportar.',
                                confirmButtonText: 'Entendido'
                            });
                            return;
                        }

                        // Preparar datos para Excel
                        const datos = [];
                        
                        // Encabezados mejorados
                        const headers = [
                            'ENTIDAD',
                            'FECHA EXAMEN',
                            'IDENTIFICACIÓN',
                            'PACIENTE',
                            'EDAD',
                            'GÉNERO',
                            'TELÉFONO',
                            'EXAMEN',
                            'CÓDIGO',
                            'TIPO PROCEDIMIENTO',
                            'ABREVIATURA',
                            'RESULTADO',
                            'REFERENCIA',
                            'ESTADO'
                        ];
                        
                        datos.push(headers);

                        // Agregar datos
                        this.resultadosEntidades.resultados.forEach(grupo => {
                            grupo.examenes.forEach(examen => {
                                const fila = [
                                    examen.entidad || 'Sin entidad',
                                    examen.fecha_examen || '',
                                    examen.identificacion || '',
                                    examen.paciente || '',
                                    examen.edad ? Math.round(parseFloat(examen.edad)) + ' años' : '',
                                    examen.genero || '',
                                    examen.telefono || '',
                                    examen.examen_nombre || '',
                                    examen.examen_codigo || '',
                                    examen.tipo_procedimiento || examen.examen_tipo || '',
                                    examen.abreviatura || '',
                                    examen.resultado || '',
                                    examen.referencia || '',
                                    examen.estado || ''
                                ];
                                datos.push(fila);
                            });
                        });

                        // Crear libro de Excel
                        const wb = XLSX.utils.book_new();
                        const ws = XLSX.utils.aoa_to_sheet(datos);

                        // Establecer anchos de columna
                        const columnWidths = [
                            { wch: 20 }, // Entidad
                            { wch: 12 }, // Fecha
                            { wch: 15 }, // Identificación
                            { wch: 30 }, // Paciente
                            { wch: 8 },  // Edad
                            { wch: 8 },  // Género
                            { wch: 12 }, // Teléfono
                            { wch: 25 }, // Examen
                            { wch: 10 }, // Código
                            { wch: 20 }, // Tipo Procedimiento
                            { wch: 15 }, // Abreviatura
                            { wch: 30 }, // Resultado
                            { wch: 20 }, // Referencia
                            { wch: 12 }  // Estado
                        ];
                        ws['!cols'] = columnWidths;

                        // Aplicar estilos al encabezado
                        const headerRange = XLSX.utils.decode_range(ws['!ref']);
                        for (let col = headerRange.s.c; col <= headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!ws[cellAddress]) continue;
                            
                            ws[cellAddress].s = {
                                font: { bold: true, color: { rgb: "FFFFFF" } },
                                fill: { fgColor: { rgb: "4F46E5" } }, // Color indigo
                                alignment: { horizontal: "center", vertical: "center" },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }

                        // Aplicar bordes a todas las celdas de datos
                        for (let row = headerRange.s.r + 1; row <= headerRange.e.r; row++) {
                            for (let col = headerRange.s.c; col <= headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                if (!ws[cellAddress]) continue;
                                
                                ws[cellAddress].s = {
                                    border: {
                                        top: { style: "thin", color: { rgb: "E5E7EB" } },
                                        bottom: { style: "thin", color: { rgb: "E5E7EB" } },
                                        left: { style: "thin", color: { rgb: "E5E7EB" } },
                                        right: { style: "thin", color: { rgb: "E5E7EB" } }
                                    },
                                    alignment: { 
                                        vertical: "center",
                                        wrapText: true
                                    }
                                };
                            }
                        }

                        // Congelar la primera fila (encabezados)
                        ws['!freeze'] = { xSplit: 0, ySplit: 1 };

                        // Agregar hoja al libro
                        XLSX.utils.book_append_sheet(wb, ws, "Reporte de Entidades");

                        // Generar nombre del archivo
                        const entidadNombre = this.formularioEntidades.entidad ? 
                            this.formularioEntidades.entidad.replace(/\s+/g, '_') : 'Todas_las_entidades';
                        const fechaInicio = this.formularioEntidades.fechaInicio;
                        const fechaFin = this.formularioEntidades.fechaFin;
                        const fileName = 'Reporte_' + entidadNombre + '_' + fechaInicio + '_' + fechaFin + '.xlsx';

                        // Descargar archivo
                        XLSX.writeFile(wb, fileName);

                        Swal.fire({
                            icon: 'success',
                            title: '¡Exportado!',
                            html: `
                                <div class="text-left">
                                    <p class="mb-2">El archivo Excel ha sido descargado correctamente.</p>
                                    <div class="bg-blue-50 p-3 rounded-lg text-sm">
                                        <p class="font-semibold text-blue-800">Características del reporte:</p>
                                        <ul class="mt-1 text-blue-700">
                                            <li>• Columnas con ancho ajustado</li>
                                            <li>• Encabezados en negrita y color</li>
                                            <li>• Bordes y formato profesional</li>
                                            <li>• Fila de encabezado congelada</li>
                                            <li>• Incluye resultados y referencias</li>
                                        </ul>
                                    </div>
                                </div>
                            `,
                            showConfirmButton: false,
                            timer: 4000
                        });
                    }
                }
            }

            // Función para mostrar/ocultar el campo de fecha según el checkbox
            function toggleFechaInput() {
                const fechaContainer = document.getElementById('fecha-container');
                const buscarInput = document.getElementById('buscar-input');
                const checkbox = document.getElementById('buscar-todas');
                if (checkbox.checked) {
                    fechaContainer.style.display = 'none';
                    buscarInput.placeholder = 'Buscar en TODAS las fechas...';
                } else {
                    fechaContainer.style.display = 'block';
                    buscarInput.placeholder = 'Buscar por documento, nombres o apellidos...';
                }
            }

            // *** NUEVO: Función para actualizar la entidad de todos los exámenes de un paciente en una fecha ***
            function actualizarEntidad(selectElement) {
                const identificacion = selectElement.getAttribute('data-identificacion');
                const fechaExamen = selectElement.getAttribute('data-fecha-examen');
                const nuevaEntidad = selectElement.value;

                if (!identificacion || !fechaExamen) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudieron obtener los datos necesarios para actualizar la entidad.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#4f46e5'
                    });
                    return;
                }

                // Mostrar indicador de carga sin modificar el select
                Swal.fire({
                    title: 'Actualizando...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('<?= $script_actual ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'actualizar_entidad',
                        'identificacion': identificacion,
                        'fecha_examen': fechaExamen,
                        'entidad': nuevaEntidad
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();

                        if (data.success) {
                            // Mensaje rápido de éxito
                            Swal.fire({
                                icon: 'success',
                                title: '¡Actualizado!',
                                text: 'Entidad asignada correctamente',
                                showConfirmButton: false,
                                timer: 1200
                            });
                        } else {
                            // Restaurar valor seleccionado original si hay error
                            selectElement.value = selectElement.querySelector('option[selected]')?.value || '';
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#4f46e5'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Restaurar valor seleccionado original si hay error
                        selectElement.value = selectElement.querySelector('option[selected]')?.value || '';
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo actualizar. Intente nuevamente.',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#4f46e5'
                        });
                    });
            }

            // Inicializar estado del campo de fecha
            document.addEventListener('DOMContentLoaded', function () {
                toggleFechaInput();
                // Auto-enfocar campo de búsqueda si hay contenido
                const buscarInput = document.getElementById('buscar-input');
                if (buscarInput && buscarInput.value) {
                    buscarInput.focus();
                    buscarInput.setSelectionRange(buscarInput.value.length, buscarInput.value.length);
                }
            });
        </script>
</body>

</html>