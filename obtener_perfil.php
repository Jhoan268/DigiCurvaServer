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
$usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);

if (!$usuario_id) {
    echo json_encode(['error' => 'El parámetro usuario_id es obligatorio o inválido']);
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
?>