<?php 
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
try{
// ✅ Obtener y sanitizar parámetro POST
$producto_id = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);

if (!$producto_id) {
    echo json_encode(['error' => 'El parámetro producto_id es obligatorio o inválido']);
    exit();
}

// ✅ Consultar información del producto
$stmt = $conex->prepare("SELECT producto_id, nombre, descripcion, precio, cantidad_existencia, fecha_publicacion, imagen_url, vendedor_id FROM producto WHERE producto_id = ?");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(['error' => 'Producto no encontrado']);
    $stmt->close();
    $conex->close();
    exit();
}

$producto = $result->fetch_assoc();
$stmt->close();

// ✅ Consultar oferta activa (si existe)
$stmt = $conex->prepare("SELECT oferta_id, precio_con_descuento, fecha_publicacion, fecha_finalizacion FROM oferta WHERE producto_id = ? AND CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$result = $stmt->get_result();

$oferta = null;
if ($result->num_rows === 1) {
    $oferta = $result->fetch_assoc();
}
$stmt->close();

// ✅ Responder con los datos del producto y la oferta (si existe)
echo json_encode([
    'success' => true,
    'producto' => $producto,
    'oferta_activa' => $oferta
]);

$conex->close();
} catch (Exception $e) {
    echo json_encode([
    'success' => false,
    'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
    $conex->close();
    exit();
}
?>