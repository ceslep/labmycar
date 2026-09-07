<?php
    //phpinfo();exit(0);
    $datos = json_decode("{}");
    require_once 'datos_conexion.php';

    $sql = "update paciente set edad=round((to_days(curdate())-to_days(fecnac))/365.242199,2)";
    $mysqli->query($sql);

    // Create a new DOMPDF instance
    use Dompdf\Dompdf;

    $server = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?\n";

    $print = 'https://' . $_SERVER['HTTP_HOST'] . '/printphp/print_examen.php?';

    $printTodo = 'https://' . $_SERVER['HTTP_HOST'] . '/printphp/imprimirTodo.php?';

    $identificacion = '';
    $fecha = "";
    $nombres = '';
    $tabla = '';
    $info = '';
    $embedido = null;
    $todo = null;
    $edad = '';

    if (isset($datos->identificacion)) {
        $identificacion = $mysqli->real_escape_string($datos->identificacion);
    } else if (isset($_GET['identificacion'])) {
        $identificacion = $mysqli->real_escape_string($_GET['identificacion']);
    }

    if (isset($datos->fecha)) {
        $fecha = $mysqli->real_escape_string($datos->fecha);
    } else if (isset($_GET['fecha'])) {
        $fecha = $mysqli->real_escape_string($_GET['fecha']);
    }

    if ($fecha == "") {
        $fecha = date("Y-m-d");

    }

    if (isset($datos->nombres)) {
        $nombres = $mysqli->real_escape_string($datos->nombres);
    } else if (isset($_GET['nombres'])) {
        $nombres = $mysqli->real_escape_string($_GET['nombres']);
    }

    if (isset($datos->tabla)) {
        $tabla = $mysqli->real_escape_string($datos->tabla);
    } else if (isset($_GET['tabla'])) {
        $tabla = $mysqli->real_escape_string($_GET['tabla']);
    }

    if (isset($datos->info)) {
        $info = $mysqli->real_escape_string($datos->info);
    } else if (isset($_GET['info'])) {
        $info = $mysqli->real_escape_string($_GET['info']);
    }

    if (isset($datos->embedido)) {
        $embedido = $datos->embedido;
    } else if (isset($_GET['embedido'])) {
        $embedido = $_GET['embedido'];
    }

    if (isset($datos->todo)) {
        $todo = $datos->todo;
    } else if (isset($_GET['todo'])) {
        $todo = $_GET['todo'];
    }

    if (isset($datos->edad)) {
        $edad = $mysqli->real_escape_string($datos->edad);
    } else if (isset($_GET['edad'])) {
        $edad = $mysqli->real_escape_string($_GET['edad']);
    }

    /* echo "id:".$identificacion. "<br>";
echo "nom:".$nombres. "<br>";
echo "fec:".$fecha. "<br>";
echo "tab:".$tabla. "<br>";
echo "emb:".$embedido. "<br>";
echo "info:".$info. "<hr>";
 */

    $sqlc = "SELECT * from configuracion order by id desc limit 1";
    $stmtConf=$mysqli->prepare($sqlc);
    $stmtConf->execute();
    $result = $stmtConf->get_result();
    if ($result->num_rows > 0) {
        $dato              = $result->fetch_assoc();
        $nombrelab         = $dato['nombreCorto'];
        $nombrelaboratorio = $dato['nombreLaboratorio'];
    }
    $stmtConf->close();

    if (! isset($embedido) && ! isset($todo)) {
        $stmtMain=$mysqli->prepare("Select examenes.identificacion,concat_ws(' ',paciente.apellidos,paciente.nombres) as nombres,edad,correo,telefono from examenes inner join paciente on examenes.identificacion=paciente.identificacion inner join procedimientos on examenes.codexamen=procedimientos.codigo where examenes.fecha=? group by examenes.identificacion order by nombres");
        $stmtMain->bind_param("s",$fecha);
    } else if (isset($embedido) || isset($todo)) {
        $stmtMain=$mysqli->prepare("Select examenes.identificacion,concat_ws(' ',paciente.apellidos,paciente.nombres) as nombres,edad,'a' as orden,correo,telefono from examenes inner join paciente on examenes.identificacion=paciente.identificacion inner join procedimientos on examenes.codexamen=procedimientos.codigo where examenes.fecha=? and paciente.identificacion=? group by examenes.identificacion UNION Select examenes.identificacion,concat_ws(' ',paciente.apellidos,paciente.nombres) as nombres,edad,'b' as orden,correo,telefono from examenes inner join paciente on examenes.identificacion=paciente.identificacion inner join procedimientos on examenes.codexamen=procedimientos.codigo where examenes.fecha=? and paciente.identificacion!=? group by examenes.identificacion order by orden,nombres");
        $stmtMain->bind_param("ssss",$fecha,$identificacion,$fecha,$identificacion);
    }
    $stmtMain->execute();
    $resultados = $stmtMain->get_result();

    function generateRandomString($length = 180)
    {
        $characters   = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $randomString;
    }
    function generateTable($identificacion, $fecha, $nombres, $edad)
    {
        $html = "<table class='table table-striped table-hover w-100 table-sm'>
            <thead>
                <tr>
                    <th>Tipo de Examen</th>
                    <th class='text-center'></th>
                    <th class='text-center'></th>
                    <th class='text-center'></th>
                    <th class='text-center'></th>
                </tr>
            </thead>
            <tbody>";
        $mysqli2 = $GLOBALS['mysqli'];
        $stmt=$mysqli2->prepare("Select procedimientos.*,concat_ws(' ',paciente.nombres,paciente.apellidos) as nombres,fecnac,genero,edad,telefono,correo,examenes.identificacion,realizado from examenes inner join procedimientos on examenes.codexamen=procedimientos.codigo inner join paciente on examenes.identificacion=paciente.identificacion where examenes.identificacion=? and examenes.fecha=? order by nombre");
        $stmt->bind_param("ss",$identificacion,$fecha);
        $stmt->execute();
        $resultados = $stmt->get_result();
        $i          = 1;
        while ($dato = $resultados->fetch_assoc()) {
            $idx          = generateRandomString();
            $nombreExamen = $dato['nombre'];
            $tablae       = urlencode($dato['tabla']);
            $tablae == "" ? $tablae : "tipo";
            $infoi     = urlencode($dato['info']);
            $nombresc  = urlencode($nombres);
            $tipoe     = $dato['tipo'];
            $codexamen = $dato['codigo'];
            $query     = "idx=$idx&identificacion=$identificacion&fecha=$fecha&nombres=$nombresc&tabla=$tablae&info=$infoi&tipo=$tipoe&codexamen=$codexamen&edad=$edad";
            $querye    = "identificacion=$identificacion&fecha=$fecha&nombres=$nombresc&tabla=$tablae&info=$infoi&tipo=$tipoe&codexamen=$codexamen&edad=$edad";
            $embedidoi = '&embedido=1';
            $server    = $GLOBALS['server'];
            $script    = "$server$query" . $embedidoi;
            $print     = $GLOBALS['print'];
            $uri       = "$print$query";
            $datax     = json_encode($dato);
            $telefono  = $dato['telefono'];
            $urie      = urlencode($print . $querye . "&ver=1");

            $realizado = $dato['realizado'];
            $style     = 'color:orange;';
            $img       = '';
            if ($realizado == 'S') {
                $style = "color:yellow;";
                $img   = "<img src='check.png' alt='' width=25>";
            }
            $html .= "<tr>
                                    <td class='align-middle'>
                                       <span style='color:yellow;font-weight:bold;'>$i</span> <span style='color:lightgreen;'>&rrarr;</span> <span class='fs-7 position-relative' style='$style'>
                                       $nombreExamen
                                       <span class='position-absolute'>
                                         $img
                                       </span>
                                       </span>
                                    </td>
                                    <td class='text-center align-middle'>
                                        <a class='btn btn-danger' href='$script' title='Ver resultados en línea'>
                                        <i class='bi bi-eye-fill'></i>
                                        </a>
                                    </td>
                                    <td class='text-center align-middle'>
                                        <a class='btn btn-light' href='$uri' title='Descargar Resultado' >
                                        <i class='bi bi-download'></i>
                                        </a>
                                    </td>
                                    <td class='text-center align-middle'>
                                        <a class='btn btn-info email' data-data='$datax' title='Enviar al Correo' >
                                        <i class='bi bi-send-check'></i>
                                        </a>
                                    </td>
                                    <td class='text-center align-middle'>
                                    <a class='btn btn-success whatsapp' href='https://wa.me/$telefono?text=\"$urie\"' title='Enviar al Whatsapp $telefono $infoi' target='_blank'>
                                    <i class='bi bi-whatsapp'></i>
                                    </a>
                                </td>
                                 </tr>
                         ";
            $i++;

        }
        $html .= "   </tbody>
                    </table>";
        return $html;

    }

