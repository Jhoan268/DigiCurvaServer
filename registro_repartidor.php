<?php
// Encabezados para permitir solicitudes desde cualquier origen (CORS) y definir tipo de contenido
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
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
// Habilitar reporte de errores de MySQLi para facilitar depuración
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Parámetros de conexión a la base de datos
$server = 'localhost';
$user = 'droa';
$password = 'droaPluving$1';
$database = 'marketplace';

// Establecer conexión con la base de datos
$conex = mysqli_connect($server, $user, $password, $database);

// Verificar si la conexión fue exitosa
if (!$conex) {
    echo json_encode(['resultado' => "La conexión falló: " . mysqli_connect_error()]);
    exit();
}

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
echo json_encode(['resultado' => 'Solicitud de repartidor registrada exitosamente']);

// ✅ Cerrar conexión a la base de datos
$conex->close();
?>