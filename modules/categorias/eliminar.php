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
$pk_categoria = (int)$_GET['id'];
$fk_estatus = ((int)$_GET['estatus'] == 1) ? 2 : 1;
$msg = ((int)$_GET['estatus'] == 1) ? "inactivada" : "activada";
$msg2 = ((int)$_GET['estatus'] == 1) ? "inactivar" : "activar";
// Inactivar categoría
$stmt = $db->prepare("UPDATE cat_categorias SET fk_estatus = ? WHERE pk_categoria = ?");
$stmt->bind_param("ii", $fk_estatus, $pk_categoria);

if ($stmt->execute()) {
    $success = "Categoría " . $msg . " exitosamente";
    header("Location: index.php?success=" . urlencode($success));
} else {
    $error = "Error al " . $msg2 . " la categoría";
    header("Location: index.php?error=" . urlencode($error));
}
exit();
?>
