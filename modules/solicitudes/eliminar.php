<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

function obtenerUrlRegresoSolicitudes($default = 'index.php') {
    $returnUrl = $_GET['return_url'] ?? $default;
    return preg_match('/^index\.php(\?.*)?$/', $returnUrl) ? $returnUrl : $default;
}

if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}

$returnUrl = obtenerUrlRegresoSolicitudes();

function redirigirConMensaje($returnUrl, $tipo, $mensaje) {
    $separator = strpos($returnUrl, '?') === false ? '?' : '&';
    header("Location: " . $returnUrl . $separator . $tipo . "=" . urlencode($mensaje));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . $returnUrl);
    exit();
}

$db = new Database();
$id = (int)$_GET['id'];

// Verificar que la solicitud existe
$stmt = $db->prepare("SELECT id FROM solicitudes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirigirConMensaje($returnUrl, 'error', 'Solicitud no encontrada');
}

// Eliminar comentarios de seguimiento primero (por la relación de clave foránea)
$db->query("DELETE FROM comentarios_seguimiento WHERE solicitud_id = $id");

// Eliminar la solicitud
//$stmt = $db->prepare("DELETE FROM solicitudes WHERE id = ?");
//$stmt->bind_param("i", $id);
$stmt = $db->prepare("UPDATE solicitudes SET eliminado = 1 WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    redirigirConMensaje($returnUrl, 'success', 'Solicitud eliminada correctamente');
} else {
    redirigirConMensaje($returnUrl, 'error', 'Error al eliminar la solicitud');
}
?>
