<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if (!$auth->isAdmin()) {
    header("Location: ../../login.php");
    exit();
}
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $categoria = sanitizeInput($_POST['categoria']);
    // Verificar si la categoría ya existe
    $stmt = $db->prepare("SELECT pk_categoria FROM cat_categorias WHERE categoria = ?");
    $stmt->bind_param("s", $categoria);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $error = "La categoría ya existe";
    } else {
        $stmt = $db->prepare("INSERT INTO cat_categorias (categoria) VALUES (?)");
        $stmt->bind_param("s", $categoria);
        if ($stmt->execute()) {
            $success = "Categoría creada exitosamente";
            header("Location: index.php?success=" . urlencode($success));
            exit();
        } else {
            $error = "Error al crear la categoría: " . $db->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear categoría - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Crear nueva categoría</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error){?>
                            <div class="alert alert-danger"><?=$error?></div>
                        <?php }?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="categoria" class="form-label">Categoría:</label>
                                <input type="text" placeholder="Escribe la categoría" class="form-control" id="categoria" name="categoria" required autocomplete="off">
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Guardar categoría</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
