<?php
// Encabezados para CORS y JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Conexión a la base de datos
$jsonString = file_get_contents('config.json');
$data = json_decode($jsonString, true);
$user = $data["username"];
$server = $data["host"];
$database = $data["database"];
$password = $data["password"];
$dominio = $data["domain"]; // Necesario para la función notificar

$conex = mysqli_connect($server, $user, $password, $database);

if (!$conex) {
    echo json_encode(['success' => false, 'error' => "La conexión falló: " . mysqli_connect_error()]);
    exit();
}

try {
    ignore_user_abort(true); // Le dice a PHP: "Si el que me llamó se desconecta, yo sigo trabajando"
    set_time_limit(0);       // Evita que el script se muera por límite de tiempo de ejecución
    // Ajuste de zona horaria para MySQL (CST)
    mysqli_query($conex, "SET time_zone = '-06:00';");

    $productos_encendidos = 0;
    $notificaciones_enviadas = 0;

    // 1. Lógica para ENCENDER (activo = 1) con NOTIFICACIONES
    // Seleccionamos primero los que deben encenderse
    $querySelectEncender = "
        SELECT id, nombre, imagen 
        FROM productos_ambulantes 
        WHERE hprendido IS NOT NULL 
          AND hapagado IS NOT NULL 
          AND CURTIME() >= hprendido 
          AND CURTIME() < hapagado 
          AND activo = 0
    ";
    
    $resultEncender = $conex->query($querySelectEncender);

    if ($resultEncender && $resultEncender->num_rows > 0) {
        // Preparamos el update individual para mayor seguridad dentro del bucle
        $stmtUpdate = $conex->prepare("UPDATE productos_ambulantes SET activo = 1 WHERE id = ?");

        while ($row = $resultEncender->fetch_assoc()) {
            $id = $row['id'];
            $nombre = $row['nombre'];
            $imagen = $row['imagen'];

            $stmtUpdate->bind_param("i", $id);
            $stmtUpdate->execute();

            // Si se actualizó correctamente, enviamos la notificación
            if ($stmtUpdate->affected_rows > 0) {
                notificar($nombre, $imagen, $dominio);
                $productos_encendidos++;
                $notificaciones_enviadas++;
            }
        }
        $stmtUpdate->close();
    }

    // 2. Lógica para APAGAR (activo = 0)
    // Esto se puede quedar como actualización masiva (silenciosa)
    $queryApagar = "
        UPDATE productos_ambulantes 
        SET activo = 0 
        WHERE hprendido IS NOT NULL 
          AND hapagado IS NOT NULL 
          AND (CURTIME() >= hapagado OR CURTIME() < hprendido) 
          AND activo = 1
    ";
    $conex->query($queryApagar);
    $productos_apagados = $conex->affected_rows;

    // 3. Responder con el resultado de la operación
    echo json_encode([
        'success' => true,
        'mensaje' => 'Sincronización de horarios completada',
        'detalles' => [
            'encendidos_ahora' => $productos_encendidos,
            'notificaciones_enviadas' => $notificaciones_enviadas,
            'apagados_ahora' => $productos_apagados
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Error al actualizar estados: ' . $e->getMessage()
    ]);
}

if (isset($conex)) {
    $conex->close();
}


// --- FUNCIÓN DE NOTIFICACIÓN ---
function notificar($nombre, $imagen, $dominio):string{
    // Datos que quieres enviar (el body del fetch)
    $datos = [
        "titulo"  => "¡Venden ".$nombre." en el tec!",
        "mensaje" => "Es posible que se te antoje un '".$nombre."'",
        "url"     => "http://".$dominio ."/DigiCurva-App/web/feed.html",
        "icon"    => $imagen // Opcional
    ];

    // Convertir el array a JSON
    $jsonDatos = json_encode($datos);

    // URL de tu API
    $urlApi = "http://".$dominio."/Implementacion-notificaciones-push/enviar_notificacion.php";

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
        $resultado = 'Error en cURL: ' . curl_error($ch);
    } else {
        $resultado = $respuesta;
    }

    // Cerrar conexión
    curl_close($ch);
    
    return $resultado;
}
?>