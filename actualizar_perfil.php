<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Conexión a la base de datos
/**
 * La contraseña será cambiada por motivos de seguridad,
 * ahora el acceso será definido por un config.json
 * se deberá implementar la nueva lógica de acceso.
 * Si tienes alguna duda Jhoan la contraseña para ti 
 * en tu base de datos local será sin contraseña y el usuario
 * root.
 * Elimina este comentario una vez implementado.
 */
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
/**
 * como ves las variables de arriba cambiaron un poco debido
 * a la nueva implementación de seguridad, has esto con todos
 * los php y elimina estos comentarios de documentación.
 */
 // Ejemplos de comentarios de documentación:" /** todo lo que esta entre estos simbolos */ "
$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['error' => 'Conexión fallida: ' . mysqli_connect_error()]);
    exit();
}

// ✅ Obtener y sanitizar parámetros POST
$usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
$nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$correo = filter_input(INPUT_POST, 'correo', FILTER_VALIDATE_EMAIL);
$contrasena_hash = filter_input(INPUT_POST, 'contrasena_hash', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$direccion = filter_input(INPUT_POST, 'direccion', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$foto_perfil_url = filter_input(INPUT_POST, 'foto_perfil_url', FILTER_SANITIZE_URL);

// ✅ Validar campos obligatorios
if (!$usuario_id || !$nombre || !$correo || !$contrasena_hash) {
    echo json_encode(['error' => 'Faltan campos obligatorios o son inválidos']);
    exit();
}

// ✅ Actualizar datos del usuario
$stmt = $conex->prepare("
    UPDATE usuario
    SET nombre = ?, correo = ?, contrasena_hash = ?, direccion = ?, telefono = ?, foto_perfil_url = ?
    WHERE usuario_id = ?
");

$stmt->bind_param("ssssssi", $nombre, $correo, $contrasena_hash, $direccion, $telefono, $foto_perfil_url, $usuario_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'mensaje' => 'Perfil actualizado correctamente'
    ]);
} else {
    echo json_encode([
        'error' => 'Error al actualizar el perfil: ' . $stmt->error
    ]);
}

$stmt->close();
$conex->close();
?>