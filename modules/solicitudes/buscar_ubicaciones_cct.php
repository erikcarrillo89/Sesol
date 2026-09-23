<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!$auth->isLoggedIn()) {
    echo json_encode(['results' => []]);
    exit();
}

$db = new Database();
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

if (mb_strlen($busqueda, 'UTF-8') < 2) {
    echo json_encode(['results' => []]);
    exit();
}

$termino = '%' . $busqueda . '%';
$stmt = $db->prepare("
    SELECT ubicacion
    FROM (
        SELECT DISTINCT N_MUNICIPIO AS ubicacion
        FROM cct
        WHERE STATUS = 1
          AND N_MUNICIPIO IS NOT NULL
          AND TRIM(N_MUNICIPIO) <> ''
          AND N_MUNICIPIO LIKE ?
        UNION
        SELECT DISTINCT N_LOCALIDAD AS ubicacion
        FROM cct
        WHERE STATUS = 1
          AND N_LOCALIDAD IS NOT NULL
          AND TRIM(N_LOCALIDAD) <> ''
          AND N_LOCALIDAD LIKE ?
    ) AS ubicaciones
    ORDER BY ubicacion
    LIMIT 20
");

$stmt->bind_param('ss', $termino, $termino);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $ubicacion = $row['ubicacion'] ?? '';
    $items[] = [
        'id' => $ubicacion,
        'text' => $ubicacion
    ];
}

$stmt->close();

echo json_encode(['results' => $items], JSON_UNESCAPED_UNICODE);
