<?php
// Encabezados para permitir solicitudes desde cualquier origen (CORS) y definir tipo de contenido
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
// Habilitar reporte de errores de MySQLi para facilitar depuración
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Conexión a la base de datos
//Logica de acceso desde config.json
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

    // ✅ Token válido: aquí tienes tu ID listo
    $vendedor_id = (int)$disTokenJSON['id'];

} else {
    echo json_encode(['success' => false, 'error' => 'No se pudo leer la identidad del token']);
    exit();
}

$nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
$cantidad = filter_input(INPUT_POST, 'cantidad_existencia', FILTER_VALIDATE_INT);
$imagen_url = filter_input(INPUT_POST, 'imagen_url', FILTER_SANITIZE_URL);
$categoria = filter_input(INPUT_POST, 'categoria', FILTER_SANITIZE_STRING);
$producto_id = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$producto_id) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "producto_id" es obligatorio o inválido.'
    ]);
    exit();
}
if (!$vendedor_id) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "vendedor_id" es obligatorio o inválido.'
    ]);
    exit();
}
if (!$nombre) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "nombre" es obligatorio o inválido.'
    ]);
    exit();
}
if (!$descripcion) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "descrpcion" es obligatorio o inválido.'
    ]);
    exit();
}
if ($precio === false || $precio < 0) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "precio" es obligatorio o inválido.'
    ]);
    exit();
}
if ($cantidad === false || $cantidad < 0) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "centidad en existenci" es obligatorio o inválido.'
    ]);
    exit();
}

if (!$imagen_url) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "imagen url" es obligatorio o inválido.'
    ]);
    exit();
}
if (!$categoria) {
    echo json_encode([
        'success' => false, 
        'error' => 'El campo "categoria" es obligatorio o inválido.'
    ]);
} else {
    $stmtTemp = $conex->prepare("SELECT nombre FROM categoria_producto");
    $stmtTemp->execute();
    $encontrado = false;
    $resultTemp = $stmtTemp->get_result();
    while ($row = $resultTemp->fetch_assoc()) {
        if($row['nombre'] == $categoria){
            $encontrado = true;
            break;
        }
    }
    if (!$encontrado) {
        echo json_encode([
            'success' => false, 
            'error' => 'El campo "categoria" no es una opción y es inválido.'
        ]);
    }
}

// ✅ Preparar e insertar los datos del nuevo producto en la base de datos
$stmt = $conex->prepare("UPDATE producto SET nombre=?, descripcion=?, precio=?, cantidad_existencia=?, imagen_url=?, categoria=? WHERE vendedor_id  = ? AND producto_id = ?");
$stmt->bind_param("ssdissii", $nombre, $descripcion, $precio, $cantidad, $imagen_url, $categoria, $vendedor_id, $producto_id);
$stmt->execute();
$stmt->close();

// ✅ Responder con mensaje de éxito
echo json_encode([
        'success' => true, 
        'resultado' => 'Producto creado exitosamente'
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