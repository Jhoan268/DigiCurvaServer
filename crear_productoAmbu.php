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
$dominio = $data["domain"];

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
        $notif = '';
        if ($activo == 1) {$notif = notificar($nombre,$imagen,$dominio);}
        echo json_encode(['success' => true, 'resultado' => 'Producto creado exitosamente', 'mensaje'=>$notif]);
    }

    $stmt->close();
    $conex->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    if (isset($conex)) $conex->close();
}


function notificar($nombre, $imagen, $dominio):string{
    // Datos que quieres enviar (el body del fetch)
    $datos = [
        "titulo"  => "¡Venden ".$nombre." en el tec!",
        "mensaje" => "Es posible que se te antoje un '".$nombre."'",
        "url"     => $dominio ."/DigiCurva-App/web/feed.html",
        "icon"    => $imagen // Opcional
    ];

    // Convertir el array a JSON
    $jsonDatos = json_encode($datos);

    // URL de tu API (si está en la misma carpeta, usa la ruta completa o local)
    $urlApi = $dominio."/Implementacion-notificaciones-push/enviar_notificacion.php";

    // Inicializar cURL
    $ch = curl_init($urlApi);

    // Configurar opciones de cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDatos);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonDatos)
    ]);

    // Ejecutar la petición y obtener la respuesta
    $respuesta = curl_exec($ch);

    // Manejo de errores de conexión
    if (curl_errno($ch)) {
        return  'Error en cURL: ' . curl_error($ch);
    } else {
        return $respuesta;
    }

    // Cerrar conexión
    curl_close($ch);
}
?>
