<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Conexión a la base de datos desde config.json
$jsonString = file_get_contents('config.json');
$data = json_decode($jsonString, true);

$user = $data["username"];
$server = $data["host"];
$database = $data["database"];
$password = $data["password"];
$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['error' => 'Conexión fallida: ' . mysqli_connect_error()]);
    exit();
}

try {
    // ✅ Consulta de Ubicaciones
    $query = "SELECT id, descripcion, x, y FROM ubicaciones";
    $result = mysqli_query($conex, $query);

    $ubicaciones = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Convertimos x e y a números para que sean fáciles de usar en JS
            $row['x'] = (int)$row['x'];
            $row['y'] = (int)$row['y'];
            $ubicaciones[] = $row;
        }
    }

    $conex->close();

    // ✅ Respuesta JSON
    echo json_encode([
        'success' => true,
        'ubicaciones' => $ubicaciones
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
    if ($conex) $conex->close();
    exit();
}
?>