?>

 <!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $nombrelaboratorio ?></title>
    <meta name="theme-color" content="#64FC73">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Resultados Laboratorio</title>
    <meta name="description" content="Visualización y entrega de Exámenes de Laboratorio">
    <meta name="author" content="ceslep@gmail.com">

    <meta property="og:title" content="Laboratorio">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://laboratorio.iedeoccidente.com/">

    <meta property="og:description" content="Resultados exámenes de laboratorio clínico">
    <meta property="og:image" content="./printphp/logo.png">
    <link rel="stylesheet" href="./bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js"></script>
<style>
    body{
        padding: auto;
       height: 50vh;
    }

    .dgridPrincipal{
        display:grid;
        grid-template-columns: 1.1fr 1.5fr ;

    }

    @media (max-width: 1288px) {
        body{

        }
  .dgridPrincipal {
    height:50vh;
    grid-template-columns: auto;
    grid-template-rows: auto; /* Adjust for smaller screens */
  }

  .tm{
    width:165px;
    height:75px;
    min-width: 165px;
    max-width: 165px;
    min-height: 75px;
    max-height: 75px;
}


}


     .dgrid{
            display: grid;
            align-items: center;
            justify-content: center;
            grid-template-columns: 2fr 1fr;
            grid-template-rows: auto auto auto;
            grid-template-areas:
            "a b";}


        .a{
            grid-area: a;
        }
        .b{
            grid-area: b;
            justify-self: center;
            align-self: center;

        }

.fs-7{
    font-size:0.75rem;
}

.exadiv{
    height:100dvh;
    overflow-y: scroll;
}

.accordion-dark{
    background-color:#333!important;
}

