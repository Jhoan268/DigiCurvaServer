<?php
// Encabezados para permitir solicitudes desde cualquier origen (CORS) y definir tipo de contenido
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Habilitar reporte de errores de MySQLi para facilitar depuración
//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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
// 1. Obtener el token de la cookie
$token = $_COOKIE['token'] ?? null;
$disTokenJSON = false;
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Sesión no encontrada o expirada','tipe'=>'sesion']);
    exit();
} else {

// 2. Cargar clave privada
$private_key = file_get_contents('private_key.pem');
$encryptedData = base64_decode($token);
$decrypted_data = '';
// 3. Desencriptar
if (openssl_private_decrypt($encryptedData, $decrypted_data, $private_key)) {
    // IMPORTANTE: el segundo parámetro 'true' lo convierte en ARRAY asociativo
    $disTokenJSON = json_decode($decrypted_data, true); 
    if (!$disTokenJSON) {
        echo json_encode(['success' => false, 'error' => 'Token corrupto','tipe'=>'sesion']);
        exit();
    }
    // 4. Lógica de verificación de expiración
    $tokenExpire = $disTokenJSON['expiracion'];
    $timeActual = date("Y-m-d H:i:s");
    if (strtotime($tokenExpire) < strtotime($timeActual)) {
        // Opcional: Borrar la cookie si ya expiró
        setcookie("token", "", time() - 3600, "/"); 
        echo json_encode(['success' => false, 'error' => 'El token ya expiró, vuelve a iniciar sesión.','tipe'=>'sesion']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al desencriptar el token','tipe'=>'sesion']);
    exit();
}
}
$usuario_id = $disTokenJSON['id'];
$id = filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST,'status', FILTER_SANITIZE_STRING);

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$usuario_id) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'usuario_id' es obligatorio o inválido"]);
    exit();
}
if (!$id) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'id' es obligatorio o inválido"]);
    exit();
}
if (!$status) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'status' es obligatorio o inválido"]);
    exit();
}
//---------------------------------------------------------
$stmt = $conex->prepare("UPDATE solicitudes_compra INNER JOIN productos_ambulantes ON 
productos_ambulantes.id = solicitudes_compra.id_producto
SET solicitudes_compra.statusd = ? WHERE solicitudes_compra.id = ?
AND ( solicitudes_compra.id_comprador = ? OR productos_ambulantes.id_vendedor = ?)");
$stmt->bind_param("siii", $status, $id, $usuario_id, $usuario_id);
//---------------------------------------------------------

// ✅ Responder con mensaje de éxito
if ($stmt->execute()) {
        echo json_encode([
        'success' => true, 
        'resultado' => 'solicitud editada exitosamente'
    ]);
    }
    $stmt->close();
    $conex->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    if (isset($conex)) $conex->close();
}
?>