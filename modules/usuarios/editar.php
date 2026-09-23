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

// Obtener datos del usuario
$stmt = $db->prepare("SELECT id, username, nombre_completo, email, rol, activo FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = sanitizeInput($_POST['nombre_completo']);
    $email = sanitizeInput($_POST['email']);
    $rol = sanitizeInput($_POST['rol']);
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Actualizar contraseña solo si se proporcionó una nueva
    if (!empty($_POST['password'])) {
        $password = password_hash(sanitizeInput($_POST['password']), PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, activo = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nombre_completo, $email, $rol, $activo, $password, $id);
    } else {
        $stmt = $db->prepare("UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, activo = ? WHERE id = ?");
        $stmt->bind_param("sssii", $nombre_completo, $email, $rol, $activo, $id);
    }

    if ($stmt->execute()) {
        $success = "Usuario actualizado exitosamente";
        header("Location: index.php?success=" . urlencode($success));
        exit();
    } else {
        $error = "Error al actualizar el usuario: " . $db->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - <?php echo SITE_NAME; ?></title>
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
                        <h4 class="mb-0">Editar Usuario: <?php echo htmlspecialchars($usuario['username']); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Nombre de Usuario</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['username']); ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Nueva Contraseña (dejar en blanco para no cambiar)</label>
                                <input type="password" class="form-control" id="password" name="password">
                            </div>

                            <div class="mb-3">
                                <label for="nombre_completo" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="rol" class="form-label">Rol</label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="admin" <?php echo $usuario['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="usuario" <?php echo $usuario['rol'] === 'usuario' ? 'selected' : ''; ?>>Usuario</option>
                                    <option value="especial" <?php echo $usuario['rol'] === 'especial' ? 'selected' : ''; ?>>Especial</option>
                                    <option value="protocolo" <?php echo $usuario['rol'] === 'protocolo' ? 'selected' : ''; ?>>Protocolo</option>
                                    <option value="atencion_ciudadana" <?php echo $usuario['rol'] === 'atencion_ciudadana' ? 'selected' : ''; ?>>Atención Ciudadana</option>
                                    <option value="monitor" <?php echo $usuario['rol'] === 'monitor' ? 'selected' : ''; ?>>Monitor</option>
                                </select>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" <?php echo $usuario['activo'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activo">Usuario Activo</label>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
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
