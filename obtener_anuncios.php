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
// ✅ Consultar anuncios activos (fecha actual entre fecha_inicio y fecha_fin)
$query = " SELECT  anuncio_id, usuario_id, titulo, mensaje, fecha_inicio, fecha_fin, costo, url_imagen
    FROM anuncio WHERE fecha_fin >= CURRENT_DATE";
$params = [];
$types = "";
$usuario_id = $_POST['id']??null;
// ✅ Agregar filtro por vendedor_id si se proporciona
if ($usuario_id != null) {
    $query .= " AND usuario_id = ?";
    $params[] = $usuario_id;
    $types .= "i";
}

$stmt = $conex->prepare($query);

// ✅ Asociar parámetros si existen
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$anuncio = []; 

while ($row = $result->fetch_assoc()) {
    $anuncio[] = $row; 
}

$stmt->close();
$conex->close();

// ✅ Responder con la lista de anuncios activos
echo json_encode([
    'success' => true,
    'anuncios_activos' => $anuncio 
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