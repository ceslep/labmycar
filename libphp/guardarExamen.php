<?php
require_once("datos_conexion.php");
require_once('api_guard.php');
exigirToken();

$tabla = $mysqli->real_escape_string($datos->tabla);

// Protección normativa: si el examen ya está marcado como realizado (resultado
// emitido), no se permite guardar ni reemplazar sus resultados.
$codexamenGuardar = $datos->codexamen ?? '';
if (!empty($codexamenGuardar)) {
    $stmtChk = $mysqli->prepare("SELECT realizado FROM examenes WHERE identificacion=? AND fecha=? AND codexamen=? LIMIT 1");
    if ($stmtChk) {
        $stmtChk->bind_param("sss", $datos->identificacion ?? '', $datos->fecha ?? '', $codexamenGuardar);
        $stmtChk->execute();
        $resChk = $stmtChk->get_result();
        if ($resChk->num_rows > 0) {
            $realizado = $resChk->fetch_assoc()['realizado'];
            if ($realizado === 'S') {
                echo json_encode(["msg" => false, "error" => "Resultado ya realizado. Por normativa no es modificable."]);
                $stmtChk->close();
                $mysqli->close();
                exit;
            }
        }
        $stmtChk->close();
    }
}

$sql = "select * from configuracion order by id desc limit 1";
$result = $mysqli->query($sql);
$datas = $result->fetch_assoc();
$bacteriologo = $datas['bacteriologoLaboratorio'] . ":T.P. " . $datas['tarjetaPLaboratorio'];

$fields = "";
$values = "";
// Lista blanca: solo se aceptan columnas reales de la tabla destino.
$colsAllow = [];
$resCols = $mysqli->query("DESCRIBE `$tabla`");
if ($resCols) {
    while ($col = $resCols->fetch_assoc()) $colsAllow[] = strtolower($col['Field']);
}
foreach ($datos as $key => $value) {
    // 'codexamen' solo se usa para la verificación normativa, nunca como columna.
    if ($key == 'id' || $key == 'fechahora' || $key == 'tabla' || $key == 'ind' || $key == 'codexamen' || $key == 'nombreExamen' || $key == 'constant' || $key == 'unidades' || $key == 'bacteriologo' || $key == 'doctor' || $key == 'hora' || $key == 'exportar') continue;
    if (!in_array(strtolower((string)$key), $colsAllow, true)) continue;
    $escapedValue = $mysqli->real_escape_string($value);
    $fields .= "$key,";
    $values .= "'$escapedValue',";
}

// Eliminar último carácter de comas
$fields = rtrim($fields, ",");
$values = rtrim($values, ",");

$fields .= ",bacteriologo";
$values .= ",'$bacteriologo'";

$sql = "REPLACE INTO $tabla ($fields) values ($values)";

if ($mysqli->query($sql))
    echo json_encode(["msg" => true]);
else
    echo json_encode(["msg" => false]);
$mysqli->close();
