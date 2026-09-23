
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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../" . $returnUrl);
    exit();
}

$db = new Database();
$id = (int)$_GET['id'];

// Obtener el comentario
$comentario = $db->query("SELECT c.*, s.id as solicitud_id 
                          FROM comentarios_seguimiento c
                          JOIN solicitudes s ON c.solicitud_id = s.id
                          WHERE c.id = $id")->fetch_assoc();

if (!$comentario) {
    $separator = strpos($returnUrl, '?') === false ? '?' : '&';
    header("Location: ../" . $returnUrl . $separator . "error=Comentario no encontrado");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_comentario = sanitizeInput($_POST['fecha_comentario']);
    $comentario_texto = sanitizeInput($_POST['comentario']);
    $archivo_nombre = $comentario['archivo']; // Por defecto, conservar el actual

    if (empty($fecha_comentario) || empty($comentario_texto)) {
        $error = "Todos los campos son obligatorios";
    } else {
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $permitidos = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/png'
            ];
            $maxSize = 1 * 1024 * 1024;

            if (in_array($_FILES['archivo']['type'], $permitidos) && $_FILES['archivo']['size'] <= $maxSize) {
                $nombre_tmp = $_FILES['archivo']['tmp_name'];
                $archivo_nuevo = time() . "_" . basename($_FILES['archivo']['name']);
                $ruta_destino = "../../../uploads/comentarios/" . $archivo_nuevo;

                if (move_uploaded_file($nombre_tmp, $ruta_destino)) {
                    // Eliminar anterior si existía
                    if (!empty($comentario['archivo']) && file_exists("../../../uploads/comentarios/" . $comentario['archivo'])) {
                        unlink("../../../uploads/comentarios/" . $comentario['archivo']);
                    }
                    $archivo_nombre = $archivo_nuevo;
                } else {
                    $error = "Error al guardar el nuevo archivo.";
                }
            } else {
                $error = "Archivo no permitido o mayor a 1MB.";
            }
        }

        if (empty($error)) {
            $stmt = $db->prepare("UPDATE comentarios_seguimiento SET fecha_comentario = ?, comentario = ?, archivo = ? WHERE id = ?");
            $stmt->bind_param("sssi", $fecha_comentario, $comentario_texto, $archivo_nombre, $id);

            if ($stmt->execute()) {
                $success = "Comentario actualizado correctamente";
                header("Location: ../ver.php?id=" . $comentario['solicitud_id'] . "&return_url=$returnParam&success=" . urlencode($success));
                exit();
            } else {
                $error = "Error al actualizar el comentario: " . $db->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Comentario - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <?php include '../../../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Editar Comentario de Seguimiento</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="fecha_comentario" class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fecha_comentario" name="fecha_comentario" required
                                       value="<?php echo isset($_POST['fecha_comentario']) ? htmlspecialchars($_POST['fecha_comentario']) : htmlspecialchars($comentario['fecha_comentario']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="comentario" class="form-label">Comentario <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="comentario" name="comentario" rows="5" required><?php 
                                    echo isset($_POST['comentario']) ? htmlspecialchars($_POST['comentario']) : htmlspecialchars($comentario['comentario']); 
                                ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="../ver.php?id=<?php echo $comentario['solicitud_id']; ?>&return_url=<?php echo $returnParam; ?>" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Actualizar Comentario</button>
                            </div>
                        
    <div class="mb-3">
        <label for="archivo" class="form-label">Reemplazar archivo adjunto (opcional)</label>
        <input type="file" name="archivo" id="archivo" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.png">
        <?php if (!empty($comentario['archivo'])): ?>
            <div class="mt-2">
                <a href="../../../uploads/comentarios/<?php echo $comentario['archivo']; ?>" target="_blank">Ver archivo actual</a>
            </div>
        <?php endif; ?>
    </div>
    
    
    <script>
document.getElementById('archivo').addEventListener('change', function () {
    const maxSize = 1 * 1024 * 1024; // 1 MB
    if (this.files[0] && this.files[0].size > maxSize) {
        alert("⚠️ El archivo no puede superar 1 MB.");
        this.value = ""; // Limpiar input
    }
});
</script>
    </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        flatpickr.localize(flatpickr.l10ns.es);
        flatpickr("#fecha_comentario", {
            dateFormat: "Y-m-d",
            allowInput: true,
            locale: "es"
        });
    </script>
</body>
</html>
