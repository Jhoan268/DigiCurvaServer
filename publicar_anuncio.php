<?php
// Encabezados para permitir solicitudes desde cualquier origen (CORS) y definir tipo de contenido
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Habilitar reporte de errores de MySQLi para facilitar depuración
//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Parámetros de conexión a la base de datos
// 1. Leer el archivo JSON
$jsonString = file_get_contents('config.json');
// 2. Decodificar el JSON en un array asociativo
$data = json_decode($jsonString, true);
// 3. Asignar las variables
$user = $data["username"];
$server = $data["host"];
$database = $data["database"];
$password = $data["password"];

// Establecer conexión con la base de datos
$conex = mysqli_connect($server, $user, $password, $database);

// Verificar si la conexión fue exitosa
if (!$conex) {
    echo json_encode(['resultado' => "La conexión falló: " . mysqli_connect_error()]);
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
} else {
    echo json_encode(['success' => false, 'error' => 'Error al desencriptar el token']);
    exit();
}
$usuario_id = $disTokenJSON['id'];
$titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$mensaje = filter_input(INPUT_POST, 'mensaje', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$fecha_inicio = filter_input(INPUT_POST, 'fecha_inicio', FILTER_SANITIZE_STRING);
$fecha_fin = filter_input(INPUT_POST, 'fecha_fin', FILTER_SANITIZE_STRING);
$costo = filter_input(INPUT_POST, 'costo', FILTER_VALIDATE_FLOAT);
$url_img = filter_input(INPUT_POST, 'url_imagen', FILTER_SANITIZE_URL);
$status = "esperando pago";
$id_transaccion = "123456789ABCD"; //ESPERANDO CONFIGURACION DE CUENTAS DE PAYPAL Y MERCADO PAGO

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$usuario_id) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'usuario_id' es obligatorio o inválido"]);
    exit();
}
if (!$titulo) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'titulo' es obligatorio o inválido"]);
    exit();
}
if (!$mensaje) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'mensaje' es obligatorio o inválido"]);
    exit();
}
if (!$fecha_inicio) {
    echo json_encode([
        'success' => false,
        'resultado' => "El campo 'fecha_inicio' es obligatorio o inválido"]);
    exit();
}
if (!$fecha_fin) {
    echo json_encode([
        'success' => false,
        'resultado' => "El campo 'fecha_fin' es obligatorio o inválido"]);
    exit();
}
if ($costo === false || $costo < 0) {
    echo json_encode([
        'success' => false,
        'resultado' => "El campo 'costo' es obligatorio o inválido"]);
    exit();
}
$stmt2 = $conex->prepare("SELECT coints FROM usuario WHERE usuario_id = ?");
$stmt2->bind_param("i", $usuario_id);
$stmt2->execute();
$result = $stmt2->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $coints = $row['coints'];
    if ($coints >= $costo) {
        $status = "pagado";
    }
}
$stmt2->close();
$stmt3 = $conex->prepare("UPDATE usuario SET coints = coints - ? WHERE usuario_id = ?");
$stmt3->bind_param("di", $costo, $usuario_id);
$stmt3->execute();
$stmt3->close();
// ✅ Preparar e insertar los datos del nuevo anuncio en la base de datos
$stmt = $conex->prepare("INSERT INTO anuncio (usuario_id, titulo, mensaje, fecha_inicio, fecha_fin, costo, status, id_transaccion, url_imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssdsss", $usuario_id, $titulo, $mensaje, $fecha_inicio, $fecha_fin, $costo, $status, $id_transaccion, $url_img);
$stmt->execute();
$stmt->close();

// ✅ Responder con mensaje de éxito
echo json_encode([
    'success' => true,
    'resultado' => 'Anuncio publicado exitosamente',
    'status' => $status,
    'id_anuncio' => $conex->insert_id
]);

// ✅ Cerrar conexión a la base de datos
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