.tm{
    width:195px;
    height:50px;
    min-width: 245px;
    max-width: 245px;
    min-height: 50px;
    max-height: 50px;
}


.text-lightgreen{
    color:green!important;
}

.text-lightyellow{
    color:yellow!important;
}

.text-yellow{
    color:yellow!important;
}

.mask{
     filter: invert(100%); /* Aumenta contraste y brillo */

}
    </style>

  </head>

  <body>
  <nav class="navbar navbar-expand-lg  bg-dark" data-bs-theme="dark">
  <div class="container-fluid">
    <img class="mask" src="./printphp/logo.png" alt="logo" width="3%">
    <a class="navbar-brand" href="#"><?php echo $nombrelab ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
      <ul class="navbar-nav">

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Opciones</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" id="abrirRelaEx">Relación de exámenes Tomados</a></li>
            <li><a class="dropdown-item" href="#" id="abrirRelaExEnt">Relación de exámenes Tomados por entidad</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#!" id="buscarResultados">Buscar <i class="bi bi-search" style='color:lime;'></i></a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Correos
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">Enviar correos de hoy</a></li>
            <li><a class="dropdown-item" href="#">Enviar correos de otras fechas</a></li>
            <li><a class="dropdown-item" href="#">Enviar por whatsapp</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
    <div class="dgridPrincipal mx-auto">
        <div  class="container exadiv">
        <div class="row">
                <div class="col-12 sol-sm-6">
                   <h3><a href="./listaExamenes.php">Resultados Examenes</a></h3>
                </div>
                <div class="col-12 sol-sm-6">
                    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="row">
                         <div class="col-8">
                         <input type="date" name="fecha" value='<?php echo $fecha ?>' class="form-control">
                         </div>
                        <div class="col-2">
                            <button type="submit" class="btn btn-primary rounded-0">Ir</button>
                        </div>
                        <div class="col-2">
                        <button title="Buscar" class="btn btn-warning rounded-0" id="btnsearchc"><i class="bi bi-search "></i></button>
                        </div>
                    </div>
                </form>

                </div>
        </div>
        <?php
            if (isset($embedido) || isset($todo)) {
                echo "Visualización Actual";
                echo "<h3 class='text-success'>$nombres</h3>";
                echo "<h4 class='text-danger'>$info</h4>";
            }
        ?>

        <h4 class="pt-2 mx-auto">Lista de Pacientes</h4>
        <hr>
        <div
            class="container pt-2 "
        >
            <div class="accordion accordion-flush" id="examenes">
                <?php
                    $i = 0;
                    while ($dato = $resultados->fetch_assoc()) {
                        $identificacione = $dato['identificacion'];
                        $nombrese        = $dato['nombres'];
                        $edade           = $dato['edad'];
                        $telefono        = $dato['telefono'];
                        $fechae          = $GLOBALS['fecha'];
                        $colorFondo      = ($i % 2 == 0) ? 'bg-light' : 'bg-white';
                        $tablaex         = generateTable($identificacione, $fechae, $nombrese, $edade);
                        $sh              = "";
                        if ((isset($embedido) || isset($todo)) && $identificacion == $identificacione) {
                            $sh = "show";
                        }
                        $idx        = generateRandomString();
                        $query      = "idx=$idx&identificacion=$identificacione&fecha=$fechae&nombres=$nombrese&edad=$edade&info=Todos los Resultados";
                        $datax      = json_encode($dato);
                        $script     = $server . $query . "&ver=1&todo=1";
                        $queryeTodo = "identificacion=$identificacione&fecha=$fechae&nombres=$nombrese&edad=$edade&info=Todos%20los%20Resultados&ver=1&todo=1";
                        $urieTodo   = urlencode($printTodo . $queryeTodo . "&ver=1");
                        $scriptd    = $printTodo . $query;
                        $html       = "
                        <div class='accordion-item'>
                        <h2 class='accordion-header accordion-dark' style='background-color:black;'>
                            <button class='accordion-button collapsed $colorFondo' type='button' data-bs-toggle='collapse' data-bs-target='#collapse$i'          aria-expanded='false' aria-controls='collapse$i'>
                                <span class='fw-bold fs-7 text-dark'>$nombrese</span><span class='mx-2 text-success fs-7 d-none'> $identificacione</span>
                            </button>
                            </h2>
                            <div id='collapse$i' class='accordion-collapse collapse $sh' data-bs-parent='#examenes'>
                            <div class='accordion-body'>
                            <div class='d-flex flex-column gap-3'>
                            <div class='w-100 w-sm-75 w-md-50 w-lg-25 '>
                                <div class='text-light'>Identificacion:</div>
                                <div class='text-secondary'>$identificacione</div>
                                <div class='text-light'>Nombres:</div>
                                <div class='text-secondary'> $nombrese </div>
                                <div class='text-light'>Fecha:</div>
                                <div class='text-secondary'> $fechae </div>
                                <div class='text-light'>Edad:</div>
                                <div class='text-secondary'> $edade </div>

                            </div>
                            <div class='row text-center'>
                                <div class='col-12 col-sm-6 pt-2 d-flex justify-content-center align-content-center gap-2 '>
                                <a
                                href='$script'
                                class='btn btn-warning mx-10 d-block rounded-0 tm imprimirTodo'>Imprimir todo <i class='bi bi-printer-fill'></i></a>
                                </div>

                                <div class='col-12 col-sm-6 pt-2  d-flex justify-content-center align-content-center gap-2 '>
                                <a href='$scriptd' class='btn btn-secondary mx-10 d-block rounded-0 tm descargarTodo'>Descargar todo <i class='bi bi-download'></i></a>
                                </div>

                                <div class='col-12 col-sm-6 pt-2 d-flex justify-content-center align-content-center gap-2 '>
                                <a href='#!' class='btn btn-info mx-10 d-block rounded-0 emailTodo tm' data-datax='$datax'>Enviar al correo <i class='bi bi-send'></i></a>
                                </div>
                                <div class='col-12 col-sm-6 pt-2 d-flex justify-content-center align-content-center gap-2'>
                                <a href='https://wa.me/$telefono?text=\"$urieTodo\"' class='btn btn-success mx-10 d-block rounded-0 whatsappTodo tm'>
                                <span>&#8594;</span> WhatsApp <i class='bi bi-whatsapp'></i></a>
                                </div>
                            </div>
                            $tablaex
                        </div>

                            </div>
                        </div>
                        </div>
                        ";
                        $i++;
                        echo $html;
                    }
                ?>
            </div>
        </div>
    </div>
    <div class="d-block">
        <?php
            if (isset($embedido)) {
                $url = "./printphp/print_examen.php?";
                $url = $print . "identificacion=$identificacion&fecha=$fecha&nombres=$nombres&tabla=$tabla&info=$info&ver=1&edad=$edad";
                //echo $url;
                echo "<iframe src='$url' width='100%' style='min-height:100vh;' frameborder='0'>

                      </iframe>";

            } else if (isset($todo)) {
                $printTodo = "./printphp/imprimirTodo.php?";
                $url       = $printTodo . "identificacion=$identificacion&fecha=$fecha&nombres=$nombres&edad=$edad&info=Resultados&ver=1";
                //echo $url;
                echo "<iframe src='$url' width='100%' style='min-height:100vh;' frameborder='0'>

                      </iframe>";
            }
        ?>
        </div>
    </div>

    <div class="modal" tabindex="-1" id="modalBusqueda" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Busqueda de Resultados</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <section class="modal-body">
          <form id="frmBusqueda">

            <input type="search" name="criterio" class="form-control" placeholder="Identificación o nombres" autofocus required minlength="4">
            <div id="resultadosBusqueda" >
            <!-- style="overflow-y:scroll;height:35dvh;" -->

            </div>
        </form>
      </section>
      <footer class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-warning" id="btnBusqueda">Buscar <div class="spinner-border spinner-border-sm d-none" role="status">
  <span class="visually-hidden">Loading...</span>
