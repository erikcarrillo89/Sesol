<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $tarea_id = isset($_POST['tarea_id']) ? (int)$_POST['tarea_id'] : 0;
    $usuario_id = $_SESSION['user_id'];
    $nuevo_estatus = isset($_POST['estatus']) ? (int)$_POST['estatus'] : 2; // 2 = Concluida
    if ($tarea_id > 0) {
        if ($_SESSION['rol'] !== 'admin') {
            $stmt = $db->prepare("SELECT pk_tarea FROM solicitudes_tareas WHERE pk_tarea = ? AND fk_usuario = ?");
            $stmt->bind_param("ii", $tarea_id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar esta tarea']);
                exit();
            }
        }
        $stmt = $db->prepare("UPDATE solicitudes_tareas SET fk_estatus = ?, fecha_cierre = NOW() WHERE pk_tarea = ?");
        $stmt->bind_param("ii", $nuevo_estatus, $tarea_id);
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Tarea actualizada correctamente',
                'estatus' => $nuevo_estatus
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la tarea']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID de tarea no válido']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
