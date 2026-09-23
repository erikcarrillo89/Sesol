<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if (!$auth->isAdmin()) {
    header("Location: ../../login.php");
    exit();
}
$db = new Database();
$usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'especial'");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
if (!$usuario) {
    header("Location: index.php?error=" . urlencode("Usuario no encontrado o no es especial"));
    exit();
}
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->query("DELETE FROM usuarios_categorias WHERE fk_usuario = $usuario_id");
        if (isset($_POST['categorias']) && is_array($_POST['categorias'])) {
            foreach ($_POST['categorias'] as $cat_id) {
                $cat_id = (int)$cat_id;
                $stmt = $db->prepare("INSERT INTO usuarios_categorias (fk_usuario, fk_categoria) VALUES (?, ?)");
                $stmt->bind_param("ii", $usuario_id, $cat_id);
                $stmt->execute();
            }
        }
        $db->query("DELETE FROM usuarios_etiquetas WHERE fk_usuario = $usuario_id");
        if (isset($_POST['etiquetas']) && is_array($_POST['etiquetas'])) {
            foreach ($_POST['etiquetas'] as $etiq_id) {
                $etiq_id = (int)$etiq_id;
                $stmt = $db->prepare("INSERT INTO usuarios_etiquetas (fk_usuario, fk_etiqueta) VALUES (?, ?)");
                $stmt->bind_param("ii", $usuario_id, $etiq_id);
                $stmt->execute();
            }
        }
        $success = "Permisos actualizados correctamente";
    } catch (Exception $e) {
        $error = "Error al actualizar permisos: " . $e->getMessage();
    }
}
$categorias = $db->query("SELECT * FROM cat_categorias WHERE fk_estatus = 1 ORDER BY categoria");
$etiquetas = $db->query("SELECT * FROM cat_etiquetas WHERE fk_estatus = 1 ORDER BY etiqueta");
$cats_asignadas = [];
$result = $db->query("SELECT fk_categoria FROM usuarios_categorias WHERE fk_usuario = $usuario_id");
while ($row = $result->fetch_assoc()) {
    $cats_asignadas[] = $row['fk_categoria'];
}
$etiqs_asignadas = [];
$result = $db->query("SELECT fk_etiqueta FROM usuarios_etiquetas WHERE fk_usuario = $usuario_id");
while ($row = $result->fetch_assoc()) {
    $etiqs_asignadas[] = $row['fk_etiqueta'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Permisos - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <style>
        .permission-card {
            max-height: 400px;
            overflow-y: auto;
            padding: 40px;
        }
        .form-check {
            padding: 8px 0;
        }
        .btn-select-all {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="bi bi-shield-check"></i>
                                Asignar permisos: <?= htmlspecialchars($usuario['nombre_completo']) ?>
                            </h4>
                            <a href="index.php" class="btn btn-light btn-sm">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Instrucciones:</strong> Selecciona las categorías y etiquetas que este usuario especial podrá ver y gestionar.
                            Solo verá las solicitudes que pertenezcan a las categorías o etiquetas asignadas.
                        </div>
                        <form method="POST" id="formPermisos">
                            <div class="row">
                                <!-- Categorías Permitidas -->
                                <div class="col-md-6 mb-4">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">
                                                    <i class="bi bi-folder"></i> Categorías permitidas
                                                </h5>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-select-all" onclick="selectAllCategorias(true)">
                                                        <i class="bi bi-check-all"></i> Seleccionar todas
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-select-all" onclick="selectAllCategorias(false)">
                                                        <i class="bi bi-x"></i> Desmarcar todas
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body permission-card">
                                            <?php if ($categorias->num_rows > 0): ?>
                                                <?php while ($cat = $categorias->fetch_assoc()): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input categoria-checkbox" type="checkbox"
                                                            name="categorias[]"
                                                            value="<?= $cat['pk_categoria'] ?>"
                                                            id="cat_<?= $cat['pk_categoria'] ?>"
                                                            <?= in_array($cat['pk_categoria'], $cats_asignadas) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="cat_<?= $cat['pk_categoria'] ?>">
                                                            <?= htmlspecialchars($cat['categoria']) ?>
                                                        </label>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <p class="text-muted">No hay categorías disponibles</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer text-muted">
                                            <small>
                                                <i class="bi bi-check-circle text-success"></i>
                                                <span id="categorias-count"><?= count($cats_asignadas) ?></span> seleccionadas
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <!-- Etiquetas Permitidas -->
                                <div class="col-md-6 mb-4">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">
                                                    <i class="bi bi-tags"></i> Etiquetas permitidas
                                                </h5>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-select-all" onclick="selectAllEtiquetas(true)">
                                                        <i class="bi bi-check-all"></i> Seleccionar todas
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-select-all" onclick="selectAllEtiquetas(false)">
                                                        <i class="bi bi-x"></i> Desmarcar todas
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body permission-card">
                                            <?php if ($etiquetas->num_rows > 0): ?>
                                                <?php while ($etiq = $etiquetas->fetch_assoc()): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input etiqueta-checkbox" type="checkbox"
                                                            name="etiquetas[]"
                                                            value="<?= $etiq['pk_etiqueta'] ?>"
                                                            id="etiq_<?= $etiq['pk_etiqueta'] ?>"
                                                            <?= in_array($etiq['pk_etiqueta'], $etiqs_asignadas) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="etiq_<?= $etiq['pk_etiqueta'] ?>">
                                                            <?= htmlspecialchars($etiq['etiqueta']) ?>
                                                        </label>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <p class="text-muted">No hay etiquetas disponibles</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer text-muted">
                                            <small>
                                                <i class="bi bi-check-circle text-success"></i>
                                                <span id="etiquetas-count"><?= count($etiqs_asignadas) ?></span> seleccionadas
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save"></i> Guardar permisos
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAllCategorias(select) {
            document.querySelectorAll('.categoria-checkbox').forEach(checkbox => {
                checkbox.checked = select;
            });
            updateCount();
        }
        function selectAllEtiquetas(select) {
            document.querySelectorAll('.etiqueta-checkbox').forEach(checkbox => {
                checkbox.checked = select;
            });
            updateCount();
        }
        function updateCount() {
            const categoriasCount = document.querySelectorAll('.categoria-checkbox:checked').length;
            const etiquetasCount = document.querySelectorAll('.etiqueta-checkbox:checked').length;
            document.getElementById('categorias-count').textContent = categoriasCount;
            document.getElementById('etiquetas-count').textContent = etiquetasCount;
        }
        document.querySelectorAll('.categoria-checkbox, .etiqueta-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateCount);
        });
        document.getElementById('formPermisos').addEventListener('submit', function(e) {
            const categoriasSeleccionadas = document.querySelectorAll('.categoria-checkbox:checked').length;
            const etiquetasSeleccionadas = document.querySelectorAll('.etiqueta-checkbox:checked').length;
            if (categoriasSeleccionadas === 0 && etiquetasSeleccionadas === 0) {
                e.preventDefault();
                alert('Debes seleccionar al menos una categoría o una etiqueta para continuar.');
                return false;
            }
        });
    </script>
</body>
</html>
