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
$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['error' => 'Conexión fallida: ' . mysqli_connect_error()]);
    exit();
}

// ✅ Obtener y sanitizar parámetros POST
$correo = filter_input(INPUT_POST, 'correo', FILTER_VALIDATE_EMAIL);
$contrasena = filter_input(INPUT_POST, 'contrasena', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$correo || !$contrasena) {
    echo json_encode(['error' => 'Correo o contraseña inválidos']);
    exit();
}
// ✅ Buscar usuario por correo
$stmt = $conex->prepare("SELECT usuario_id, contrasena_hash FROM usuario WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();

    // ✅ Verificar la contraseña
    if (password_verify($contrasena, $usuario['contrasena_hash'])) {
        // ✅ Generar token de sesión
        $publicKey = file_get_contents('public_key.pem');
        $tokenExpiracion = date("Y-m-d H:i:s",strtotime("+2 hours"));
        $encodeJSON = json_encode([
            'id' => $usuario['usuario_id'],
            'correo' => $correo,
            'contrasena' => $contrasena,
            'expiracion' => $tokenExpiracion
        ]);
        openssl_public_encrypt($encodeJSON,$token,$publicKey);
        echo json_encode([
            'success' => true,
            'mensaje' => 'Autenticación exitosa',
            'token' => base64_encode($token)
        ]);
        /**
         * Use una encriptacion RSA para encriptar los datos y obtener el token
         * el token tiene su hora de expiración de esta manera no se comprometen 
         * los datos del usuario después de haber iniciado sesión.
         */
    } else {
        echo json_encode(['error' => 'Contraseña incorrecta']);
    }
} else {
    echo json_encode(['error' => 'Usuario no encontrado']);
}

$stmt->close();
$conex->close();
?>