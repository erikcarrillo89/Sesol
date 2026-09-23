<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

function obtenerUrlRegresoSolicitudes($default = 'index.php') {
    $returnUrl = $_GET['return_url'] ?? '';
    return preg_match('/^index\.php(\?.*)?$/', $returnUrl) ? $returnUrl : $default;
}

if (!$auth->isLoggedIn()) {
    header("Location: ../../../login.php");
    exit();
}

$solicitud_id = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : 0;
$returnUrl = obtenerUrlRegresoSolicitudes();

if ($solicitud_id < 1) {
    $separator = strpos($returnUrl, '?') === false ? '?' : '&';
    header("Location: ../" . $returnUrl . $separator . "error=ID inválido");
    exit();
}

// Redirige a ver.php con el ID
header("Location: ../ver.php?id=$solicitud_id&return_url=" . urlencode($returnUrl));
exit();
?>
