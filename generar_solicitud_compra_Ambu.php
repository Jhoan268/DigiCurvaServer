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
$dominio = $data["domain"];
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
$disTokenJSON = false;
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Sesión no encontrada o expirada','tipe'=>'sesion']);
    exit();
} else {

// 2. Cargar clave privada
$private_key = file_get_contents('private_key.pem');
$encryptedData = base64_decode($token);
$decrypted_data = '';
// 3. Desencriptar
if (openssl_private_decrypt($encryptedData, $decrypted_data, $private_key)) {
    // IMPORTANTE: el segundo parámetro 'true' lo convierte en ARRAY asociativo
    $disTokenJSON = json_decode($decrypted_data, true); 
    if (!$disTokenJSON) {
        echo json_encode(['success' => false, 'error' => 'Token corrupto','tipe'=>'sesion']);
        exit();
    }
    // 4. Lógica de verificación de expiración
    $tokenExpire = $disTokenJSON['expiracion'];
    $timeActual = date("Y-m-d H:i:s");
    if (strtotime($tokenExpire) < strtotime($timeActual)) {
        // Opcional: Borrar la cookie si ya expiró
        setcookie("token", "", time() - 3600, "/"); 
        echo json_encode(['success' => false, 'error' => 'El token ya expiró, vuelve a iniciar sesión.','tipe'=>'sesion']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al desencriptar el token','tipe'=>'sesion']);
    exit();
}
}
$usuario_id = $disTokenJSON['id'];
$id_producto = filter_input(INPUT_POST,'id_producto',FILTER_VALIDATE_INT);
$id_ubicacion = filter_input(INPUT_POST,'id_ubicacion',FILTER_VALIDATE_INT);
$cantidad = filter_input(INPUT_POST,'cantidad',FILTER_VALIDATE_INT);
$status = 'sin_atender';
date_default_timezone_set('America/Mexico_City');
$fecha_actual = date('Y-m-d H:i:s');

// ✅ Validar que los campos obligatorios estén presentes y sean válidos
if (!$usuario_id) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'usuario_id' es obligatorio o inválido"]);
    exit();
}
if (!$id_producto) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'id_producto' es obligatorio o inválido"]);
    exit();
}
if (!$id_ubicacion) {
    echo json_encode(['success' => false, 'resultado' => "El campo 'id_ubicacion' es obligatorio o inválido"]);
    exit();
}
if (!$cantidad) {
    echo json_encode([
        'success' => false,
        'resultado' => "El campo 'cantidad' es obligatorio o inválido"]);
    exit();
}
//---------------------------------------------------------
$stmt = $conex->prepare("INSERT INTO solicitudes_compra(id_producto, id_ubicacion, cantidad, fecha_solicitud, statusd, id_comprador) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("iidssi", $id_producto,$id_ubicacion,$cantidad,$fecha_actual,$status,$usuario_id);
//---------------------------------------------------------

// ✅ Responder con mensaje de éxito
if ($stmt->execute()) {
        $notif = '';
        $notif = notificar($usuario_id, $dominio);
        echo json_encode(['success' => true, 'resultado' => 'Solicitud de compra registrada exitosamente', 'mensaje'=>$notif]);
    }
    $stmt->close();
    $conex->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    if (isset($conex)) $conex->close();
}
function notificar($usuario_id, $dominio):string{
    // Datos que quieres enviar (el body del fetch)
    $datos = [
        "titulo"  => "Nueva solicitud de compra.",
        "mensaje" => "No pierdas a un cliente, mejor pierde la clase, es broma! solo atiende.",
        "url"     => $dominio."/DigiCurva-App/web/perfil.html",
        "usuario_id" => $usuario_id
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