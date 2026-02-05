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
$token = $_COOKIE['token'];
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
if (strtotime($tokenExpire) > strtotime($timeActual)){
    echo json_encode(['error'=>'El token ya expiró, vuelve a iniciar sesion.']);
    exit();
}
$usuario_id = $disTokenJSON['id'];
$titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$mensaje = filter_input(INPUT_POST, 'mensaje', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$fecha_inicio = filter_input(INPUT_POST, 'fecha_inicio', FILTER_SANITIZE_STRING);
$fecha_fin = filter_input(INPUT_POST, 'fecha_fin', FILTER_SANITIZE_STRING);
$costo = filter_input(INPUT_POST, 'costo', FILTER_VALIDATE_FLOAT);
$status = "esperando pago";
$id_transaccion = "123456789ABCD"; //ESPERANDO CONFIGURACION DE CUENTAS DE PAYPAL Y MERCADO PAGO

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$usuario_id) {
    echo json_encode(['resultado' => "El campo 'usuario_id' es obligatorio o inválido"]);
    exit();
}
if (!$titulo) {
    echo json_encode(['resultado' => "El campo 'titulo' es obligatorio o inválido"]);
    exit();
}
if (!$mensaje) {
    echo json_encode(['resultado' => "El campo 'mensaje' es obligatorio o inválido"]);
    exit();
}
if (!$fecha_inicio) {
    echo json_encode(['resultado' => "El campo 'fecha_inicio' es obligatorio o inválido"]);
    exit();
}
if (!$fecha_fin) {
    echo json_encode(['resultado' => "El campo 'fecha_fin' es obligatorio o inválido"]);
    exit();
}
if ($costo === false || $costo < 0) {
    echo json_encode(['resultado' => "El campo 'costo' es obligatorio o inválido"]);
    exit();
}

// ✅ Preparar e insertar los datos del nuevo anuncio en la base de datos
$stmt = $conex->prepare("INSERT INTO anuncio (usuario_id, titulo, mensaje, fecha_inicio, fecha_fin, costo, status, id_transaccion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssd", $usuario_id, $titulo, $mensaje, $fecha_inicio, $fecha_fin, $costo, $status, $id_transaccion);
$stmt->execute();
$stmt->close();

// ✅ Responder con mensaje de éxito
echo json_encode(['resultado' => 'Anuncio publicado exitosamente']);

// ✅ Cerrar conexión a la base de datos
$conex->close();

?>
