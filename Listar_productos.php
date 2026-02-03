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

// ✅ Obtener y sanitizar parámetros POST
$vendedor_id = filter_input(INPUT_POST, 'vendedor_id', FILTER_VALIDATE_INT);

// ✅ Construir consulta base
$query = "SELECT nombre, descripcion, precio, cantidad_existencia, fecha_publicacion, imagen_url FROM producto";
$params = [];
$types = "";

// ✅ Agregar filtro por vendedor_id si se proporciona
if ($vendedor_id) {
    $query .= " WHERE vendedor_id = ?";
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
?>