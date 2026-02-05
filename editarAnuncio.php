<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Activar errores de mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Leer el archivo JSON
$jsonString = file_get_contents('config.json');
// 2. Decodificar el JSON en un array asociativo
$data = json_decode($jsonString, true);
// 3. Asignar las variables
$user = $data["username"];
$server = $data["host"];
$database = $data["database"];
$password = $data["password"];

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
