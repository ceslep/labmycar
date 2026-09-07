<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ob_start();
require_once 'datos_conexion.php';
ob_end_clean();

function generarCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCSRF($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

$csrf_token = generarCSRF();

if (isset($_POST['logout'])) {
    if (validarCSRF($_POST['csrf_token'] ?? '')) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();
    }
    header("Content-Type: text/html; charset=UTF-8");
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=prt_mejorado.php"></head><body></body></html>';
    exit;
}

$TABLAS_PERMITIDAS = ['examen_tipo_1','examen_tipo_2','examen_tipo_3','examen_tipo_5','examen_tipo_7','perfilLipidico','hemogramaRayto'];

function validarTabla($tabla) {
    global $TABLAS_PERMITIDAS;
    return in_array($tabla, $TABLAS_PERMITIDAS);
}

$cache_tablas = [];
$cache_estructura = [];

function tablaExiste($mysqli, $tabla, &$cache) {
    if (!isset($cache[$tabla])) {
        $cache[$tabla] = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($tabla) . "'")->num_rows > 0;
    }
    return $cache[$tabla];
}

function obtenerColumnas($mysqli, $tabla, &$cache) {
    if (!isset($cache[$tabla])) {
        $estructura = $mysqli->query("DESCRIBE `" . str_replace('`', '', $tabla) . "`");
        $cache[$tabla] = [];
        if ($estructura) { while ($col = $estructura->fetch_assoc()) { $cache[$tabla][] = $col['Field']; } }
    }
    return $cache[$tabla];
}

function extraerResultados($mysqli, $tabla, $identificacion, $codexamen, $fecha, &$cache_tablas, &$cache_estructura) {
    if (!validarTabla($tabla)) return ['resultado' => 'Tabla no permitida', 'referencia' => 'N/A', 'estado' => 'Error'];
    if (!tablaExiste($mysqli, $tabla, $cache_tablas)) return ['resultado' => 'Tabla no encontrada', 'referencia' => 'N/A', 'estado' => 'Error'];
    $columnas = obtenerColumnas($mysqli, $tabla, $cache_estructura);
    $wc = []; $wp = []; $wt = "";
    if (in_array('identificacion', $columnas)) { $wc[] = "identificacion = ?"; $wp[] = $identificacion; $wt .= "s"; }
    if (in_array('codexamen', $columnas)) { $wc[] = "codexamen = ?"; $wp[] = $codexamen; $wt .= "s"; }
    elseif (in_array('examen', $columnas)) { $wc[] = "examen = ?"; $wp[] = $codexamen; $wt .= "s"; }
    if (in_array('fecha', $columnas)) { $wc[] = "fecha = ?"; $wp[] = $fecha; $wt .= "s"; }
    if (empty($wc)) return ['resultado' => 'Sin columnas clave', 'referencia' => 'N/A', 'estado' => 'Error'];
    try {
        $s = $mysqli->prepare("SELECT * FROM `$tabla` WHERE " . implode(" AND ", $wc) . " LIMIT 1");
        if (!$s) { error_log("prepare() failed for table $tabla: " . $mysqli->error); return ['resultado' => 'Error interno', 'referencia' => 'N/A', 'estado' => 'Error']; }
        $s->bind_param($wt, ...$wp); $s->execute(); $r = $s->get_result();
        if ($r->num_rows > 0) {
            $d = $r->fetch_assoc(); $v = ''; $ref = '';
            switch ($tabla) {
                case 'examen_tipo_1': case 'examen_tipo_2': $v = $d['valoracion'] ?? ''; break;
                case 'examen_tipo_3': foreach (['densidad','color','ph','proteinas','glucosa','bilirrubina','nitritos','leucocitos'] as $c) { if (!empty($d[$c]) && $d[$c] !== 'N/A') $v .= ($v ? ', ' : '') . $c . ': ' . $d[$c]; } break;
                case 'examen_tipo_5': foreach (['hemoglobina','hematocrito','leucocitos','WBC','RBC','PLT'] as $c) { if (!empty($d[$c]) && $d[$c] !== 'N/A') $v .= ($v ? ', ' : '') . $c . ': ' . $d[$c]; } break;
                case 'examen_tipo_7': foreach (['tiempo_de_protrombina','tiempo_de_control','tpts'] as $c) { if (!empty($d[$c]) && $d[$c] !== 'N/A') $v .= ($v ? ', ' : '') . $c . ': ' . $d[$c]; } break;
                case 'perfilLipidico': foreach (['colesterol_total','colesterol_hdl','colesterol_ldl','trigliceridos'] as $c) { if (!empty($d[$c]) && $d[$c] !== 'N/A') $v .= ($v ? ', ' : '') . $c . ': ' . $d[$c]; } break;
                case 'hemogramaRayto': foreach (['WBC','RBC','HGB','HCT','PLT'] as $c) { if (!empty($d[$c]) && $d[$c] !== 'N/A') $v .= ($v ? ', ' : '') . $c . ': ' . $d[$c]; } break;
                default: foreach ($d as $col => $val) { if (in_array(strtolower($col), ['resultado','valor','result','value','valoracion']) && !empty($val)) { $v = $val; break; } } if (empty($v)) { $ex = ['ind','identificacion','codexamen','examen','fecha','hora','id','bacteriologo','observaciones','fechahora','fechaResultados']; foreach ($d as $col => $val) { if (!in_array(strtolower($col), $ex) && !empty($val) && $val !== '0000-00-00' && $val !== '0' && $val !== 'N/A') { $v = $val; break; } } }
            }
            foreach (['referencia','rango','range','normal','valor_de_referencia'] as $campo) { if (!empty($d[$campo]) && $d[$campo] !== 'N/A') { $ref = $d[$campo]; break; } }
            return ['resultado' => $v ?: 'Sin valor', 'referencia' => $ref ?: 'N/A', 'estado' => 'Completado', 'resultado_completo' => $d];
        }
        return ['resultado' => 'Pendiente', 'referencia' => 'N/A', 'estado' => 'Pendiente'];
    } catch (Exception $e) { error_log("extraerResultados error for $tabla: " . $e->getMessage()); return ['resultado' => 'Error en consulta', 'referencia' => 'N/A', 'estado' => 'Error']; }
}

function parseRango($ref) {
    $ref = trim($ref);
    if (empty($ref) || $ref === 'N/A') return null;
    // Formatos soportados: "5.0 - 10.0", "5.0-10.0", "< 10.0", "> 5.0", "hasta 10.0"
    if (preg_match('/([0-9]+\.?[0-9]*)\s*[-–]\s*([0-9]+\.?[0-9]*)/', $ref, $m)) {
        return ['min' => floatval($m[1]), 'max' => floatval($m[2])];
    }
    if (preg_match('/^<\s*=?\s*([0-9]+\.?[0-9]*)/', $ref, $m)) {
        return ['min' => null, 'max' => floatval($m[1])];
    }
    if (preg_match('/^>\s*=?\s*([0-9]+\.?[0-9]*)/', $ref, $m)) {
        return ['min' => floatval($m[1]), 'max' => null];
    }
    if (preg_match('/hasta\s+([0-9]+\.?[0-9]*)/i', $ref, $m)) {
        return ['min' => null, 'max' => floatval($m[1])];
    }
    return null;
}

function evaluarResultado($valor, $ref) {
    $valor_num = null;
    if (preg_match('/([0-9]+\.?[0-9]*)/', str_replace(',', '.', $valor), $m)) {
        $valor_num = floatval($m[1]);
    }
    $rango = parseRango($ref);
    if ($valor_num === null || $rango === null) return 'normal';
    $min = $rango['min'];
    $max = $rango['max'];
    if ($min !== null && $max !== null) {
        if ($valor_num < $min || $valor_num > $max) {
            // Critico si esta fuera por mas del 50% del rango
            $rangoSize = $max - $min;
            $desviacion = ($valor_num < $min) ? ($min - $valor_num) : ($valor_num - $max);
            return ($rangoSize > 0 && $desviacion / $rangoSize > 0.5) ? 'danger' : 'warning';
        }
        return 'normal';
    }
    if ($max !== null && $valor_num > $max) return 'warning';
    if ($min !== null && $valor_num < $min) return 'warning';
    return 'normal';
}

function direccionResultado($valor, $ref) {
    $valor_num = null;
    if (preg_match('/([0-9]+\.?[0-9]*)/', str_replace(',', '.', $valor), $m)) {
        $valor_num = floatval($m[1]);
    }
    $rango = parseRango($ref);
    if ($valor_num === null || $rango === null) return '';
    $min = $rango['min'];
    $max = $rango['max'];
    if ($max !== null && $valor_num > $max) return ' result-value-high';
    if ($min !== null && $valor_num < $min) return ' result-value-low';
    return '';
}

