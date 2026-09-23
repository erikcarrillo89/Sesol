<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
if (!$auth->isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}
if (!isset($_POST['tarea_id']) || !is_numeric($_POST['tarea_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de tarea inválido'
    ]);
    exit();
}
$tarea_id = (int)$_POST['tarea_id'];
$db = new Database();
try {
    $stmt = $db->prepare("SELECT fk_estatus, fk_solicitud FROM solicitudes_tareas WHERE pk_tarea = ?");
    $stmt->bind_param("i", $tarea_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Tarea no encontrada'
        ]);
        exit();
    }
    $tarea = $result->fetch_assoc();

    // No se pueden eliminar tareas con estatus 2 (Concluida)
    if (in_array($tarea['fk_estatus'], [2])) {
        $estado_textos = [
            0 => 'no iniciada',
            1 => 'en proceso',
            2 => 'concluida',
            3 => 'pendiente'
        ];
        $estado_texto = $estado_textos[$tarea['fk_estatus']] ?? 'con estatus desconocido';
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar una tarea {$estado_texto}. Solo se pueden eliminar tareas en proceso."
        ]);
        exit();
    }
    $stmt_delete = $db->prepare("DELETE FROM solicitudes_tareas WHERE pk_tarea = ?");
    $stmt_delete->bind_param("i", $tarea_id);
    if ($stmt_delete->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Tarea eliminada exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar la tarea'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
