<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!$auth->isAdmin()) {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$db = new Database();
$id = (int)$_GET['id'];

// Verificar que no sea el usuario admin por defecto
$stmt = $db->prepare("SELECT username FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario || $usuario['username'] === 'admin.yuc25') {
    header("Location: index.php");
    exit();
}

// Eliminar el usuario
$stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $success = "Usuario eliminado exitosamente";
    header("Location: index.php?success=" . urlencode($success));
} else {
    $error = "Error al eliminar el usuario";
    header("Location: index.php?error=" . urlencode($error));
}
exit();
?>