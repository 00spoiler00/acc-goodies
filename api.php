<?php
header('Content-Type: application/json');

$jsonFile = 'ids.json';
$data = json_decode(file_get_contents($jsonFile), true);

// Definir la contraseña correcta
$password = 'OffTrackAT1:(';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['password']) || $input['password'] !== $password) {
    echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($input['id'])) {
        $data[] = $input['id'];
        file_put_contents($jsonFile, json_encode($data));
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID no proporcionado']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (isset($input['id'])) {
        $data = array_filter($data, function($id) use ($input) {
            return $id !== $input['id'];
        });
        file_put_contents($jsonFile, json_encode($data));
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID no proporcionado']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>