</div></button>
      </footer>
    </div>
  </div>
</div>

<div class="modal" tabindex="-1" id="modalEmail" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enviar al Correo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <section class="modal-body">
          <form id="frmEmail">
            <input type="hidden" name='tipo'>
            <input type="hidden" name='identificacion'>
            <input type="text" name="nombres" class="form-control" readonly>
            <input type="email" name="correo" class="form-control" placeholder="Correo del Paciente" autofocus required minlength="4" readonly>
            <input type="text" name="info" class="form-control" readonly>
            <input type='hidden' name='content'>
        </form>
      </section>
      <footer class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-warning" id="btnEnviarEmail">Enviar <div class="spinner-border spinner-border-sm d-none" role="status">
  <span class="visually-hidden">Loading...</span>
</div></button>
      </footer>
    </div>
  </div>
</div>

<div class="modal" tabindex="-1" id="modalRelacionExamenes" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-scrollable modal-xl modal-fullscreen-lg-down">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Relación de Exámenes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <section class="modal-body">
          <form id="frmRelaEx">
          <div class="row">
                         <div class="col-6">
                                   <label>Entre el
                                  <input type="date" name="fecha1" value='<?php echo $fecha ?>' class="form-control">
                                  </label>
                         </div>
                         <div class="col-6">
                            <label>
                                   Y el
                                  <input type="date" name="fecha2" value='<?php echo $fecha ?>' class="form-control">
                                  </label>
                         </div>
           </div>
           <div id="resultadosRelaEx" ></div>
        </form>
      </section>
      <footer class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="submit" class="btn btn-warning" id="btnRelaEx">Buscar
            <div class="d-none spinner-border spinner-border-sm" role="status">
  <span class="visually-hidden">Loading...</span>
</div>
            </button>
      </footer>
    </div>
  </div>
</div>



<!-- Modal -->
<div
    class="modal fade"
    id="modalEntidades"
    tabindex="-1"
    role="dialog"
    aria-labelledby="modalTitleId"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-fullscreen-lg-down" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitleId">
                    Relación por entidad
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="repentidad" class="form-label">Entidad</label>
                    <select name="entidad" id="repentidad" class="form-select">

                    </select>
                </div>
                <div class="row">
                         <div class="col-6">
                                   <label>Entre el
                                  <input id="relfecha1" type="date" name="fecha1" value='<?php echo $fecha ?>' class="form-control">
                                  </label>
                         </div>
                         <div class="col-6">
                            <label>
                                   Y el
                                  <input id="relfecha2" type="date" name="fecha2" value='<?php echo $fecha ?>' class="form-control">
                                  </label>
                         </div>
           </div>

           <div id="resultentidades"></div>


            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Cerrar
                </button>
                <button type="button" class="btn btn-success " id="btnbrelent"> Buscar
                    <div id="spnrelaex"
                        class="d-flex justify-content-center align-items-center d-none"
                    >
                        <div
                            class="spinner-border text-primary spinner-border-sm"
                            role="status"
                        >
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    

                </button>
                <button type="button" class="btn btn-info " id="btnbrelentdesc"> Descargar</button>
            </div>
        </div>
    </div>
