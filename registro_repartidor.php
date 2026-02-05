<?php
// Encabezados para permitir solicitudes desde cualquier origen (CORS) y definir tipo de contenido
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Habilitar reporte de errores de MySQLi para facilitar depuración
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Parámetros de conexión a la base de datos
// 1. Leer el archivo JSON
$jsonString = file_get_contents('config.json');
// 2. Decodificar el JSON en un array asociativo
$data = json_decode($jsonString, true);
// 3. Asignar las variables
$user = $data["username"];
$server = $data["host"];
$database = $data["database"];
$password = $data["password"];

// Establecer conexión con la base de datos
$conex = mysqli_connect($server, $user, $password, $database);

// Verificar si la conexión fue exitosa
if (!$conex) {
    echo json_encode(['resultado' => "La conexión falló: " . mysqli_connect_error()]);
    exit();
}
try{
// ✅ Obtener y sanitizar datos del formulario (POST)
$nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$correo = filter_input(INPUT_POST, 'correo', FILTER_VALIDATE_EMAIL);
$telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_NUMBER_INT);
$ine_url = filter_input(INPUT_POST, 'ine_url', FILTER_SANITIZE_URL);
$empresa = filter_input(INPUT_POST, 'empresa', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$nombre) {
    echo json_encode(['resultado' => "El campo 'nombre' es obligatorio o inválido"]);
    exit();
}

if (!$correo) {
    echo json_encode(['resultado' => "El campo 'correo' es obligatorio o inválido"]);
    exit();
}

if (!$telefono) {
    echo json_encode(['resultado' => "El campo 'telefono' es obligatorio o inválido"]);
    exit();
}

if (!$ine_url) {
    echo json_encode(['resultado' => "El campo 'ine_url' es obligatorio o inválido"]);
    exit();
}

if (!$empresa) {
    echo json_encode(['resultado' => "El campo 'empresa' es obligatorio o inválido"]);
    exit();
}

if (!$ine_url) {
    echo json_encode(['resultado' => "El campo 'ine_url' es obligatorio o inválido"]);
    exit();
}
// ✅ Preparar e insertar los datos del nuevo repartidor en la base de datos
$stmt = $conex->prepare("INSERT INTO repartidor (nombre, correo, telefono, ine_url, empresa) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssiss", $nombre, $correo, $telefono, $ine_url, $empresa);
$stmt->execute();
$stmt->close();

// ✅ Responder con mensaje de éxito
echo json_encode([
    'success' => true,
    'resultado' => 'Solicitud de repartidor registrada exitosamente'
]);

// ✅ Cerrar conexión a la base de datos
$conex->close();
} catch (Exception $e) {
    echo json_encode([
    'success' => false,
    'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
    $conex->close();
    exit();
}
?>