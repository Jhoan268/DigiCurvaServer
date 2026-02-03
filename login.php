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
        $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales

        // Opcional: guardar el token en la base de datos si deseas persistencia
        // $stmt_token = $conex->prepare("UPDATE usuario SET token_sesion = ? WHERE id = ?");
        // $stmt_token->bind_param("si", $token, $usuario['id']);
        // $stmt_token->execute();
        // $stmt_token->close();

        echo json_encode([
            'success' => true,
            'mensaje' => 'Autenticación exitosa',
            'usuario_id' => $usuario['usuario_id'],
            'token' => $token
        ]);
    } else {
        echo json_encode(['error' => 'Contraseña incorrecta']);
    }
} else {
    echo json_encode(['error' => 'Usuario no encontrado']);
}

$stmt->close();
$conex->close();
?>