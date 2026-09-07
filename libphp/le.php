<?php
session_start();
require_once 'datos_conexion.php';

// --- 1. LÓGICA DE LOGIN (ORIGINAL) ---
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
    } else { $error = "Acceso denegado."; }
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
    <title>Acceso - Laboratorio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-slate-900 h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-md border-t-4 border-indigo-600">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-800">Laboratorio Clínico</h1>
            <p class="text-slate-500 text-sm">Ingrese su clave de acceso</p>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="password" name="password" placeholder="Contraseña" required 
                   class="w-full p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
            <button type="submit" name="login" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 transition">
                ENTRAR
            </button>
        </form>
    </div>
</body>
</html>
<?php exit; endif; 

// --- 2. LÓGICA DE BÚSQUEDA AVANZADA (ADAPTADA) ---
$mysqli->query("UPDATE paciente set edad=round((to_days(curdate())-to_days(fecnac))/365.242199,2)");

// Parámetros de filtro
$fecha_filtro = $_GET['fecha'] ?? date("Y-m-d");
$identificacion = $_GET['identificacion'] ?? '';
$nombre_busqueda = $_GET['nombre'] ?? '';
$entidad_filtro = $_GET['entidad'] ?? '';

// Construcción de la consulta SQL dinámica basada en el esquema
$where_clauses = [];