</div>




  </body>
  <script>
 const modalBusquedaEl=document.getElementById("modalBusqueda");
 let modalBusqueda;

function searchForTextInArticle(div, text) {
    let items = div.querySelectorAll(".accordion-item");
    for(let item of items){

        const dato=item.children[0].textContent.trim();
        if (!dato.includes(text)) item.classList.add('d-none')
    }


}
        document.getElementById("btnsearchc").addEventListener("click", async (e) => {
    e.preventDefault();
    const result = await Swal.fire({
        title: "Qué desea buscar",
        input: "text",
        showCancelButton: true,
        cancelButtonText: "Cancelar",
    });
    if (result.isConfirmed) {
        console.log(result.value);
        searchForTextInArticle(
            document.getElementById("examenes"),
            result.value.toUpperCase()
        );
    }
});

const elBusqueda = document.querySelector("[name='criterio']");

document.getElementById("buscarResultados").addEventListener("click", (e)=>{
    e.preventDefault();
    if(!modalBusqueda)modalBusqueda=new bootstrap.Modal(modalBusquedaEl);
    modalBusqueda.show();

});


modalBusquedaEl.addEventListener('shown.bs.modal', event => {
        document.getElementById("resultadosBusqueda").innerHTML="";
     //   document.getElementById("resultadosBusqueda").classList.add("d-none");
     setTimeout(()=>{
        elBusqueda.focus();
        console.log(".......");
     },500)
})


const generarResultados = (resultados)=>{
    const spn=`<div class="spinner-border spinner-border-sm d-none" role="status">
  <span class="visually-hidden">Loading...</span>
</div>`;
    let html=`<table class='table table-bordered table-hover'>
    <thead class="bg-primary text-white">
      <tr>
          <th scope="col"  >Identificación</th>
          <th scope="col" >Nombres</th>
          <th scope="col" >Edad</th>
          <th scope="col" class="text-center">Ver</th>
      </tr>
      </thead>
      <tbody>
    `;
    resultados.forEach((value, index, array) => {
        const {identificacion,nombres,edad} =value;
        html+=`
            <tr>
                <td class="align-middle text-center">
                    ${identificacion}
                </td>
                <td class="align-middle">
                    ${nombres}
                </td>
                <td class="align-middle text-center">
                    ${edad}
                </td>
                <td class="align-middle text-center">
                    <button class="btn btn-sm btn-danger btnEx" data-paciente='${JSON.stringify(value)}'><i class="bi bi-bullseye"></i>${spn}</button>
                </td>
            </tr>
        `;
    });
    html+=`
        </tbody>
        </table>
    `;
    return html;
}

document.getElementById('btnBusqueda').addEventListener('click',async e=>{
    e.preventDefault();

    const spn=e.target.children[0];
    const frm=document.getElementById("frmBusqueda");
    if (!spn.classList.contains('spinner-border-sm')) return;
    spn.classList.toggle("d-none");
    console.log(spn);
    const response = await fetch("busquedaExamenes.php",{
        method:"POST",
        body:JSON.stringify(Object.fromEntries(new FormData(frm))),
    }
        );
        const {msg,data}=await response.json();
        if(msg){
            console.log(data);

        spn.classList.toggle("d-none");

        document.getElementById("resultadosBusqueda").innerHTML=await generarResultados(data);
        }
     //   document.getElementById("resultadosBusqueda").remove("d-none");
},);


function generateRandomString(length = 180) {
  // Ensure length is a positive integer
  if (typeof length !== 'number' || length <= 0) {
    throw new TypeError('length must be a positive integer');
  }

  const characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  let randomString = '';

  // Use cryptographically secure random number generation for better security
  const crypto = window.crypto || window.msCrypto; // Support for different browsers
  const randomValues = new Uint32Array(length);
  crypto.getRandomValues(randomValues);

  for (let i = 0; i < length; i++) {
    // Efficiently select a random character using bitwise AND and modulus
    const index = randomValues[i] & (characters.length - 1);
    randomString += characters[index];
  }

  return randomString;
}


