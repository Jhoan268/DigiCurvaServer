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
$token = $_POST['token'];
if (!$token) {
    echo json_encode(['error' => 'El token es obligatorio o inválido']);
    exit();
}
// logica para obtener el token
$private_key = file_get_contents('private_key.pem');
$encryptedData = base64_decode($token);
openssl_private_decrypt($encryptedData, $decrypted_data, $private_key);
$disTokenJSON = json_decode($decrypted_data);
//logica de verificacion del token
$tokenExpire = $disTokenJSON['expiracion'];
$timeActual = date("Y-m-d H:i:s");
if (strtotime($tokenExpire) < strtotime($timeActual)){
    echo json_encode(['error'=>'El token ya expiró, vuelve a iniciar sesion.']);
    exit();
}
$vendedor_id = (int)$disTokenJSON['id'];
$nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
$cantidad = filter_input(INPUT_POST, 'cantidad_existencia', FILTER_VALIDATE_INT);
$imagen_url = filter_input(INPUT_POST, 'imagen_url', FILTER_SANITIZE_URL);

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$vendedor_id) {
    echo json_encode(['resultado' => "El campo 'vendedor_id' es obligatorio o inválido"]);
    exit();
}
if (!$nombre) {
    echo json_encode(['resultado' => "El campo 'nombre' es obligatorio o inválido"]);
    exit();
}
if (!$descripcion) {
    echo json_encode(['resultado' => "El campo 'descripcion' es obligatorio o inválido"]);
    exit();
}
if ($precio === false || $precio < 0) {
    echo json_encode(['resultado' => "El campo 'precio' es obligatorio o inválido"]);
    exit();
}
if ($cantidad === false || $cantidad < 0) {
    echo json_encode(['resultado' => "El campo 'cantidad_existencia' es obligatorio o inválido"]);
    exit();
}

if ($cantidad === false || $cantidad < 0) {
    echo json_encode(['resultado' => "El campo 'cantidad_existencia' es obligatorio o inválido"]);
    exit();
}

if (!$imagen_url) {
    echo json_encode(['resultado' => "El campo 'imagen_url' es obligatorio o inválido"]);
    exit();
}

// ✅ Preparar e insertar los datos del nuevo producto en la base de datos
$stmt = $conex->prepare("INSERT INTO producto (vendedor_id, nombre, descripcion, precio, cantidad_existencia, imagen_url) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issdis", $vendedor_id, $nombre, $descripcion, $precio, $cantidad, $imagen_url);
$stmt->execute();
$stmt->close();

// ✅ Responder con mensaje de éxito
echo json_encode(['resultado' => 'Producto creado exitosamente']);

// ✅ Cerrar conexión a la base de datos
$conex->close();
?>