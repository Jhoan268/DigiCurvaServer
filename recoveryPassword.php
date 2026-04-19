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
    // 1. Recibir el cuerpo de la petición
    $jsonRecibido = file_get_contents('php://input');
    $data = json_decode($jsonRecibido, true);

    // 2. Extraer datos del JSON (con fallback a vacío para evitar errores)
    $correoRaw = $data['correo'] ?? $_GET['correo'] ?? '';

    // 3. Validar y Sanitizar el Correo
    // filter_var devolverá el string del correo si es válido, o FALSE si no lo es.
    $correo = filter_var($correoRaw, FILTER_VALIDATE_EMAIL);
    if (!$correo) {
        echo json_encode(['success'=>false,'error'=>"faltan parametros", 'correo' => $_GET['correo']]);
        exit;
    }
    // 4. Preparar e Insertar
    // Nota: 'notificados' (default 0) y 'activo' (default 1) se omiten para usar sus valores por defecto
    $stmt = $conex->prepare("SELECT usuario_id, nombre FROM usuario WHERE correo = ?");
    
    $stmt->bind_param("s",$correo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();
        // ✅ Generar token
        $publicKey = file_get_contents('public_key.pem');
        $tokenExpiracion = date("Y-m-d H:i:s",strtotime("+10 minutes"));
        $encodeJSON = json_encode([
            'id' => $usuario['usuario_id'],
            'correo' => $correo,
            'expiracion' => $tokenExpiracion
        ]);
        openssl_public_encrypt($encodeJSON,$token,$publicKey);
        //Guardo la cookie con el token encriptado
        $token = base64_encode($token); // Codificar el token en base64
        $message = 'Si confirmas que deceas recuperar tu contraseña: '.$dominio.'/DigiCurva-App/Web/recoveryNewPassword.html?token='.$token;
        $sendmail = json_decode(setCorreo($usuario['nombre'],$correo,$message,$dominio),true);
        if (!$sendmail['success']) {
            echo json_encode(['success' => false, 'error' => $sendmail['error']]);
        } else {
            echo json_encode(['success' => true, 'resultado' => 'operación correcta', 'token' => $token, 'operacion'=>$sendmail]);
        }
    } else {
        echo json_encode(['success' => false, 'error'  => 'no se encontró ninguna coincidencia']);
    }

    $stmt->close();
    $conex->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    if (isset($conex)) $conex->close();
}

function setCorreo($nombre, $correo, $message, $dominio):string{
    // Datos que quieres enviar (el body del fetch)
    $datos = [
        "name" => $nombre,
        "email" => $correo,
        "message" => $message
    ];
    // URL de tu API (si está en la misma carpeta, usa la ruta completa o local)
    $urlApi = $dominio."/phpmailer-tutorial/sendJson.php";
    // Inicializar cURL
    $ch = curl_init($urlApi);
    // Configurar opciones de cURL
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Ejecutar la petición y obtener la respuesta
    $respuesta = curl_exec($ch);
    // Manejo de errores de conexión
    if (curl_errno($ch)) {
    // Cerrar conexión
    curl_close($ch);
        return (string)(json_encode(['success' => false,'error' => 'Error en cURL: ' . curl_error($ch)]));
    } else {
    // Cerrar conexión
    curl_close($ch);
        return (string)$respuesta;
    }
}
?>
