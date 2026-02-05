<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Activar errores de mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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
// 1. Obtener el token de la cookie
$token = $_COOKIE['token'] ?? null;

if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Sesión no encontrada o expirada']);
    exit();
}

// 2. Cargar clave privada
$private_key = file_get_contents('private_key.pem');
$encryptedData = base64_decode($token);
$decrypted_data = '';

// 3. Desencriptar
if (openssl_private_decrypt($encryptedData, $decrypted_data, $private_key)) {
    
    // IMPORTANTE: el segundo parámetro 'true' lo convierte en ARRAY asociativo
    $disTokenJSON = json_decode($decrypted_data, true); 

    if (!$disTokenJSON) {
        echo json_encode(['success' => false, 'error' => 'Token corrupto']);
        exit();
    }

    // 4. Lógica de verificación de expiración
    $tokenExpire = $disTokenJSON['expiracion'];
    $timeActual = date("Y-m-d H:i:s");

    if (strtotime($tokenExpire) < strtotime($timeActual)) {
        // Opcional: Borrar la cookie si ya expiró
        setcookie("token", "", time() - 3600, "/"); 
        echo json_encode(['success' => false, 'error' => 'El token ya expiró, vuelve a iniciar sesión.']);
        exit();
    }

    // ✅ Token válido: aquí tienes tu ID listo
    $usuario_id = (int)$disTokenJSON['id'];

} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo leer la identidad del token']);
    exit();
}


// ✅ Consultar información del perfil
$stmt = $conex->prepare("SELECT nombre, correo, direccion, telefono, foto_perfil_url FROM usuario WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $perfil = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'perfil' => $perfil
    ]);
} else {
    echo json_encode(['error' => 'Usuario no encontrado']);
}

$stmt->close();
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