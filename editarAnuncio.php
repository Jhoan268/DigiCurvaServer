<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
/**
 * La contraseña será cambiada por motivos de seguridad,
 * ahora el acceso será definido por un config.json
 * se deberá implementar la nueva lógica de acceso.
 * Si tienes alguna duda Jhoan la contraseña para ti 
 * en tu base de datos local será sin contraseña y el usuario
 * root.
 * Elimina este comentario una vez implementado.
 */
// Activar errores de mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Datos BD
$server = 'localhost';
$user = 'droa';
$password = 'droaPluving$1';
$database = 'marketplace';

// Conectar BD
$conex = mysqli_connect($server, $user, $password, $database);
if (!$conex) {
    echo json_encode(['resultado' => "Error de conexión: " . mysqli_connect_error()]);
    exit();
}
try{
// Obtener anuncio_id (POST o GET)
$anuncio_id = filter_input(INPUT_POST, 'anuncio_id', FILTER_VALIDATE_INT);
if (!$anuncio_id) {
    $anuncio_id = filter_input(INPUT_GET, 'anuncio_id', FILTER_VALIDATE_INT);
}

if (!$anuncio_id) {
    echo json_encode(['resultado' => "Falta 'anuncio_id'"]);
    exit();
}

// Obtener id_transaccion (POST o GET)
$id_transaccion = filter_input(INPUT_POST, 'id_transaccion', FILTER_SANITIZE_STRING);
if (!$id_transaccion) {
    $id_transaccion = filter_input(INPUT_GET, 'id_transaccion', FILTER_SANITIZE_STRING);
}

if (!$id_transaccion) {
    echo json_encode(['resultado' => "Falta 'id_transaccion'"]);
    exit();
}

// UPDATE
$stmt = $conex->prepare("UPDATE anuncio SET status='pagado', id_transaccion=? WHERE anuncio_id=?");
$stmt->bind_param("si", $id_transaccion, $anuncio_id);
$stmt->execute();
$stmt->close();

echo json_encode(['resultado' => "Actualizado correctamente"]);

$conex->close();
} catch (Exception $e) {
    echo json_encode(['resultado' => 'Error del servidor: ' . $e->getMessage()]);
    $conex->close();
    exit();
}
?>
