<?php
/* $tabla="xxxhemogramaRayto";
echo strpos(strtolower($tabla),"hemo", );
exit(0) */ ;

// Include the dompdf library
require_once 'vendor/autoload.php';
require_once '../datos_conexion.php';

// Create a new DOMPDF instance
use Dompdf\Dompdf;
use Dompdf\Options;
$identificacion = '9695141';
$fecha = '2012-08-23';
$nombres = 'CESAR LEANDRO PATIÑO VELEZ';
$edad = "";

/* $tabla='hemogramaRayto';
$info='Hemograma';  */
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

if (isset($datos->codexamen)) {
    $ver = $datos->ver;
} else if (isset($_GET['codexamen'])) {
    $codexamen = $_GET['codexamen'];
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



function generarExponente($cadena)
{
    if (strpos($cadena, "^")) {

        $cadena = str_replace("^", "<sup class='exponente'>", "$cadena");
        $cadena = str_replace("/", "</sup>/", $cadena);
        $cadena = str_replace("/L", "/L  :  ", $cadena);
    } else if (strpos($cadena, "%")) {

        $cadena = str_replace("%", " %   :  ", $cadena);
    } else if (strpos($cadena, "dL")) {
        $cadena = str_replace("dL", "dL   :  ", $cadena);
    } else if (strpos($cadena, "pg")) {
        $cadena = str_replace("pg", "pg   :  ", $cadena);
    } else if (strpos($cadena, "fl")) {
        $cadena = str_replace("fl", "fl   :  ", $cadena);
    }

    return $cadena;
}

function splitCamelCaseRegex($str)
{
    return preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $str);
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
    $info = $GLOBALS['info'];
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
                      <img src='$logo' alt='$info' width='50%'>
                  </td>


                  <td class='r' style='text-align:center;padding:0;'>
                      <div>$info</div>
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
$sql = "SELECT * from configuracion
    order by id desc
          limit 1
    ";
$result = $mysqli->query($sql);
if ($result->num_rows > 0) {
    $dato = $result->fetch_assoc();
    $logo = $dato['urlLogoLaboratorio'];
    $info = $dato['nombreLaboratorio'];
    $direccion = $dato['direccionLaboratorio'];
    $telefonos = $dato['telefonosLaboratorio'];
    $correo = $dato['correoLaboratorio'];
    $web = $dato['webLaboratorio'];
}

$encabezado = encabezado();

$html = "
<!DOCTYPE html>
<html lang='es'>
<head>
<title>Resultados de laboratorio</title>

<style>

    body {
        background-color:white;
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
</head>
<body>


";

$tablaExamenes = ['hemogramaRayto', 'parcialOrina', 'coprologico', 'examen_tipo_1', 'examen_tipo_2', 'frotisVaginal', 'perfilLipidico'];
sort($tablaExamenes);
//var_dump($tablaExamenes);

$html .= "
$encabezado
<table class='table table-sm table-bordered pb-0 mb-0'>
            <tbody>
                <tr>
                  <td class='w-25'>Identificacion:</td><td class='text-start '>$identificacion</td>
                </tr>
                <tr>
                  <td>Nombres:</td><td class='text-start'>$nombres</td>
                </tr>
                <tr>
                <td>Edad:</td><td class='text-start'>$edad</td>
              </tr>
                <tr>
                  <td>Fecha:</td><td class='text-start'>$fecha</td>
                </tr>
                <tr>
                  <td>Entidad:</td><td class='text-start'>$entidad</td>
                </tr>
               
            </tbody>
        </table>
";

$dompdf = new Dompdf();
$options = new Options();
$options->set(array('isRemoteEnabled' => true));
$options->set('isHtml5ParserEnabled', true); // Habilitar el analizador HTML5
$options->set('isPhpEnabled', true); // Habilitar el soporte PHP dentro de Dompdf
//$dompdf->set_option('DOMPDF_ENABLE_REMOTE', true);
$dompdf->setOptions($options);

foreach ($tablaExamenes as $tablaex) {
    if (strpos($tablaex, "tipo")) {

        $sql = "SELECT *,procedimientos.nombre as nombreExamen,if(procedimientos.constante2<>'',procedimientos.constante2,procedimientos.constante) as constant,procedimientos.unidades from $tablaex
        inner join procedimientos on $tablaex.examen=procedimientos.codigo
              where identificacion='$identificacion' and fecha='$fecha'
        ";
        //  echo "<br/>".$sql."<br/>";
    } else {
        $sql = "
  Select * from $tablaex
  where 1=1
  and  identificacion='$identificacion'
  and  fecha='$fecha'";
    }

    // echo "<br/>".$sql."<br/>";

    if ($result = $mysqli->query($sql)) {

        if ($result->num_rows == 0) {
            continue;
        }

        while ($datos = $result->fetch_assoc()) {
            $info = ucfirst($tablaex);
            $info = splitCamelCaseRegex($info);
            if (strpos($tablaex, "tipo")) {
                //    var_dump($datos);exit(0);
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
                        </tr>
                    </tbody>
                </table>
                <br/>
        ";
            } else {
                $html .= "<table>
            <caption><h4>Resultados del Análisis $info</h4></caption>
            <thead >
                <tr>
                    <th>
                      Dato
                    </th>
                    <th>
                      Valor
                    </th>
                </tr>
            </thead>
            \t<tbody id='datos$tablaex'>
    ";

                foreach ($datos as $key => $value) {
                    if ($key == 'ind' || $key == 'id' || $key == 'examen' || $key == 'identificacion' || $key == 'fecha' || $key == 'fechahora' || $key == 'tabla' || $key == 'fechahora' || $key == 'doctor' || $key == 'bacteriologo') {
                        continue;
                    }
                    if ($value == "" || $value == null) {
                        continue;
                    }

                    $dato = ucfirst($key);
                    $dato = str_replace("_", " ", $dato);

                    if (strpos(strtolower($tablaex), "hemo") >= 0 || strpos(strtolower($tabla), "hema") >= 0) {
                        $value = generarExponente($value);
                    }

                    $html .= "\t\t\t\t\t<tr>\n";
                    $html .= "\t\t\t\t\t\t\t<td class='bold w-25 text-start'>$dato</td>\n";
                    $html .= "\t\t\t\t\t\t\t<td class='text-start'>$value</td>\n";
                    $html .= "\t\t\t\t\t</tr>\n";
                }
                $html .= "\t</tbody>\n";
                $html .= "</table>\n";

            }
        }
        //  $result->free();
    }
}
/*$sql = "select * from configuracion order by id desc limit 1";
$result = $mysqli->query($sql);
$datas = $result->fetch_assoc();
$logo = 'https://mycar.iedeoccidente.com/printphp/firma1.png';
$bacteriologo = $datas['bacteriologoLaboratorio'] . ":T.P. " . $datas['tarjetaPLaboratorio'];*/
    $sql = "select * from configuracion order by id desc limit 1";
    $result = $mysqli->query($sql);
    $datas = $result->fetch_assoc();
    $logo = 'https://mycar.iedeoccidente.com/printphp/firma1.png';

// === INICIO: Lógica de bacteriólogo por fecha ===
    require_once 'configuracion_bacteriologos.php'; // Ajusta la ruta si es necesario
    $bacteriologo_db = $datas['bacteriologoLaboratorio'] . ":T.P. " . $datas['tarjetaPLaboratorio'];
    $bacteriologo = obtenerBacteriologoPorFecha($fecha, $bacteriologo_db);
    if ($bacteriologo!==$bacteriologo_db) 
    $logo='https://mycar.iedeoccidente.com/printphp/sin_firma.png';
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
$html .= "</body>\n
     </html>";
//echo $html;exit(0);

$dompdf->loadHtml($html);

// (Optional) Set paper size and orientation
$dompdf->setPaper('letter', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the PDF (in this case, download to browser)

if (isset($ver)) {
    ob_clean();
    header('Content-Type: application/pdf');
    $dompdf->stream("$nombres.pdf", ['Attachment' => false]);
    exit(0);
} else {
    $dompdf->stream("$tabla-$identificacion-$nombres-$fecha.pdf");
}

$mysqli->close();
