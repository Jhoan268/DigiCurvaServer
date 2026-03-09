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
$search = filter_input(INPUT_POST, 'search', FILTER_SANITIZE_STRING);
if (!$search) {
    echo json_encode([
        'success' => false,
        'error' => 'No se está buscando nada.'
    ]);
    exit();
}
$search = '%' . $search . '%';
$stmt = $conex->prepare("SELECT producto_id, nombre, descripcion, precio, cantidad_existencia, fecha_publicacion, imagen_url, categoria 
FROM producto WHERE producto_id NOT IN (SELECT oferta.producto_id FROM oferta WHERE oferta.fecha_finalizacion >= CURRENT_DATE )
AND (producto.nombre LIKE ? OR producto.descripcion LIKE ?)");
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$productos = [];

while ($row = $result->fetch_assoc()) {
    $productos[] = $row;
}
$stmt->close();
$stmt2 = $conex->prepare("SELECT producto.nombre, producto.descripcion, producto.precio, producto.cantidad_existencia, 
producto.fecha_publicacion, producto.imagen_url, oferta.descuento, 
oferta.fecha_finalizacion, producto.producto_id FROM producto 
INNER JOIN oferta ON oferta.producto_id = producto.producto_id
WHERE oferta.fecha_finalizacion >= CURRENT_DATE 
AND (producto.nombre LIKE ? OR producto.descripcion LIKE ?)");
$stmt2->bind_param("ss", $search, $search);
$stmt2->execute();
$result2 = $stmt2->get_result();
$ofertas = [];
while ($row = $result2->fetch_assoc()) {
    $ofertas[] = $row;
}
$stmt2->close();
$conex->close();

// ✅ Responder con la lista de productos
echo json_encode([
    'success' => true,
    'productos' => $productos,
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