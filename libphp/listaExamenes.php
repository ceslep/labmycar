<?php
session_start();
require_once 'datos_conexion.php';

// Verificar autenticación
if (!isset($_SESSION['autenticado'])) {
    header("Location: listaExamenes.php");
    exit;
}

// Obtener configuración del laboratorio
$res_conf = $mysqli->query("SELECT nombreLaboratorio FROM configuracion ORDER BY id DESC LIMIT 1");
$dato_conf = $res_conf->fetch_assoc();
$nombreLab = $dato_conf['nombreLaboratorio'] ?? 'Laboratorio Clínico';

// Procesar el formulario
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
$tipo_reporte = $_POST['tipo_reporte'] ?? 'completo';
$formato = $_POST['formato'] ?? 'html';

// Función para obtener datos del reporte
function obtenerReporteExamenes($mysqli, $fecha_inicio, $fecha_fin, $tipo_reporte) {
    $query = "
        SELECT 
            e.identificacion,
            CONCAT_WS(' ', p.apellidos, p.nombres) as paciente,
            p.entidad as eps,
            DATE(e.fecha) as fecha_examen,
            pr.nombre as examen,
            pr.tabla as tabla_examen,
            GROUP_CONCAT(DISTINCT pr.codigo) as codigos_examenes
        FROM examenes e
        INNER JOIN paciente p ON e.identificacion = p.identificacion
        INNER JOIN procedimientos pr ON e.codexamen = pr.codigo
        WHERE e.fecha BETWEEN ? AND ?
        GROUP BY e.identificacion, e.fecha, pr.nombre
        ORDER BY e.fecha DESC, p.apellidos, p.nombres
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $datos = [];
    $total_examenes = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Obtener resultados según el tipo de examen
        $resultados = obtenerResultadosExamen($mysqli, $row['identificacion'], $row['fecha_examen'], $row['tabla_examen'], $row['codigos_examenes']);
        
        $datos[] = [
            'identificacion' => $row['identificacion'],
            'paciente' => $row['paciente'],
            'eps' => $row['eps'],
            'fecha' => $row['fecha_examen'],
            'examen' => $row['examen'],
            'resultados' => $resultados
        ];
        $total_examenes++;
    }
    
    return [
        'datos' => $datos,
        'total_examenes' => $total_examenes,
        'periodo' => "$fecha_inicio al $fecha_fin"
    ];
}

// Función para obtener resultados específicos según la tabla
function obtenerResultadosExamen($mysqli, $identificacion, $fecha, $tabla, $codigos) {
    $resultados = [];
    
    // Dividir los códigos si hay múltiples
    $codigos_array = explode(',', $codigos);
    
    foreach ($codigos_array as $codigo) {
        $codigo = trim($codigo);
        
        switch ($tabla) {
            case 'examen_tipo_1':
                $query = "SELECT valoracion, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ? AND examen = ?";
                break;
            case 'examen_tipo_2':
                $query = "SELECT valoracion, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ? AND examen = ?";
                break;
            case 'examen_tipo_3':
                $query = "SELECT densidad, color, aspecto, ph, proteinas, glucosa, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ? AND examen = ?";
                break;
            case 'examen_tipo_4':
            case 'coprologico':
                $query = "SELECT consistencia, color, ph, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ? AND examen = ?";
                break;
            case 'examen_tipo_5':
                $query = "SELECT hemoglobina, hematocrito, leucocitos, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ? AND examen = ?";
                break;
            case 'hemogramaRayto':
                $query = "SELECT WBC, RBC, HGB, HCT, PLT, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ?";
                break;
            case 'parcialOrina':
                $query = "SELECT densidad, color, aspecto, ph, proteinas, glucosa, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ?";
                break;
            case 'frotisVaginal':
                $query = "SELECT ph, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ?";
                break;
            case 'perfilLipidico':
                $query = "SELECT colesterol_total, trigliceridos, observaciones FROM $tabla 
                         WHERE identificacion = ? AND fecha = ?";
                break;
            default:
                $query = "SELECT * FROM $tabla 
                         WHERE identificacion = ? AND fecha = ? AND examen = ? LIMIT 1";
        }
        
        $stmt = $mysqli->prepare($query);
        
        if (in_array($tabla, ['hemogramaRayto', 'parcialOrina', 'frotisVaginal', 'perfilLipidico', 'coprologico'])) {
            $stmt->bind_param("ss", $identificacion, $fecha);
        } else {
            $stmt->bind_param("sss", $identificacion, $fecha, $codigo);
        }
        
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $resultados[] = [
                'codigo' => $codigo,
                'datos' => array_filter($row) // Eliminar valores nulos
            ];
        }
    }
    
    return $resultados;
}

// Generar reporte
$reporte = obtenerReporteExamenes($mysqli, $fecha_inicio, $fecha_fin, $tipo_reporte);

