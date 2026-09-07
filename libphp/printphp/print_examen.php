<?php

// Include the dompdf library
require_once 'vendor/autoload.php';
require_once '../datos_conexion.php';

// Create a new DOMPDF instance
use Dompdf\Dompdf;
use Dompdf\Options;

/* $identificacion = '9695141';
$fecha = '2012-08-23';
$nombres = 'CESAR LEANDRO PATIÑO VELEZ';
$tabla='hemogramaRayto';
$info='Hemograma'; */
if (isset($datos->identificacion)) {
    $identificacion = $datos->identificacion;
} else if (isset($_GET['identificacion'])) {
    $identificacion = $_GET['identificacion'];
}

if (isset($datos->fecha)) {
    $fecha = $datos->fecha;
} else if (isset($_GET['fecha'])) {
    $fecha = $_GET['fecha'];
}

if (isset($datos->nombres)) {
    $nombres = $datos->nombres;
} else if (isset($_GET['nombres'])) {
    $nombres = $_GET['nombres'];
}

if (isset($datos->tabla)) {
    $tabla = $datos->tabla;
} else if (isset($_GET['tabla'])) {
    $tabla = $_GET['tabla'];
}

if (isset($datos->info)) {
    $info = $datos->info;
} else if (isset($_GET['info'])) {
    $info = $_GET['info'];
}

if (isset($datos->ver)) {
    $ver = $datos->ver;
} else if (isset($_GET['ver'])) {
    $ver = $_GET['ver'];
}

if (isset($datos->edad)) {
    $edad = $datos->edad;
} else if (isset($_GET['edad'])) {
    $edad = $_GET['edad'];
}

if (isset($datos->entidad)) {
    $entidad = $datos->entidad;
} else if (isset($_GET['entidad'])) {
    $entidad = $_GET['entidad'];
}

if (isset($datos->codexamen)) {
    $codexamen = $datos->codexamen;
} else if (isset($_GET['codexamen'])) {
    $codexamen = $_GET['codexamen'];
}
function extractID($url)
{
    $paths = explode("/", $url);
    // var_dump( $paths);
    return $paths[5];
}

function encabezado()
{
    $logo = $GLOBALS['logo'];
    $infol = $GLOBALS['infol'];
    $direccion = $GLOBALS['direccion'];
    $telefonos = $GLOBALS['telefonos'];
    $correo = $GLOBALS['correo'];
    $web = $GLOBALS['web'];
    $id = extractID($logo);
    $logo = "https://drive.google.com/thumbnail?id=$id";
    $logo = 'https://mycar.iedeoccidente.com/printphp/logo.png';
    $html = "
    <table class='table'>
    <tr>
            <td class='l'>
                <img src='$logo' alt='$infol' width='45%'>
            </td>


            <td class='r' style='text-align:center;padding:0;'>
                <div>$infol</div>
                <div>$direccion</div>
                <div>Tel. $telefonos</div>
                <div>$correo $web</div>
            </td>
            </tr>
    </table>
    <br/>
    ";

    return $html;
}
$logo = "";

function generarExponente($cadena)
{
    if (strpos($cadena, "^")) {

        $cadena = str_replace("^", "<sup class='exponente'>", "$cadena");
        $cadena = str_replace("/", "</sup>/", $cadena);
        $cadena = str_replace("/L", "/L  :  ", $cadena);
    } else if (strpos($cadena, "%")) {

        $cadena = str_replace("%", " %   :  ", $cadena);
    } else if (strpos($cadena, "dL")) {
        $cadena = str_replace("dL", "dL  :  ", $cadena);
    } else if (strpos($cadena, "pg")) {
        $cadena = str_replace("pg", "pg   :  ", $cadena);
    } else if (strpos($cadena, "fl")) {
        $cadena = str_replace("fl", "fl   :  ", $cadena);
    }

    return $cadena;
}

$sql = "SELECT * from configuracion
order by id desc
      limit 1
";
$result = $mysqli->query($sql);
if ($result->num_rows > 0) {
    $dato = $result->fetch_assoc();
    $logo = $dato['urlLogoLaboratorio'];
    $infol = $dato['nombreLaboratorio'];
    $direccion = $dato['direccionLaboratorio'];
    $telefonos = $dato['telefonosLaboratorio'];
    $correo = $dato['correoLaboratorio'];
    $web = $dato['webLaboratorio'];
}

