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
$id_producto = filter_input(INPUT_POST,'id_producto',FILTER_VALIDATE_INT);
$id_ubicacion = filter_input(INPUT_POST,'id_ubicacion',FILTER_VALIDATE_INT);
$cantidad = filter_input(INPUT_POST,'cantidad',FILTER_VALIDATE_INT);
$status = 'sin_atender';
date_default_timezone_set('America/Mexico_City');
$fecha_actual = date('Y-m-d H:i:s');

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$usuario_id) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'usuario_id' es obligatorio o inválido"]);
    exit();
}
//---------------------------------------------------------
$stmt = $conex->prepare("SELECT productos_ambulantes.id AS producto_id, productos_ambulantes.nombre AS title, solicitudes_compra.id AS solicitud_id,
solicitudes_compra.id_ubicacion, ubicaciones.descripcion AS ubicacion, ubicaciones.x, ubicaciones.y, usuario.nombre, productos_ambulantes.imagen,
solicitudes_compra.cantidad, solicitudes_compra.fecha_solicitud FROM solicitudes_compra
INNER JOIN productos_ambulantes ON productos_ambulantes.id = solicitudes_compra.id_producto
INNER JOIN ubicaciones ON ubicaciones.id = solicitudes_compra.id_ubicacion
INNER JOIN usuario ON usuario.usuario_id = solicitudes_compra.id_comprador
WHERE usuario.usuario_id = ? AND solicitudes_compra.statusd = 'sin_atender'");
$stmt->bind_param("i", $usuario_id);
//---------------------------------------------------------
$stmt->execute();
$result = $stmt->get_result();
$solicitudes = [];

while ($row = $result->fetch_assoc()) {
    $solicitudes[] = $row;
}
$stmt->close();
//-------------segunda consulta-------------
$stmt2 = $conex->prepare("SELECT productos_ambulantes.activo FROM productos_ambulantes WHERE
productos_ambulantes.id_vendedor = ? LIMIT 1");
$stmt2->bind_param("i",$usuario_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
$fila = $res2->fetch_assoc();
$activo = $fila['activo'] ?? null;
$stmt2->close();
//------------------------------------------
$conex->close();

// ✅ Responder con la lista de solicitudes
echo json_encode([
    'success' => true,
    'solicitudes' => $solicitudes,
    'activo' => $activo
]);
} catch (Exception $e) {
    echo json_encode([
    'success' => false,
    'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
    $conex->close();
    exit();
}
?>