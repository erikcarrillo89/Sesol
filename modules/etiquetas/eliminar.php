<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if (!$auth->isAdmin()) {
    header("Location: ../../login.php");
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['estatus']) || !is_numeric($_GET['estatus'])) {
    header("Location: index.php");
    exit();
}
$db = new Database();
$pk_etiqueta = (int)$_GET['id'];
$fk_estatus = ((int)$_GET['estatus'] == 1) ? 2 : 1;
$msg = ((int)$_GET['estatus'] == 1) ? "inactivada" : "activada";
$msg2 = ((int)$_GET['estatus'] == 1) ? "inactivar" : "activar";
// Eliminar la etiqueta
$stmt = $db->prepare("UPDATE cat_etiquetas SET fk_estatus = ? WHERE pk_etiqueta = ?");
$stmt->bind_param("ii", $fk_estatus, $pk_etiqueta);
if ($stmt->execute()) {
    $success = "Etiqueta " . $msg . " exitosamente";
    header("Location: index.php?success=" . urlencode($success));
} else {
    $error = "Error al " . $msg2 . " la etiqueta";
    header("Location: index.php?error=" . urlencode($error));
}
exit();
?>