$encabezado = encabezado();
if (strpos($tabla, "tipo")) {

    $sql = "SELECT *,procedimientos.nombre as nombreExamen,if(procedimientos.constante2<>'',procedimientos.constante2,procedimientos.constante) as constant,procedimientos.unidades from $tabla
    inner join procedimientos on $tabla.examen=procedimientos.codigo
          where identificacion='$identificacion' and fecha='$fecha'
          and nombre like '%$info%'
    ";
    //echo "<br/>".$sql."<br/>";exit(0);
} else {
    $sql = "
  Select * from $tabla
  where 1=1
  and  identificacion='$identificacion'
  and  fecha='$fecha'";
}
if ($result = $mysqli->query($sql)) {
    $dompdf = new Dompdf();
    $options = new Options();
    $options->set(array('isRemoteEnabled' => true));
    $options->set('isHtml5ParserEnabled', true); // Habilitar el analizador HTML5
    $options->set('isPhpEnabled', true); // Habilitar el soporte PHP dentro de Dompdf
    //$dompdf->set_option('DOMPDF_ENABLE_REMOTE', true);
    $dompdf->setOptions($options);
    $datos = $result->fetch_assoc();

    $html = "
    <!DOCTYPE html>
<html lang='es'>
<head>
<title>Resultados de Exámen " . ($tabla === 'hemogramaRayto' ? 'Cuadro Hemático completo' : $tabla) . "</title>
    <style>

    body {
        font-family: Tahoma, sans-serif;
      }
    /* Estilo base para la tabla */
table {
    font-size:0.7rem;
  width: 100%; /* Ancho completo de la tabla */
  border-collapse: collapse; /* Elimina los bordes entre celdas */
  border: 1px solid black;
}

/* Estilo para el encabezado de la tabla */
th {
  background-color: #9B9D9E; /* Color de fondo del encabezado */
  color: #fff; /* Color de texto del encabezado */
  text-align: center; /* Alineación de texto en el encabezado */
  padding: 1px 1px; /* Padding alrededor del texto del encabezado */
  border: 1px solid #ddd; /* Línea divisoria inferior */
}

/* Estilo para las filas de datos */
tr {
  border: 1px solid black; /* Línea divisoria inferior para cada fila */
}

/* Estilo para las celdas de datos */
td {
  text-align: center; /* Alineación de texto a la izquierda */
  border-bottom: 1px solid #eee; /* Línea divisoria inferior para cada celda */
  border: 1px solid #ddd;
}

/* Estilo para las filas pares (opcional) */
tr:nth-child(even) {
  background-color: #f9fafb; /* Color de fondo para filas pares */
}

td:nth-child(1) {
    width:150px;
  }
td:nth-child(2) {
    text-align: left; /* Alinea el texto a la
  }



tbody#datos tr td {
    font-size: 0.75rem; /* Reducir el tamaño de fuente en un 20% */
  }

  .bold{
    font-weight:bold;
  }

  .exponente{
    font-weight:bold;
    font-size:0.6rem;
  }

  caption{
    padding:0;
  }

  .edgrid{
    display:grid;
    grid-template-columns: 1fr 2fr
  }

  .l{
    text-align:center;
  }

  .r{
    text-align:center;
  }
    </style>
    <body>
    $encabezado
    ";
    $titulo_examen = ($tabla === 'hemogramaRayto') ? 'Cuadro Hemático completo' : $info;
    $html .= "<h3 class='text-primary'>$titulo_examen</h3>";
    $html .= "<table class='table table-sm'>
                <tbody>
                    <tr>
                      <td>Identificacion:</td><td>$identificacion</td>
                    </tr>
                    <tr>
                      <td>Nombres:</td><td>$nombres</td>
                    </tr>
                    <tr>
                    <td>Edad:</td><td>$edad</td>
                  </tr>
                    <tr>
                      <td>Fecha:</td><td>$fecha</td>
                    </tr>
                    <tr>
                      <td>Entidad:</td><td>$entidad</td>
                    </tr>
                   
                </tbody>
            </table>
    ";
    if (strpos($tabla, "tipo")) {
        $info1 = ucfirst(strtolower($datos['nombreExamen']));
        $valoracion = $datos['valoracion'];
        $unidades = $datos['unidades'];
        $constante = $datos['constant'];
        $observaciones = $datos['observaciones'];
        $observaciones = $observaciones != "" ? $observaciones : "Ninguna";

        $html .= "<table>
                    <caption><h4>Resultados del Análisis $info1</h4></caption>
                        <thead>
                            <tr>
                                <th>
                                Valoración
                                </th>
                                <th>
                                Referencia
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    $valoracion $unidades
                                </td>
                                <td>
                                    $constante
                                </td>
                            </tr>
                            <tr>
                            <td>
                               Observaciones
                            </td>
                            <td>
                                $observaciones
                            </td>
                        </tbody>
                    </table>
                    <br/>
            ";
    } else {
        // Para hemogramaRayto y perfilLipidico, obtener constantes de referencia desde procedimientos.constante2
        // El JSON es un array de objetos: [{"parametro":"WBC","nombre":"...","valorReferencia":"..."},...]
        $referencias_hemo = [];
        $referencias_lipidico = [];
        
        if ($tabla === 'hemogramaRayto') {
            // Buscar por codexamen si existe, sino buscar por tabla
            if (!empty($codexamen)) {
                $stmt_ref = $mysqli->prepare("SELECT IF(constante2 <> '', constante2, constante) as constant FROM procedimientos WHERE codigo = ? LIMIT 1");
                $stmt_ref->bind_param("s", $codexamen);
            } else {
                $stmt_ref = $mysqli->prepare("SELECT IF(constante2 <> '', constante2, constante) as constant FROM procedimientos WHERE tabla = 'hemogramaRayto' AND constante2 <> '' LIMIT 1");
            }
            $stmt_ref->execute();
            $res_ref = $stmt_ref->get_result();
            if ($res_ref && $res_ref->num_rows > 0) {
                $row_ref = $res_ref->fetch_assoc();
                $raw_json = $row_ref['constant'];
                // Limpiar: BOM, trim, y asegurar UTF-8 válido
                $raw_json = trim($raw_json);
                $raw_json = ltrim($raw_json, "\xEF\xBB\xBF");
                // Convertir a UTF-8 si no lo es
                if (!mb_check_encoding($raw_json, 'UTF-8')) {
                    $raw_json = mb_convert_encoding($raw_json, 'UTF-8', 'ISO-8859-1');
                }
                // Reemplazar non-breaking spaces (U+00A0) por espacios normales
                $raw_json = str_replace("\xC2\xA0", ' ', $raw_json);
                // Eliminar todos los caracteres de control incluyendo tabs y newlines
                $raw_json = preg_replace('/[\x00-\x1F\x7F]/', ' ', $raw_json);
                $json_ref = json_decode($raw_json, true);
                if (is_array($json_ref)) {
                    foreach ($json_ref as $item) {
                        if (isset($item['parametro'])) {
                            $referencias_hemo[$item['parametro']] = $item['valorReferencia'] ?? '';
                        }
                    }
                }
            }
        } elseif ($tabla === 'perfilLipidico') {
            // Buscar por codexamen si existe, sino buscar por tabla
            if (!empty($codexamen)) {
                $stmt_ref = $mysqli->prepare("SELECT IF(constante2 <> '', constante2, constante) as constant FROM procedimientos WHERE codigo = ? LIMIT 1");
                $stmt_ref->bind_param("s", $codexamen);
            } else {
                $stmt_ref = $mysqli->prepare("SELECT IF(constante2 <> '', constante2, constante) as constant FROM procedimientos WHERE tabla = 'perfilLipidico' AND constante2 <> '' LIMIT 1");
            }
            $stmt_ref->execute();
            $res_ref = $stmt_ref->get_result();
            if ($res_ref && $res_ref->num_rows > 0) {
                $row_ref = $res_ref->fetch_assoc();
                $raw_json = $row_ref['constant'];
                // Limpiar: BOM, trim, y asegurar UTF-8 válido
                $raw_json = trim($raw_json);
                $raw_json = ltrim($raw_json, "\xEF\xBB\xBF");
                // Convertir a UTF-8 si no lo es
                if (!mb_check_encoding($raw_json, 'UTF-8')) {
                    $raw_json = mb_convert_encoding($raw_json, 'UTF-8', 'ISO-8859-1');
                }
                // Reemplazar non-breaking spaces (U+00A0) por espacios normales
                $raw_json = str_replace("\xC2\xA0", ' ', $raw_json);
                // Eliminar todos los caracteres de control incluyendo tabs y newlines
                $raw_json = preg_replace('/[\x00-\x1F\x7F]/', ' ', $raw_json);
                $json_ref = json_decode($raw_json, true);
                if (is_array($json_ref)) {
                    foreach ($json_ref as $item) {
                        if (isset($item['dato']) && isset($item['valor_recomendado'])) {
                            // Normalize the 'dato' from JSON to match database keys
                            $normalized_dato = strtolower(str_replace(' ', '_', $item['dato']));
                            $referencias_lipidico[$normalized_dato] = $item['valor_recomendado'];
                        }
                    }
                }
            }
        }

        $tiene_referencias = !empty($referencias_hemo) || !empty($referencias_lipidico);
        // DEBUG: descomentar para diagnosticar - se muestra en el PDF
        $html .= "<table class='table table-sm'>
            <caption><h4>Resultados del Análisis</h4></caption>
            <thead class='bg-light'>
                <tr>
                    <th>
                      Dato
                    </th>
                    <th>
                      Valor
                    </th>" .
                    ($tiene_referencias ? "<th>Referencia</th>" : "") .
                "</tr>
            </thead>
            <tbody id='datos'>
    ";
        foreach ($datos as $key => $value) {
            if ($key == 'id' || $key == 'identificacion' || $key == 'fecha' || $key == 'fechahora' || $key == 'tabla' || $key == 'fechahora' || $key == 'doctor' || $key == 'bacteriologo') {
                continue;
            }
            if ($value == "" || $value == null) {
                continue;
            }

            $dato = ucfirst($key);
            $dato = str_replace("_", " ", $dato);
            if (strpos(strtolower($tabla), "hemo") !== false || strpos(strtolower($tabla), "hema") !== false) {
                $value = generarExponente($value);
            }

            $html .= "<tr>";
            $html .= "<td class='bold'>$dato</td>";
            $html .= "<td>$value</td>";
            if ($tiene_referencias) {
                $referencia = '';
                if ($tabla === 'hemogramaRayto') {
                    $referencia = $referencias_hemo[$key] ?? '';
                } elseif ($tabla === 'perfilLipidico') {
                    $referencia = $referencias_lipidico[$key] ?? '';
                }
                $html .= "<td>" . htmlspecialchars($referencia) . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";
    }
    /* $sql = "select * from configuracion order by id desc limit 1";
     $result = $mysqli->query($sql);
     $datas = $result->fetch_assoc();
     $logo = 'https://mycar.iedeoccidente.com/printphp/firma1.png';
     $bacteriologo = $datas['bacteriologoLaboratorio'] . ":T.P. " . $datas['tarjetaPLaboratorio'];*/

    $sql = "select * from configuracion order by id desc limit 1";
    $result = $mysqli->query($sql);
    $datas = $result->fetch_assoc();

    // === INICIO: Lógica de bacteriólogo por fecha ===
    require_once 'configuracion_bacteriologos.php';
    $bacteriologo_db = $datas['bacteriologoLaboratorio'] . ":T.P. " . $datas['tarjetaPLaboratorio'];
    $bacteriologo = obtenerBacteriologoPorFecha($fecha, $bacteriologo_db);
    $logo = obtenerImagenFirma($fecha, $bacteriologo_db);
    // === FIN: Lógica de bacteriólogo por fecha ===

    $html .= "
     <br/>
     <br/>
     <table style='border:0px;'>
     <tr style='background-color:white;border:0px;'>
                <td style='width:50%;border:0px;'>
                <img src='$logo' width='200'>
                  </td>
              </tr>
            <tr style='background-color:white;border:none;'>
                <td style='width:50%;border:0px;'>
                $bacteriologo
                </td>
            </tr>
            <tr style='background-color:white;border:0px;'>
            <td style='width:50%;border:0px;'>
                Bacteriologo
                </td>
            </tr>
     </table>";
    $html .= "
              </body>
              </html>

    ";
    //   echo $html;exit(0);

    $dompdf->loadHtml($html);

    // (Optional) Set paper size and orientation
    $dompdf->setPaper('letter', 'portrait');

    // Render the HTML as PDF
    $dompdf->render();

    // Output the PDF (in this case, download to browser)

    if (isset($ver)) {
        ob_clean();
        header('Content-Type: application/pdf');
        $dompdf->stream("$name.pdf", ['Attachment' => false]);
        exit(0);
    } else {
        $dompdf->stream("$tabla-$identificacion-$nombres-$fecha.pdf");
    }

}