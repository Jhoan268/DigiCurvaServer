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

// ✅ Consultar pedidos disponibles (sin repartidor asignado)
$stmt = $conex->prepare("SELECT pedido_id, comprador_id, fecha_pedido, estado, direccion_envio, metodo_pago, punto_encuentro_acordado FROM pedido
    WHERE repartidor_id IS NULL");

$stmt->execute();
$result = $stmt->get_result();

$pedidos_disponibles = [];

while ($row = $result->fetch_assoc()) {
    $pedidos_disponibles[] = $row;
}

$stmt->close();
$conex->close();

// ✅ Responder con la lista de pedidos disponibles
echo json_encode([
    'success' => true,
    'pedidos_disponibles' => $pedidos_disponibles
]);
?>