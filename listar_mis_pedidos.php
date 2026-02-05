<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
/**
 * La contraseña será cambiada por motivos de seguridad,
 * ahora el acceso será definido por un config.json
 * se deberá implementar la nueva lógica de acceso.
 * Si tienes alguna duda Jhoan la contraseña para ti 
 * en tu base de datos local será sin contraseña y el usuario
 * root.
 * Elimina este comentario una vez implementado.
 */
// Conexión a la base de datos
$user = 'droa';
$server = 'localhost';
$database = 'marketplace';
$password = 'droaPluving$1';
$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['error' => 'Conexión fallida: ' . mysqli_connect_error()]);
    exit();
}

// ✅ Obtener y sanitizar parámetro POST
$token = $_COOKIE['token'];
if (!$token) {
    echo json_encode(['error' => 'El token es obligatorio o inválido']);
    exit();
}
// logica para obtener el token
$private_key = file_get_contents('private_key.pem');
$encryptedData = base64_decode($token);
openssl_private_decrypt($encryptedData, $decrypted_data, $private_key);
$disTokenJSON = json_decode($decrypted_data);
//logica de verificacion del token
$tokenExpire = $disTokenJSON['expiracion'];
$timeActual = date("Y-m-d H:i:s");
if (strtotime($tokenExpire) < strtotime($timeActual)){
    echo json_encode(['error'=>'El token ya expiró, vuelve a iniciar sesion.']);
    exit();
}
$comprador_id = (int)$disTokenJSON['id'];
if (!$comprador_id) {
    echo json_encode(['error' => 'El parámetro comprador_id es obligatorio o inválido']);
    exit();
}

// ✅ Consultar historial de pedidos del usuario
$stmt = $conex->prepare("
    SELECT pedido_id,fecha_pedido, estado, metodo_pago, direccion_envio FROM pedido WHERE comprador_id = ?
    ORDER BY fecha_pedido DESC
");
$stmt->bind_param("i", $comprador_id);
$stmt->execute();
$result = $stmt->get_result();

$pedidos = [];

while ($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}

$stmt->close();
$conex->close();

// ✅ Responder con el historial de pedidos
echo json_encode([
    'success' => true,
    'pedidos' => $pedidos
]);
?>