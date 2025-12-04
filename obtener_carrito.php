<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Conexión a la base de datos
$user = 'droa';
$server = 'localhost';
$database = 'marketplace';
$password = 'droaPluving$1';
$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['error' => 'Conexión fallida: ' . mysqli_connect_error()]);
    exit();
}

// ✅ Obtener y sanitizar parámetro POST
$usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);

if (!$usuario_id) {
    echo json_encode(['error' => 'El parámetro usuario_id es obligatorio o inválido']);
    exit();
}

// ✅ Consultar contenido del carrito del usuario
$stmt = $conex->prepare("
    SELECT 
        carrito_compra.carrito_id,
        carrito_compra.producto_id,
        producto.nombre,
        producto.descripcion,
        producto.precio,
        carrito_compra.cantidad,
        producto.imagen_url
    FROM carrito_compra
    INNER JOIN producto ON carrito_compra.producto_id = producto.producto_id
    WHERE carrito_compra.usuario_id = ?
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$carrito = [];

while ($row = $result->fetch_assoc()) {
    $carrito[] = $row;
}

$stmt->close();
$conex->close();

// ✅ Responder con el contenido del carrito
echo json_encode([
    'success' => true,
    'carrito' => $carrito
]);
?>