<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
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
$cars = [];
$usuario_id = 0;
$saldo = array(
    'saldo' => 0,
    'coints' => 0
);
$precio = 0.0;
$use_coints = $data['use_Coints'];
try { 
    $jsonRecibido = file_get_contents('php://input');
    $data = json_decode($jsonRecibido, true);
    $cars = $data['cars'];
    if (count($cars) == 0) {
        echo json_encode([
            'success' => false,
            'error' => "No se recivieron 'cars'."
        ]);
        exit();
    }
} catch (\Throwable $th) {
    echo json_encode(['success' => false, 
    'error' => 'Error GP1: '. $th->getMessage().' comunicalo a un administrador.']);
    exit();
}
try {
    // 1. Obtener el token de la cookie
    $token = $_COOKIE['token'] ?? null;

    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Sesión no encontrada o expirada']);
        exit();
    }

    // 2. Cargar clave privada
    $private_key = file_get_contents('private_key.pem');
    $encryptedData = base64_decode($token);
    $decrypted_data = '';

    // 3. Desencriptar
    if (openssl_private_decrypt($encryptedData, $decrypted_data, $private_key)) {
        
        // IMPORTANTE: el segundo parámetro 'true' lo convierte en ARRAY asociativo
        $disTokenJSON = json_decode($decrypted_data, true); 

        if (!$disTokenJSON) {
            echo json_encode(['success' => false, 'error' => 'Token corrupto']);
            exit();
        }

        // 4. Lógica de verificación de expiración
        $tokenExpire = $disTokenJSON['expiracion'];
        $timeActual = date("Y-m-d H:i:s");

        if (strtotime($tokenExpire) < strtotime($timeActual)) {
            // Opcional: Borrar la cookie si ya expiró
            setcookie("token", "", time() - 3600, "/"); 
            echo json_encode(['success' => false, 'error' => 'El token ya expiró, vuelve a iniciar sesión.']);
            exit();
        }

        // ✅ Token válido: aquí tienes tu ID listo
        $usuario_id = (int)$disTokenJSON['id'];
    } else {
        echo json_encode(['success' => false, 'error' => 'No se pudo leer la identidad del token']);
        exit();
    }
} catch (\Throwable $th) {
    echo json_encode(['success' => false, 
    'error' => 'Error GP2: '. $th->getMessage().' comunicalo a un administrador.']);
    exit();
}
try {
    // ✅ Consultar información del perfil
    $stmt = $conex->prepare("SELECT coints, saldo FROM usuario WHERE usuario_id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($result->num_rows === 1) {
        $perfil = $result->fetch_assoc();
        $saldo['coints'] = $perfil['coints'];
        $saldo['saldo'] = $perfil['saldo'];
    } else {
        echo json_encode([
            'success'=>false,
            'error' => 'Usuario no encontrado'
        ]);
        exit();
    }
} catch (\Throwable $th) {
    echo json_encode(['success' => false, 
    'error' => 'Error GP3: '. $th->getMessage().' comunicalo a un administrador.']);
    exit();
}
try {
    $types = str_repeat('i', count($cars));
    $types .= $types;
    $placeholders = implode(
        ',',
        array_fill(0,count($cars),'?')
        );
    $query = "SELECT SUM(precio) AS precio FROM (
    SELECT SUM(producto.precio * carrito_compra.cantidad) AS precio
    FROM carrito_compra
    INNER JOIN producto ON carrito_compra.producto_id = producto.producto_id
    WHERE carrito_compra.carrito_id IN($placeholders) AND producto.producto_id 
    NOT IN (SELECT oferta.producto_id FROM oferta WHERE oferta.fecha_finalizacion >= CURRENT_DATE )
    UNION ALL
    SELECT SUM(producto.precio * carrito_compra.cantidad * (oferta.descuento / 100)) AS precio
    FROM carrito_compra
    INNER JOIN producto ON carrito_compra.producto_id = producto.producto_id
    INNER JOIN oferta ON oferta.producto_id = producto.producto_id
    WHERE carrito_compra.carrito_id IN($placeholders) AND oferta.fecha_finalizacion >= CURRENT_DATE
) AS consulta;";
    $stmt2 = $conex->prepare($query);
    $stmt2->bind_param($types, ...$cars, ...$cars);
    $stmt2->execute();
    $result = $stmt2->get_result();
    if ($result->num_rows === 1) {
        $consulta = $result->fetch_assoc();
        $precio = ((float)$consulta['precio']);
        if ($use_coints && $precio > $saldo['coints'] ||
        ($precio > $saldo['saldo'])){
            echo json_encode([
                'success'=>false,
                'error' => 'No tienes ni saldo ni coints suficientes.'
            ]);
            exit();
        } else if($use_coints && $precio <= $saldo['coints']) {
            $saldo['coints'] = 0;
        }else if ($precio <= $saldo['saldo']) {
            $saldo['saldo'] = 0;
        }
    } else {
        echo json_encode([
            'success'=>false,
            'error' => 'Falló la consultas'
        ]);
        exit();
    }
} catch (\Throwable $th) {
    echo json_encode(['success' => false, 
    'error' => 'Error GP4: '. $th->getMessage().' comunicalo a un administrador.']);
    exit();
}
try {
    $types = str_repeat('i', count($cars));
    $placeholders = implode(
            ',',
            array_fill(0,count($cars),'?')
            );
    $query = "UPDATE carrito_compra SET status='pagado' WHERE carrito_id IN($placeholders)";
    $stmt3 = $conex->prepare($query);
    $stmt3->bind_param($types, ...$cars);
    $stmt3->execute();
    if ($saldo['coints'] == 0) {
        $stmt4 = $conex->prepare("UPDATE usuario SET coints=(coints - ?) WHERE usuario_id=?");
        $stmt4->bind_param('f,i', $precio,$usuario_id);
        $stmt4->execute();
    } else {
        $stmt4 = $conex->prepare("UPDATE usuario SET coints=(coints - ?) WHERE usuario_id=?");
        $stmt4->bind_param('f,i', $precio,$usuario_id);
        $stmt4->execute();
    }
    echo json_encode(['success' => true, 
    'resultado' => 'Todo salio bien, todo está pagado.']);
    exit();
} catch (\Throwable $th) {
    echo json_encode(['success' => false, 
    'error' => 'Error GP5: '. $th->getMessage().' comunicalo a un administrador.']);
    exit();
}
?>