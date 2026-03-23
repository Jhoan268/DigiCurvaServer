<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Conexión a la base de datos
// 1. Leer el archivo JSON
$jsonString = file_get_contents('config.json');
// 2. Decodificar el JSON en un array asociativo
$data = json_decode($jsonString, true);
// 3. Asignar las variables
$user = $data["username"];
$server = $data["host"];
$database = $data["database"];
$password = $data["password"];
$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['error' => 'Conexión fallida: ' . mysqli_connect_error()]);
    exit();
}
try{
// ✅ Obtener y sanitizar parámetros POST
$token = $_COOKIE['token'] ?? null;
$is_consulta_vendedor = $_GET['is_consulta_vendedor'] ?? false;
$vendedor_id = null;
if ($token && $is_consulta_vendedor) {
// logica para obtener el token
$private_key = file_get_contents('private_key.pem');
$encryptedData = base64_decode($token);
openssl_private_decrypt($encryptedData, $decrypted_data, $private_key);
$disTokenJSON = json_decode($decrypted_data, true);
//logica de verificacion del token
$tokenExpire = $disTokenJSON['expiracion'];
$timeActual = date("Y-m-d H:i:s");
if (strtotime($tokenExpire) < strtotime($timeActual)){
    echo json_encode(['error'=>'El token ya expiró, vuelve a iniciar sesion.']);
    exit();
}
$vendedor_id = (int)$disTokenJSON['id'];
}
// ✅ Construir consulta base
$query = "SELECT usuario.nombre AS nombre_user, ubicaciones.descripcion AS ubicacion, productos_ambulantes.activo, 
productos_ambulantes.imagen, productos_ambulantes.nombre, productos_ambulantes.descripcion, productos_ambulantes.id, 
productos_ambulantes.id_ubicacion,
productos_ambulantes.precio, productos_ambulantes.categoria FROM productos_ambulantes INNER JOIN ubicaciones ON ubicaciones.id = 
productos_ambulantes.id_ubicacion INNER JOIN usuario ON usuario.usuario_id = productos_ambulantes.id_vendedor AND 
productos_ambulantes.activo = 1  ";
if ($is_consulta_vendedor) {
    $query = "SELECT usuario.nombre AS nombre_user, ubicaciones.descripcion AS ubicacion, productos_ambulantes.activo, 
productos_ambulantes.imagen, productos_ambulantes.nombre, productos_ambulantes.descripcion, productos_ambulantes.id, 
productos_ambulantes.id_ubicacion,
productos_ambulantes.precio, productos_ambulantes.categoria FROM productos_ambulantes INNER JOIN ubicaciones ON ubicaciones.id = 
productos_ambulantes.id_ubicacion INNER JOIN usuario ON usuario.usuario_id = productos_ambulantes.id_vendedor ";
}
$params = [];
$types = "";

// ✅ Agregar filtro por vendedor_id si se proporciona
if ($vendedor_id != null && $is_consulta_vendedor) {
    $query .= " AND productos_ambulantes.id_vendedor = ?";
    $params[] = $vendedor_id;
    $types .= "i";
}

$stmt = $conex->prepare($query);

// ✅ Asociar parámetros si existen
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$productos = [];

while ($row = $result->fetch_assoc()) {
    $productos[] = $row;
}

$stmt->close();
$conex->close();

// ✅ Responder con la lista de productos
echo json_encode([
    'success' => true,
    'productos' => $productos
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