<?php
// Encabezados para CORS y JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Conexión a la base de datos desde config.json
$jsonString = file_get_contents('config.json');
$data = json_decode($jsonString, true);
$user = $data["username"];
$server = $data["host"];
$database = $data["database"];
$password = $data["password"];

$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['success' => false, 'error' => "La conexión falló: " . mysqli_connect_error()]);
    exit();
}

try {
    // 1. Obtener y desencriptar el token para obtener el id_vendedor
    $token = $_COOKIE['token'] ?? null;
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Sesión no encontrada']);
        exit();
    }

    $private_key = file_get_contents('private_key.pem');
    $encryptedData = base64_decode($token);
    $decrypted_data = '';

    if (openssl_private_decrypt($encryptedData, $decrypted_data, $private_key)) {
        $disTokenJSON = json_decode($decrypted_data, true);
        if (!$disTokenJSON || strtotime($disTokenJSON['expiracion']) < time()) {
            echo json_encode(['success' => false, 'error' => 'Token inválido o expirado']);
            exit();
        }
        $vendedor_id = (int)$disTokenJSON['id'];
    } else {
        echo json_encode(['success' => false, 'error' => 'Error de identidad']);
        exit();
    }

    // 2. Recibir y filtrar datos según las columnas de la imagen
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
    $imagen = filter_input(INPUT_POST, 'imagen_url', FILTER_SANITIZE_URL); // Columna 'imagen'
    $categoria = filter_input(INPUT_POST, 'categoria', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $id_ubicacion = filter_input(INPUT_POST, 'id_ubicacion', FILTER_VALIDATE_INT);
    $activo = filter_input(INPUT_POST, 'activo', FILTER_VALIDATE_INT);

    // 3. Validaciones básicas
    if (!$nombre || !$precio || !$categoria || !$id_ubicacion) {
        echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios']);
        exit();
    }

    // 4. Preparar e Insertar
    // Nota: 'notificados' (default 0) y 'activo' (default 1) se omiten para usar sus valores por defecto
    $stmt = $conex->prepare("INSERT INTO productos_ambulantes (nombre, descripcion, precio, imagen, categoria, id_vendedor, id_ubicacion, activo) VALUES (?, ?, ?, ?, ?, ?, ?,?)");
    
    // "ssdissi" -> string, string, double, string, string, int, int
    $stmt->bind_param("ssdssiii", 
        $nombre, 
        $descripcion, 
        $precio, 
        $imagen, 
        $categoria, 
        $vendedor_id, 
        $id_ubicacion, 
        $activo 
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'resultado' => 'Producto creado exitosamente']);
    }

    $stmt->close();
    $conex->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    if (isset($conex)) $conex->close();
}
?>