// Exportar a Excel si se solicita
if ($formato === 'excel' && isset($_POST['generar'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="reporte_examenes_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>td { border: 1px solid #ccc; padding: 5px; }</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>' . htmlspecialchars($nombreLab) . '</h2>';
    echo '<h3>Reporte de Exámenes Realizados</h3>';
    echo '<p>Periodo: ' . $reporte['periodo'] . '</p>';
    echo '<p>Total de exámenes: ' . $reporte['total_examenes'] . '</p>';
    echo '<br>';
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>#</th>';
    echo '<th>Identificación</th>';
    echo '<th>Paciente</th>';
    echo '<th>EPS</th>';
    echo '<th>Fecha</th>';
    echo '<th>Examen</th>';
    echo '<th>Resultados</th>';
    echo '</tr>';
    
    $contador = 1;
    foreach ($reporte['datos'] as $item) {
        echo '<tr>';
        echo '<td>' . $contador++ . '</td>';
        echo '<td>' . htmlspecialchars($item['identificacion']) . '</td>';
        echo '<td>' . htmlspecialchars($item['paciente']) . '</td>';
        echo '<td>' . htmlspecialchars($item['eps']) . '</td>';
        echo '<td>' . htmlspecialchars($item['fecha']) . '</td>';
        echo '<td>' . htmlspecialchars($item['examen']) . '</td>';
        
        // Formatear resultados
        $resultados_texto = '';
        foreach ($item['resultados'] as $resultado) {
            if (!empty($resultado['datos'])) {
                foreach ($resultado['datos'] as $key => $value) {
                    if (!empty($value)) {
                        $resultados_texto .= ucfirst(str_replace('_', ' ', $key)) . ': ' . $value . '; ';
                    }
                }
            }
        }
        
        echo '<td>' . htmlspecialchars($resultados_texto) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Si no es Excel, mostrar en HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Exámenes - <?= htmlspecialchars($nombreLab) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-4">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($nombreLab) ?></h1>
                    <p class="text-gray-600">Reporte de Exámenes Realizados</p>
                </div>
                <a href="listaExamenes.php" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <!-- Formulario de filtros -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Filtros del Reporte</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>" 
                           class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                    <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>" 
                           class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Reporte</label>
                    <select name="tipo_reporte" class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="completo" <?= $tipo_reporte == 'completo' ? 'selected' : '' ?>>Completo</option>
                        <option value="resumido" <?= $tipo_reporte == 'resumido' ? 'selected' : '' ?>>Resumido</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Formato</label>
                    <select name="formato" class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="html" <?= $formato == 'html' ? 'selected' : '' ?>>HTML (Ver en navegador)</option>
                        <option value="excel" <?= $formato == 'excel' ? 'selected' : '' ?>>Excel (Descargar)</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <button type="submit" name="generar" 
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                        <i class="bi bi-file-earmark-text"></i> Generar Reporte
                    </button>
                </div>
            </form>
        </div>

        <!-- Resultados del reporte -->
        <?php if (isset($_POST['generar']) && $formato === 'html'): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Resultados del Reporte</h2>
                    <p class="text-gray-600">Periodo: <?= $reporte['periodo'] ?></p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                        <i class="bi bi-printer"></i> Imprimir
                    </button>
                    <form method="POST">
                        <input type="hidden" name="fecha_inicio" value="<?= $fecha_inicio ?>">
                        <input type="hidden" name="fecha_fin" value="<?= $fecha_fin ?>">
                        <input type="hidden" name="tipo_reporte" value="<?= $tipo_reporte ?>">
                        <input type="hidden" name="formato" value="excel">
                        <button type="submit" name="generar" 
                                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                            <i class="bi bi-file-excel"></i> Exportar a Excel
                        </button>
                    </form>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="text-blue-600 font-bold text-2xl"><?= $reporte['total_examenes'] ?></div>
                    <div class="text-blue-800 font-medium">Total Exámenes</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <div class="text-green-600 font-bold text-2xl"><?= count($reporte['datos']) ?></div>
                    <div class="text-green-800 font-medium">Registros</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                    <div class="text-purple-600 font-bold text-2xl"><?= date('d/m/Y', strtotime($fecha_inicio)) ?></div>
                    <div class="text-purple-800 font-medium">Fecha Inicio</div>
                </div>
                <div class="bg-amber-50 p-4 rounded-lg border border-amber-200">
                    <div class="text-amber-600 font-bold text-2xl"><?= date('d/m/Y', strtotime($fecha_fin)) ?></div>
                    <div class="text-amber-800 font-medium">Fecha Fin</div>
                </div>
            </div>

            <!-- Tabla de resultados -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Identificación</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paciente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EPS</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Examen</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resultados</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $contador = 1; ?>
                        <?php foreach ($reporte['datos'] as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= $contador++ ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-gray-900"><?= htmlspecialchars($item['identificacion']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['paciente']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($item['eps']) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700"><?= date('d/m/Y', strtotime($item['fecha'])) ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-blue-700"><?= htmlspecialchars($item['examen']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <?php foreach ($item['resultados'] as $resultado): ?>
                                    <?php if (!empty($resultado['datos'])): ?>
                                        <div class="mb-2 last:mb-0">
                                            <?php foreach ($resultado['datos'] as $key => $value): ?>
                                                <?php if (!empty($value) && $key !== 'observaciones'): ?>
                                                    <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded mr-1 mb-1">
                                                        <span class="font-medium"><?= ucfirst(str_replace('_', ' ', $key)) ?>:</span> <?= htmlspecialchars($value) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if (!empty($resultado['datos']['observaciones'])): ?>
                                                <div class="mt-1 text-xs text-gray-600">
                                                    <span class="font-medium">Observaciones:</span> <?= htmlspecialchars($resultado['datos']['observaciones']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pie de página del reporte -->
            <div class="mt-8 pt-6 border-t border-gray-200 text-sm text-gray-500">
                <div class="flex justify-between">
                    <div>
                        <p>Reporte generado el: <?= date('d/m/Y H:i:s') ?></p>
                        <p>Usuario: <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></p>
                    </div>
                    <div class="text-right">
                        <p><?= htmlspecialchars($nombreLab) ?></p>
                        <p>Total de registros: <?= count($reporte['datos']) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>