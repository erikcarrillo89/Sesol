<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if (!$auth->isAdmin()) {
    header("Location: ../../login.php");
    exit();
}

$db = new Database();
$usuarios = $db->query("SELECT id, username, nombre_completo, email, rol, activo FROM usuarios ORDER BY nombre_completo");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuarios - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Gestión de Usuarios</h2>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-3">
                    <a href="crear.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Nuevo Usuario
                    </a>
                </div>

                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Nombre Completo</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $usuario['id']; ?></td>
                                        <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td><?php echo htmlspecialchars(nombreRol($usuario['rol'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['activo'] ? 'success' : 'danger'; ?>">
                                                <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="editar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-warning" title="Editar" onclick="return confirmarEditarEnlace(event, this);">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($usuario['rol'] === 'especial'): ?>
                                            <a href="asignar_permisos.php?id=<?php echo $usuario['id']; ?>"
                                               class="btn btn-sm btn-info"
                                               title="Asignar permisos de categorías y etiquetas">
                                                <i class="bi bi-shield-check"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($usuario['username'] != 'admin.yuc25'): ?>
                                            <a href="eliminar.php?id=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirmarBorradoEnlace(event, this);">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <script>
function confirmarBorradoEnlace(event, enlace) {
    const clave = prompt("Para eliminar, escribe la palabra clave:");
    if (clave === "delete25") {
        return true; // se permite continuar al href
    } else {
        alert("Palabra clave incorrecta. No se eliminó el registro.");
        event.preventDefault(); // bloquea la navegación
        return false;
    }
}
</script>


        <script>
function confirmarEditarEnlace(event, enlace) {
    const clave = prompt("Para editar, escribe la palabra clave:");
    if (clave === "edit25") {
        return true; // se permite continuar al href
    } else {
        alert("Palabra clave incorrecta. No se eliminó el registro.");
        event.preventDefault(); // bloquea la navegación
        return false;
    }
}
</script>
</body>
</html>
