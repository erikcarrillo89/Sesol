<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

function obtenerUrlRegresoSolicitudes($default = 'index.php') {
    $returnUrl = $_GET['return_url'] ?? $default;
    return preg_match('/^index\.php(\?.*)?$/', $returnUrl) ? $returnUrl : $default;
}

if (!$auth->isLoggedIn()) {
    header("Location: ../../../login.php");
    exit();
}

$returnUrl = obtenerUrlRegresoSolicitudes();
$returnParam = urlencode($returnUrl);

if (!isset($_GET['solicitud_id']) || !is_numeric($_GET['solicitud_id'])) {
    header("Location: ../" . $returnUrl);
    exit();
}

$db = new Database();
$solicitud_id = (int)$_GET['solicitud_id'];

// Verificar que la solicitud existe
$solicitud = $db->query("SELECT id FROM solicitudes WHERE id = $solicitud_id")->fetch_assoc();

if (!$solicitud) {
    $separator = strpos($returnUrl, '?') === false ? '?' : '&';
    header("Location: ../" . $returnUrl . $separator . "error=Solicitud no encontrada");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_comentario = sanitizeInput($_POST['fecha_comentario']);
    $comentario = sanitizeInput($_POST['comentario']);
    $usuario_id = $_SESSION['user_id'];
    $archivo_nombre = null;

    if (empty($fecha_comentario) || empty($comentario)) {
        $error = "Todos los campos son obligatorios";
    } else {
        // Procesar archivo adjunto
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $permitidos = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/png'
            ];
            $maxSize = 1 * 1024 * 1024; // 1 MB

            if (in_array($_FILES['archivo']['type'], $permitidos) && $_FILES['archivo']['size'] <= $maxSize) {
                $nombre_tmp = $_FILES['archivo']['tmp_name'];
                $archivo_nombre = time() . "_" . basename($_FILES['archivo']['name']);
                $ruta_destino = "../../../uploads/comentarios/" . $archivo_nombre;

                if (!move_uploaded_file($nombre_tmp, $ruta_destino)) {
                    $error = "No se pudo guardar el archivo.";
                }
            } else {
                $error = "Archivo no permitido o excede el tamaño máximo de 1MB.";
            }
        }

        if (empty($error)) {
            $stmt = $db->prepare("INSERT INTO comentarios_seguimiento (solicitud_id, fecha_comentario, comentario, usuario_id, archivo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issis", $solicitud_id, $fecha_comentario, $comentario, $usuario_id, $archivo_nombre);

            if ($stmt->execute()) {
                $success = "Comentario agregado correctamente";
                header("Location: ../ver.php?id=$solicitud_id&return_url=$returnParam&success=" . urlencode($success));
                exit();
            } else {
                $error = "Error al agregar el comentario: " . $db->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Comentario - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../../includes/navbar.php'; ?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Agregar Comentario de Seguimiento</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="fecha_comentario" class="form-label">Fecha del comentario</label>
                            <input type="date" class="form-control" id="fecha_comentario" name="fecha_comentario" required
                                       value="<?php echo isset($_POST['fecha_comentario']) ? htmlspecialchars($_POST['fecha_comentario']) : date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="comentario" class="form-label">Comentario</label>
                            <textarea class="form-control" name="comentario" id="comentario" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="archivo" class="form-label">Adjuntar archivo (PDF, Word, Excel, PNG – máx 1MB)</label>
                            <input type="file" name="archivo" accept=".pdf,.docx,.xlsx,.png" onchange="validarTamano(this)">
<script>
  function validarTamano(input) {
    if (input.files[0].size > 1048576) {
      alert("El archivo no puede ser mayor a 1 MB.");
      input.value = ""; // Limpia el archivo
    }
  }
</script>
                        </div>
                        <div class="text-end">
                            <a href="../ver.php?id=<?php echo $solicitud_id; ?>&return_url=<?php echo $returnParam; ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Agregar Comentario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
