<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
if (!$auth->isAdmin()) {
    header("Location: ../../login.php");
    exit();
}
$db = new Database();
$categorias = $db->query("SELECT pk_categoria, categoria, fk_estatus FROM cat_categorias ORDER BY categoria");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Gestión de categorías</h2>
                <?php if (isset($_GET['success'])){?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php }?>
                <?php if (isset($_GET['error'])){?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php }?>
                <div class="d-flex justify-content-between mb-3">
                    <a href="crear.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Nueva categoría
                    </a>
                </div>
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Estatus</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($categoria = $categorias->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($categoria['categoria']); ?></td>
                                            <td>
                                                <span class="badge bg-<?= ($categoria['fk_estatus'] == 1) ? 'primary' : 'secondary' ?>">
                                                    <?=$categoria['fk_estatus'] == 1 ? 'Activo' : 'Inactivo'?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="editar.php?id=<?= $categoria['pk_categoria'] ?>" class="btn btn-sm btn-success" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="#" class="btn btn-sm btn-danger delete" data-id="<?= $categoria['pk_categoria'] ?>" data-estatus="<?= $categoria['fk_estatus'] ?>" title="<?= ($categoria['fk_estatus'] == 1) ? 'Inactivar' : 'Activar' ?>">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $(".delete").on("click", function(event) {
                event.preventDefault();
                const id = $(this).data("id");
                const estatus = $(this).data("estatus");
                const action = (estatus == 1) ? "Inactivar" : "Activar";
                swal({
                    title: "¿Estás seguro?",
                    text: "Vas a " + action + " esta categoría.",
                    icon: "warning",
                    buttons: ["Cancelar", "Aceptar"],
                    dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        window.location.href = "eliminar.php?id=" + id + "&estatus=" + estatus;
                    }
                });
            });
        });
    </script>
</body>
</html>
