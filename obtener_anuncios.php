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
try{
// ✅ Consultar anuncios activos (fecha actual entre fecha_inicio y fecha_fin)
$stmt = $conex->prepare(" SELECT  anuncio_id, usuario_id, titulo, mensaje, fecha_inicio, fecha_fin, costo
    FROM anuncio WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin ORDER BY fecha_inicio DESC
");

$stmt->execute();
$result = $stmt->get_result();

$anuncio = []; 

while ($row = $result->fetch_assoc()) {
    $anuncio[] = $row; 
}

$stmt->close();
$conex->close();

// ✅ Responder con la lista de anuncios activos
echo json_encode([
    'success' => true,
    'anuncios_activos' => $anuncio 
]);
} catch (Exception $e) {
    echo json_encode([
    'success' => false,
    'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
    $conex->close();
    exit();
}
?>