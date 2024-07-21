<?php
header('Content-Type: application/json');

$jsonFile = 'ids.json';
$data = json_decode(file_get_contents($jsonFile), true);
$password = file_get_contents('.password');

// Cast existing IDs to integers
$data = array_map('intval', $data);

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['password']) || $input['password'] !== $password) {
    echo json_encode(['status' => 'error', 'message' => 'Contrasenya incorrecta']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($input['id'])) {
        $id = (int)$input['id'];
        if (in_array($id, $data)) {
            echo json_encode(['status' => 'error', 'message' => 'ID ja existeix']);
        } else {
            $data[] = $id;
            file_put_contents($jsonFile, json_encode($data));
            echo json_encode(['status' => 'success']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID no proporcionat']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (isset($input['id'])) {
        $id = (int)$input['id'];
        $data = array_filter($data, function($currentId) use ($id) {
            return $currentId !== $id;
        });
        file_put_contents($jsonFile, json_encode(array_values($data)));
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID no proporcionat']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Mètode no permès']);
}
?>
