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

// ✅ Obtener y sanitizar datos del formulario (POST)

$nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$correo = filter_input(INPUT_POST, 'correo', FILTER_VALIDATE_EMAIL);
$contrasena_hash = filter_input(INPUT_POST, 'contrasena_hash', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
/**
 * estas contraseñas hash de php son seguras y tienen una longitud fija de 60 caracteres 
 * sin importar el tamaño de la contraseña original de la contraseña o lo poco segura que sea.
 */
$contrasena_hash = password_hash($contrasena_hash, PASSWORD_DEFAULT);

// ✅ Preparar e insertar los datos del nuevo usuario en la base de datos

$stmt = $conex->prepare("INSERT INTO usuario (nombre, correo, contrasena_hash, direccion, telefono, foto_perfil_url, karma) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssi", $nombre, $correo, $contrasena_hash, $direccion, $telefono, $foto_perfil_url, $karma);
$stmt->execute();
$stmt->close();

// ✅ Generar token de sesión
        $publicKey = file_get_contents('public_key.pem');
        $tokenExpiracion = date("Y-m-d H:i:s",strtotime("+2 hours"));
        $encodeJSON = json_encode([
            'id' => $conex->insert_id,
            'correo' => $correo,
            'contrasena' => $contrasena_hash,
            'expiracion' => $tokenExpiracion
        ]);
        openssl_public_encrypt($encodeJSON,$token,$publicKey);
        //Guardo la cookie con el token encriptado
        setcookie("token", $base64_encode($token), [
            'expires' => time() + 7200,
            'path' => '/',
            'domain' => 'digicurva.local',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        echo json_encode([
            'success' => true,
            'mensaje' => 'Autenticación exitosa'
        ]);
// ✅ Responder con mensaje de éxito (sin incluir el ID del usuario)
echo json_encode(['resultado' => 'Registro exitoso']);

// ✅ Cerrar conexión a la base de datos

$conex->close();

?>
