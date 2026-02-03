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
$contrasena = filter_input(INPUT_POST, 'contrasena_hash', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$direccion = filter_input(INPUT_POST, 'direccion', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_NUMBER_INT);
$foto_perfil_url = filter_input(INPUT_POST, 'foto_perfil_url', FILTER_SANITIZE_URL);

// Inicializar karma en 0 por defecto
$karma = 0;

// ✅ Validar que los campos obligatorios estén presentes y sean válidos

if (!$nombre) {
    echo json_encode(['resultado' => "El campo 'nombre' es obligatorio o inválido"]);
    exit();
}

if (!$correo) {
    echo json_encode(['resultado' => "El campo 'correo' es obligatorio o inválido"]);
    exit();
}

if (!$contrasena_hash) {
    echo json_encode(['resultado' => "El campo 'contraseña' es obligatorio o inválido"]);
    exit();
}

if (!$direccion) {
    echo json_encode(['resultado' => "El campo 'dirección' es obligatorio o inválido"]);
    exit();
}

if (!$telefono) {
    echo json_encode(['resultado' => "El campo 'teléfono' es obligatorio o inválido"]);
    exit();
}

if (!$foto_perfil_url) {
    $foto_perfil_url = 'https://storage.googleapis.com/mi-proyecto-uploads-digicurva/perfil_defaul.png';
}

// ✅ Generar hash seguro de la contraseña

$contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

// ✅ Preparar e insertar los datos del nuevo usuario en la base de datos

$stmt = $conex->prepare("INSERT INTO usuario (nombre, correo, contrasena_hash, direccion, telefono, foto_perfil_url, karma) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssi", $nombre, $correo, $contrasena_hash, $direccion, $telefono, $foto_perfil_url, $karma);
$stmt->execute();
$stmt->close();

// ✅ Responder con mensaje de éxito (sin incluir el ID del usuario)

echo json_encode(['resultado' => 'Registro exitoso']);

// ✅ Cerrar conexión a la base de datos

$conex->close();

?>
