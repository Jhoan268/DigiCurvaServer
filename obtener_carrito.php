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
if (strtotime($tokenExpire) > strtotime($timeActual)){
    echo json_encode(['error'=>'El token ya expiró, vuelve a iniciar sesion.']);
    exit();
}
$usuario_id = $disTokenJSON['id'];

if (!$usuario_id) {
    echo json_encode(['error' => 'El parámetro usuario_id es obligatorio o inválido']);
    exit();
}

// ✅ Consultar contenido del carrito del usuario
$stmt = $conex->prepare("
    SELECT 
        carrito_compra.carrito_id,
        carrito_compra.producto_id,
        producto.nombre,
        producto.descripcion,
        producto.precio,
        carrito_compra.cantidad,
        producto.imagen_url
    FROM carrito_compra
    INNER JOIN producto ON carrito_compra.producto_id = producto.producto_id
    WHERE carrito_compra.usuario_id = ?
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$carrito = [];

while ($row = $result->fetch_assoc()) {
    $carrito[] = $row;
}

$stmt->close();
$conex->close();

// ✅ Responder con el contenido del carrito
echo json_encode([
    'success' => true,
    'carrito' => $carrito
]);
?>