if (!empty($identificacion)) {
    // Si hay identificación, ignoramos otros filtros según requisito
    $where_clauses[] = "e.identificacion = '" . $mysqli->real_escape_string($identificacion) . "'";
} else {
    $where_clauses[] = "e.fecha = '" . $mysqli->real_escape_string($fecha_filtro) . "'";
    if (!empty($nombre_busqueda)) {
        $nombre_busqueda_escaped = $mysqli->real_escape_string($nombre_busqueda);
        $where_clauses[] = "(p.nombres LIKE '%$nombre_busqueda_escaped%' OR p.apellidos LIKE '%$nombre_busqueda_escaped%')";
    }
    if (!empty($entidad_filtro)) {
        $where_clauses[] = "e.entidad = '" . $mysqli->real_escape_string($entidad_filtro) . "'";
    }
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT e.identificacion, e.fecha, e.entidad, concat_ws(' ', p.apellidos, p.nombres) as nombres, p.edad, p.correo, p.telefono 
        FROM examenes e 
        INNER JOIN paciente p ON e.identificacion = p.identificacion 
        WHERE $where_sql 
        GROUP BY e.identificacion, e.fecha 
        ORDER BY e.fecha DESC, nombres ASC";

$resultados = $mysqli->query($sql);

// Obtener entidades para el filtro dropdown
$entidades_res = $mysqli->query("SELECT DISTINCT entidad FROM examenes WHERE entidad IS NOT NULL AND entidad != ''");

$res_conf = $mysqli->query("SELECT nombreLaboratorio FROM configuracion LIMIT 1");
$nombreLab = $res_conf->fetch_assoc()['nombreLaboratorio'] ?? 'Laboratorio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nombreLab ?> - Gestión de Resultados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 h-screen flex flex-col overflow-hidden" x-data="labApp('<?= $identificacion ?>')">

    <header class="bg-indigo-900 text-white p-4 shadow-lg flex justify-between items-center z-20">
        <div class="flex items-center gap-3">
            <div class="bg-white/10 p-2 rounded-lg"><i class="bi bi-activity text-xl text-indigo-300"></i></div>
            <span class="font-bold text-lg uppercase tracking-wider"><?= $nombreLab ?></span>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <span class="hidden md:inline bg-indigo-800 px-3 py-1 rounded-full text-indigo-100">Sesión: <?= $_SESSION['usuario_nombre'] ?></span>
            <a href="?logout=1" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg font-bold transition flex items-center gap-2">
                <i class="bi bi-power"></i> SALIR
            </a>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        
        <aside class="w-full md:w-[450px] bg-white border-r border-slate-200 flex flex-col shadow-xl z-10">
            
            <div class="p-4 border-b border-slate-100 bg-slate-50/50">
                <form action="" method="GET" class="space-y-3">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <i class="bi bi-person-vcard-fill"></i>
                        </span>
                        <input type="text" name="identificacion" value="<?= $identificacion ?>" 
                               placeholder="Documento (Búsqueda global...)" 
                               class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white text-sm shadow-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative">
                            <input type="date" name="fecha" value="<?= $fecha_filtro ?>" 
                                   class="w-full p-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-indigo-400">
                        </div>
                        <select name="entidad" class="w-full p-2 border border-slate-200 rounded-lg text-xs outline-none focus:border-indigo-400 bg-white">
                            <option value="">Todas las Entidades</option>
                            <?php while($ent = $entidades_res->fetch_assoc()): ?>
                                <option value="<?= $ent['entidad'] ?>" <?= $entidad_filtro == $ent['entidad'] ? 'selected' : '' ?>><?= $ent['entidad'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <input type="text" name="nombre" value="<?= $nombre_busqueda ?>" placeholder="Nombre del paciente..."
                               class="flex-1 p-2 border border-slate-200 rounded-lg text-xs outline-none">
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2 text-sm font-bold">
                            <i class="bi bi-search"></i> FILTRAR
                        </button>
                    </div>
                </form>
            </div>

            <div class="flex-1 overflow-y-auto bg-white">
                <?php if($resultados && $resultados->num_rows > 0): ?>
                    <div class="divide-y divide-slate-100">
                        <?php while($row = $resultados->fetch_assoc()): ?>
                            <div @click="idAbierto = '<?= $row['identificacion'] ?>'" 
                                 class="p-4 hover:bg-indigo-50 cursor-pointer transition relative group"
                                 :class="idAbierto == '<?= $row['identificacion'] ?>' ? 'bg-indigo-50 border-l-4 border-indigo-600' : ''">
                                <div class="flex justify-between items-start mb-1">
                                    <h3 class="font-bold text-slate-800 uppercase text-sm truncate pr-4"><?= $row['nombres'] ?></h3>
                                    <span class="text-[10px] bg-slate-200 px-2 py-0.5 rounded text-slate-600 font-mono"><?= $row['fecha'] ?></span>
                                </div>
                                <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                    <span><i class="bi bi-fingerprint"></i> <?= $row['identificacion'] ?></span>
                                    <span><i class="bi bi-calendar3"></i> <?= $row['edad'] ?> años</span>
                                    <?php if($row['entidad']): ?>
                                        <span class="bg-green-100 text-green-700 px-1.5 rounded uppercase font-bold"><?= $row['entidad'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-10 text-center text-slate-400">
                        <i class="bi bi-search text-4xl mb-3 block opacity-20"></i>
                        <p class="text-sm">No se encontraron pacientes con los criterios seleccionados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="flex-1 bg-slate-200 relative">
            <template x-if="idAbierto">
                <div class="h-full flex flex-col">
                    <div class="bg-white p-3 border-b flex justify-between items-center shadow-sm">
                        <span class="text-sm font-bold text-slate-600 flex items-center gap-2">
                            <i class="bi bi-file-earmark-medical text-indigo-600"></i>
                            EXÁMENES DEL PACIENTE: <span x-text="idAbierto" class="text-indigo-600"></span>
                        </span>
                        <div class="flex gap-2">
                            <button @click="imprimirFrame()" class="text-xs bg-slate-800 text-white px-3 py-1.5 rounded-lg hover:bg-black transition flex items-center gap-1">
                                <i class="bi bi-printer"></i> IMPRIMIR
                            </button>
                            <button @click="idAbierto = null" class="md:hidden text-xs bg-slate-200 px-3 py-1.5 rounded-lg">CERRAR</button>
                        </div>
                    </div>
                    <iframe :src="'indexResultados.php?identificacion=' + idAbierto" 
                            id="frameReporte"
                            class="w-full flex-1 border-none bg-slate-200"></iframe>
                </div>
            </template>
            <template x-if="!idAbierto">
                <div class="h-full flex items-center justify-center text-slate-400 flex-col gap-4">
                    <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center border-4 border-dashed border-slate-300">
                        <i class="bi bi-arrow-left text-3xl opacity-30"></i>
                    </div>
                    <p class="font-medium">Seleccione un paciente para ver sus resultados</p>
                </div>
            </template>
        </main>
    </div>

    <script>
        function labApp(idInicial) {
            return {
                idAbierto: idInicial || null,
                imprimirFrame() {
                    const frame = document.getElementById('frameReporte');
                    if(frame) {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    }
                }
            }
        }
    </script>
</body>
</html>