<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!$auth->canAccessVisitasEscuelas()) {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$db = new Database();
$id = (int)$_GET['id'];

$stmt = $db->prepare("SELECT id, cct FROM visitasescuelas WHERE id = ? AND eliminado = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$visita = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visita) {
    header("Location: index.php?error=" . urlencode("Visita no encontrada"));
    exit();
}

$stmt = $db->prepare("UPDATE visitasescuelas SET eliminado = 1 WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: index.php?success=" . urlencode("Visita eliminada correctamente. CCT: " . ($visita['cct'] ?? '')));
    exit();
}

header("Location: index.php?error=" . urlencode("Error al eliminar la visita"));
exit();
