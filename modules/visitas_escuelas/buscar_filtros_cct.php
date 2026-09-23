<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!$auth->canAccessVisitasEscuelas()) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit();
}

$campo = $_GET['campo'] ?? '';
$busqueda = trim($_GET['q'] ?? '');

$camposPermitidos = [
    'cct' => [
        'columna' => 'CLAVECCT',
        'extra' => 'NOMBRECT'
    ],
    'escuela' => [
        'columna' => 'NOMBRECT',
        'extra' => 'CLAVECCT'
    ],
    'nivel' => [
        'columna' => 'N_NIVEL',
        'extra' => ''
    ],
    'municipio' => [
        'columna' => 'N_MUNICIPIO',
        'extra' => ''
    ],
    'localidad' => [
        'columna' => 'N_LOCALIDAD',
        'extra' => ''
    ]
];

if (!isset($camposPermitidos[$campo]) || mb_strlen($busqueda) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

$db = new Database();
$columna = $camposPermitidos[$campo]['columna'];
$extra = $camposPermitidos[$campo]['extra'];
$termino = '%' . $busqueda . '%';

$selectExtra = $extra !== '' ? ", MAX($extra) AS extra" : ", '' AS extra";
$sql = "SELECT $columna AS valor $selectExtra
    FROM cct
    WHERE STATUS = 1
        AND $columna IS NOT NULL
        AND TRIM($columna) <> ''
        AND $columna LIKE ?
    GROUP BY $columna
    ORDER BY $columna
    LIMIT 20";

$stmt = $db->prepare($sql);
$stmt->bind_param("s", $termino);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $valor = trim($row['valor'] ?? '');
    $texto = $valor;
    if (!empty($row['extra'])) {
        $texto .= ' - ' . trim($row['extra']);
    }

    $items[] = [
        'id' => $valor,
        'text' => $texto
    ];
}

$stmt->close();

echo json_encode(['results' => $items], JSON_UNESCAPED_UNICODE);
