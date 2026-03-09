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
$token = $_COOKIE['token'];
if (!$token) {
    echo json_encode(['error' => 'El token es obligatorio o inválido']);
    exit();
}
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
$vendedor_id = $_POST['id'] ?? null;

// ✅ Construir consulta base
$query = "SELECT producto.nombre, producto.descripcion, producto.precio, producto.cantidad_existencia, 
producto.fecha_publicacion, producto.imagen_url, oferta.descuento, 
oferta.fecha_finalizacion, producto.producto_id FROM producto 
INNER JOIN oferta ON oferta.producto_id = producto.producto_id
WHERE oferta.fecha_finalizacion >= CURRENT_DATE";
$params = [];
$types = "";

// ✅ Agregar filtro por vendedor_id si se proporciona
if ($vendedor_id != null) {
    $query .= " AND vendedor_id = ?";
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

$ofertas = [];

while ($row = $result->fetch_assoc()) {
    $ofertas[] = $row;
}

$stmt->close();
$conex->close();

// ✅ Responder con la lista de ofertas
echo json_encode([
    'success' => true,
    'ofertas' => $ofertas
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