const generarResultadosExin = (resultados,nombres,edad)=>{
    const spn=`<div class="spinner-border spinner-border-sm d-none" role="status">
  <span class="visually-hidden">Loading...</span>
</div>`;
    let html=`<table class='table table-bordered table-hover table-striped'>
    <thead class="bg-primary text-white">
      <tr>
          <th scope="col"  >Exámen</th>
          <th scope="col" >Fecha</th>
          <th scope="col" class="text-center">Ver</th>
      </tr>
      </thead>
      <tbody>
    `;
    resultados.forEach((value, index, array) => {
        const {codexamen,examen,fecha,identificacion,tabla,info,tipo} =value;
        html+=`
            <tr>
                <td class="align-middle">
                    ${examen}
                </td>
                <td class="align-middle text-center">
                    ${fecha}
                </td>
                <td class="align-middle text-center">
                    <a
                    href='./listaExamenes.php?idx=${generateRandomString()}&identificacion=${identificacion}&fecha=${fecha}&nombres=${nombres}&tabla=${tabla}&info=${info}&tipo=${tipo}&codexamen=${codexamen}&edad=${edad}&embedido=1'
                    class="btn btn-sm btn-info btnAx" data-paciente='${JSON.stringify(value)}'><i class="bi bi-eye-fill"></i>${spn}</a>
                </td>

            </tr>

        `;
    });
    html+=`
        </tbody>
        </table>
    `;
    return html;
}

document.getElementById("resultadosBusqueda").addEventListener('click', async e=>{
    e.preventDefault();
    let button=e.target.closest("button");
    if(!button) button=e.target.closest("a");
    console.log(button);
    if (button.classList.contains("btnEx")){
        const icon=button.children[0];
        const spn=button.children[1];
        spn.classList.toggle("d-none");
        icon.classList.toggle("d-none");
        const {identificacion,nombres,edad}=JSON.parse(button.dataset.paciente);
        const response = await fetch("getExamenesPaciente.php",{
            method:"POST",
            body:JSON.stringify({criterio:identificacion}),
        });
        const data=await response.json();
        console.log(data);
        spn.classList.toggle("d-none");
        icon.classList.toggle("d-none");
        document.getElementById("resultadosBusqueda").innerHTML=await generarResultadosExin(data,nombres,edad);
    }else
    if(button.classList.contains("btnAx")){
        const icon=button.children[0];
        const spn=button.children[1];
        spn.classList.toggle("d-none");
        icon.classList.toggle("d-none");
        const href=button.getAttribute("href");
        document.location=href;
    }
});


const modalEmailEl=document.getElementById("modalEmail");
let modalEmail;


document.getElementById("examenes").addEventListener("click",e=>{

    let button=e.target.closest("button");
    if (!button)
    button=e.target.closest("a");
    if(button.classList.contains("email")){
        e.preventDefault();
        const {identificacion,correo,nombres,nombre,info}=JSON.parse(button.dataset.data);
        if(!modalEmail)modalEmail=new bootstrap.Modal(modalEmailEl);
        const formEmail=document.getElementById("frmEmail");
        formEmail.querySelector("[name='identificacion']").value=identificacion;
        formEmail.querySelector("[name='nombres']").value=nombres;
        formEmail.querySelector("[name='correo']").value=correo;
        formEmail.querySelector("[name='info']").value=nombre;

        let urlParams = new URLSearchParams(window.location.search);

        const params = urlParams.toString().replace(/^.*?(identificacion=)/, "");

        console.log(params);
        formEmail.querySelector("[name='content']").value=`https://${location.hostname}/printphp/print_examen.php?identificacion=${params}&ver=1`;
        modalEmail.show();
    }else if (button.classList.contains("whatsapp")){
        e.preventDefault();
        const href = button.getAttribute('href');
        let shref=href.substring(href.indexOf("=")+1,href.length);
        console.log(shref);
        navigator.clipboard.writeText(decodeURIComponent(shref))
        window.open(href,"_blank");

    }if(button.classList.contains("emailTodo")){
        e.preventDefault();
        const {identificacion,correo,nombres,nombre,info}=JSON.parse(button.dataset.datax);
        if(!modalEmail)modalEmail=new bootstrap.Modal(modalEmailEl);
        const formEmail=document.getElementById("frmEmail");
        formEmail.querySelector("[name='identificacion']").value=identificacion;
        formEmail.querySelector("[name='nombres']").value=nombres;
        formEmail.querySelector("[name='correo']").value=correo;
        formEmail.querySelector("[name='info']").value='Todos los Resultados';

        let urlParams = new URLSearchParams(window.location.search);

        const params = urlParams.toString().replace(/^.*?(identificacion=)/, "");

        console.log(params);
        formEmail.querySelector("[name='content']").value=`https://${location.hostname}/printphp/imprimirTodo.php?identificacion=${params}&ver=1`;
        modalEmail.show();
    }if(button.classList.contains("imprimirTodo")){
        e.preventDefault();
        const href = button.getAttribute('href');
        let shref=href.substring(href.indexOf("=")+1,href.length);
        console.log(shref);
        navigator.clipboard.writeText(decodeURIComponent(shref))
        window.open(href,"_self");
    }
    if(button.classList.contains("descargarTodo")){
        e.preventDefault();
        const href = button.getAttribute('href');
        let shref=href.substring(href.indexOf("=")+1,href.length);
        console.log(shref);
        navigator.clipboard.writeText(decodeURIComponent(shref))
        window.open(href,"_self");
    }
    if (button.classList.contains("whatsappTodo")){
        e.preventDefault();
        const href = button.getAttribute('href');
        let shref=href.substring(href.indexOf("=")+1,href.length);
        console.log(shref);
        navigator.clipboard.writeText(decodeURIComponent(shref))
        window.open(href,"_blank");

    }
});

