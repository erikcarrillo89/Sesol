<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}

if (($_SESSION['rol'] ?? '') === 'monitor') {
    header("Location: index.php?error=" . urlencode("No tienes permisos para agregar nuevas solicitudes."));
    exit();
}

$db = new Database();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y sanitizar datos del formulario
    $categoria = sanitizeInput($_POST['categoria']);
    $cct = sanitizeInput($_POST['cct']);
    $N_MUNICIPIO = sanitizeInput($_POST['N_MUNICIPIO'] ?? '');
    $N_LOCALIDAD = sanitizeInput($_POST['N_LOCALIDAD'] ?? '');
    $NOMBRECT = sanitizeInput($_POST['NOMBRECT'] ?? '');
    $solicitante = sanitizeInput($_POST['solicitante']);
    $ap1 = sanitizeInput($_POST['ap1']);
    $ap2 = sanitizeInput($_POST['ap2']);
    $fecha_peticion = sanitizeInput($_POST['fecha_peticion']);
    $tipo_procedencia = sanitizeInput($_POST['tipo_procedencia']);
    $compromiso = sanitizeInput($_POST['compromiso']);
    $instruido = sanitizeInput($_POST['instruido']);
    $solicitado = sanitizeInput($_POST['solicitado']);
    $atendio = sanitizeInput($_POST['atendio']);
    $telefono = sanitizeInput($_POST['telefono']);
    $descripcion = sanitizeInput($_POST['descripcion']);
    $fecha_seguimiento = sanitizeInput($_POST['fecha_seguimiento']);
    $responsable_seguimiento = sanitizeInput($_POST['responsable_seguimiento']);
    $indicaciones_secretario = sanitizeInput($_POST['indicaciones_secretario']);
    $fecha_vencimiento = sanitizeInput($_POST['fecha_vencimiento']);
    $prioridad = sanitizeInput($_POST['prioridad']);
    $estado = sanitizeInput($_POST['estado']);

    // Validar si se intenta crear como "Concluida" con tareas
    if ($estado === 'Concluida' && isset($_POST['tarea_usuario']) && is_array($_POST['tarea_usuario'])) {
        $tiene_tareas = false;
        foreach ($_POST['tarea_usuario'] as $i => $usuario) {
            if (!empty($usuario) && !empty($_POST['tarea_texto'][$i])) {
                $tiene_tareas = true;
                break;
            }
        }
        if ($tiene_tareas) {
            $error = "No se puede crear una solicitud con estado Concluida si tiene tareas asignadas. Las tareas nuevas siempre inician como pendientes.";
        }
    }

    $fecha_seguimiento = $fecha_seguimiento !== '' ? $fecha_seguimiento : null;
    $fecha_vencimiento = $fecha_vencimiento !== '' ? $fecha_vencimiento : null;
    $asignado_id = isset($_POST['asignado_id']) && $_POST['asignado_id'] !== '' ? (int)$_POST['asignado_id'] : null;
    $asignado2_id = isset($_POST['asignado2_id']) && $_POST['asignado2_id'] !== '' ? (int)$_POST['asignado2_id'] : null;
    $asignado3_id = isset($_POST['asignado3_id']) && $_POST['asignado3_id'] !== '' ? (int)$_POST['asignado3_id'] : null;
    $asignado4_id = isset($_POST['asignado4_id']) && $_POST['asignado4_id'] !== '' ? (int)$_POST['asignado4_id'] : null;
    $asignado5_id = isset($_POST['asignado5_id']) && $_POST['asignado5_id'] !== '' ? (int)$_POST['asignado5_id'] : null;
    $usuario_id = $_SESSION['user_id'];

    if ($cct !== '') {
        $cct = strtoupper($cct);
        $stmt_cct = $db->prepare("SELECT N_MUNICIPIO, N_LOCALIDAD, NOMBRECT FROM cct WHERE CLAVECCT = ? LIMIT 1");
        $stmt_cct->bind_param("s", $cct);
        $stmt_cct->execute();
        $cct_result = $stmt_cct->get_result();
        if ($cct_data = $cct_result->fetch_assoc()) {
            $N_MUNICIPIO = $cct_data['N_MUNICIPIO'] ?? '';
            $N_LOCALIDAD = $cct_data['N_LOCALIDAD'] ?? '';
            $NOMBRECT = $cct_data['NOMBRECT'] ?? '';
        } else {
            $N_MUNICIPIO = '';
            $N_LOCALIDAD = '';
            $NOMBRECT = '';
        }
        $stmt_cct->close();
    }

    // Validar campos obligatorios
    if (!empty($error)) {
        // Ya hay un error de validación
    } elseif (empty($categoria) || empty($solicitante) || empty($fecha_peticion) || empty($compromiso) || empty($descripcion) || empty($asignado_id)) {
        $error = "Los campos marcados con * son obligatorios";
    } elseif (!empty($telefono) && !preg_match('/^\d{10}$/', $telefono)) {
        $error = "El teléfono debe tener exactamente 10 dígitos";
    } else {
        // Generar folio automático
        $folio = generarFolio();

        // Insertar la solicitud en la base de datos
        $stmt = $db->prepare("INSERT INTO solicitudes (
            folio,
            fk_categoria,
            cct,
            N_MUNICIPIO,
            N_LOCALIDAD,
            NOMBRECT,
            solicitante,
            ap1,
            ap2,
            fecha_peticion,
            tipo_procedencia,
            compromiso,
            instruido,
            solicitado,
            atendio,
            telefono,
            descripcion,
            fecha_seguimiento,
            responsable_seguimiento,
            indicaciones_secretario,
            fecha_vencimiento,
            prioridad,
            estado,
            asignado_id,
            asignado2_id,
            asignado3_id,
            asignado4_id,
            asignado5_id,
            usuario_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sisssssssssssssssssssssiiiiii",
            $folio,
            $categoria,
            $cct,
            $N_MUNICIPIO,
            $N_LOCALIDAD,
            $NOMBRECT,
            $solicitante,
            $ap1,
            $ap2,
            $fecha_peticion,
            $tipo_procedencia,
            $compromiso,
            $instruido,
            $solicitado,
            $atendio,
            $telefono,
            $descripcion,
            $fecha_seguimiento,
            $responsable_seguimiento,
            $indicaciones_secretario,
            $fecha_vencimiento,
            $prioridad,
            $estado,
            $asignado_id,
            $asignado2_id,
            $asignado3_id,
            $asignado4_id,
            $asignado5_id,
            $usuario_id
        );

        if ($stmt->execute()) {
            // Obtener el último ID autoincremental insertado para la tabla solicitudes
            $solicitud_id = $db->getLastInsertId();
            // Aquí puedes realizar acciones adicionales después de la inserción, como enviar notificaciones
            $etiquetas_seleccionadas = isset($_POST['etiquetas']) ? $_POST['etiquetas'] : [];
            if (count($etiquetas_seleccionadas) > 0) {
                foreach ($etiquetas_seleccionadas as $etiqueta_id) {
                    $stmt2 = $db->prepare("INSERT INTO solicitudes_rel (fk_solicitud, fk_etiqueta) VALUES (?, ?)");
                    $stmt2->bind_param("ii", $solicitud_id, $etiqueta_id);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }

            // Guardar tareas asignadas
            if (isset($_POST['tarea_usuario']) && is_array($_POST['tarea_usuario'])) {
                $tareas_usuarios = $_POST['tarea_usuario'];
                $tareas_textos = $_POST['tarea_texto'];

                for ($i = 0; $i < count($tareas_usuarios); $i++) {
                    if (!empty($tareas_usuarios[$i]) && !empty($tareas_textos[$i])) {
                        $stmt_tarea = $db->prepare("INSERT INTO solicitudes_tareas (fk_solicitud, fk_usuario, tarea, fecha, fk_estatus) VALUES (?, ?, ?, NOW(), 0)");
                        $stmt_tarea->bind_param("iis", $solicitud_id, $tareas_usuarios[$i], $tareas_textos[$i]);
                        $stmt_tarea->execute();
                        $stmt_tarea->close();
                    }
                }
            }

            $success = "Solicitud creada exitosamente con folio: $folio";
            header("Location: index.php?success=" . urlencode($success));
            exit();
        } else {
            $error = "Error al crear la solicitud: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php


    $query = "SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo ASC";
    $result = $db->query($query);

    $usuario_id = $_SESSION['user_id'];
    $usuario_rol = $_SESSION['rol'];

    // Para crear.php, el usuario siempre es el creador, por lo que puede editar
    $puede_editar_categoria = true;

    // Obtener categorías según el rol
    if ($usuario_rol === 'especial') {
        // Solo categorías asignadas al usuario especial
        $categorias = $db->query("
            SELECT c.pk_categoria, c.categoria
            FROM cat_categorias c
            INNER JOIN usuarios_categorias uc ON c.pk_categoria = uc.fk_categoria
            WHERE uc.fk_usuario = $usuario_id
            AND c.fk_estatus = 1
            ORDER BY c.categoria
        ");
    } elseif ($usuario_rol === 'atencion_ciudadana') {
        $categorias = $db->query("
            SELECT pk_categoria, categoria
            FROM cat_categorias
            WHERE fk_estatus = 1
            AND LOWER(TRIM(categoria)) IN ('atención ciudadana', 'atencion ciudadana')
            ORDER BY categoria
        ");
    } else {
        // Admin y usuario ven todas
        $categorias = $db->query("SELECT pk_categoria, categoria FROM cat_categorias WHERE fk_estatus = 1 ORDER BY categoria");
    }    // Obtener etiquetas según el rol
    if ($usuario_rol === 'especial') {
        // Solo etiquetas asignadas al usuario especial
        $etiquetas = $db->query("
            SELECT e.pk_etiqueta, e.etiqueta
            FROM cat_etiquetas e
            INNER JOIN usuarios_etiquetas ue ON e.pk_etiqueta = ue.fk_etiqueta
            WHERE ue.fk_usuario = $usuario_id
            AND e.fk_estatus = 1
            ORDER BY e.etiqueta
        ");
    } elseif ($usuario_rol === 'atencion_ciudadana') {
        $etiquetas = $db->query("
            SELECT pk_etiqueta, etiqueta
            FROM cat_etiquetas
            WHERE fk_estatus = 1
            AND LOWER(TRIM(etiqueta)) IN ('atención ciudadana', 'atencion ciudadana')
            ORDER BY etiqueta
        ");
    } else {
        // Admin y usuario ven todas
        $etiquetas = $db->query("SELECT pk_etiqueta, etiqueta FROM cat_etiquetas WHERE fk_estatus = 1 ORDER BY etiqueta");
    }
    ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white" style="background-color: #8D2D44 !important;">
                        <h4 class="mb-0">Nueva Solicitud</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" id="formCrear">
                            <div class="row g-3">
                                <!-- Primera columna -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="categoria" class="form-label">Categoría <span class="text-danger">*</span></label>
                                        <select class="form-control" id="categoria" name="categoria" required>
                                            <option value="">Selecciona una opción</option>
                                            <?php while ($categoria = $categorias->fetch_assoc()) { ?>
                                                <option value="<?= $categoria['pk_categoria'] ?>" <?= (isset($_POST['categoria']) && $_POST['categoria'] == $categoria['pk_categoria']) ? 'selected' : '' ?>><?= $categoria['categoria'] ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="cct" class="form-label">CCT</label>
                                        <select class="form-control upper" id="cct" name="cct">
                                            <?php if (!empty($_POST['cct'])) { ?>
                                                <option value="<?= htmlspecialchars($_POST['cct']) ?>" selected><?= htmlspecialchars($_POST['cct']) ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="hidden" id="N_MUNICIPIO" name="N_MUNICIPIO" value="<?= isset($_POST['N_MUNICIPIO']) ? htmlspecialchars($_POST['N_MUNICIPIO']) : '' ?>">
                                        <input type="hidden" id="N_LOCALIDAD" name="N_LOCALIDAD" value="<?= isset($_POST['N_LOCALIDAD']) ? htmlspecialchars($_POST['N_LOCALIDAD']) : '' ?>">
                                        <input type="hidden" id="NOMBRECT" name="NOMBRECT" value="<?= isset($_POST['NOMBRECT']) ? htmlspecialchars($_POST['NOMBRECT']) : '' ?>">
                                        <div id="cctInfo" class="form-text">
                                            <?php
                                            if (!empty($_POST['NOMBRECT'])) {
                                                echo htmlspecialchars($_POST['NOMBRECT'] . ' - ' . ($_POST['N_MUNICIPIO'] ?? '') . ', ' . ($_POST['N_LOCALIDAD'] ?? ''));
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="solicitante" class="form-label">Solicitante(Nombre) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="solicitante" name="solicitante" required
                                            value="<?php echo isset($solicitud['solicitante']) ? htmlspecialchars($solicitud['solicitante']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="ap1" class="form-label">Primer apellido</label>
                                        <input type="text" class="form-control" id="ap1" name="ap1" autocomplete="off"
                                            value="<?php echo isset($solicitud['ap1']) ? htmlspecialchars($solicitud['ap1']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="ap2" class="form-label">Segundo apellido</label>
                                        <input type="text" class="form-control" id="ap2" name="ap2" autocomplete="off"
                                            value="<?php echo isset($solicitud['ap2']) ? htmlspecialchars($solicitud['ap2']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="fecha_peticion" class="form-label">Fecha de Petición <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="fecha_peticion" name="fecha_peticion" required
                                            value="<?php echo isset($_POST['fecha_peticion']) ? htmlspecialchars($_POST['fecha_peticion']) : date('Y-m-d'); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="tipo_procedencia" class="form-label">Tipo de Procedencia</label>
                                        <input type="text" class="form-control" id="tipo_procedencia" name="tipo_procedencia"
                                            value="<?php echo isset($_POST['tipo_procedencia']) ? htmlspecialchars($_POST['tipo_procedencia']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="compromiso" class="form-label">Compromiso o Asunto <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="compromiso" name="compromiso" rows="3" onkeyup="this.value = this.value.toUpperCase()" required><?php echo isset($_POST['compromiso']) ? htmlspecialchars($_POST['compromiso']) : ''; ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="instruido" class="form-label">Instruido</label>
                                        <input type="text" class="form-control" id="instruido" name="instruido"
                                            value="<?php echo isset($_POST['instruido']) ? htmlspecialchars($_POST['instruido']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="solicitado" class="form-label">Solicitado</label>
                                        <input type="text" class="form-control" id="solicitado" name="solicitado"
                                            value="<?php echo isset($_POST['solicitado']) ? htmlspecialchars($_POST['solicitado']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="asignado_id" class="form-label">Asignado<span class="text-danger">*</span></label>
                                        <select class="form-control" id="asignado_id" name="asignado_id" required>
                                            <option value="">-- Seleccionar usuario --</option>
                                            <?php
                                            $db = new Database();
                                            $usuarios = $db->query("SELECT id, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo");
                                            while ($usuario = $usuarios->fetch_assoc()) {
                                                $selected = ($usuario['id'] == ($solicitud['asignado_id'] ?? '')) ? 'selected' : '';
                                                echo '<option value="' . $usuario['id'] . '" ' . $selected . '>' . htmlspecialchars($usuario['nombre_completo']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="asignado2_id" class="form-label">Informado 1: </label>
                                        <select class="form-select" id="asignado2_id" name="asignado2_id">
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($usuarios as $usuario): ?>
                                                <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nombre_completo']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="asignado3_id" class="form-label">Informado 2: </label>
                                        <select class="form-select" id="asignado3_id" name="asignado3_id">
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($usuarios as $usuario): ?>
                                                <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nombre_completo']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="asignado4_id" class="form-label">Informado 3: </label>
                                        <select class="form-select" id="asignado4_id" name="asignado4_id">
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($usuarios as $usuario) { ?>
                                                <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nombre_completo']) ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="asignado5_id" class="form-label">Informado 4: </label>
                                        <select class="form-select" id="asignado5_id" name="asignado5_id">
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($usuarios as $usuario) { ?>
                                                <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nombre_completo']) ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Segunda columna -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="atendio" class="form-label">Atendió</label>
                                        <input type="text" class="form-control" id="atendio" name="atendio"
                                            value="<?php echo isset($_POST['atendio']) ? htmlspecialchars($_POST['atendio']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Teléfono (10 dígitos)</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="10"
                                            value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="descripcion" class="form-label">Descripción <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" onkeyup="this.value = this.value.toUpperCase()" required><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="fecha_seguimiento" class="form-label">Fecha para Seguimiento</label>
                                        <input type="date" class="form-control" id="fecha_seguimiento" name="fecha_seguimiento"
                                            value="<?php echo isset($_POST['fecha_seguimiento']) ? htmlspecialchars($_POST['fecha_seguimiento']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="responsable_seguimiento" class="form-label">Responsable del Seguimiento</label>
                                        <input type="text" class="form-control" id="responsable_seguimiento" name="responsable_seguimiento"
                                            value="<?php echo isset($_POST['responsable_seguimiento']) ? htmlspecialchars($_POST['responsable_seguimiento']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="indicaciones_secretario" class="form-label">Indicaciones Secretario</label>
                                        <textarea class="form-control" id="indicaciones_secretario" name="indicaciones_secretario" rows="2"><?php echo isset($_POST['indicaciones_secretario']) ? htmlspecialchars($_POST['indicaciones_secretario']) : ''; ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="fecha_vencimiento" class="form-label">Fecha Vencimiento</label>
                                        <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento"
                                            value="<?php echo isset($_POST['fecha_vencimiento']) ? htmlspecialchars($_POST['fecha_vencimiento']) : ''; ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="prioridad" class="form-label">Prioridad <span class="text-danger">*</span></label>
                                            <select class="form-select" id="prioridad" name="prioridad" required>
                                                <option value="Baja" <?php echo (isset($_POST['prioridad']) && $_POST['prioridad'] === 'Baja') ? 'selected' : ''; ?>>Baja</option>
                                                <option value="Alta" <?php echo (isset($_POST['prioridad']) && $_POST['prioridad'] === 'Alta') ? 'selected' : ''; ?>>Alta</option>
                                                <option value="Media" <?php echo (isset($_POST['prioridad']) && $_POST['prioridad'] === 'Media') ? 'selected' : ''; ?>>Media</option>

                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                                            <select class="form-select" id="estado" name="estado" required>
                                                <option value="No iniciada" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'No iniciada') ? 'selected' : ''; ?>>No iniciada</option>
                                                <option value="En proceso" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'En proceso') ? 'selected' : ''; ?>>En proceso</option>
                                                <option value="Concluida" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Concluida') ? 'selected' : ''; ?>>Concluida</option>
                                                <option value="Pendiente" <?php echo (isset($_POST['estado']) && $_POST['estado'] === 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="etiquetas" class="form-label">Etiquetas</label>
                                            <select class="form-select" id="etiquetas" name="etiquetas[]" multiple>
                                                <?php while ($etiqueta = $etiquetas->fetch_assoc()) { ?>
                                                    <option value="<?= $etiqueta['pk_etiqueta'] ?>"><?= $etiqueta['etiqueta'] ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Sección de Tareas -->
                                    <!-- El usuario que crea la solicitud puede asignar tareas -->
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
                                                <div>
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <strong>Asignación de tareas</strong>
                                                </div>
                                            </div>

                                            <button type="button" class="btn btn-sm btn-success" id="btnNuevaTarea">
                                                <i class="bi bi-plus-circle"></i> Nueva tarea
                                            </button>
                                            <div id="tareasContainer" class="mt-3">
                                                <!-- Las tareas se agregarán dinámicamente aquí -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                <a href="index.php" class="btn btn-secondary me-md-2">Cancelar</a>
                                <button type="submit" class="btn btn-primary" style="background-color: #8D2D44 !important;">Guardar Solicitud</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script>
        // Validación del formulario antes de enviar
        document.getElementById('formCrear').addEventListener('submit', function(e) {
            const estadoSelect = document.getElementById('estado');
            if (estadoSelect.value === 'Concluida') {
                // Verificar si hay tareas agregadas
                const tareasAgregadas = document.querySelectorAll('.tarea-item');
                if (tareasAgregadas.length > 0) {
                    e.preventDefault();
                    swal({
                        title: "No se puede crear así",
                        text: "No se puede crear una solicitud con estado Concluida si tiene tareas asignadas. Las tareas nuevas siempre inician como pendientes.",
                        icon: "warning",
                        button: "Entendido"
                    });
                    return false;
                }
            }
        });

        // Configuración de flatpickr para fechas
        flatpickr.localize(flatpickr.l10ns.es);
        flatpickr("#fecha_peticion, #fecha_seguimiento, #fecha_vencimiento", {
            dateFormat: "Y-m-d",
            allowInput: true,
            locale: "es"
        });

        // Validación del teléfono
        document.getElementById('telefono').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        $(document).ready(function() {
            $('#cct').select2({
                ajax: {
                    url: 'buscar_cct.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return data;
                    },
                    cache: true
                },
                tags: true,
                minimumInputLength: 2,
                placeholder: "Teclea o selecciona un CCT",
                language: {
                    inputTooShort: function() {
                        return "Teclea al menos 2 caracteres";
                    },
                    noResults: function() {
                        return "No se encontraron resultados";
                    },
                    searching: function() {
                        return "Buscando...";
                    }
                },
                templateResult: function(item) {
                    return item.text || '';
                },
                templateSelection: function(item) {
                    return item.id || item.text || '';
                }
            });

            $('#cct').on('select2:select', function(e) {
                const data = e.params.data || {};
                const cct = (data.id || '').toUpperCase();
                const municipio = data.N_MUNICIPIO || '';
                const localidad = data.N_LOCALIDAD || '';
                const nombrect = data.NOMBRECT || '';

                if (data.id && data.id !== cct) {
                    const option = new Option(cct, cct, true, true);
                    $('#cct').append(option);
                }
                $('#cct').val(cct).trigger('change.select2');
                $('#N_MUNICIPIO').val(municipio);
                $('#N_LOCALIDAD').val(localidad);
                $('#NOMBRECT').val(nombrect);
                $('#cctInfo').text(nombrect ? `${nombrect} - ${municipio}, ${localidad}` : '');
            });

            $('#cct').on('select2:clear change', function() {
                const selected = $('#cct').select2('data')[0] || {};
                if (!selected.NOMBRECT) {
                    $('#N_MUNICIPIO, #N_LOCALIDAD, #NOMBRECT').val('');
                    $('#cctInfo').text('');
                }
            });

            $('#etiquetas').select2({
                placeholder: "Selecciona la(s) etiqueta(s)",
                language: {
                    noResults: function() {
                        return "No se encontraron resultados";
                    },
                    removeItem: function() {
                        return "Borrar";
                    }
                }
            });
        });

        // Gestión de tareas dinámicas
        let tareaCounter = 0;
        const usuarios = <?php
                            $db_usuarios = new Database();
                            $usuarios_result = $db_usuarios->query("SELECT id, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo");
                            $usuarios_array = [];
                            while ($u = $usuarios_result->fetch_assoc()) {
                                $usuarios_array[] = $u;
                            }
                            echo json_encode($usuarios_array);
                            ?>;

        // Agregar nueva tarea
        $('#btnNuevaTarea').click(function() {
            tareaCounter++;
            const tareaHTML = `
                <div class="card mb-3 tarea-item" data-tarea-id="${tareaCounter}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Usuario asignado</label>
                                <select class="form-control tarea-usuario" name="tarea_usuario[]" required>
                                    <option value="">-- Seleccionar usuario --</option>
                                    ${usuarios.map(u => `<option value="${u.id}">${u.nombre_completo}</option>`).join('')}
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Descripción de la tarea</label>
                                <textarea class="form-control tarea-texto" name="tarea_texto[]" rows="2"
                                    style="text-transform: uppercase;" required></textarea>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm btnEliminarTarea"
                                    data-tarea-id="${tareaCounter}"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Eliminar tarea">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#tareasContainer').append(tareaHTML);

            // Inicializar Select2 para el nuevo select
            $(`.tarea-item[data-tarea-id="${tareaCounter}"] .tarea-usuario`).select2({
                placeholder: "-- Seleccionar usuario --",
                language: {
                    noResults: function() {
                        return "No se encontraron resultados";
                    }
                }
            });

            // Inicializar tooltip para el botón eliminar
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Convertir texto a mayúsculas
            $(`.tarea-item[data-tarea-id="${tareaCounter}"] .tarea-texto`).on('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
        $(document).on('click', '.btnEliminarTarea', function() {
            const tareaId = $(this).data('tarea-id');
            const tareaCard = $(`.tarea-item[data-tarea-id="${tareaId}"]`);
            const tooltipElement = this;
            const tooltipInstance = bootstrap.Tooltip.getInstance(tooltipElement);
            if (tooltipInstance) {
                tooltipInstance.dispose();
            }

            tareaCard.remove();
        });
    </script>
</body>

</html>
