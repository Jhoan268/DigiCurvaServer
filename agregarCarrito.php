<?php
// Encabezados CORS y tipo de contenido
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
    echo json_encode(['resultado' => "La conexión falló: " . mysqli_connect_error()]);
    exit();
}

try {
    // Obtener token de la cookie
    $token = $_COOKIE['token'] ?? null;
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Sesión no encontrada o expirada']);
        exit();
    }

    // Desencriptar token
    $private_key = file_get_contents('private_key.pem');
    $encryptedData = base64_decode($token);
    $decrypted_data = '';

    if (openssl_private_decrypt($encryptedData, $decrypted_data, $private_key)) {
        $disTokenJSON = json_decode($decrypted_data, true);
        if (!$disTokenJSON) {
            echo json_encode(['success' => false, 'error' => 'Token corrupto']);
            exit();
        }

        $tokenExpire = $disTokenJSON['expiracion'];
        $timeActual = date("Y-m-d H:i:s");

        if (strtotime($tokenExpire) < strtotime($timeActual)) {
            setcookie("token", "", time() - 3600, "/");
            echo json_encode(['success' => false, 'error' => 'El token ya expiró, vuelve a iniciar sesión.']);
            exit();
        }

        // ✅ Usuario válido
        $usuario_id = (int)$disTokenJSON['id'];

    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo leer la identidad del token']);
        exit();
    }

    // Capturar datos del POST
    $producto_id = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
    
    if (!$producto_id) {
        echo json_encode(['success' => false, 'error' => 'El campo "producto_id" es obligatorio o inválido.']);
        exit();
    }
    if ($cantidad === false || $cantidad <= 0) {
        echo json_encode(['success' => false, 'error' => 'El campo "cantidad" es obligatorio o inválido.']);
        exit();
    }

    // Fecha actual
    $fecha_agregado = date("Y-m-d H:i:s");

    // ✅ Insertar en carrito_compra
    $stmt = $conex->prepare("INSERT INTO carrito_compra (usuario_id, producto_id, cantidad, fecha_agregado) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $usuario_id, $producto_id, $cantidad, $fecha_agregado);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'resultado' => 'Producto agregado al carrito exitosamente'
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