document.getElementById("btnEnviarEmail").addEventListener('click', async e=>{
    e.preventDefault();
    const form = document.getElementById("frmEmail");
    const data = Object.fromEntries(new FormData(form));
    const spn=e.target.children[0];
    spn.classList.toggle("d-none");
    const response = await fetch("enviarCorreo.php",{
        method: 'POST',
        body:JSON.stringify(data),
        headers:{
            'Content-Type': 'application/json'
        }
        });
    const {msg} = await response.json();
    spn.classList.toggle("d-none");
    if(msg!='error'){
        Swal.fire({
            icon: 'success',
            title: msg,
            })
        }
});


const modalRelacionExamenesEl=document.getElementById("modalRelacionExamenes");
let modalRelacionExamenes;




document.getElementById("abrirRelaEx").addEventListener('click',e=>{
    e.preventDefault();
    if(!modalRelacionExamenes) modalRelacionExamenes=new bootstrap.Modal(modalRelacionExamenesEl);
    modalRelacionExamenes.show();

});


const generaDataRel = (dataExamen,datosPacientes,fechas)=>{
    const datos=Object.groupBy(datosPacientes,({genero})=>genero);
    let html=`
            <table class="table table-hover table-striped table-bordered table-primary">
            `;
    Object.keys(datos).forEach((key, index, array) => {
        html+=`
            <tr>
                <td class="align-middle text-lightyellow">
                    ${key}
                </td>
                <td class="text-center align-middle text-lightgreen">
                ${datos[key].length}
                </td>
                <td class="text-center align-middle">
                    <button class="btn btn-info binRelax" data-examen='${JSON.stringify(dataExamen)}' data-fechas='${JSON.stringify(fechas)}' data-genero='${JSON.stringify({genero:key})}'><i class='bi bi-eye-fill'>

                    </i>
                    <div class="spinner-border spinner-border-sm d-none" role="status">
  <span class="visually-hidden">Loading...</span>
</div>
                    </button>
                </td>
            </tr>
        `;
    });
    html+="</table>";
    return html;

}

document.getElementById("btnRelaEx").addEventListener('click',async e=>{
    e.preventDefault();
    const spn=e.target.children[0];
    spn.classList.toggle("d-none");
    const form=document.getElementById("frmRelaEx");
    const fechas=Object.fromEntries(new FormData(form));
    const response = await fetch("getRelaEx.php",{
        method:"POST",
        body:JSON.stringify(fechas)
    });
    const datosRelacionEx= await response.json();
    let html= `
    <div class="accordion mt-2" id="accordionRelaEx">
    `;
    datosRelacionEx.forEach((item,index)=>{
        const {codigo,nombre,cantidad}=item.dataExamen;
        const {datosPacientes}=item;
        html+=`
        <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${index}" aria-expanded="true" aria-controls="collapse${index}">
        <div class="row w-100">
            <div class="col-10 text-lightgreen">
            ${nombre}
            </div>
            <div class="col-2 text-lightyellow text-center">
            <span class="badge text-bg-warning">${cantidad}</span>
            </div>
        </div>
      </button>
    </h2>
    <div id="collapse${index}" class="accordion-collapse collapse" data-bs-parent="#accordionRelaEx">
      <div class="accordion-body">
        ${generaDataRel(item.dataExamen,datosPacientes,fechas)}
      </div>
    </div>
  </div>
        `;
    });
    html+=`</div>`;
    document.getElementById("resultadosRelaEx").innerHTML=html;
    spn.classList.toggle("d-none");
})


document.getElementById("resultadosRelaEx").addEventListener('click',async e=>{
    e.preventDefault();
    let element=e.target.closest("button");
    if (!element)element=e.target.closest("a");
    if(element.classList.contains("binRelax")){
        const icon=element.children[0];
        const spinner=element.children[1];
        icon.classList.toggle("d-none");
        spinner.classList.toggle("d-none");
        const dataExamen=JSON.parse(element.dataset.examen);
        const fechas=JSON.parse(element.dataset.fechas);
        const genero=JSON.parse(element.dataset.genero);
        const datar={...dataExamen,...fechas,...genero};
        const response = await fetch("getExamenesC.php",{
            method:"POST",
            body:JSON.stringify(datar)
        });
        const datos=await response.json();
        let html=`<table class="table table-bordered table-striped">
                    <thead>
                        <tr class="text-lightgreen">
                            <th class="align-middle text-center">
                                Fecha
                            </th>
                            <th class="align-middle text-center">
                                Nombres
                            </th>
                            <th class="align-middle text-center">
                                Exámen
                            </th>
                            <th class="align-middle text-center">
                                Edad
                            </th>
                        </tr>
                    </thead>
        <tbody>`;
        datos.forEach((value, index, array) => {
            const {fecha,nombres,edad,nombre,identificacion,tabla,info,tipo,codexamen}=value;
            href=`./listaExamenes.php?identificacion=${identificacion}&fecha=${fecha}&nombres=${nombres}&tabla=${tabla}&info=${info}&tipo=${tipo}&codexamen=${codexamen}&edad=${edad}&embedido=1`;
            html+=`
                <tr>
                    <td class="align-middle text-center">
                        ${fecha}
                    </td>
                    <td class="align-middle text-yellow">
                       <a href="#!" class=""> ${nombres}</a>
                    </td>
                    <td class="align-middle text-center">
                    <a href="${href}" class="btn btn-sm btn-outline-info viexrel">
                       <span>${nombre}</span>
                       <div class="spinner-border spinner-border-sm d-none" role="status">
  <span class="visually-hidden">Loading...</span>
</div>
                    </a>
                    </td>
                    <td class="align-middle text-center">
                        ${edad}
                    </td>
                </tr>
            `;
        });
        html+=`
            </tbody>
            </table>
        `;
        icon.classList.toggle("d-none");
        spinner.classList.toggle("d-none");
        const pe=element.closest("table").parentElement;
        if(pe.children.length==1){
         const div=document.createElement("div");
         div.innerHTML=html;
         pe.appendChild(div);
        }else{
            pe.children[1].innerHTML=html;
        }
    }else if (element.classList.contains("viexrel")){
        const icon=element.children[0];
        const spn=element.children[1];
        spn.classList.toggle("d-none");
        icon.classList.toggle("d-none");
        const href=element.getAttribute("href");
        document.location=href;
    }
})


