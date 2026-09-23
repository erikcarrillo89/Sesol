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
    SELECT CLAVECCT, NOMBRECT, N_MUNICIPIO, N_LOCALIDAD
    FROM cct
    WHERE STATUS = 1
      AND (
        CLAVECCT LIKE ?
        OR NOMBRECT LIKE ?
      )
    ORDER BY
        CASE WHEN CLAVECCT LIKE ? THEN 0 ELSE 1 END,
        CLAVECCT
    LIMIT 20
");

$prefijo = $busqueda . '%';
$stmt->bind_param('sss', $termino, $termino, $prefijo);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $texto = $row['CLAVECCT'] . ' - ' . $row['NOMBRECT'];
    if (!empty($row['N_MUNICIPIO']) || !empty($row['N_LOCALIDAD'])) {
        $texto .= ' (' . trim(($row['N_MUNICIPIO'] ?? '') . ', ' . ($row['N_LOCALIDAD'] ?? ''), ' ,') . ')';
    }

    $items[] = [
        'id' => $row['CLAVECCT'],
        'text' => $texto,
        'N_MUNICIPIO' => $row['N_MUNICIPIO'] ?? '',
        'N_LOCALIDAD' => $row['N_LOCALIDAD'] ?? '',
        'NOMBRECT' => $row['NOMBRECT'] ?? ''
    ];
}

$stmt->close();

echo json_encode(['results' => $items], JSON_UNESCAPED_UNICODE);