function renderizarTarjetaPaciente($id_p, $nom_p, $edad_p, $genero_p, $telefono_p, $telefono_movil, $telefono_residencia2, $fecha_examen, $entidad_p, $todos, $mysqli, &$cache_tablas, &$cache_estructura, $entidades = null) {
    $cid = $id_p . '_' . $fecha_examen;
    $avatarClass = ($genero_p == 'F') ? 'female' : (($genero_p == 'M') ? 'male' : 'unknown');
    $iniciales = htmlspecialchars(strtoupper(substr($nom_p, 0, 1)), ENT_QUOTES, 'UTF-8');
    $iniciales2 = htmlspecialchars(strtoupper(substr($nom_p, 0, 2)), ENT_QUOTES, 'UTF-8');
    $url_todo = "printphp/imprimirTodo.php?idx=" . bin2hex(random_bytes(10)) . "&identificacion=" . urlencode($id_p) . "&fecha=$fecha_examen&nombres=" . urlencode($nom_p) . "&edad=$edad_p&entidad=" . urlencode($entidad_p) . "&info=Resultados&ver=1";

    // Cargar entidades (cache estatico para evitar multiples consultas)
    static $entidades_cache = null;
    if ($entidades === null && $entidades_cache === null) {
        $entidades_cache = [];
        $res_ent = $mysqli->query("SELECT DISTINCT entidad as nombre FROM examenes WHERE entidad IS NOT NULL AND entidad != '' ORDER BY entidad ASC");
        if ($res_ent) { while ($row_ent = $res_ent->fetch_assoc()) { $entidades_cache[] = $row_ent; } }
    }
    if ($entidades === null) $entidades = $entidades_cache;

    // Examenes
    $stmt_ex = $mysqli->prepare("SELECT p.*, e.codexamen, e.entidad FROM examenes e INNER JOIN procedimientos p ON e.codexamen=p.codigo WHERE e.identificacion=? AND e.fecha=? ORDER BY p.nombre ASC");
    $stmt_ex->bind_param("ss", $id_p, $fecha_examen);
    $stmt_ex->execute();
    $res_ex = $stmt_ex->get_result();
    $completados = 0;
    $examenes_html = '';
    while ($ex = $res_ex->fetch_assoc()) {
        $er = extraerResultados($mysqli, $ex['tabla'], $id_p, $ex['codexamen'], $fecha_examen, $cache_tablas, $cache_estructura);
        if ($er['estado'] === 'Completado') $completados++;
        $es_completado = ($er['estado'] === 'Completado');
        $eval = ($es_completado && !empty($er['resultado']) && !empty($er['referencia'])) ? evaluarResultado($er['resultado'], $er['referencia']) : 'normal';
        $es_critico = ($eval === 'danger');
        $es_warning = ($eval === 'warning');
        $status_class = $es_critico ? 'status-chip-danger' : ($es_warning ? 'status-chip-warning' : ($es_completado ? 'status-chip-normal' : 'status-chip-pending'));
        $status_icon = $es_critico ? 'bi-exclamation-octagon-fill' : ($es_warning ? 'bi-exclamation-triangle-fill' : ($es_completado ? 'bi-check-circle-fill' : 'bi-circle'));
        $status_text = $es_critico ? 'Critico' : ($es_warning ? 'Fuera rango' : ($es_completado ? 'Normal' : 'Pendiente'));
        $direccion = $es_completado ? direccionResultado($er['resultado'], $er['referencia']) : '';
        $q = http_build_query(['idx'=>bin2hex(random_bytes(10)),'identificacion'=>$id_p,'fecha'=>$fecha_examen,'nombres'=>$nom_p,'tabla'=>$ex['tabla'],'info'=>$ex['nombre'],'tipo'=>$ex['tipo'],'codexamen'=>$ex['codigo'],'edad'=>$edad_p,'entidad'=>$ex['entidad'],'embedido'=>1,'ver'=>1]);
        $us = "printphp/print_examen.php?$q";
        $examenes_html .= '<div class="exam-row">';
        $examenes_html .= '<div class="flex items-center justify-between gap-2">';
        $examenes_html .= '<div class="flex items-center gap-2 flex-1 min-w-0"><div class="w-1.5 h-1.5 rounded-full flex-shrink-0 ' . ($es_completado ? ($es_critico ? 'bg-red-500' : ($es_warning ? 'bg-amber-500' : 'bg-emerald-500')) : 'bg-slate-300 dark:bg-slate-600') . '"></div>';
        $examenes_html .= '<span class="text-xs font-medium truncate dark:text-slate-300 text-slate-700" title="' . htmlspecialchars($ex['nombre'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($ex['nombre'], ENT_QUOTES, 'UTF-8') . '</span>';
        $examenes_html .= '<span class="status-chip ' . $status_class . '"><i class="bi ' . $status_icon . '"></i>' . $status_text . '</span></div>';
        $examenes_html .= '<button @click="cargarReporte(\'' . $us . '\',{id:\'' . htmlspecialchars($id_p, ENT_QUOTES, 'UTF-8') . '\',nombre:\'' . htmlspecialchars($nom_p, ENT_QUOTES, 'UTF-8') . '\',fecha:\'' . htmlspecialchars($fecha_examen, ENT_QUOTES, 'UTF-8') . '\',url:\'' . $us . '\',initials:\'' . $iniciales2 . '\'})" class="p-1.5 rounded-lg transition-colors flex-shrink-0 focus-visible:ring-2 dark:hover:bg-indigo-900/30 dark:text-indigo-400 hover:bg-indigo-50 text-indigo-500" title="Ver examen"><i class="bi bi-eye-fill text-sm"></i></button>';
        $examenes_html .= '</div>';
        if ($es_completado && !empty($er['resultado'])) {
            $examenes_html .= '<div class="exam-row-result mt-1.5 ml-3.5"><span class="result-value ' . ($es_critico ? 'result-value-danger' : ($es_warning ? 'result-value-warning' : 'result-value-normal')) . $direccion . '">' . htmlspecialchars($er['resultado'], ENT_QUOTES, 'UTF-8') . '</span>';
            if (!empty($er['referencia'])) $examenes_html .= '<span class="text-[10px] dark:text-slate-500 text-slate-400"> Ref: ' . htmlspecialchars($er['referencia'], ENT_QUOTES, 'UTF-8') . '</span>';
            $examenes_html .= '</div>';
        }
        $examenes_html .= '<div class="mt-1.5 ml-3.5"><select class="select-modern py-1 px-2 text-[11px] font-medium border rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 outline-none w-full dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300 bg-white border-slate-200 text-slate-700" data-identificacion="' . htmlspecialchars($id_p, ENT_QUOTES, 'UTF-8') . '" data-fecha-examen="' . htmlspecialchars($fecha_examen, ENT_QUOTES, 'UTF-8') . '" data-codexamen="' . htmlspecialchars($ex['codexamen'], ENT_QUOTES, 'UTF-8') . '" @change="actualizarEntidad(event.target)">';
        $examenes_html .= '<option value="">-- Sin Entidad --</option>';
        foreach ($entidades as $entidad) {
            $selected = (trim($ex['entidad'] ?? '') === trim($entidad['nombre'])) ? ' selected="selected"' : '';
            $examenes_html .= '<option value="' . htmlspecialchars($entidad['nombre'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($entidad['nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $examenes_html .= '</select></div></div>';
    }
    $total_ex = $res_ex->num_rows;
    $pct = $total_ex > 0 ? round(($completados / $total_ex) * 100) : 0;

    $cid_e = htmlspecialchars($cid, ENT_QUOTES, 'UTF-8');
    $html = '<div class="swipe-card" @touchstart="iniciarSwipe($event,\'' . $cid_e . '\')" @touchmove="moverSwipe($event)" @touchend="finalizarSwipe($event,\'' . $cid_e . '\')">';
    $html .= '<div class="swipe-actions md:hidden">';
    $html .= '<button @click="resetSwipe(\'' . $cid_e . '\');cargarReporte(\'' . $url_todo . '\',{id:\'' . htmlspecialchars($id_p, ENT_QUOTES, 'UTF-8') . '\',nombre:\'' . htmlspecialchars($nom_p, ENT_QUOTES, 'UTF-8') . '\',fecha:\'' . htmlspecialchars($fecha_examen, ENT_QUOTES, 'UTF-8') . '\',url:\'' . $url_todo . '\',initials:\'' . $iniciales2 . '\'})" class="bg-orange-500"><i class="bi bi-printer"></i><span>Imprimir</span></button>';
    $html .= '<button @click="resetSwipe(\'' . $cid_e . '\');enviarWhatsappMulti(\'' . htmlspecialchars($telefono_p, ENT_QUOTES, 'UTF-8') . '\',\'' . htmlspecialchars($telefono_movil ?? '', ENT_QUOTES, 'UTF-8') . '\',\'' . htmlspecialchars($telefono_residencia2 ?? '', ENT_QUOTES, 'UTF-8') . '\',\'' . urlencode($url_todo) . '\')" class="bg-green-500 ' . ((empty($telefono_p) || $telefono_p == '0') ? 'opacity-50' : '') . '" ' . ((empty($telefono_p) || $telefono_p == '0') ? 'disabled' : '') . '><i class="bi bi-whatsapp"></i><span>WhatsApp</span></button>';
    $html .= '</div>';
    $html .= '<div class="swipe-content" id="swipe-' . $cid_e . '">';
    $html .= '<div class="patient-card animate-fade-in" :class="[idAbierto===\'' . $cid_e . '\'?\'active\':\'\', densidad===\'compacta\'?\'patient-card-compact\':\'patient-card-comfortable\']">';
    $html .= '<button @click="idAbierto=(idAbierto===\'' . $cid_e . '\'?null:\'' . $cid_e . '\')" class="patient-card-main w-full text-left flex justify-between items-center gap-3 transition-colors dark:hover:bg-slate-700/30 hover:bg-slate-50/50">';
    $html .= '<div class="flex items-center gap-3 flex-1 min-w-0">';
    $html .= '<div class="patient-card-avatar ' . $avatarClass . '">' . $iniciales . '</div>';
    $html .= '<div class="flex-1 min-w-0">';
    $html .= '<div class="font-bold text-sm truncate leading-tight"><span @click.stop="abrirHistorial(\'' . htmlspecialchars($id_p, ENT_QUOTES, 'UTF-8') . '\',\'' . htmlspecialchars($nom_p, ENT_QUOTES, 'UTF-8') . '\', $event)" role="button" tabindex="0" class="cursor-pointer hover:underline hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors">' . htmlspecialchars($nom_p, ENT_QUOTES, 'UTF-8') . '</span></div>';
    $html .= '<div class="patient-card-meta flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-1 dark:text-slate-500 text-slate-500">';
    $html .= '<span class="font-mono px-1.5 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-300 bg-slate-100 text-slate-600">' . htmlspecialchars($id_p, ENT_QUOTES, 'UTF-8') . '</span>';
    if (!empty($edad_p)) $html .= '<span><i class="bi bi-calendar3 mr-0.5"></i>' . round($edad_p) . 'a</span>';
    if (!empty($telefono_p) && $telefono_p != '0') $html .= '<span><i class="bi bi-telephone-fill mr-0.5"></i>' . htmlspecialchars($telefono_p, ENT_QUOTES, 'UTF-8') . '</span>';
    if ($todos) $html .= '<span class="font-medium dark:text-purple-400 text-purple-500"><i class="bi bi-calendar-check mr-0.5"></i>' . date('d/m/Y', strtotime($fecha_examen)) . '</span>';
    $html .= '</div>';
    $html .= '<div class="patient-card-progress"><div class="patient-card-progress-fill" style="width:' . $pct . '%"></div></div>';
    $html .= '<div class="text-[10px] mt-1 dark:text-slate-500 text-slate-400">' . $completados . '/' . $total_ex . ' examenes completados</div>';
    $html .= '</div></div>';
    $html .= '<i class="bi text-lg transition-transform duration-300 flex-shrink-0" :class="idAbierto===\'' . $cid_e . '\'?\'bi-chevron-up text-indigo-500\':\'bi-chevron-down text-slate-300 dark:text-slate-500\'"></i>';
    $html .= '</button>';
    $html .= '<div x-show="idAbierto===\'' . $cid_e . '\'" x-cloak x-transition class="border-t dark:border-slate-700 border-slate-100">';
    $html .= '<div class="p-3 space-y-2 dark:bg-gradient-to-b dark:from-slate-800/50 dark:to-transparent bg-gradient-to-b from-slate-50/80 to-white">';
    $html .= '<div class="grid grid-cols-2 gap-2">';
    $html .= '<button @click="cargarReporte(\'' . $url_todo . '\',{id:\'' . htmlspecialchars($id_p, ENT_QUOTES, 'UTF-8') . '\',nombre:\'' . htmlspecialchars($nom_p, ENT_QUOTES, 'UTF-8') . '\',fecha:\'' . htmlspecialchars($fecha_examen, ENT_QUOTES, 'UTF-8') . '\',url:\'' . $url_todo . '\',initials:\'' . $iniciales2 . '\'})" class="text-[10px] uppercase font-bold border-2 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 transition-all group dark:bg-slate-800 dark:border-slate-600 dark:hover:border-orange-500 dark:hover:bg-orange-900/20 dark:text-slate-300 bg-white border-slate-200 hover:border-orange-300 hover:bg-orange-50/50 text-slate-700"><i class="bi bi-collection-fill text-orange-400 text-sm group-hover:scale-110 transition-transform"></i><span>Imprimir Todo</span></button>';
    $html .= '<button @click="enviarWhatsappMulti(\'' . htmlspecialchars($telefono_p, ENT_QUOTES, 'UTF-8') . '\',\'' . htmlspecialchars($telefono_movil ?? '', ENT_QUOTES, 'UTF-8') . '\',\'' . htmlspecialchars($telefono_residencia2 ?? '', ENT_QUOTES, 'UTF-8') . '\',\'' . urlencode($url_todo) . '\')" class="text-[10px] uppercase font-bold border-2 py-1.5 px-2 rounded-lg flex items-center justify-center gap-1.5 transition-all group ' . ((empty($telefono_p) || $telefono_p == '0') ? 'opacity-40 cursor-not-allowed' : '') . '" ' . ((empty($telefono_p) || $telefono_p == '0') ? 'disabled title="Sin telefono"' : '') . ' dark:bg-slate-800 dark:border-slate-600 dark:hover:border-green-500 dark:hover:bg-green-900/20 dark:text-slate-300 bg-white border-slate-200 hover:border-green-300 hover:bg-green-50/50 text-slate-700"><i class="bi bi-whatsapp text-green-500 text-sm group-hover:scale-110 transition-transform"></i><span>WhatsApp</span></button>';
    $html .= '</div>';
    $html .= '<div><div class="text-[11px] font-bold uppercase tracking-wider mb-2 flex items-center gap-1.5 dark:text-slate-400 text-slate-500"><i class="bi bi-list-check dark:text-indigo-400 text-indigo-400"></i>Examenes<span class="font-normal dark:text-slate-500 text-slate-400">(' . date('d/m/Y', strtotime($fecha_examen)) . ')</span></div>';
    $html .= '<div class="space-y-1">' . ($examenes_html ?: '<div class="text-center py-6 dark:text-slate-600 text-slate-300"><i class="bi bi-clipboard-x text-2xl mb-1 block"></i><p class="text-[11px]">No hay examenes registrados</p></div>') . '</div></div></div></div></div></div></div>';

    return $html;
}

$res_conf = $mysqli->query("SELECT nombreCorto, nombreLaboratorio, urlLogoLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
$dato_conf = $res_conf ? $res_conf->fetch_assoc() : null;
$nombreLab = $dato_conf['nombreLaboratorio'] ?? 'Laboratorio Clinico';
$nombreCorto = $dato_conf['nombreCorto'] ?? 'LAB';
$tieneLogo = !empty($dato_conf['urlLogoLaboratorio']);
$urlLogo = $tieneLogo ? "data:image/png;base64," . $dato_conf['urlLogoLaboratorio'] : '';
$error = "";

if (isset($_POST['login'])) {
        $password_ingresado = $_POST['password'] ?? '';
        $stmt = $mysqli->prepare("SELECT nombreLaboratorio FROM configuracion WHERE tarjetaPlaboratorio = ? LIMIT 1");
        if (!$stmt) { error_log("Login prepare failed: " . $mysqli->error); $error = "Error interno, contacte soporte."; }
        else {
            $stmt->bind_param("s", $password_ingresado); $stmt->execute(); $res_login = $stmt->get_result();
            if ($res_login->num_rows > 0) {
                $datos_u = $res_login->fetch_assoc();
                $_SESSION['autenticado'] = true; $_SESSION['usuario_nombre'] = $datos_u['nombreLaboratorio'];
                $_SESSION['lab_id'] = hash('sha256', $password_ingresado . '_lab_salt');
                $csrf_token = generarCSRF();
            } else { $error = "Acceso denegado. Verifique sus datos."; }
    }
}
if (isset($_POST['action']) && $_POST['action'] == 'actualizar_entidad') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Sesion invalida.']); exit; }
    $identificacion = $_POST['identificacion'] ?? ''; $fecha_examen = $_POST['fecha_examen'] ?? ''; $codexamen = $_POST['codexamen'] ?? '';
    if (!empty($identificacion) && !empty($fecha_examen) && !empty($codexamen)) {
        $nueva_entidad = $_POST['entidad'] ?? '';
        $stmt_u = $mysqli->prepare("UPDATE examenes SET entidad = ? WHERE identificacion = ? AND fecha = ? AND codexamen = ?");
        if (!$stmt_u) { error_log("actualizar_entidad prepare failed: " . $mysqli->error); echo json_encode(['success' => false, 'message' => 'Error interno.']); exit; }
        $stmt_u->bind_param("ssss", $nueva_entidad, $identificacion, $fecha_examen, $codexamen);
        if ($stmt_u->execute()) echo json_encode(['success' => true, 'message' => 'Entidad actualizada correctamente.', 'entidad' => $nueva_entidad]);
        else { error_log("actualizar_entidad execute failed: " . $mysqli->error); echo json_encode(['success' => false, 'message' => 'Error al actualizar.']); }
    } else { echo json_encode(['success' => false, 'message' => 'Datos incompletos.']); }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'consulta_entidades') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Sesion invalida.']); exit; }
    $entidad = $_POST['entidad'] ?? ''; $fecha_inicio = $_POST['fecha_inicio'] ?? ''; $fecha_fin = $_POST['fecha_fin'] ?? '';
    $solo_resultados = $_POST['solo_resultados'] ?? '0'; $agrupar_fecha = $_POST['agrupar_fecha'] ?? '1';
    if (empty($fecha_inicio) || empty($fecha_fin)) { echo json_encode(['success' => false, 'message' => 'Las fechas son obligatorias.']); exit; }
    $sql_con = "SELECT e.identificacion, e.fecha as fecha_examen, p.nombre as examen_nombre, e.codexamen as examen_codigo, e.entidad, p.tipo as examen_tipo, p.tabla as examen_tabla, CONCAT_WS(' ', pa.apellidos, pa.nombres) as paciente, pa.edad as edad, pa.genero, pa.telefono, '' as resultado, '' as referencia, '' as estado FROM examenes e INNER JOIN procedimientos p ON e.codexamen = p.codigo LEFT JOIN paciente pa ON e.identificacion = pa.identificacion WHERE e.fecha BETWEEN ? AND ?";
    $params_con = [$fecha_inicio, $fecha_fin]; $types_con = "ss";
    if (!empty($entidad)) { $sql_con .= " AND e.entidad = ?"; $params_con[] = $entidad; $types_con .= "s"; }
    if ($solo_resultados == '1') { $sql_con .= " AND e.codexamen IN (SELECT DISTINCT codexamen FROM examen_tipo_1 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_2 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_3 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_5 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_7 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM perfilLipidico WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM hemogramaRayto WHERE resultado IS NOT NULL AND resultado != '')"; }
    $sql_con .= " ORDER BY e.fecha DESC, e.identificacion ASC LIMIT 1000";
    $stmt_con = $mysqli->prepare($sql_con);
    if (!$stmt_con) { error_log("consulta_entidades prepare failed: " . $mysqli->error); echo json_encode(['success' => false, 'message' => 'Error interno.']); exit; }
    $stmt_con->bind_param($types_con, ...$params_con); $stmt_con->execute(); $result_con = $stmt_con->get_result();
    $examenes = []; $total_fechas = 0; $total_registros = $result_con->num_rows;
    while ($row_con = $result_con->fetch_assoc()) {
        $er = extraerResultados($mysqli, $row_con['examen_tabla'] ?? $row_con['examen_tipo'], $row_con['identificacion'], $row_con['examen_codigo'], $row_con['fecha_examen'], $cache_tablas, $cache_estructura);
        $row_con['resultado'] = $er['resultado']; $row_con['referencia'] = $er['referencia']; $row_con['estado'] = $er['estado'];
        $examenes[] = $row_con;
    }
    $examenes_agrupados = [];
    if ($agrupar_fecha == '1') {
        foreach ($examenes as $ex) { $fecha = $ex['fecha_examen']; if (!isset($examenes_agrupados[$fecha])) { $examenes_agrupados[$fecha] = ['fecha' => $fecha, 'cantidad' => 0, 'examenes' => []]; $total_fechas++; } $examenes_agrupados[$fecha]['cantidad']++; $examenes_agrupados[$fecha]['examenes'][] = $ex; }
        krsort($examenes_agrupados); $examenes_agrupados = array_values($examenes_agrupados);
    } else { $examenes_agrupados = $examenes; }
    echo json_encode(['success' => true, 'resultados' => $examenes_agrupados, 'total_fechas' => $total_fechas, 'total_registros' => $total_registros]);
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'consulta_pacientes_resultados') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Sesion invalida.']); exit; }
    $identificacion = $_POST['identificacion'] ?? ''; $nombres = $_POST['nombres'] ?? ''; $telefono = $_POST['telefono'] ?? '';
    $ciudad = $_POST['ciudad'] ?? ''; $entidad = $_POST['entidad'] ?? ''; $include_examenes = $_POST['include_examenes'] ?? '0';
    $solo_con_resultados = $_POST['solo_con_resultados'] ?? '0'; $limit = min(intval($_POST['limit'] ?? 50), 200);
    if (empty($identificacion) && empty($nombres) && empty($telefono) && empty($ciudad) && empty($entidad)) { echo json_encode(['success' => false, 'message' => 'Debe proporcionar al menos un criterio de busqueda.']); exit; }
    $sql_pac = "SELECT DISTINCT pa.identificacion, CONCAT_WS(' ', pa.apellidos, pa.nombres) as nombre_completo, pa.fecnac, pa.edad as edad, pa.genero, pa.telefono, pa.telefono_movil, pa.correo, pa.ciudad_residencia, pa.direccion_residencia, e.entidad, COUNT(DISTINCT e.fecha) as total_visitas, MAX(e.fecha) as ultima_visita, COUNT(DISTINCT e.codexamen) as total_examenes FROM paciente pa INNER JOIN examenes e ON pa.identificacion = e.identificacion WHERE 1=1";
    $params_pac = []; $types_pac = "";
    if (!empty($identificacion)) { $sql_pac .= " AND pa.identificacion LIKE ?"; $params_pac[] = "%$identificacion%"; $types_pac .= "s"; }
    if (!empty($nombres)) { $sql_pac .= " AND CONCAT_WS(' ', pa.apellidos, pa.nombres) LIKE ?"; $params_pac[] = "%$nombres%"; $types_pac .= "s"; }
    if (!empty($telefono)) { $sql_pac .= " AND (pa.telefono LIKE ? OR pa.telefono_movil LIKE ?)"; $params_pac[] = "%$telefono%"; $params_pac[] = "%$telefono%"; $types_pac .= "ss"; }
    if (!empty($ciudad)) { $sql_pac .= " AND pa.ciudad_residencia LIKE ?"; $params_pac[] = "%$ciudad%"; $types_pac .= "s"; }
    if (!empty($entidad)) { $sql_pac .= " AND e.entidad LIKE ?"; $params_pac[] = "%$entidad%"; $types_pac .= "s"; }
    if ($solo_con_resultados == '1') { $sql_pac .= " AND e.codexamen IN (SELECT DISTINCT codexamen FROM examen_tipo_1 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_2 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_3 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_5 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM examen_tipo_7 WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM perfilLipidico WHERE resultado IS NOT NULL AND resultado != '' UNION SELECT DISTINCT codexamen FROM hemogramaRayto WHERE resultado IS NOT NULL AND resultado != '')"; }
    $sql_pac .= " GROUP BY pa.identificacion ORDER BY ultima_visita DESC LIMIT ?";
    $params_pac[] = $limit; $types_pac .= "i";
    $stmt_pac = $mysqli->prepare($sql_pac);
    if (!$stmt_pac) { error_log("consulta_pacientes prepare failed: " . $mysqli->error); echo json_encode(['success' => false, 'message' => 'Error interno.']); exit; }
    $stmt_pac->bind_param($types_pac, ...$params_pac); $stmt_pac->execute(); $result_pac = $stmt_pac->get_result();
    $pacientes = []; $examenes_con_resultados_total = 0;
    while ($row_pa = $result_pac->fetch_assoc()) {
        $total_examenes_pa = $row_pa['total_examenes']; $examenes_con_resultados_pa = 0; $examenes_pa = [];
        if ($include_examenes == '1') {
            $sql_ex = "SELECT e.fecha, p.nombre, p.codigo, p.tipo, p.tabla, e.entidad, e.codexamen FROM examenes e INNER JOIN procedimientos p ON e.codexamen = p.codigo WHERE e.identificacion = ? ORDER BY e.fecha DESC";
            $stmt_ex = $mysqli->prepare($sql_ex);
            if ($stmt_ex) {
                $stmt_ex->bind_param("s", $row_pa['identificacion']); $stmt_ex->execute(); $result_ex = $stmt_ex->get_result();
                while ($row_ex = $result_ex->fetch_assoc()) {
                    $er = extraerResultados($mysqli, $row_ex['tabla'], $row_pa['identificacion'], $row_ex['codexamen'], $row_ex['fecha'], $cache_tablas, $cache_estructura);
                    $estado_ex = $er['estado']; if ($estado_ex === 'Completado') $examenes_con_resultados_pa++;
                    $examenes_pa[] = ['fecha' => $row_ex['fecha'], 'nombre' => $row_ex['nombre'], 'codigo' => $row_ex['codexamen'], 'tipo' => $row_ex['tipo'], 'tabla' => $row_ex['tabla'], 'entidad' => $row_ex['entidad'], 'estado' => $estado_ex, 'resultado' => $er['resultado'], 'referencia' => $er['referencia']];
                }
            }
        }
        $examenes_con_resultados_total += $examenes_con_resultados_pa;
        $pacientes[] = ['identificacion' => $row_pa['identificacion'], 'nombre_completo' => trim($row_pa['nombre_completo']), 'edad' => $row_pa['edad'], 'genero' => $row_pa['genero'], 'telefono' => $row_pa['telefono'], 'telefono_movil' => $row_pa['telefono_movil'] ?? '', 'correo' => $row_pa['correo'], 'ciudad_residencia' => $row_pa['ciudad_residencia'], 'entidad' => $row_pa['entidad'], 'total_visitas' => $row_pa['total_visitas'], 'ultima_visita' => $row_pa['ultima_visita'], 'total_examenes' => $total_examenes_pa, 'examenes_con_resultados' => $examenes_con_resultados_pa, 'examenes' => $examenes_pa];
    }
    echo json_encode(['success' => true, 'pacientes' => $pacientes, 'total_pacientes' => count($pacientes), 'examenes_con_resultados_total' => $examenes_con_resultados_total]);
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'cargar_mas') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Sesion invalida.']); exit; }
    $fecha = $_POST['fecha'] ?? date('Y-m-d'); $buscar = $_POST['buscar'] ?? ''; $todos = $_POST['todos'] ?? '0'; $offset = intval($_POST['offset'] ?? 0); $limit_carga = 20;
    $where = "WHERE 1=1"; $params_cm = []; $types_cm = "";
    if ($todos !== '1') { $where .= " AND e.fecha = ?"; $params_cm[] = $fecha; $types_cm .= "s"; }
    if (!empty($buscar)) { $where .= " AND (pa.identificacion LIKE ? OR CONCAT(pa.apellidos,' ',pa.nombres) LIKE ?)"; $params_cm[] = "%$buscar%"; $params_cm[] = "%$buscar%"; $types_cm .= "ss"; }
    $sql_cm = "SELECT e.identificacion, e.fecha as fecha_examen, CONCAT_WS(' ', pa.apellidos, pa.nombres) as nombres, pa.edad as edad, pa.genero, pa.telefono, pa.telefono_movil, pa.telefono_residencia2, e.entidad, pa.fecnac FROM examenes e INNER JOIN paciente pa ON e.identificacion = pa.identificacion $where GROUP BY e.identificacion, e.fecha ORDER BY e.fecha DESC, nombres ASC LIMIT $limit_carga OFFSET $offset";
    $stmt_cm = $mysqli->prepare($sql_cm);
    if (!$stmt_cm) { error_log("cargar_mas prepare failed: " . $mysqli->error); echo json_encode(['success' => false, 'message' => 'Error interno.']); exit; }
    if (!empty($params_cm)) $stmt_cm->bind_param($types_cm, ...$params_cm); $stmt_cm->execute(); $result_cm = $stmt_cm->get_result();
    $sql_total = "SELECT COUNT(DISTINCT CONCAT(e.identificacion, e.fecha)) as total FROM examenes e INNER JOIN paciente pa ON e.identificacion = pa.identificacion $where";
    $stmt_total = $mysqli->prepare($sql_total);
    if ($stmt_total) {
        if (!empty($params_cm)) $stmt_total->bind_param($types_cm, ...$params_cm); $stmt_total->execute(); $total_cm = $stmt_total->get_result()->fetch_assoc()['total'];
    } else { $total_cm = 0; }
    $html_cm = "";
    while ($row_cm = $result_cm->fetch_assoc()) {
        $id_p=$row_cm['identificacion']; $nom_p=$row_cm['nombres']; $edad_p=$row_cm['edad'];
        $genero_p=$row_cm['genero']; $telefono_p=$row_cm['telefono']; $fecha_examen=$row_cm['fecha_examen']; $entidad_p=$row_cm['entidad'];
        if(empty($edad_p)&&!empty($row_cm['fecnac'])){$edad_p=(new DateTime())->diff(new DateTime($row_cm['fecnac']))->y;}
        $html_cm .= renderizarTarjetaPaciente($id_p, $nom_p, $edad_p, $genero_p, $telefono_p, $row_cm['telefono_movil'] ?? '', $row_cm['telefono_residencia2'] ?? '', $fecha_examen, $entidad_p, $todos, $mysqli, $cache_tablas, $cache_estructura);
    }
    echo json_encode(['success' => true, 'html' => $html_cm, 'next_offset' => $offset + $limit_carga, 'total' => $total_cm]);
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'historial_paciente') {
    if (!validarCSRF($_POST['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Sesion invalida.']); exit; }
    $identificacion = trim($_POST['identificacion'] ?? '');
    if (empty($identificacion)) { echo json_encode(['success' => false, 'message' => 'Identificacion requerida.']); exit; }
    $stmt_hp = $mysqli->prepare("SELECT pa.identificacion, CONCAT_WS(' ', pa.apellidos, pa.nombres) as nombre, pa.edad, pa.genero, pa.telefono, pa.telefono_movil, pa.ciudad_residencia, pa.direccion_residencia, pa.fecnac, e.entidad FROM paciente pa LEFT JOIN examenes e ON pa.identificacion = e.identificacion WHERE pa.identificacion = ? LIMIT 1");
    if (!$stmt_hp) { error_log("historial_paciente prepare failed: " . $mysqli->error); echo json_encode(['success' => false, 'message' => 'Error interno.']); exit; }
    $stmt_hp->bind_param("s", $identificacion); $stmt_hp->execute(); $pac_row = $stmt_hp->get_result()->fetch_assoc();
    if (!$pac_row) { echo json_encode(['success' => false, 'message' => 'Paciente no encontrado.']); exit; }
    $edad_pac = $pac_row['edad'];
    if (empty($edad_pac) && !empty($pac_row['fecnac'])) { $edad_pac = (new DateTime())->diff(new DateTime($pac_row['fecnac']))->y; }
    $paciente_info = ['identificacion' => $pac_row['identificacion'], 'nombre' => trim($pac_row['nombre']), 'edad' => round($edad_pac), 'genero' => $pac_row['genero'], 'telefono' => $pac_row['telefono'], 'telefono_movil' => $pac_row['telefono_movil'] ?? '', 'ciudad' => $pac_row['ciudad_residencia'] ?? '', 'entidad' => $pac_row['entidad'] ?? ''];
    $stmt_ex_hp = $mysqli->prepare("SELECT e.fecha, p.nombre, p.codigo, p.tipo, p.tabla, e.entidad, e.codexamen FROM examenes e INNER JOIN procedimientos p ON e.codexamen = p.codigo WHERE e.identificacion = ? ORDER BY e.fecha DESC, p.nombre ASC");
    if (!$stmt_ex_hp) { error_log("historial_paciente examenes prepare failed: " . $mysqli->error); echo json_encode(['success' => false, 'message' => 'Error interno.']); exit; }
    $stmt_ex_hp->bind_param("s", $identificacion); $stmt_ex_hp->execute(); $res_ex_hp = $stmt_ex_hp->get_result();
    $fechas_agrupadas = []; $total_examenes_hp = 0; $examenes_completados_hp = 0; $fechas_set = []; $examenes_disponibles_map = [];
    while ($row_ex_hp = $res_ex_hp->fetch_assoc()) {
        $total_examenes_hp++;
        $er_hp = extraerResultados($mysqli, $row_ex_hp['tabla'], $identificacion, $row_ex_hp['codexamen'], $row_ex_hp['fecha'], $cache_tablas, $cache_estructura);
        $estado_hp = $er_hp['estado'];
        $status_hp = 'normal';
        if ($estado_hp === 'Completado' && !empty($er_hp['resultado']) && !empty($er_hp['referencia']) && $er_hp['referencia'] !== 'N/A') {
            $status_hp = evaluarResultado($er_hp['resultado'], $er_hp['referencia']);
            $examenes_completados_hp++;
        } elseif ($estado_hp === 'Completado') { $examenes_completados_hp++; }
        $valor_numerico = null;
        if ($status_hp !== 'normal' || ($estado_hp === 'Completado' && !empty($er_hp['resultado']))) {
            if (preg_match('/^([0-9]+\.?[0-9]*)$/', trim(str_replace(',', '.', $er_hp['resultado'])), $mn)) {
                $valor_numerico = floatval($mn[1]);
            }
        }
        $fecha_ex_hp = $row_ex_hp['fecha'];
        $fechas_set[$fecha_ex_hp] = true;
        if (!isset($fechas_agrupadas[$fecha_ex_hp])) $fechas_agrupadas[$fecha_ex_hp] = [];
        $fechas_agrupadas[$fecha_ex_hp][] = ['nombre' => $row_ex_hp['nombre'], 'codigo' => $row_ex_hp['codexamen'], 'tipo' => $row_ex_hp['tipo'], 'resultado' => $er_hp['resultado'] ?: 'Pendiente', 'referencia' => $er_hp['referencia'] ?? 'N/A', 'estado' => $estado_hp, 'status' => $status_hp, 'valor_numerico' => $valor_numerico];
        $cod_ex_hp = $row_ex_hp['codexamen'];
        if (!isset($examenes_disponibles_map[$cod_ex_hp])) $examenes_disponibles_map[$cod_ex_hp] = ['nombre' => $row_ex_hp['nombre'], 'codigo' => $cod_ex_hp, 'tiene_numerico' => false];
        if ($valor_numerico !== null) $examenes_disponibles_map[$cod_ex_hp]['tiene_numerico'] = true;
    }
    $fechas_result = [];
    foreach ($fechas_agrupadas as $fecha_key => $examenes_arr) { $fechas_result[] = ['fecha' => $fecha_key, 'examenes' => $examenes_arr]; }
    $examenes_disponibles_result = array_values(array_filter($examenes_disponibles_map, function($e) { return $e['tiene_numerico']; }));
    echo json_encode(['success' => true, 'paciente' => $paciente_info, 'total_visitas' => count($fechas_set), 'total_examenes' => $total_examenes_hp, 'examenes_completados' => $examenes_completados_hp, 'fechas' => $fechas_result, 'examenes_disponibles' => $examenes_disponibles_result]);
    exit;
}

if (!isset($_SESSION['autenticado'])):
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="icons/thiings/microscope.png">
<title>Acceso al Sistema</title>
<link rel="stylesheet" href="assets/css/lab.css?v=3">
<script src="https://cdn.tailwindcss.com"></script>
<script>if (window.tailwind) { tailwind.config = { darkMode: 'class' }; }</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/0.160.0/three.min.js"></script>
<script src="assets/js/utils.js?v=3"></script>
</head>
<body class="login-bg h-screen flex items-center justify-center p-4" style="display:flex!important;align-items:center!important;justify-content:center!important;min-height:100vh!important">
<canvas id="three-login-canvas"></canvas>
<div class="glass-card p-8 sm:p-10 rounded-3xl shadow-2xl w-full max-w-md login-enter border border-white/20 relative z-10">
<div class="text-center mb-8">
<div class="mb-5 icon-float">
<?php if ($tieneLogo): ?><img src="<?= str_starts_with(trim($urlLogo),'data:image')?$urlLogo:htmlspecialchars($urlLogo) ?>" alt="Logo" class="w-20 h-20 mx-auto object-contain drop-shadow-lg rounded-2xl">
<?php else: ?><img src="icons/thiings/microscope.png" alt="Lab" class="w-20 h-20 mx-auto object-contain drop-shadow-lg rounded-2xl"><?php endif; ?>
</div>
<h1 class="text-2xl font-extrabold text-slate-800 tracking-tight"><?= htmlspecialchars($nombreLab) ?></h1>
<p class="text-slate-500 text-sm mt-1">Ingrese su clave de configuracion</p>
</div>
<?php if ($error): ?><div class="bg-red-50 text-red-600 p-3 rounded-xl mb-5 text-sm flex items-center gap-2 border border-red-100"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form action="" method="POST" class="space-y-5">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
<div><label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Contrasena</label>
<div class="relative"><span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400"><i class="bi bi-key"></i></span>
<input type="password" name="password" id="loginPassword" placeholder="Ingrese contrasena" required class="login-input w-full pl-11 pr-12 py-3.5 border-2 border-slate-200 rounded-2xl focus:border-indigo-500 outline-none transition text-sm">
<button type="button" onclick="togglePassword()" class="pw-toggle absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 transition-colors"><i class="bi bi-eye-slash" id="pwIcon"></i></button></div></div>
<button type="submit" name="login" class="login-btn w-full bg-gradient-to-r from-indigo-600 to-indigo-700 text-white py-3.5 rounded-2xl font-bold text-sm tracking-wide shadow-lg shadow-indigo-200">INICIAR SESION</button>
</form>
<p class="text-center text-[11px] text-slate-400 mt-6">Panel de Resultados Clinicos</p>
</div>
<script>function togglePassword(){const p=document.getElementById('loginPassword'),i=document.getElementById('pwIcon');if(p.type==='password'){p.type='text';i.className='bi bi-eye'}else{p.type='password';i.className='bi bi-eye-slash'}}</script>
<script src="assets/js/three-login.js"></script>
</body></html>
<?php exit; endif; ?>

<?php
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$buscar = $_GET['buscar'] ?? '';
$todos = isset($_GET['todos']) && $_GET['todos'] == '1';
$script_actual = basename($_SERVER['PHP_SELF']);
$es_hoy = ($fecha == date('Y-m-d'));

$identificacion_inicial = $_GET['identificacion'] ?? '';
$entidad_inicial = $_GET['entidad'] ?? '';

$error_db = null;
$entidades = [];
$resultados = null;
$total_pacientes = 0;
$has_more = false;

try {
    $res_entidades = $mysqli->query("SELECT DISTINCT entidad as nombre FROM examenes WHERE entidad IS NOT NULL AND entidad != '' ORDER BY entidad ASC");
    if ($res_entidades) {
        while ($row_ent = $res_entidades->fetch_assoc()) { $entidades[] = $row_ent; }
    }
} catch (Exception $e) {
    error_log("Error cargando entidades: " . $e->getMessage());
    $error_db = "Error interno al cargar entidades.";
}

$where_main = "WHERE 1=1";
$params_main = []; $types_main = "";
if (!$todos) { $where_main .= " AND e.fecha = ?"; $params_main[] = $fecha; $types_main .= "s"; }
if (!empty($buscar)) { $where_main .= " AND (pa.identificacion LIKE ? OR CONCAT(pa.apellidos,' ',pa.nombres) LIKE ?)"; $params_main[] = "%$buscar%"; $params_main[] = "%$buscar%"; $types_main .= "ss"; }

try {
    $sql_main = "SELECT e.identificacion, e.fecha as fecha_examen, CONCAT_WS(' ', pa.apellidos, pa.nombres) as nombres, pa.edad as edad, pa.genero, pa.telefono, pa.telefono_movil, pa.telefono_residencia2, e.entidad, pa.fecnac FROM examenes e INNER JOIN paciente pa ON e.identificacion = pa.identificacion $where_main GROUP BY e.identificacion, e.fecha ORDER BY e.fecha DESC, nombres ASC LIMIT 100";
    $stmt_main = $mysqli->prepare($sql_main);
    if (!empty($params_main)) { $stmt_main->bind_param($types_main, ...$params_main); }
    $stmt_main->execute();
    $resultados = $stmt_main->get_result();

    $sql_total_main = "SELECT COUNT(DISTINCT CONCAT(e.identificacion, e.fecha)) as total FROM examenes e INNER JOIN paciente pa ON e.identificacion = pa.identificacion $where_main";
    $stmt_total_main = $mysqli->prepare($sql_total_main);
    if (!empty($params_main)) { $stmt_total_main->bind_param($types_main, ...$params_main); }
    $stmt_total_main->execute();
    $total_pacientes = $stmt_total_main->get_result()->fetch_assoc()['total'];
    $has_more = $total_pacientes > 100;
} catch (Exception $e) {
    error_log("Error cargando resultados: " . $e->getMessage());
    $error_db = "Error interno al cargar resultados.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
<link rel="icon" type="image/png" href="icons/thiings/microscope.png">
<title><?= htmlspecialchars($nombreLab) ?> - Resultados</title>
<link rel="stylesheet" href="assets/css/lab.css?v=3">
<script src="https://cdn.tailwindcss.com"></script>
<script>if (window.tailwind) { tailwind.config = { darkMode: 'class' }; }</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/0.160.0/three.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<script src="assets/js/utils.js?v=3"></script>
<script src="assets/js/lab-app.js?v=3"></script>
<style>
[x-cloak]{display:none!important}
</style>
</head>
<body class="h-screen flex flex-col overflow-hidden transition-colors duration-300"
      :class="dark ? 'bg-[#0b1120] text-slate-100' : 'bg-slate-100 text-slate-900'"
      x-data="labApp('<?= htmlspecialchars($identificacion_inicial) ?>')"
      data-fecha="<?= htmlspecialchars($fecha) ?>"
      data-busqueda="<?= htmlspecialchars($buscar) ?>"
      data-todos="<?= $todos ? '1' : '0' ?>"
      data-total="<?= $total_pacientes ?>"
      data-has-more="<?= $has_more ? '1' : '0' ?>">
<header class="glass border-b px-4 py-2.5 flex-shrink-0 z-30 transition-colors duration-300" :class="dark ? 'border-slate-700/60' : 'border-slate-200/60'">
<div class="max-w-[1600px] mx-auto flex justify-between items-center gap-3">
<div class="flex items-center gap-3 min-w-0">
<button @click="menuAbierto=!menuAbierto" class="md:hidden p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition text-slate-600 dark:text-slate-300"><i class="bi" :class="menuAbierto?'bi-x-lg':'bi-list'"></i></button>
<?php if($tieneLogo):?><img src="<?= str_starts_with(trim($urlLogo),'data:image')?$urlLogo:htmlspecialchars($urlLogo) ?>" alt="Logo" class="h-9 w-9 object-contain rounded-lg flex-shrink-0">
<?php else:?><img src="icons/thiings/laboratory.png" alt="Lab" class="h-9 w-9 object-contain rounded-lg flex-shrink-0 thiings-icon"><?php endif;?>
<div class="min-w-0"><div class="font-bold text-sm truncate leading-tight"><?= htmlspecialchars($nombreLab) ?></div><div class="text-[11px] leading-tight" :class="dark ? 'text-slate-400' : 'text-slate-400'">Panel de Resultados</div></div>
</div>
<div class="hidden md:flex items-center gap-2">
<button @click="abrirModalEntidades()" class="btn-primary text-white px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"><i class="bi bi-building text-sm"></i><span>ENTIDADES</span></button>
<button @click="abrirModalPacientesBusqueda()" class="btn-success text-white px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm"><i class="bi bi-people-fill text-sm"></i><span>PACIENTES</span></button>
<a href="admin.php" class="px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm border" :class="dark ? 'bg-slate-700 hover:bg-slate-800 text-white border-transparent' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200'"><i class="bi bi-shield-lock-fill text-sm"></i><span>ADMIN</span></a>
<div class="flex items-center gap-2 ml-2 pl-2" :class="dark ? 'border-slate-700' : 'border-slate-200'" style="border-left:1px solid">
<div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px]" :class="dark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-600'"><i class="bi bi-person-fill" :class="dark ? 'text-slate-500' : 'text-slate-400'"></i><span class="font-medium truncate max-w-[120px]"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span></div>
<div class="px-2.5 py-1 rounded-lg text-[11px] font-medium" :class="dark ? 'bg-slate-800 text-slate-400' : 'bg-slate-100 text-slate-500'"><?php if($todos&&!empty($busqueda)):?>Todas las fechas<?php else:?><?= date('d/m/Y',strtotime($fecha)) ?><?php endif;?></div>
<div class="px-2.5 py-1 rounded-lg text-[11px] font-bold" :class="dark ? 'bg-emerald-900/30 text-emerald-400' : 'bg-emerald-50 text-emerald-700'"><?= $total_pacientes ?> pacientes</div>
</div>
<button @click="showCommandPalette=!showCommandPalette;commandQuery='';updateCommandResults()" class="p-2 rounded-xl transition text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus-visible:ring-2" :class="dark ? 'hover:bg-slate-800' : 'hover:bg-slate-100'" title="Command Palette (Ctrl+K)"><i class="bi bi-search text-sm"></i></button>
<div class="density-toggle" title="Densidad de lista">
  <button @click="densidad='compacta'" :class="densidad==='compacta'?'active':''"><i class="bi bi-list"></i></button>
  <button @click="densidad='comoda'" :class="densidad==='comoda'?'active':''"><i class="bi bi-list-nested"></i></button>
</div>
<button @click="toggleDarkMode()" class="dark-mode-toggle focus-visible:ring-2" :title="dark ? 'Modo claro' : 'Modo oscuro'"><i class="bi bi-sun-fill icon-sun text-amber-400"></i><i class="bi bi-moon-fill icon-moon text-indigo-300"></i></button>
<form method="POST" class="inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><button type="submit" name="logout" class="bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ml-1"><i class="bi bi-box-arrow-right text-sm"></i><span>SALIR</span></button></form>
</div>
</div>
<div x-show="menuAbierto" x-cloak x-transition class="md:hidden mt-3 pt-3 border-t space-y-2" :class="dark ? 'border-slate-700/60' : 'border-slate-200/60'">
<button @click="abrirModalEntidades();menuAbierto=false" class="w-full btn-primary text-white px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2"><i class="bi bi-building"></i> ENTIDADES</button>
<button @click="abrirModalPacientesBusqueda();menuAbierto=false" class="w-full btn-success text-white px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2"><i class="bi bi-people-fill"></i> PACIENTES</button>
<a href="admin.php" class="w-full px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 border" :class="dark ? 'bg-slate-700 hover:bg-slate-800 text-white border-transparent' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200'"><i class="bi bi-shield-lock-fill"></i> ADMIN</a>
<div class="flex items-center justify-between"><div class="flex items-center gap-1.5 text-[11px]" :class="dark ? 'text-slate-400' : 'text-slate-600'"><i class="bi bi-person-fill" :class="dark ? 'text-slate-500' : 'text-slate-400'"></i> <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div><form method="POST" class="inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"><button type="submit" name="logout" class="text-red-500 text-xs font-bold flex items-center gap-1"><i class="bi bi-box-arrow-right"></i> SALIR</button></form></div>
<div class="flex items-center gap-2 mt-2">
<button @click="toggleDarkMode()" class="flex items-center gap-1.5 text-[11px] px-3 py-1.5 rounded-lg" :class="dark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-600'"><i class="bi" :class="dark ? 'bi-sun-fill text-amber-400' : 'bi-moon-fill text-slate-400'"></i> <span x-text="dark ? 'Modo claro' : 'Modo oscuro'"></span></button>
<button @click="showCommandPalette=true;commandQuery='';updateCommandResults();menuAbierto=false" class="flex items-center gap-1.5 text-[11px] px-3 py-1.5 rounded-lg" :class="dark ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-600'"><i class="bi bi-search"></i> Buscar (Ctrl+K)</button>
</div>
</div>
</header>
<div class="flex flex-1 overflow-hidden">
<aside class="w-full md:w-[440px] lg:w-[500px] xl:w-[540px] border-r flex flex-col shadow-sm z-20 transition-colors duration-300" :class="{'hidden md:flex': vistaReporte, 'bg-white': !dark, 'bg-[#131c31]': dark}" :style="'border-color:' + (dark ? '#1e293b' : '#e2e8f0')">
<div class="p-3 border-b space-y-2 transition-colors duration-300" :class="dark ? 'border-slate-700' : 'border-slate-100'" x-show="!idAbierto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2">
<div class="flex items-center justify-between">
<button @click="filtrosAbiertos=!filtrosAbiertos" class="flex items-center gap-1.5 text-[11px] font-bold px-2 py-1 rounded-lg transition" :class="dark ? 'text-slate-400 hover:bg-slate-800' : 'text-slate-500 hover:bg-slate-100'"><i class="bi text-sm" :class="filtrosAbiertos?'bi-funnel-fill':'bi-funnel'"></i> Filtros</button>
<div class="flex items-center gap-1 text-[10px]" :class="dark ? 'text-slate-500' : 'text-slate-400'"><i class="bi bi-calendar3"></i><span><?= date('d/m/Y',strtotime($fecha)) ?></span></div>
</div>
<div x-show="filtrosAbiertos" x-transition>
<div class="grid grid-cols-1 gap-2 pt-1">
<div class="relative" id="fecha-container" x-show="!buscarTodos">
<div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"><i class="bi bi-calendar3 text-sm" :class="dark ? 'text-slate-500' : 'text-slate-400'"></i></div>
<input type="date" x-model="fechaBusqueda" @change="cambiarFecha()" class="input-modern pl-10 flatpickr-input" style="padding-right:1rem">
</div>
<div class="flex gap-2">
<div class="relative flex-1">
<div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"><i class="bi bi-search text-sm" :class="dark ? 'text-slate-500' : 'text-slate-400'"></i></div>
<input type="text" x-model="textoBusqueda" @input.debounce.400ms="buscarPacientes()" placeholder="Documento, nombres o apellidos..." class="input-modern pl-10" style="padding-right:2.5rem" autocomplete="off">
<button x-show="textoBusqueda.length>0" @click="textoBusqueda='';buscarPacientes()" class="absolute inset-y-0 right-0 pr-3 flex items-center transition" :class="dark ? 'text-slate-500 hover:text-red-400' : 'text-slate-400 hover:text-red-500'"><i class="bi bi-x-circle-fill"></i></button>
</div>
</div>
<div class="flex items-center gap-2">
<input type="checkbox" id="buscar-todas" x-model="buscarTodos" @change="buscarPacientes()" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
<label for="buscar-todas" class="text-xs cursor-pointer select-none" :class="dark ? 'text-slate-400' : 'text-slate-600'">Buscar en todas las fechas</label>
</div>
</div>
</div>
<div x-show="textoBusqueda.length>0||buscarTodos" x-transition class="rounded-xl p-2" :class="dark ? 'bg-amber-900/20 border border-amber-800/30' : 'bg-amber-50 border border-amber-200/60'">
<div class="flex justify-between items-center"><div class="text-xs" :class="dark ? 'text-amber-300' : 'text-amber-800'"><i class="bi bi-search mr-1"></i>Busqueda: "<span class="font-bold" x-text="textoBusqueda||'Todas'"></span>"</div><span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full" :class="dark ? 'bg-amber-800/30 text-amber-300' : 'bg-amber-100 text-amber-800'" x-text="totalResultados+' resultado(s)'"></span></div>
</div>
<?php if($es_hoy&&!$todos):?>
<div class="rounded-xl p-2 flex items-center gap-2" :class="dark ? 'bg-indigo-900/20 border border-indigo-800/30' : 'bg-indigo-50/80 border border-indigo-100'"><div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" :class="dark ? 'bg-indigo-800/30' : 'bg-indigo-100'"><i class="bi bi-info-circle-fill text-xs" :class="dark ? 'text-indigo-400' : 'text-indigo-500'"></i></div><div><div class="text-xs font-bold" :class="dark ? 'text-indigo-300' : 'text-indigo-800'">Hoy, <?= date('d/m/Y') ?></div><div class="text-[10px]" :class="dark ? 'text-indigo-400' : 'text-indigo-500'">Mostrando resultados del dia actual</div></div></div>
<?php endif;?>
</div>
<div class="flex-1 overflow-y-auto p-3 space-y-2 scrollbar-thin" id="lista-pacientes" @scroll="verificarScroll($event)">
<?php if($error_db):?>
<div class="rounded-xl p-4 border" :class="dark ? 'bg-red-900/20 border-red-800/30' : 'bg-red-50 border-red-200'">
<div class="flex items-center gap-2 mb-2"><i class="bi bi-exclamation-triangle-fill text-red-500"></i><span class="text-sm font-bold text-red-600">Error de Base de Datos</span></div>
<p class="text-xs text-red-500 mb-2"><?= htmlspecialchars($error_db) ?></p>
<p class="text-[11px]" :class="dark ? 'text-slate-400' : 'text-slate-500'">Verifique que las tablas <code class="font-mono bg-red-100 dark:bg-red-900/30 px-1 rounded">examenes</code> y <code class="font-mono bg-red-100 dark:bg-red-900/30 px-1 rounded">paciente</code> existan en la base de datos.</p>
</div>
<?php elseif($resultados && $resultados->num_rows>0):?>
<?php $fecha_actual_grupo=null; while($row=$resultados->fetch_assoc()):
$id_p=$row['identificacion']; $nom_p=$row['nombres']; $edad_p=$row['edad'];
$genero_p=$row['genero']; $telefono_p=$row['telefono']; $fecha_examen=$row['fecha_examen']; $entidad_p=$row['entidad'];
if($todos&&$fecha_examen!=$fecha_actual_grupo){$fecha_actual_grupo=$fecha_examen;?>
<div class="sticky top-0 z-10 -mx-3 px-3 py-1.5 rounded-xl border backdrop-blur-sm" :class="dark ? 'bg-[#0b1120]/90 border-slate-700/60' : 'bg-slate-50/90 border-slate-200/60'"><div class="flex justify-between items-center"><div class="text-[11px] font-bold uppercase tracking-wider" :class="dark ? 'text-slate-400' : 'text-slate-600'"><i class="bi bi-calendar3 mr-1.5" :class="dark ? 'text-slate-500' : 'text-slate-400'"></i><?= date('d/m/Y',strtotime($fecha_examen)) ?></div><a href="<?= $script_actual ?>?fecha=<?= $fecha_examen ?>" class="text-[11px] font-medium px-2 py-0.5 rounded-lg transition" :class="dark ? 'text-indigo-400 hover:text-indigo-300 hover:bg-indigo-900/20' : 'text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50'">Ver solo este dia</a></div></div>
<?php }
if(empty($edad_p)&&!empty($row['fecnac'])){$edad_p=(new DateTime())->diff(new DateTime($row['fecnac']))->y;}
echo renderizarTarjetaPaciente($id_p, $nom_p, $edad_p, $genero_p, $telefono_p, $row['telefono_movil'] ?? '', $row['telefono_residencia2'] ?? '', $fecha_examen, $entidad_p, $todos, $mysqli, $cache_tablas, $cache_estructura, $entidades);
endwhile;?>
<?php else:?>
<div class="h-full flex flex-col items-center justify-center p-8 text-center animate-fade-in">
<img src="icons/thiings/patient.png" alt="Sin resultados" class="w-28 h-28 object-contain mb-5 thiings-icon opacity-80 empty-state-img">
<h3 class="text-lg font-bold mb-1.5" :class="dark ? 'text-slate-200' : 'text-slate-700'">No se encontraron resultados</h3>
<?php if(!empty($busqueda)):?>
<p class="text-sm mb-4" :class="dark ? 'text-slate-400' : 'text-slate-500'">para: "<span class="font-mono px-2 py-0.5 rounded-lg" :class="dark ? 'bg-slate-700 text-slate-300' : 'bg-slate-100 text-slate-600'"><?= htmlspecialchars($busqueda) ?></span>"</p>
<a href="<?= $script_actual ?>" class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 border rounded-xl transition" :class="dark ? 'text-indigo-400 border-indigo-800 hover:bg-indigo-900/20' : 'text-indigo-600 border-indigo-200 hover:bg-indigo-50'"><i class="bi bi-arrow-left"></i> Ver todos</a>
<?php else:?>
<p class="text-sm mb-4" :class="dark ? 'text-slate-400' : 'text-slate-500'">No hay pacientes para <span class="font-bold"><?= date('d/m/Y',strtotime($fecha)) ?></span></p>
<div class="flex gap-2">
<a href="<?= $script_actual ?>?fecha=<?= date('Y-m-d') ?>" class="btn-primary text-white text-sm font-medium px-4 py-2 rounded-xl inline-flex items-center gap-2"><i class="bi bi-calendar3"></i> Ver hoy</a>
<a href="<?= $script_actual ?>?fecha=<?= date('Y-m-d',strtotime('-1 day')) ?>" class="text-sm font-medium px-4 py-2 rounded-xl inline-flex items-center gap-2 transition border" :class="dark ? 'bg-slate-700 hover:bg-slate-800 text-white border-transparent' : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200'"><i class="bi bi-arrow-left"></i> Ver ayer</a>
</div>
<?php endif;?>
</div>
<?php endif;?>

<div x-show="hayMas" class="py-3 text-center" id="load-more-trigger">
<button @click="cargarMas()" x-show="!cargandoMas" class="text-xs font-bold px-4 py-2 rounded-xl border transition flex items-center gap-1.5 mx-auto" :class="dark ? 'text-indigo-400 border-indigo-800 hover:bg-indigo-900/20' : 'text-indigo-600 border-indigo-200 hover:bg-indigo-50'"><i class="bi bi-arrow-down-circle"></i> Cargar mas</button>
<div x-show="cargandoMas" class="flex items-center justify-center gap-2 text-xs" :class="dark ? 'text-slate-500' : 'text-slate-400'"><div class="w-4 h-4 border-2 rounded-full animate-spin" :class="dark ? 'border-indigo-800 border-t-indigo-400' : 'border-indigo-200 border-t-indigo-500'"></div> Cargando...</div>
</div>
</div>
</aside>
<main class="flex-1 relative flex flex-col transition-colors duration-300" :class="dark ? 'bg-[#0b1120]' : 'bg-slate-50'">
<canvas id="three-visor-canvas"></canvas>
<div x-show="!urlReporte" x-cloak class="flex-1 flex flex-col items-center justify-center p-8 animate-fade-in relative z-10">
<div class="text-center max-w-lg">
<img src="icons/thiings/stethoscope.png" alt="Visor" class="w-32 h-32 object-contain mx-auto mb-6 thiings-icon">
<h3 class="text-xl font-extrabold mb-2" :class="dark ? 'text-slate-100' : 'text-slate-800'">Visor de Resultados</h3>
<p class="text-sm mb-8 leading-relaxed" :class="dark ? 'text-slate-400' : 'text-slate-500'">Seleccione un examen de la lista para visualizar e imprimir los resultados.</p>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-xl mx-auto stagger-children">
<div class="card p-5 text-center card-hover"><img src="icons/thiings/microscope.png" alt="" class="w-12 h-12 mx-auto mb-3 thiings-icon"><div class="text-sm font-bold mb-0.5" :class="dark ? 'text-slate-100' : 'text-slate-800'">Visualizar</div><div class="text-[11px]" :class="dark ? 'text-slate-500' : 'text-slate-400'">Ver resultados completos</div></div>
<div class="card p-5 text-center card-hover"><div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3" :class="dark ? 'bg-emerald-900/30' : 'bg-emerald-50'"><i class="bi bi-printer-fill text-xl" :class="dark ? 'text-emerald-400' : 'text-emerald-500'"></i></div><div class="text-sm font-bold mb-0.5" :class="dark ? 'text-slate-100' : 'text-slate-800'">Imprimir</div><div class="text-[11px]" :class="dark ? 'text-slate-500' : 'text-slate-400'">Generar copia fisica</div></div>
<div class="card p-5 text-center card-hover"><div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3" :class="dark ? 'bg-green-900/30' : 'bg-green-50'"><i class="bi bi-whatsapp text-xl" :class="dark ? 'text-green-400' : 'text-green-500'"></i></div><div class="text-sm font-bold mb-0.5" :class="dark ? 'text-slate-100' : 'text-slate-800'">Compartir</div><div class="text-[11px]" :class="dark ? 'text-slate-500' : 'text-slate-400'">Enviar por WhatsApp</div></div>
</div></div></div>
<div x-show="urlReporte" x-cloak class="report-panel h-full flex flex-col relative z-10">
<div class="glass border-b p-3 flex justify-between items-center flex-shrink-0 transition-colors duration-300" :class="dark ? 'border-slate-700/60' : 'border-slate-200/60'">
<button @click="urlReporte=null;vistaReporte=false" class="md:hidden p-2 rounded-xl transition focus-visible:ring-2" :class="dark ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'"><i class="bi bi-arrow-left text-lg"></i></button>
<div class="flex items-center gap-2.5"><div class="w-8 h-8 rounded-xl flex items-center justify-center" :class="dark ? 'bg-indigo-900/30' : 'bg-indigo-100'"><i class="bi bi-file-earmark-text-fill text-sm" :class="dark ? 'text-indigo-400' : 'text-indigo-500'"></i></div><div><div class="text-[11px] font-bold uppercase tracking-wider" :class="dark ? 'text-indigo-400' : 'text-indigo-600'">Reporte</div><div class="text-[10px]" :class="dark ? 'text-slate-500' : 'text-slate-400'" x-text="nombreReporte||'Cargando...'"></div></div></div>
<div class="report-toolbar">
  <button @click="ajustarZoom('fit')" title="Ajustar a ancho"><i class="bi bi-arrows-angle-expand"></i></button>
  <button @click="ajustarZoom('page')" title="Ajustar a pagina"><i class="bi bi-file-earmark"></i></button>
  <button @click="descargarReporte()" title="Descargar PDF"><i class="bi bi-download"></i></button>
  <button @click="imprimirFrame()" class="btn-primary text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5"><i class="bi bi-printer"></i> IMPRIMIR</button>
</div>
</div>
<div class="flex-1 relative overflow-hidden bg-white">
  <div x-show="cargandoReporte" class="report-skeleton">
    <div class="report-skeleton-header"></div>
    <div class="report-skeleton-line medium"></div>
    <div class="report-skeleton-line short"></div>
    <div class="report-skeleton-line"></div>
    <div class="report-skeleton-line"></div>
    <div class="report-skeleton-line short"></div>
  </div>
  <iframe :src="urlReporte" id="frameReporte" class="w-full h-full border-none" :class="zoomReporte" @load="cargarNombreReporte" title="Vista previa del reporte"></iframe>
</div>
</div>
</main>
</div>
<div x-show="mostrarModalTelefono" x-cloak class="fixed inset-0 flex items-center justify-center p-4 z-[70]" @click.self="mostrarModalTelefono=false" x-transition role="dialog" aria-modal="true" aria-labelledby="modal-telefono-title">
<div class="fixed inset-0 transition-opacity" :class="dark ? 'bg-black/60' : 'bg-black/40'" style="backdrop-filter:blur(6px)"></div>
<div class="bg-white dark:bg-[#131c31] rounded-2xl shadow-2xl max-w-sm w-full p-6 relative z-10 animate-scale-in" @click.stop>
<div class="text-center mb-4"><div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3" :class="dark ? 'bg-green-900/30' : 'bg-green-100'"><i class="bi bi-whatsapp text-2xl" :class="dark ? 'text-green-400' : 'text-green-500'"></i></div><h3 id="modal-telefono-title" class="text-lg font-bold" :class="dark ? 'text-slate-100' : 'text-slate-800'">Enviar por WhatsApp</h3><p class="text-xs mt-1" :class="dark ? 'text-slate-500' : 'text-slate-400'">Seleccione el telefono del paciente</p></div>
<div class="space-y-2">
<template x-for="(tel,idx) in telefonosDisponibles" :key="idx">
<button @click="enviarAWHatsapp(tel.numero)" class="w-full flex items-center gap-3 p-3 rounded-xl border-2 transition text-left" :class="dark ? 'border-slate-600 hover:border-green-500 hover:bg-green-900/20' : 'border-slate-200 hover:border-green-400 hover:bg-green-50/50'">
<div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" :class="dark ? 'bg-green-900/30' : 'bg-green-100'"><i class="bi bi-telephone-fill" :class="dark ? 'text-green-400' : 'text-green-600'"></i></div>
<div class="flex-1 min-w-0"><div class="text-sm font-bold" :class="dark ? 'text-slate-100' : 'text-slate-800'" x-text="tel.numero"></div><div class="text-[11px]" :class="dark ? 'text-slate-500' : 'text-slate-400'" x-text="tel.tipo"></div></div>
<i class="bi bi-chevron-right" :class="dark ? 'text-slate-600' : 'text-slate-300'"></i>
</button>
</template>
</div>
<button @click="mostrarModalTelefono=false" class="w-full mt-4 py-2.5 text-sm font-bold transition" :class="dark ? 'text-slate-400 hover:text-slate-200' : 'text-slate-500 hover:text-slate-700'">Cancelar</button>
</div></div>
<div x-show="mostrarModalEntidades" x-cloak class="fixed inset-0 flex items-center justify-center p-4 z-50" @click.self="mostrarModalEntidades=false" x-transition role="dialog" aria-modal="true" aria-labelledby="modal-entidades-title">
<div class="fixed inset-0 transition-opacity" :class="dark ? 'bg-black/60' : 'bg-black/40'" style="backdrop-filter:blur(6px)"></div>
<div class="bg-white dark:bg-[#131c31] rounded-3xl shadow-2xl max-w-6xl w-full h-[85vh] flex flex-col overflow-hidden relative z-10 animate-scale-in" @click.stop>
<div x-show="!mostrarResultsModal" class="flex flex-col h-full">
<div class="modal-header-gradient-violet p-6 flex-shrink-0">
<div class="flex justify-between items-center mb-3"><div class="flex items-center gap-3"><img src="icons/thiings/building.png" alt="" class="w-10 h-10 object-contain thiings-icon"><h3 id="modal-entidades-title" class="text-xl font-bold">Consulta por Entidades</h3></div><button @click="mostrarModalEntidades=false" class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition focus-visible:ring-2"><i class="bi bi-x-lg"></i></button></div>
<div class="stepper mb-3">
  <div class="stepper-step active"><div class="stepper-step-number">1</div><span>Filtros</span></div>
  <div class="stepper-divider"></div>
  <div class="stepper-step"><div class="stepper-step-number">2</div><span>Resultados</span></div>
</div>
<p class="text-violet-200 text-sm">Consulta resultados por entidad y rango de fechas</p>
</div>
<form @submit.prevent="consultarPorEntidades()" class="p-6 space-y-5 overflow-y-auto flex-1">
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Entidad</label>
<select x-model="formularioEntidades.entidad" class="select-modern w-full px-4 py-3 border-2 rounded-2xl focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 outline-none transition text-sm font-medium" :class="dark ? 'bg-slate-800 border-slate-600 text-slate-200' : 'border-slate-200 text-slate-700'"><option value="">-- Todas las entidades --</option><?php foreach($entidades as $entidad):?><option value="<?= htmlspecialchars($entidad['nombre'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($entidad['nombre'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach;?></select></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Fecha Inicio</label><input type="date" x-model="formularioEntidades.fechaInicio" required class="input-modern flatpickr-input"></div>
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Fecha Fin</label><input type="date" x-model="formularioEntidades.fechaFin" required class="input-modern flatpickr-input"></div>
</div>
<div class="p-4 rounded-2xl border" :class="dark ? 'bg-violet-900/10 border-violet-800/30' : 'bg-violet-50/80 border-violet-100'">
<div class="flex items-center gap-2 mb-3"><i class="bi bi-funnel" :class="dark ? 'text-violet-400' : 'text-violet-500'"></i><span class="text-sm font-semibold" :class="dark ? 'text-violet-300' : 'text-violet-800'">Opciones de filtrado</span></div>
<div class="space-y-2.5">
<label class="flex items-center gap-3 cursor-pointer group"><div class="relative"><input type="checkbox" x-model="formularioEntidades.soloConResultados" class="sr-only peer"><div class="w-10 h-5 bg-slate-200 dark:bg-slate-700 rounded-full peer-checked:bg-violet-500 transition-colors"></div><div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-5"></div></div><span class="text-sm transition" :class="dark ? 'text-slate-300 group-hover:text-slate-100' : 'text-slate-700 group-hover:text-slate-900'">Solo examenes con resultados</span></label>
<label class="flex items-center gap-3 cursor-pointer group"><div class="relative"><input type="checkbox" x-model="formularioEntidades.agruparPorFecha" class="sr-only peer"><div class="w-10 h-5 bg-slate-200 dark:bg-slate-700 rounded-full peer-checked:bg-violet-500 transition-colors"></div><div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow-sm transition-transform peer-checked:translate-x-5"></div></div><span class="text-sm transition" :class="dark ? 'text-slate-300 group-hover:text-slate-100' : 'text-slate-700 group-hover:text-slate-900'">Agrupar por fecha</span></label>
</div></div>
<div class="flex gap-3 pt-4 border-t" :class="dark ? 'border-slate-700' : 'border-slate-100'">
<button type="button" @click="mostrarModalEntidades=false" class="flex-1 px-4 py-3 border-2 rounded-2xl font-bold transition text-sm" :class="dark ? 'border-slate-600 text-slate-300 hover:bg-slate-800' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">Cancelar</button>
<button type="submit" class="flex-1 bg-gradient-to-r from-violet-600 to-indigo-600 text-white px-4 py-3 rounded-2xl font-bold hover:from-violet-700 hover:to-indigo-700 transition shadow-lg shadow-violet-200 dark:shadow-violet-900/30 text-sm"><i class="bi bi-search mr-2"></i>Consultar</button>
</div></form></div>
<div x-show="mostrarResultsModal" class="flex flex-col h-full">
<div class="modal-header-gradient-violet p-4 flex-shrink-0">
<div class="flex justify-between items-center">
<div class="flex items-center gap-3"><button @click="mostrarResultsModal=false" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition focus-visible:ring-2"><i class="bi bi-arrow-left"></i></button><div><div class="font-bold text-sm">Resultados por Entidad</div><div class="stepper mt-1">
  <div class="stepper-step completed"><div class="stepper-step-number"><i class="bi bi-check"></i></div><span>Filtros</span></div>
  <div class="stepper-divider"></div>
  <div class="stepper-step active"><div class="stepper-step-number">2</div><span>Resultados</span></div>
</div></div></div>
<div class="flex items-center gap-2">
<button @click="prepararExportarExcel()" class="bg-emerald-500 hover:bg-emerald-600 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 focus-visible:ring-2"><i class="bi bi-file-earmark-excel"></i> EXCEL</button>
<button @click="imprimirResultsModal()" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 focus-visible:ring-2"><i class="bi bi-printer"></i> IMPRIMIR</button>
<button @click="mostrarModalEntidades=false" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition focus-visible:ring-2"><i class="bi bi-x-lg"></i></button>
</div></div></div>
<div class="flex-1 overflow-hidden flex flex-col"><div class="max-w-6xl mx-auto w-full p-4 flex flex-col h-full">
<div class="card p-4 mb-4 flex-shrink-0">
<div class="filter-chips mb-3">
  <div class="filter-chip"><i class="bi bi-calendar3"></i><span x-text="formularioEntidades.fechaInicio + ' - ' + formularioEntidades.fechaFin"></span></div>
  <div x-show="formularioEntidades.entidad" class="filter-chip"><i class="bi bi-building"></i><span x-text="formularioEntidades.entidad"></span><button @click="formularioEntidades.entidad='';consultarPorEntidades()"><i class="bi bi-x"></i></button></div>
  <div x-show="formularioEntidades.soloConResultados" class="filter-chip"><i class="bi bi-check-circle"></i><span>Con resultados</span><button @click="formularioEntidades.soloConResultados=false;consultarPorEntidades()"><i class="bi bi-x"></i></button></div>
</div>
<div class="grid grid-cols-4 gap-4 stagger-children">
<div class="stat-card"><div class="stat-icon" :class="dark ? 'bg-violet-900/30' : 'bg-violet-100'"><i class="bi bi-building text-sm" :class="dark ? 'text-violet-400' : 'text-violet-500'"></i></div><div class="stat-value" x-text="resultadosEntidades?.total_fechas||0">0</div><div class="stat-label">Fechas</div></div>
<div class="stat-card"><div class="stat-icon" :class="dark ? 'bg-blue-900/30' : 'bg-blue-100'"><i class="bi bi-people-fill text-sm" :class="dark ? 'text-blue-400' : 'text-blue-500'"></i></div><div class="stat-value" x-text="resultadosEntidades?.total_registros||0">0</div><div class="stat-label">Examenes</div></div>
<div class="stat-card"><div class="stat-icon" :class="dark ? 'bg-emerald-900/30' : 'bg-emerald-100'"><i class="bi bi-calendar-check text-sm" :class="dark ? 'text-emerald-400' : 'text-emerald-500'"></i></div><div class="stat-value" x-text="resultadosEntidades?.resultados?.length||0">0</div><div class="stat-label">Dias</div></div>
<div class="stat-card"><div class="stat-icon" :class="dark ? 'bg-amber-900/30' : 'bg-amber-100'"><i class="bi bi-funnel text-sm" :class="dark ? 'text-amber-400' : 'text-amber-500'"></i></div><div class="stat-value truncate" x-text="formularioEntidades?.entidad||'Todas'">-</div><div class="stat-label">Entidad</div></div>
</div></div>
<div class="flex-1 overflow-y-auto space-y-3 scrollbar-thin">
<template x-for="grupo in (resultadosEntidades?.resultados||[])" :key="grupo.fecha">
<div class="card overflow-hidden">
<div class="p-3 border-b" :class="dark ? 'bg-gradient-to-r from-violet-900/20 to-indigo-900/20 border-violet-800/30' : 'bg-gradient-to-r from-violet-50 to-indigo-50 border-violet-100/60'">
<div class="flex justify-between items-center">
<div class="flex items-center gap-2"><i class="bi bi-calendar3" :class="dark ? 'text-violet-400' : 'text-violet-400'"></i><div><div class="font-bold text-sm" :class="dark ? 'text-violet-300' : 'text-violet-800'"><span x-text="new Date(grupo.fecha+'T00:00:00').toLocaleDateString('es-CO',{weekday:'long',year:'numeric',month:'long',day:'numeric'})"></span></div><div class="text-[11px]" :class="dark ? 'text-violet-400' : 'text-violet-500'"><span x-text="grupo.cantidad"></span> examenes</div></div></div>
<button @click="imprimirGrupoFechaModal(grupo.fecha)" class="bg-violet-600 hover:bg-violet-700 text-white px-2.5 py-1 rounded-lg text-[11px] font-bold transition"><i class="bi bi-printer mr-1"></i>Dia</button>
</div></div>
<div class="p-3"><div class="space-y-2">
<template x-for="(examen,index) in grupo.examenes" :key="examen.identificacion+'_'+examen.examen_codigo">
<div>
<div class="flex items-center justify-between p-2.5 rounded-xl transition-colors" :class="dark ? 'bg-slate-800/50 hover:bg-slate-800' : 'bg-slate-50 hover:bg-slate-100/80'">
<div class="flex items-center gap-2.5 flex-1 min-w-0"><div class="w-2 h-2 rounded-full flex-shrink-0" :class="dark ? 'bg-violet-400' : 'bg-violet-400'"></div><div class="min-w-0"><div class="font-medium text-sm truncate" :class="dark ? 'text-slate-200' : 'text-slate-800'" x-text="examen.paciente"></div><div class="text-[11px] flex items-center gap-1.5" :class="dark ? 'text-slate-500' : 'text-slate-400'"><span class="font-mono px-1 py-0.5 rounded text-[10px]" :class="dark ? 'bg-slate-700 text-slate-400' : 'bg-slate-200/80 text-slate-600'" x-text="examen.identificacion"></span></div></div></div>
<div class="flex items-center gap-1.5 flex-shrink-0">
<div class="text-right mr-1"><div class="text-[11px] font-medium truncate max-w-[140px]" :class="dark ? 'text-slate-300' : 'text-slate-700'" x-text="examen.examen_nombre"></div><div class="text-[10px]" :class="dark ? 'text-slate-500' : 'text-slate-400'" x-text="examen.tipo_procedimiento||examen.examen_tipo"></div></div>
<button @click="examen.mostrarResultado=!examen.mostrarResultado" class="p-1.5 rounded-lg transition-colors" :class="examen.mostrarResultado?(dark?'bg-emerald-900/30 text-emerald-400':'bg-emerald-100 text-emerald-600'):(dark?'bg-slate-700 text-slate-400':'bg-slate-200/80 text-slate-500')"><i class="bi bi-file-text text-xs"></i></button>
<button @click="verExamenIndividual(examen)" class="p-1.5 rounded-lg transition-colors" :class="dark ? 'bg-indigo-900/30 text-indigo-400 hover:bg-indigo-900/50' : 'bg-indigo-100 text-indigo-600 hover:bg-indigo-200'"><i class="bi bi-eye-fill text-xs"></i></button>
</div></div>
      <div x-show="examen.mostrarResultado" x-transition class="ml-6 mt-1 p-2.5 rounded-xl border" :class="dark ? 'bg-gradient-to-br from-emerald-900/20 to-green-900/20 border-emerald-800/30' : 'bg-gradient-to-br from-emerald-50 to-green-50 border-emerald-200/60'">
        <div class="text-[11px] space-y-1" :class="dark ? 'text-emerald-400' : 'text-emerald-700'">
          <div class="flex justify-between items-center"><span class="font-medium">Valor:</span><span class="font-bold font-mono" :class="dark ? 'text-emerald-300' : 'text-emerald-900'" x-text="examen.resultado||'N/A'"></span></div>
          <div x-show="examen.referencia&&examen.referencia!=='N/A'" class="flex justify-between"><span class="font-medium">Ref:</span><span x-text="examen.referencia"></span></div>
          <div class="flex justify-between items-center"><span class="font-medium">Estado:</span>
            <span class="status-chip" :class="evaluarResultadoJS(examen.resultado, examen.referencia)==='danger'?'status-chip-danger':(evaluarResultadoJS(examen.resultado, examen.referencia)==='warning'?'status-chip-warning':'status-chip-normal')">
              <i class="bi" :class="evaluarResultadoJS(examen.resultado, examen.referencia)==='danger'?'bi-exclamation-octagon-fill':(evaluarResultadoJS(examen.resultado, examen.referencia)==='warning'?'bi-exclamation-triangle-fill':'bi-check-circle-fill')"></i>
              <span x-text="evaluarResultadoJS(examen.resultado, examen.referencia)==='danger'?'Critico':(evaluarResultadoJS(examen.resultado, examen.referencia)==='warning'?'Fuera rango':'Normal')"></span>
            </span>
          </div>
          <div x-show="examen.resultado && examen.referencia && examen.referencia !== 'N/A'" :id="'range-ent-'+index" x-init="$nextTick(() => renderRangeBar('range-ent-'+index, examen.resultado, examen.referencia))"></div>
        </div>
      </div>
</div>
</template>
</div></div></div>
</template>
</div></div></div></div></div></div>
<div x-show="mostrarModalPacientesBusqueda" x-cloak class="fixed inset-0 flex items-center justify-center p-4 z-[60]" @click.self="cerrarModalPacientesBusqueda()" x-transition role="dialog" aria-modal="true" aria-labelledby="modal-pacientes-title">
<div class="fixed inset-0 transition-opacity" :class="dark ? 'bg-black/60' : 'bg-black/40'" style="backdrop-filter:blur(6px)"></div>
<div class="bg-white dark:bg-[#131c31] rounded-3xl shadow-2xl max-w-6xl w-full h-[85vh] flex flex-col overflow-hidden relative z-10 animate-scale-in" @click.stop>
<div x-show="!mostrarResultsPacientesBusqueda" class="flex flex-col h-full">
<div class="modal-header-gradient-emerald p-6 flex-shrink-0">
<div class="flex justify-between items-center mb-3"><div class="flex items-center gap-3"><img src="icons/thiings/stethoscope.png" alt="" class="w-10 h-10 object-contain thiings-icon"><h3 id="modal-pacientes-title" class="text-xl font-bold">Busqueda de Pacientes</h3></div><button @click="cerrarModalPacientesBusqueda()" class="text-white/70 hover:text-white hover:bg-white/20 p-2 rounded-xl transition focus-visible:ring-2"><i class="bi bi-x-lg"></i></button></div>
<div class="stepper mb-3">
  <div class="stepper-step active"><div class="stepper-step-number">1</div><span>Buscar</span></div>
  <div class="stepper-divider"></div>
  <div class="stepper-step"><div class="stepper-step-number">2</div><span>Resultados</span></div>
</div>
<p class="text-emerald-200 text-sm">Busque por identificacion, nombres, telefono, ciudad o entidad</p></div>
<form @submit.prevent="buscarPacientesModal()" class="p-6 space-y-5 overflow-y-auto flex-1">
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Identificacion</label><input type="text" x-model="formularioPacientesBusqueda.identificacion" placeholder="Documento del paciente" class="input-modern"></div>
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Nombres Completos</label><input type="text" x-model="formularioPacientesBusqueda.nombres" placeholder="Nombres y apellidos" class="input-modern"></div>
</div>
<div class="flex items-center gap-2 cursor-pointer select-none" @click="busquedaAvanzada=!busquedaAvanzada">
<i class="bi text-xs" :class="busquedaAvanzada?'bi-chevron-down':'bi-chevron-right'"></i>
<span class="text-xs font-bold uppercase tracking-wider" :class="dark ? 'text-slate-400' : 'text-slate-500'">Busqueda avanzada</span></div>
<div x-show="busquedaAvanzada" x-transition class="grid grid-cols-1 sm:grid-cols-3 gap-4">
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Telefono</label><input type="text" x-model="formularioPacientesBusqueda.telefono" placeholder="Numero de telefono" class="input-modern"></div>
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Ciudad</label><input type="text" x-model="formularioPacientesBusqueda.ciudad" placeholder="Ciudad de residencia" class="input-modern"></div>
<div><label class="block text-xs font-bold uppercase tracking-wider mb-2" :class="dark ? 'text-slate-400' : 'text-slate-500'">Entidad</label><input type="text" x-model="formularioPacientesBusqueda.entidad" placeholder="Nombre de entidad" class="input-modern"></div>
</div>
<div class="flex gap-3 pt-2"><label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" x-model="formularioPacientesBusqueda.soloConResultados" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 rounded"><span class="text-xs" :class="dark ? 'text-slate-400' : 'text-slate-600'">Solo con resultados</span></label></div>
<button type="submit" class="btn-success w-full py-3.5 rounded-2xl font-bold text-sm tracking-wide shadow-lg shadow-emerald-200 dark:shadow-emerald-900/30 flex items-center justify-center gap-2"><i class="bi bi-search"></i> BUSCAR PACIENTES</button>
</form></div>
<div x-show="mostrarResultsPacientesBusqueda" class="flex flex-col h-full">
<div class="modal-header-gradient-emerald p-4 flex-shrink-0">
<div class="flex justify-between items-center">
<div class="flex items-center gap-3"><button @click="volverFormularioBusqueda()" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition focus-visible:ring-2"><i class="bi bi-arrow-left"></i></button><div><h3 class="text-lg font-bold">Resultados</h3><div class="stepper mt-1">
  <div class="stepper-step completed"><div class="stepper-step-number"><i class="bi bi-check"></i></div><span>Buscar</span></div>
  <div class="stepper-divider"></div>
  <div class="stepper-step active"><div class="stepper-step-number">2</div><span>Resultados</span></div>
</div></div></div>
<div class="flex gap-2">
<button @click="exportarPacientesExcel()" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 focus-visible:ring-2"><i class="bi bi-file-earmark-excel"></i> EXCEL</button>
<button @click="volverFormularioBusqueda()" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1 focus-visible:ring-2"><i class="bi bi-arrow-left"></i> VOLVER</button>
<button @click="cerrarModalPacientesBusqueda()" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition focus-visible:ring-2"><i class="bi bi-x-lg"></i></button>
</div></div></div>
<div class="flex-1 overflow-y-auto p-4 scrollbar-thin">
<div x-show="cargandoPacientesBusqueda" class="flex items-center justify-center h-full"><div class="text-center"><div class="w-12 h-12 border-4 rounded-full animate-spin mx-auto" :class="dark ? 'border-emerald-800 border-t-emerald-400' : 'border-emerald-200 border-t-emerald-500'"></div><p class="mt-4 text-sm" :class="dark ? 'text-slate-400' : 'text-slate-500'">Buscando pacientes...</p></div></div>
<div class="filter-chips mb-3" x-show="!cargandoPacientesBusqueda&&resultadosPacientesBusqueda?.pacientes?.length>0">
  <div x-show="formularioPacientesBusqueda.identificacion" class="filter-chip"><i class="bi bi-card-text"></i><span x-text="formularioPacientesBusqueda.identificacion"></span><button @click="formularioPacientesBusqueda.identificacion='';buscarPacientesModal()"><i class="bi bi-x"></i></button></div>
  <div x-show="formularioPacientesBusqueda.nombres" class="filter-chip"><i class="bi bi-person"></i><span x-text="formularioPacientesBusqueda.nombres"></span><button @click="formularioPacientesBusqueda.nombres='';buscarPacientesModal()"><i class="bi bi-x"></i></button></div>
  <div x-show="formularioPacientesBusqueda.telefono" class="filter-chip"><i class="bi bi-telephone"></i><span x-text="formularioPacientesBusqueda.telefono"></span><button @click="formularioPacientesBusqueda.telefono='';buscarPacientesModal()"><i class="bi bi-x"></i></button></div>
  <div x-show="formularioPacientesBusqueda.ciudad" class="filter-chip"><i class="bi bi-geo-alt"></i><span x-text="formularioPacientesBusqueda.ciudad"></span><button @click="formularioPacientesBusqueda.ciudad='';buscarPacientesModal()"><i class="bi bi-x"></i></button></div>
  <div x-show="formularioPacientesBusqueda.entidad" class="filter-chip"><i class="bi bi-building"></i><span x-text="formularioPacientesBusqueda.entidad"></span><button @click="formularioPacientesBusqueda.entidad='';buscarPacientesModal()"><i class="bi bi-x"></i></button></div>
</div>
<div x-show="!cargandoPacientesBusqueda&&resultadosPacientesBusqueda?.pacientes?.length>0" class="space-y-4 max-w-6xl mx-auto">
<template x-for="(paciente,index) in (resultadosPacientesBusqueda?.pacientes||[])" :key="paciente.identificacion">
<div class="card overflow-hidden card-hover">
<div class="p-4 border-b" :class="dark ? 'bg-gradient-to-r from-emerald-900/20 to-teal-900/20 border-emerald-800/30' : 'bg-gradient-to-r from-emerald-50/80 to-teal-50/80 border-emerald-100/60'">
<div class="flex justify-between items-start gap-3">
<div class="flex gap-3">
<div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0" :class="dark ? 'bg-emerald-900/30' : 'bg-emerald-100'"><i class="bi bi-person-fill text-lg" :class="dark ? 'text-emerald-400' : 'text-emerald-500'"></i></div>
<div><h4 class="font-bold" :class="dark ? 'text-slate-100' : 'text-slate-800'" x-text="paciente.nombre_completo"></h4>
<div class="flex flex-wrap gap-1.5 mt-1.5">
<span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold" :class="dark ? 'bg-slate-700 text-slate-300' : 'bg-slate-100 text-slate-600'" x-text="paciente.identificacion"></span>
<span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold" :class="dark ? 'bg-blue-900/30 text-blue-400' : 'bg-blue-100 text-blue-600'" x-text="`${paciente.edad} anios`"></span>
<span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold" :class="dark ? 'bg-emerald-900/30 text-emerald-400' : 'bg-emerald-100 text-emerald-600'" x-text="paciente.telefono||'Sin telefono'"></span>
</div></div></div>
<button @click="seleccionarPaciente(paciente,index)" class="btn-success text-white px-3 py-1.5 rounded-xl text-xs font-bold flex-shrink-0"><i class="bi bi-eye mr-1"></i> VER</button>
</div></div>
<div class="p-4">
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3 stagger-children">
<div class="stat-card"><div class="stat-value" x-text="paciente.total_visitas||0"></div><div class="stat-label">Visitas</div></div>
<div class="stat-card"><div class="stat-value" x-text="paciente.total_examenes||0"></div><div class="stat-label">Examenes</div></div>
<div class="stat-card"><div class="stat-value" :class="dark ? 'text-emerald-400' : 'text-emerald-600'" x-text="paciente.examenes_con_resultados||0"></div><div class="stat-label">Con resultados</div></div>
<div class="stat-card"><div class="stat-value text-sm" :class="dark ? 'text-slate-300' : 'text-slate-700'" x-text="paciente.ultima_visita||'N/A'"></div><div class="stat-label">Ultima visita</div></div>
</div>
<div x-show="paciente.examenes&&paciente.examenes.length>0">
<button @click="toggleExamenExpandido(index)" class="w-full px-3 py-2 rounded-xl text-sm font-medium transition flex items-center justify-between mb-2" :class="dark ? 'bg-slate-800 hover:bg-slate-700 text-slate-300' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"><span>Examenes (<span x-text="paciente.examenes.length"></span>)</span><i class="bi" :class="examenesExpandidos[index]?'bi-chevron-up':'bi-chevron-down'"></i></button>
<div x-show="examenesExpandidos[index]" x-transition class="space-y-1.5">
<template x-for="(examen,ei) in paciente.examenes" :key="ei">
<div class="rounded-xl p-2.5 flex items-center justify-between" :class="dark ? 'bg-slate-800/50' : 'bg-slate-50'">
<div class="flex-1 min-w-0"><div class="font-medium text-sm truncate" :class="dark ? 'text-slate-300' : 'text-slate-700'" x-text="examen.nombre"></div><div class="text-[11px]" :class="dark ? 'text-slate-500' : 'text-slate-400'"><span x-text="examen.fecha"></span></div></div>
<div class="flex items-center gap-1.5 flex-shrink-0">
<span class="text-[10px] px-2 py-0.5 rounded-lg font-bold" :class="examen.estado==='Completado'?(dark?'bg-emerald-900/30 text-emerald-400':'bg-emerald-100 text-emerald-700'):(dark?'bg-amber-900/30 text-amber-400':'bg-amber-100 text-amber-700')" x-text="examen.estado||'Pendiente'"></span>
<button @click="verExamenPaciente(examen,paciente)" class="p-1.5 rounded-lg transition" :class="dark ? 'bg-indigo-900/30 text-indigo-400 hover:bg-indigo-900/50' : 'bg-indigo-100 text-indigo-600 hover:bg-indigo-200'"><i class="bi bi-eye-fill text-xs"></i></button>
</div></div>
</template>
</div></div></div></div>
</template>
</div>
<div x-show="!cargandoPacientesBusqueda&&(!resultadosPacientesBusqueda?.pacientes||resultadosPacientesBusqueda.pacientes.length===0)" class="flex items-center justify-center h-full">
<div class="text-center"><img src="icons/thiings/patient.png" alt="" class="w-24 h-24 object-contain mx-auto mb-4 thiings-icon opacity-70 empty-state-img"><h3 class="text-lg font-bold mb-1" :class="dark ? 'text-slate-200' : 'text-slate-700'">No se encontraron pacientes</h3><p class="text-sm" :class="dark ? 'text-slate-400' : 'text-slate-500'">Intente con otros criterios de busqueda</p></div>
</div></div></div></div></div>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MODAL: HISTORIAL COMPLETO DEL PACIENTE -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div x-show="mostrarModalHistorial" x-cloak class="fixed inset-0 flex items-center justify-center p-4 z-[60]" @click.self="cerrarHistorial()" x-transition role="dialog" aria-modal="true" aria-labelledby="modal-historial-title">
<div class="fixed inset-0 transition-opacity" :class="dark ? 'bg-black/60' : 'bg-black/40'" style="backdrop-filter:blur(6px)"></div>
<div class="bg-white dark:bg-[#131c31] rounded-3xl shadow-2xl max-w-5xl w-full h-[90vh] flex flex-col overflow-hidden relative z-10 animate-scale-in" @click.stop>
<div class="modal-header-gradient-indigo p-5 flex-shrink-0">
<div class="flex justify-between items-start mb-3">
<div class="flex items-center gap-4">
<div class="w-14 h-14 rounded-2xl flex items-center justify-center text-xl font-bold bg-white/20 flex-shrink-0"><span x-text="(historialPaciente?.paciente?.nombre||'?').substring(0,1).toUpperCase()"></span></div>
<div class="min-w-0">
<h3 id="modal-historial-title" class="text-xl font-bold truncate" x-text="historialPaciente?.paciente?.nombre || 'Cargando...'"></h3>
<div class="flex flex-wrap items-center gap-2 mt-1 text-sm text-white/70">
<span class="font-mono px-2 py-0.5 rounded-lg bg-white/20" x-text="historialPaciente?.paciente?.identificacion || ''"></span>
<span x-show="historialPaciente?.paciente?.edad" x-text="(historialPaciente?.paciente?.edad || '') + ' anios'"></span>
<span x-show="historialPaciente?.paciente?.genero" x-text="historialPaciente?.paciente?.genero === 'F' ? 'Femenino' : 'Masculino'"></span>
<span x-show="historialPaciente?.paciente?.telefono" x-text="historialPaciente?.paciente?.telefono || ''"></span>
<span x-show="historialPaciente?.paciente?.ciudad" x-text="historialPaciente?.paciente?.ciudad || ''"></span>
</div></div></div>
<div class="flex items-center gap-2 flex-shrink-0">
<button @click="imprimirHistorial()" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1"><i class="bi bi-printer"></i> IMPRIMIR</button>
<button @click="cerrarHistorial()" class="bg-white/20 hover:bg-white/30 p-2 rounded-xl transition focus-visible:ring-2"><i class="bi bi-x-lg"></i></button>
</div></div>
<div class="grid grid-cols-3 gap-3">
<div class="bg-white/10 rounded-xl p-2.5 text-center"><div class="text-2xl font-bold" x-text="historialPaciente?.total_visitas || 0"></div><div class="text-[11px] text-white/60">Visitas</div></div>
<div class="bg-white/10 rounded-xl p-2.5 text-center"><div class="text-2xl font-bold" x-text="historialPaciente?.total_examenes || 0"></div><div class="text-[11px] text-white/60">Examenes</div></div>
<div class="bg-white/10 rounded-xl p-2.5 text-center"><div class="text-2xl font-bold" x-text="historialPaciente?.examenes_completados || 0"></div><div class="text-[11px] text-white/60">Completados</div></div>
</div></div>
<div x-show="historialCargando" class="flex-1 flex items-center justify-center p-8">
<div class="text-center"><div class="w-10 h-10 border-[3px] rounded-full animate-spin mx-auto mb-3" :class="dark ? 'border-indigo-800 border-t-indigo-400' : 'border-indigo-200 border-t-indigo-500'"></div><div class="text-sm" :class="dark ? 'text-slate-400' : 'text-slate-500'">Cargando historial...</div></div></div>
<div x-show="!historialCargando && historialPaciente" class="flex-1 overflow-y-auto p-5 space-y-4 scrollbar-thin">
<div x-show="historialPaciente?.examenes_disponibles?.length > 0" class="card overflow-hidden">
<button @click="historialGraficoActivo = historialGraficoActivo ? null : '_open'" class="w-full p-3 flex items-center justify-between transition-colors" :class="dark ? 'hover:bg-slate-800' : 'hover:bg-slate-50'">
<div class="flex items-center gap-2"><div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="dark ? 'bg-indigo-900/30' : 'bg-indigo-100'"><i class="bi bi-graph-up text-sm" :class="dark ? 'text-indigo-400' : 'text-indigo-500'"></i></div><div class="text-left"><div class="text-sm font-bold" :class="dark ? 'text-slate-100' : 'text-slate-800'">Analisis Temporal</div><div class="text-[11px]" :class="dark ? 'text-slate-500' : 'text-slate-400'">Tendencia de valores numericos</div></div></div>
<i class="bi text-lg transition-transform" :class="historialGraficoActivo ? 'bi-chevron-up text-indigo-500' : 'bi-chevron-down text-slate-400'"></i></button>
<div x-show="historialGraficoActivo" x-cloak x-transition class="border-t" :class="dark ? 'border-slate-700' : 'border-slate-100'">
<div class="p-3 flex flex-wrap gap-2">
<template x-for="exDisp in (historialPaciente?.examenes_disponibles || [])" :key="exDisp.codigo">
<button @click="renderGraficoHistorial(exDisp.nombre)" class="px-3 py-1.5 rounded-full text-[11px] font-bold transition border-2" :class="historialGraficoActivo === exDisp.nombre ? (dark ? 'bg-indigo-600 border-indigo-500 text-white' : 'bg-indigo-500 border-indigo-400 text-white') : (dark ? 'bg-slate-800 border-slate-600 text-slate-300 hover:border-indigo-500' : 'bg-white border-slate-200 text-slate-700 hover:border-indigo-300')"><span x-text="exDisp.nombre"></span></button>
</template></div>
<div x-show="historialGraficoActivo && historialGraficoActivo !== '_open'" class="historial-graph-container p-3"><canvas id="historial-grafico-canvas" class="w-full" style="height:180px"></canvas></div>
</div></div>
<div class="space-y-3">
<template x-for="(grupo, gIdx) in (historialPaciente?.fechas || [])" :key="grupo.fecha">
<div class="card overflow-hidden historial-timeline-item">
<div class="p-3 border-b flex items-center justify-between" :class="dark ? 'bg-gradient-to-r from-indigo-900/20 to-blue-900/20 border-indigo-800/30' : 'bg-gradient-to-r from-indigo-50 to-blue-50 border-indigo-100/60'">
<div class="flex items-center gap-2"><i class="bi bi-calendar3" :class="dark ? 'text-indigo-400' : 'text-indigo-400'"></i><div><div class="font-bold text-sm" :class="dark ? 'text-indigo-300' : 'text-indigo-800'" x-text="new Date(grupo.fecha+'T00:00:00').toLocaleDateString('es-CO',{weekday:'long',year:'numeric',month:'long',day:'numeric'})"></div><div class="text-[11px]" :class="dark ? 'text-indigo-400' : 'text-indigo-500'" x-text="grupo.examenes.length + ' examenes'"></div></div></div></div>
<div class="p-3 space-y-2">
<template x-for="(examen, eIdx) in grupo.examenes" :key="examen.codigo + '_' + eIdx">
<div>
<div class="flex items-center justify-between p-2.5 rounded-xl transition-colors" :class="dark ? 'bg-slate-800/50 hover:bg-slate-800' : 'bg-slate-50 hover:bg-slate-100/80'">
<div class="flex items-center gap-2.5 flex-1 min-w-0"><div class="w-2 h-2 rounded-full flex-shrink-0" :class="examen.status==='danger'?'bg-red-500':(examen.status==='warning'?'bg-amber-500':'bg-emerald-500')"></div><div class="min-w-0"><div class="font-medium text-sm truncate" :class="dark ? 'text-slate-200' : 'text-slate-800'" x-text="examen.nombre"></div><div class="text-[11px]" :class="dark ? 'text-slate-500' : 'text-slate-400'" x-text="examen.tipo || ''"></div></div></div>
<div class="flex items-center gap-2 flex-shrink-0">
<span class="text-sm font-mono font-bold" :class="examen.status==='danger'?'text-red-400':(examen.status==='warning'?'text-amber-400':(dark?'text-emerald-400':'text-emerald-600'))" x-text="examen.resultado || 'Pendiente'"></span>
<span class="status-chip" :class="examen.status==='danger'?'status-chip-danger':(examen.status==='warning'?'status-chip-warning':'status-chip-normal')"><i class="bi" :class="examen.status==='danger'?'bi-exclamation-octagon-fill':(examen.status==='warning'?'bi-exclamation-triangle-fill':'bi-check-circle-fill')"></i></span>
</div></div>
<div x-show="examen.resultado && examen.referencia && examen.referencia !== 'N/A' && examen.resultado !== 'Pendiente'" class="ml-6 mt-1" :id="'range-hist-'+gIdx+'-'+eIdx" x-init="$nextTick(() => renderRangeBar('range-hist-'+gIdx+'-'+eIdx, examen.resultado, examen.referencia))"></div>
</div>
</template></div></div>
</template>
</div></div></div></div>
<div x-show="showCommandPalette" x-cloak class="command-overlay" @click.self="showCommandPalette=false" x-transition role="dialog" aria-modal="true" aria-label="Command Palette">
<div class="command-box" @click.stop>
<div class="p-4" :class="dark ? 'border-b border-slate-700' : 'border-b border-slate-200'">
<div class="flex items-center gap-3">
<i class="bi bi-search" :class="dark ? 'text-slate-500' : 'text-slate-400'"></i>
<input type="text" x-model="commandQuery" @input="updateCommandResults()" @keydown.arrow-down.prevent="commandActiveIndex = Math.min(commandActiveIndex + 1, commandResults.length - 1)" @keydown.arrow-up.prevent="commandActiveIndex = Math.max(commandActiveIndex - 1, 0)" @keydown.enter.prevent="ejecutarComando(commandResults[commandActiveIndex])" placeholder="Buscar comando, paciente..." class="command-input" autofocus>
<button @click="showCommandPalette=false" class="text-xs px-2 py-1 rounded focus-visible:ring-2" :class="dark ? 'bg-slate-700 text-slate-400' : 'bg-slate-100 text-slate-500'">ESC</button>
</div></div>
<div class="command-list">
<template x-for="(cmd,idx) in commandResults" :key="idx">
<div>
<div x-show="cmd.type === 'category'" class="command-category" x-text="cmd.label"></div>
<button x-show="cmd.type !== 'category'" @click="ejecutarComando(cmd)" class="command-item" :class="{'active': idx === commandActiveIndex}">
<i x-show="cmd.type !== 'recent'" class="bi" :class="cmd.icon"></i>
<div x-show="cmd.type === 'recent'" class="command-recent-avatar" x-text="cmd.initials"></div>
<div class="flex-1 min-w-0">
<div class="text-sm font-medium truncate" x-html="highlightMatch(cmd.label, commandQuery)"></div>
<div class="text-[11px] truncate" :class="dark ? 'text-slate-500' : 'text-slate-400'" x-html="highlightMatch(cmd.description, commandQuery)"></div>
</div>
<div x-show="cmd.shortcut" class="command-shortcut">
<template x-for="key in cmd.shortcut.split(' ')" :key="key"><kbd class="kbd" x-text="key"></kbd></template>
</div>
</button>
</div>
</template>
<div x-show="commandResults.length === 0" class="p-8 text-center">
<p class="text-sm" :class="dark ? 'text-slate-500' : 'text-slate-400'">No se encontraron comandos</p>
</div>
</div>
<div class="command-footer">
<span><kbd class="kbd">Ctrl+K</kbd> Abrir</span>
<span><kbd class="kbd">Esc</kbd> Cerrar</span>
<span><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> Navegar</span>
<span><kbd class="kbd">Enter</kbd> Ejecutar</span>
</div>
</div></div>

<div id="toast-container"></div>
<script src="assets/js/three-visor.js"></script>
</body></html>