const modalEntidadesEl=document.getElementById("modalEntidades");
let modalEntidades;

document.getElementById("abrirRelaExEnt").addEventListener('click', async (e)=>{
    e.preventDefault();
    if (!modalEntidades) modalEntidades = new bootstrap.Modal(modalEntidadesEl);
    const response=await fetch("getEntidades.php")
    const {msg,data} = await response.json();
    if (msg){
        let html='<option value="" selected disabled>Seleccione la entidad</option>';
        if (data.length){
            data.forEach(({entidad})=>{
                html+=`
                    <option value=${entidad}>${entidad}</option>
                `;
            });
            document.getElementById("repentidad").innerHTML=html;
            modalEntidades.show();
        }
    }
})






document.getElementById("btnbrelent").addEventListener('click',async e=>{
    e.preventDefault();
    document.getElementById("resultentidades").innerHTML='';
    const rfecha1=document.getElementById("relfecha1").value;
    const rfecha2=document.getElementById("relfecha2").value;
    const rentidad=document.getElementById("repentidad").value;
    document.getElementById("spnrelaex").classList.toggle("d-none");
    const response= await fetch("relaexentidad.php",{
        body:JSON.stringify({rfecha1,rfecha2,rentidad}),
        method:"POST"
    });
    const datos=await response.json();
    let html='';
    if(datos.length){
            html+=`
                <div
                     class="table-responsive p-2">
                    <table id="tablarelenti"
                        class="table table-striped table-hover table-borderless table-primary align-middle">
                        <thead class="table-light">
            <tr>
                <th>N°</th>
                <th>Entidad</th>
                <th>Identificación</th>
                <th>Nombres</th>
                 <th>Genero</th>
                  <th>Edad</th>
                <th>Exámen</th>
                <th>Valoración</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody class="table-group-divider">
            `
    
    datos.forEach(({entidad,identificacion,nombres,genero,edad,examen,valoracion,fecha},index)=>{
        html+=`
         <tr>
                <td>${index+1}</td>
                <td>${entidad}</td>
                <td>${identificacion}</td>
                <td>${nombres}</td>
                <td>${genero}</td>
                <td>${edad}</td>
                <td>${examen}</td>
                <td>${valoracion??''}</td>
                <td>${fecha}</td>
            </tr>
        
        `
    });
    html+=`
        </tbody>
        </table>
        </div>
    
    `;
}
    document.getElementById("resultentidades").innerHTML=html;
    document.getElementById("spnrelaex").classList.toggle("d-none");
})


function descargarTablaEnExcel(idTabla) {
        const tabla = document.getElementById(idTabla);

        // Obtenemos el HTML de la tabla
        const tablaHTML = tabla.outerHTML;

        // Incrustamos un meta tag para especificar la codificación UTF-8
        // para que Excel lo interprete correctamente.
        const metaTag = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';

        // Concatenamos el meta tag al inicio del HTML
        const htmlCompleto = metaTag + tablaHTML;

        // Convertimos a Base64 (primero escapamos con encodeURIComponent,
        // luego "unescape" para que btoa no falle con caracteres extraños)
        const contenidoBase64 = btoa(unescape(encodeURIComponent(htmlCompleto)));

        // Generamos un nombre de archivo "reporte" con marca de tiempo
        const fecha = new Date();
        const anio = fecha.getFullYear();
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia = String(fecha.getDate()).padStart(2, '0');
        const horas = String(fecha.getHours()).padStart(2, '0');
        const minutos = String(fecha.getMinutes()).padStart(2, '0');
        const segundos = String(fecha.getSeconds()).padStart(2, '0');
        const nombreArchivo = `reporte_${anio}-${mes}-${dia}_${horas}-${minutos}-${segundos}.xls`;

        // Armamos la URL de descarga con el tipo vnd.ms-excel + base64 + UTF-8
        const enlaceDescarga = document.createElement('a');
        enlaceDescarga.href = `data:application/vnd.ms-excel;base64,${contenidoBase64}`;
        enlaceDescarga.download = nombreArchivo;

        // Disparamos la descarga
        document.body.appendChild(enlaceDescarga);
        enlaceDescarga.click();
        document.body.removeChild(enlaceDescarga);
    }



document.getElementById("btnbrelentdesc").addEventListener('click',e=>{
    e.preventDefault();
    descargarTablaEnExcel("tablarelenti")

});

        </script>


</html>
