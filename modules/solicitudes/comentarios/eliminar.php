<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../includes/db.php';
require_once '../../../includes/auth.php';

function obtenerUrlRegresoSolicitudes($default = 'index.php') {
    $returnUrl = $_GET['return_url'] ?? $default;
    return preg_match('/^index\.php(\?.*)?$/', $returnUrl) ? $returnUrl : $default;
}

if (!$auth->isLoggedIn()) {
    header("Location: ../../../login.php");
    exit();
}

$comentario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$solicitud_id = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : 0;
$returnParam = urlencode(obtenerUrlRegresoSolicitudes());

if ($comentario_id <= 0 || $solicitud_id <= 0) {
    die("Error: Parámetros inválidos.");
}

try {
    $db = new Database();

    // Obtener archivo del comentario
    $stmt = $db->prepare("SELECT archivo FROM comentarios_seguimiento WHERE id = ?");
    $stmt->bind_param("i", $comentario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($comentario = $result->fetch_assoc()) {
        if (!empty($comentario['archivo'])) {
            $ruta = __DIR__ . '/../../../uploads/comentarios/' . $comentario['archivo'];
            $ruta_real = realpath($ruta);
            $base_real = realpath(__DIR__ . '/../../../uploads/comentarios/');

            if ($ruta_real && strpos($ruta_real, $base_real) === 0 && file_exists($ruta_real)) {
                unlink($ruta_real); // Eliminar el archivo físico
            }
        }
    }

    // Eliminar comentario de la base de datos
    $stmt = $db->prepare("DELETE FROM comentarios_seguimiento WHERE id = ?");
    $stmt->bind_param("i", $comentario_id);
    $stmt->execute();


    header("Location: ../ver.php?id=" . $solicitud_id . "&return_url=" . $returnParam . "&success=Comentario eliminado correctamente");
    exit();

} catch (Exception $e) {
    echo "<h3>Error al eliminar el comentario:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    exit();
}
?>
