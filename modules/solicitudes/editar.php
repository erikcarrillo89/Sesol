    <style type="text/css">
        .swal-button {
            padding: 10px 24px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .dropdown-estatus .dropdown-menu {
            min-width: 180px;
        }

        .dropdown-estatus .dropdown-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            cursor: pointer;
        }

        .dropdown-estatus .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-estatus .dropdown-item i {
            margin-right: 8px;
        }

        .swal-button--cancel {
            background-color: #6c757d !important;
            color: white !important;
            border: none !important;
        }

        .swal-button--cancel:hover {
            background-color: #5a6268 !important;
        }

        .swal-button--cancel:active {
            background-color: #545b62 !important;
        }

        .swal-button--confirm {
            background-color: #198754 !important;
            color: white !important;
            border: none !important;
        }

        .swal-button--confirm:hover {
            background-color: #218838 !important;
        }

        .swal-button--confirm:active {
            background-color: #198754 !important;
        }

        .swal-button:not(.swal-button--cancel):not(.swal-button--confirm) {
            background-color: #198754 !important;
            color: white !important;
        }

        .swal-button:not(.swal-button--cancel):not(.swal-button--confirm):hover {
            background-color: #218838 !important;
        }

        :root {
            --morena-guinda: #8D2D44;
            --morena-guinda-dark: #5f1830;
            --morena-border: #e9dfdf;
        }

        body {
            background: #f7f5f2;
        }

        .solicitud-card {
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(43, 32, 36, .07);
        }

        .solicitud-header {
            background: #fff;
            border-bottom: 1px solid var(--morena-border);
            border-left: 5px solid var(--morena-guinda);
            color: var(--morena-guinda-dark);
            padding: 16px 18px;
        }

        .solicitud-header h4 {
            font-size: 1.15rem;
            font-weight: 800;
        }

        .btn-morena {
            background-color: var(--morena-guinda) !important;
            border: 1px solid var(--morena-guinda) !important;
            color: #fff !important;
            font-weight: 700;
            box-shadow: 0 2px 0 rgba(95, 24, 48, .35);
        }

        .btn-morena:hover,
        .btn-morena:focus {
            background-color: var(--morena-guinda-dark) !important;
            border-color: var(--morena-guinda-dark) !important;
            color: #fff !important;
            box-shadow: 0 3px 0 rgba(95, 24, 48, .38);
        }

        .btn-morena:active {
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, .18);
        }
    </style>
    <?php
    require_once '../../includes/config.php';
    require_once '../../includes/auth.php';
    require_once '../../includes/functions.php';

    function obtenerUrlRegresoSolicitudes($default = 'index.php') {
        $returnUrl = $_GET['return_url'] ?? $default;
        return preg_match('/^index\.php(\?.*)?$/', $returnUrl) ? $returnUrl : $default;
    }

    if (!$auth->isLoggedIn()) {
        header("Location: ../../login.php");
        exit();
    }
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: index.php");
        exit();
    }
    $db = new Database();
    $id = (int)$_GET['id'];
    $returnUrl = obtenerUrlRegresoSolicitudes();
    $usuario_rol = $_SESSION['rol'];
    $usuario_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM solicitudes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitud = $result->fetch_assoc();
    if (!$solicitud) {
        header("Location: index.php");
        exit();
    }

    if ($usuario_rol === 'atencion_ciudadana') {
        $condicionAtencionCiudadana = condicionAtencionCiudadanaSQL('s', 'c');
        $stmtPermisoAtencion = $db->prepare("SELECT 1
            FROM solicitudes AS s
            INNER JOIN cat_categorias AS c ON c.pk_categoria = s.fk_categoria
            WHERE s.id = ?
            AND (s.eliminado IS NULL OR s.eliminado = 0)
            AND {$condicionAtencionCiudadana}
            LIMIT 1");
        $stmtPermisoAtencion->bind_param("i", $id);
        $stmtPermisoAtencion->execute();
        if ($stmtPermisoAtencion->get_result()->num_rows === 0) {
            header("Location: index.php?error=" . urlencode("No tienes permiso para editar esta solicitud"));
            exit();
        }
    }

    $usuarios_relacionados = array_filter([
        $solicitud['asignado_id'] ?? null,
        $solicitud['asignado2_id'] ?? null,
        $solicitud['asignado3_id'] ?? null,
        $solicitud['asignado4_id'] ?? null,
        $solicitud['asignado5_id'] ?? null
    ]);
    $es_admin = ($usuario_rol === 'admin');
    $es_monitor = ($usuario_rol === 'monitor');
    $es_creador = ($solicitud['usuario_id'] == $usuario_id);
    $es_asignado = in_array($usuario_id, $usuarios_relacionados, true);
    $error = '';
    $success = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $es_monitor) {
        $error = "No tienes permisos de edición.";
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$es_monitor) {
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
        if ($estado === 'Concluida') {
            $stmt_check = $db->prepare("SELECT COUNT(*) as no_concluidas FROM solicitudes_tareas WHERE fk_solicitud = ? AND fk_estatus != 2");
            $stmt_check->bind_param("i", $id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $check = $result_check->fetch_assoc();
            if ($check['no_concluidas'] > 0) {
                $error = 'No se puede marcar como "Concluida" mientras existan tareas sin finalizar. Por favor, concluye todas las tareas asignadas primero.';
            }
            $stmt_check->close();
        }
        $fecha_seguimiento = $fecha_seguimiento !== '' ? $fecha_seguimiento : null;
        $fecha_vencimiento = $fecha_vencimiento !== '' ? $fecha_vencimiento : null;
        $asignado_id = isset($_POST['asignado_id']) && $_POST['asignado_id'] !== '' ? (int)$_POST['asignado_id'] : null;
        $asignado2_id = isset($_POST['asignado2_id']) && $_POST['asignado2_id'] !== '' ? (int)$_POST['asignado2_id'] : null;
        $asignado3_id = isset($_POST['asignado3_id']) && $_POST['asignado3_id'] !== '' ? (int)$_POST['asignado3_id'] : null;
        $asignado4_id = isset($_POST['asignado4_id']) && $_POST['asignado4_id'] !== '' ? (int)$_POST['asignado4_id'] : null;
        $asignado5_id = isset($_POST['asignado5_id']) && $_POST['asignado5_id'] !== '' ? (int)$_POST['asignado5_id'] : null;
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
        } else {
            $N_MUNICIPIO = '';
            $N_LOCALIDAD = '';
            $NOMBRECT = '';
        }
        if (!empty($error)) {
        } elseif (empty($categoria) || empty($solicitante) || empty($fecha_peticion) || empty($compromiso) || empty($descripcion) || empty($asignado_id)) {
            $error = "Los campos marcados con * son obligatorios";
        } elseif (!empty($telefono) && !preg_match('/^\d{10}$/', $telefono)) {
            $error = "El teléfono debe tener exactamente 10 dígitos";
        } else {
            $stmt = $db->prepare("UPDATE solicitudes SET
            fk_categoria = ?, cct = ?, N_MUNICIPIO = ?, N_LOCALIDAD = ?, NOMBRECT = ?, solicitante = ?, ap1 = ?, ap2 = ?, fecha_peticion = ?, tipo_procedencia = ?, compromiso = ?,
            instruido = ?, solicitado = ?, atendio = ?, telefono = ?, descripcion = ?,
            fecha_seguimiento = ?, responsable_seguimiento = ?, indicaciones_secretario = ?,
            fecha_vencimiento = ?, prioridad = ?, estado = ?, asignado_id = ?, asignado2_id = ?, asignado3_id =?, asignado4_id =?, asignado5_id =?
            WHERE id = ?");
            $stmt->bind_param(
                "isssssssssssssssssssssiiiiii",
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
                $id
            );
            if ($stmt->execute()) {
                $stmt2 = $db->prepare("DELETE FROM solicitudes_rel WHERE fk_solicitud = ?");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
                $etiquetas_seleccionadas_insert = isset($_POST['etiquetas']) ? $_POST['etiquetas'] : [];
                if (count($etiquetas_seleccionadas_insert) > 0) {
                    foreach ($etiquetas_seleccionadas_insert as $etiqueta_id) {
                        $stmt2 = $db->prepare("INSERT INTO solicitudes_rel (fk_solicitud, fk_etiqueta) VALUES (?, ?)");
                        $stmt2->bind_param("ii", $id, $etiqueta_id);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
                if (isset($_POST['tarea_usuario']) && is_array($_POST['tarea_usuario'])) {
                    $tareas_usuarios = $_POST['tarea_usuario'];
                    $tareas_textos = $_POST['tarea_texto'];
                    for ($i = 0; $i < count($tareas_usuarios); $i++) {
                        if (!empty($tareas_usuarios[$i]) && !empty($tareas_textos[$i])) {
                            $stmt_tarea = $db->prepare("INSERT INTO solicitudes_tareas (fk_solicitud, fk_usuario, tarea, fecha, fk_estatus) VALUES (?, ?, ?, NOW(), 0)");
                            $stmt_tarea->bind_param("iis", $id, $tareas_usuarios[$i], $tareas_textos[$i]);
                            $stmt_tarea->execute();
                            $stmt_tarea->close();
                        }
                    }
                }
                if (isset($_POST['tarea_existente_usuario']) && is_array($_POST['tarea_existente_usuario'])) {
                    foreach ($_POST['tarea_existente_usuario'] as $tarea_id => $usuario_id) {
                        if (!empty($usuario_id) && is_numeric($tarea_id)) {
                            $stmt_update = $db->prepare("UPDATE solicitudes_tareas SET fk_usuario = ? WHERE pk_tarea = ? AND fk_solicitud = ?");
                            $stmt_update->bind_param("iii", $usuario_id, $tarea_id, $id);
                            $stmt_update->execute();
                            $stmt_update->close();
                        }
                    }
                }
                if (($usuario_rol === 'admin' || $solicitud['usuario_id'] == $usuario_id) && isset($_POST['tarea_existente_descripcion']) && is_array($_POST['tarea_existente_descripcion'])) {
                    foreach ($_POST['tarea_existente_descripcion'] as $tarea_id => $descripcion) {
                        if (!empty($descripcion) && is_numeric($tarea_id)) {
                            $stmt_update_desc = $db->prepare("UPDATE solicitudes_tareas SET tarea = ? WHERE pk_tarea = ? AND fk_solicitud = ?");
                            $stmt_update_desc->bind_param("sii", $descripcion, $tarea_id, $id);
                            $stmt_update_desc->execute();
                            $stmt_update_desc->close();
                        }
                    }
                }
                $success = "Solicitud actualizada exitosamente. Folio: " . ($solicitud['folio'] ?? '');
                $separator = strpos($returnUrl, '?') === false ? '?' : '&';
                header("Location: " . $returnUrl . $separator . "success=" . urlencode($success));
                exit();
            } else {
                $error = "Error al actualizar la solicitud: " . $stmt->error;
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Solicitud - <?php echo SITE_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="../../assets/css/style.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    </head>

    <body>
        <?php include '../../includes/navbar.php';
        $puede_editar_categoria = true;
        if ($usuario_rol === 'especial' && $solicitud['usuario_id'] != $usuario_id) {
            $puede_editar_categoria = false;
        }
        if ($usuario_rol === 'especial') {
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
            $categorias = $db->query("SELECT pk_categoria, categoria FROM cat_categorias WHERE fk_estatus = 1 ORDER BY categoria");
        }
        if ($usuario_rol === 'especial') {
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
            $etiquetas = $db->query("SELECT pk_etiqueta, etiqueta FROM cat_etiquetas WHERE fk_estatus = 1 ORDER BY etiqueta");
        }
        $etiquetas_seleccionadas = [];
        if ($usuario_rol === 'especial') {
            $stmt = $db->prepare("
            SELECT sr.fk_etiqueta
            FROM solicitudes_rel sr
            INNER JOIN usuarios_etiquetas ue ON sr.fk_etiqueta = ue.fk_etiqueta
            WHERE sr.fk_solicitud = ? AND ue.fk_usuario = ?
        ");
            $stmt->bind_param("ii", $id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $etiquetas_seleccionadas[] = $row['fk_etiqueta'];
            }
            $stmt->close();
        } else {
            $stmt = $db->prepare("SELECT fk_etiqueta FROM solicitudes_rel WHERE fk_solicitud = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $etiquetas_seleccionadas[] = $row['fk_etiqueta'];
            }
            $stmt->close();
        }
        $stmt_cat = $db->prepare("SELECT categoria FROM cat_categorias WHERE pk_categoria = ?");
        $stmt_cat->bind_param("i", $solicitud['fk_categoria']);
        $stmt_cat->execute();
        $result_cat = $stmt_cat->get_result();
        $categoria_actual = $result_cat->fetch_assoc();
        $stmt_cat->close();
        $etiquetas_actuales = [];
        if (count($etiquetas_seleccionadas) > 0) {
            $placeholders = implode(',', array_fill(0, count($etiquetas_seleccionadas), '?'));
            $stmt_etiq = $db->prepare("SELECT etiqueta FROM cat_etiquetas WHERE pk_etiqueta IN ($placeholders)");
            $types = str_repeat('i', count($etiquetas_seleccionadas));
            $stmt_etiq->bind_param($types, ...$etiquetas_seleccionadas);
            $stmt_etiq->execute();
            $result_etiq = $stmt_etiq->get_result();
            while ($row = $result_etiq->fetch_assoc()) {
                $etiquetas_actuales[] = $row['etiqueta'];
            }
            $stmt_etiq->close();
        }
        ?>
        <div class="container mt-4">
            <div class="row">
                <div class="col-md-12">
                    <div class="card solicitud-card">
                        <div class="card-header solicitud-header">
                            <h4 class="mb-0">Editar Solicitud: <?php echo htmlspecialchars($solicitud['folio']); ?></h4>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>
                            <form method="POST" action="" id="formEditar">
                                <div class="row g-3">
                                    <!-- Primera columna -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="categoria" class="form-label">Categoría <span class="text-danger">*</span></label>
                                            <?php if ($puede_editar_categoria): ?>
                                                <select class="form-control" id="categoria" name="categoria" required>
                                                    <option value="">Selecciona una opción</option>
                                                    <?php while ($categoria = $categorias->fetch_assoc()) { ?>
                                                        <option value="<?= $categoria['pk_categoria'] ?>" <?= ($categoria['pk_categoria'] == $solicitud['fk_categoria']) ? 'selected' : '' ?>><?= $categoria['categoria'] ?></option>
                                                    <?php } ?>
                                                </select>
                                            <?php else: ?>
                                                <p class="form-control-plaintext"><strong><?= htmlspecialchars($categoria_actual['categoria']) ?></strong></p>
                                                <input type="hidden" name="categoria" value="<?= $solicitud['fk_categoria'] ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="cct" class="form-label">CCT</label>
                                            <select class="form-control upper" id="cct" name="cct">
                                                <?php if (!empty($solicitud['cct'])) { ?>
                                                    <option value="<?= htmlspecialchars($solicitud['cct']) ?>" selected><?= htmlspecialchars($solicitud['cct']) ?></option>
                                                <?php } ?>
                                            </select>
                                            <input type="hidden" id="N_MUNICIPIO" name="N_MUNICIPIO" value="<?= htmlspecialchars($solicitud['N_MUNICIPIO'] ?? '') ?>">
                                            <input type="hidden" id="N_LOCALIDAD" name="N_LOCALIDAD" value="<?= htmlspecialchars($solicitud['N_LOCALIDAD'] ?? '') ?>">
                                            <input type="hidden" id="NOMBRECT" name="NOMBRECT" value="<?= htmlspecialchars($solicitud['NOMBRECT'] ?? '') ?>">
                                            <div id="cctInfo" class="form-text">
                                                <?php
                                                if (!empty($solicitud['NOMBRECT'])) {
                                                    echo htmlspecialchars($solicitud['NOMBRECT'] . ' - ' . ($solicitud['N_MUNICIPIO'] ?? '') . ', ' . ($solicitud['N_LOCALIDAD'] ?? ''));
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
                                                value="<?php echo htmlspecialchars($solicitud['fecha_peticion']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="tipo_procedencia" class="form-label">Tipo de Procedencia</label>
                                            <input type="text" class="form-control" id="tipo_procedencia" name="tipo_procedencia"
                                                value="<?php echo htmlspecialchars($solicitud['tipo_procedencia']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="compromiso" class="form-label">Compromiso o Asunto <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="compromiso" name="compromiso" rows="3" required><?php echo mTL($solicitud['compromiso']);
                                                                                                                                ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="instruido" class="form-label">Instruido</label>
                                            <input type="text" class="form-control" id="instruido" name="instruido"
                                                value="<?php echo htmlspecialchars($solicitud['instruido']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="solicitado" class="form-label">Solicitado</label>
                                            <input type="text" class="form-control" id="solicitado" name="solicitado"
                                                value="<?php echo htmlspecialchars($solicitud['solicitado']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="asignado_id" class="form-label">Asignado <span class="text-danger">*</span> </label>
                                            <select class="form-control" id="asignado_id" name="asignado_id" required>
                                                <option value="">-- Seleccionar usuario --</option>
                                                <?php
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
                                                    <option value="<?= $usuario['id'] ?>" <?= $solicitud['asignado2_id'] == $usuario['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($usuario['nombre_completo']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="asignado3_id" class="form-label">Informado 2: </label>
                                            <select class="form-select" id="asignado3_id" name="asignado3_id">
                                                <option value="">-- Seleccionar --</option>
                                                <?php foreach ($usuarios as $usuario): ?>
                                                    <option value="<?= $usuario['id'] ?>" <?= $solicitud['asignado3_id'] == $usuario['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($usuario['nombre_completo']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="asignado4_id" class="form-label">Informado 3: </label>
                                            <select class="form-select" id="asignado4_id" name="asignado4_id">
                                                <option value="">-- Seleccionar --</option>
                                                <?php foreach ($usuarios as $usuario): ?>
                                                    <option value="<?= $usuario['id'] ?>" <?= $solicitud['asignado4_id'] == $usuario['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($usuario['nombre_completo']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="asignado5_id" class="form-label">Informado 4: </label>
                                            <select class="form-select" id="asignado5_id" name="asignado5_id">
                                                <option value="">-- Seleccionar --</option>
                                                <?php foreach ($usuarios as $usuario): ?>
                                                    <option value="<?= $usuario['id'] ?>" <?= $solicitud['asignado5_id'] == $usuario['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($usuario['nombre_completo']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Segunda columna -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="atendio" class="form-label">Atendió</label>
                                            <input type="text" class="form-control" id="atendio" name="atendio"
                                                value="<?php echo htmlspecialchars($solicitud['atendio']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono (10 dígitos)</label>
                                            <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="10"
                                                value="<?php echo htmlspecialchars($solicitud['telefono']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="descripcion" class="form-label">Descripción <span class="text-danger">*</span></label>
                                            <?php
                                            $puede_editar_desc_solicitud = ($usuario_rol === 'admin' || $solicitud['usuario_id'] == $usuario_id || $usuario_id == 47);
                                            ?>
                                            <?php if ($puede_editar_desc_solicitud): ?>
                                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" onkeyup="this.value = this.value.toUpperCase()" required><?php echo mTL($solicitud['descripcion']); ?></textarea>
                                            <?php else: ?>
                                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" readonly required><?php echo mTL($solicitud['descripcion']); ?></textarea>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="fecha_seguimiento" class="form-label">Fecha para Seguimiento</label>
                                            <input type="date" class="form-control" id="fecha_seguimiento" name="fecha_seguimiento"
                                                value="<?php echo htmlspecialchars($solicitud['fecha_seguimiento']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="responsable_seguimiento" class="form-label">Responsable del Seguimiento</label>
                                            <input type="text" class="form-control" id="responsable_seguimiento" name="responsable_seguimiento"
                                                value="<?php echo htmlspecialchars($solicitud['responsable_seguimiento']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="indicaciones_secretario" class="form-label">Indicaciones Secretario</label>
                                            <textarea class="form-control" id="indicaciones_secretario" name="indicaciones_secretario" rows="2"><?php echo mTL($solicitud['indicaciones_secretario']); ?>
                                        </textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="fecha_vencimiento" class="form-label">Fecha Vencimiento</label>
                                            <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento"
                                                value="<?php echo htmlspecialchars($solicitud['fecha_vencimiento']); ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prioridad" class="form-label">Prioridad <span class="text-danger">*</span></label>
                                                <select class="form-select" id="prioridad" name="prioridad" required>
                                                    <option value="Alta" <?php echo $solicitud['prioridad'] === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                                                    <option value="Media" <?php echo $solicitud['prioridad'] === 'Media' ? 'selected' : ''; ?>>Media</option>
                                                    <option value="Baja" <?php echo $solicitud['prioridad'] === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                                                <select class="form-select" id="estado" name="estado" required>
                                                    <option value="No iniciada" <?php echo $solicitud['estado'] === 'No iniciada' ? 'selected' : ''; ?>>No iniciada</option>
                                                    <option value="En proceso" <?php echo $solicitud['estado'] === 'En proceso' ? 'selected' : ''; ?>>En proceso</option>
                                                    <option value="Concluida" <?php echo $solicitud['estado'] === 'Concluida' ? 'selected' : ''; ?>>Concluida</option>
                                                    <option value="Pendiente" <?php echo $solicitud['estado'] === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="etiquetas" class="form-label">Etiquetas</label>
                                                <?php if ($puede_editar_categoria): ?>
                                                    <select class="form-select" id="etiquetas" name="etiquetas[]" multiple>
                                                        <?php while ($etiqueta = $etiquetas->fetch_assoc()) { ?>
                                                            <option value="<?= $etiqueta['pk_etiqueta'] ?>" <?php echo in_array($etiqueta['pk_etiqueta'], $etiquetas_seleccionadas) ? ' selected' : ''; ?>><?= $etiqueta['etiqueta'] ?></option>
                                                        <?php } ?>
                                                    </select>
                                                <?php else: ?>
                                                    <p class="form-control-plaintext">
                                                        <?php if (count($etiquetas_actuales) > 0): ?>
                                                            <?php foreach ($etiquetas_actuales as $etiq): ?>
                                                                <span class="badge bg-primary me-1"><?= htmlspecialchars($etiq) ?></span>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin etiquetas</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <!-- Mantener etiquetas actuales con campos hidden -->
                                                    <?php foreach ($etiquetas_seleccionadas as $etiq_id): ?>
                                                        <input type="hidden" name="etiquetas[]" value="<?= $etiq_id ?>">
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <!-- Sección de Tareas -->
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <?php
                                                if ($es_admin || $es_creador || $es_asignado) {
                                                    $tareas_query = $db->prepare("SELECT st.pk_tarea, st.tarea, st.fk_usuario, st.fk_estatus, st.fecha, st.fecha_cierre, u.nombre_completo
                                                                                                FROM solicitudes_tareas st
                                                                                                INNER JOIN usuarios u ON st.fk_usuario = u.id
                                                                                                WHERE st.fk_solicitud = ?
                                                                                                ORDER BY st.fecha DESC");
                                                    $tareas_query->bind_param("i", $id);
                                                } else {
                                                    $tareas_query = $db->prepare("SELECT st.pk_tarea, st.tarea, st.fk_usuario, st.fk_estatus, st.fecha, st.fecha_cierre, u.nombre_completo
                                                                                                FROM solicitudes_tareas st
                                                                                                INNER JOIN usuarios u ON st.fk_usuario = u.id
                                                                                                WHERE st.fk_solicitud = ? AND st.fk_usuario = ?
                                                                                                ORDER BY st.fecha DESC");
                                                    $tareas_query->bind_param("ii", $id, $usuario_id);
                                                }
                                                $tareas_query->execute();
                                                $tareas_result = $tareas_query->get_result();
                                                if ($tareas_result->num_rows > 0) {
                                                ?>
                                                    <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
                                                        <div>
                                                            <i class="bi bi-info-circle me-2"></i>
                                                            <strong>Asignación de tareas</strong>
                                                        </div>
                                                    </div>
                                                <?php }
                                                if (($_SESSION['rol'] === 'admin' || $solicitud['usuario_id'] == $_SESSION['user_id']) && !$es_monitor): ?>
                                                    <button type="button" class="btn btn-sm btn-success" id="btnNuevaTarea">
                                                        <i class="bi bi-plus-circle"></i> Nueva tarea
                                                    </button>
                                                <?php endif; ?>
                                                <!-- Tareas existentes -->
                                                <div id="tareasExistentes" class="mt-3">
                                                    <?php
                                                    if ($tareas_result->num_rows === 0) {
                                                        echo '<div class="alert alert-info" role="alert">
                                                                                                                        <i class="bi bi-info-circle me-2"></i>Sin tareas registradas
                                                                                                                    </div>';
                                                    }
                                                    while ($tarea = $tareas_result->fetch_assoc()) {
                                                        $estado_badges = [
                                                            0 => 'bg-secondary',  // No iniciada
                                                            1 => 'bg-warning',    // En proceso
                                                            2 => 'bg-success',    // Concluida
                                                            3 => 'bg-info'        // Pendiente
                                                        ];
                                                        $estado_textos = [
                                                            0 => 'No iniciada',
                                                            1 => 'En proceso',
                                                            2 => 'Concluida',
                                                            3 => 'Pendiente'
                                                        ];
                                                        $estado_badge = $estado_badges[$tarea['fk_estatus']] ?? 'bg-secondary';
                                                        $estado_texto = $estado_textos[$tarea['fk_estatus']] ?? 'Desconocido';
                                                        $puede_eliminar = in_array($tarea['fk_estatus'], [0, 1, 3]) && $es_admin;
                                                        $puede_cambiar_estatus = $tarea['fk_estatus'] != 2 && $tarea['fk_usuario'] == $usuario_id;
                                                        $puede_editar_descripcion = ($es_admin || $es_creador) && $tarea['fk_estatus'] != 2;
                                                        $tarea_propietaria = ($tarea['fk_usuario'] == $usuario_id);
                                                        if ($es_admin) {
                                                            $puede_cambiar_estatus = true;
                                                        }

                                                    ?>
                                                        <div class="card mb-3 tarea-existente" data-tarea-id="<?= $tarea['pk_tarea'] ?>">
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <?php if ($es_admin || $es_creador) { ?>
                                                                        <!-- Vista para administrador o creador de la solicitud -->
                                                                        <div class="col-md-4">
                                                                            <label class="form-label">Usuario asignado</label>
                                                                            <?php if ($tarea['fk_estatus'] == 2) { ?>
                                                                                <div class="form-control" style="background-color: #e9ecef;"><?= htmlspecialchars($tarea['nombre_completo']) ?></div>
                                                                            <?php } else { ?>
                                                                                <select class="form-control tarea-usuario-existente" name="tarea_existente_usuario[<?= $tarea['pk_tarea'] ?>]">
                                                                                    <?php
                                                                                    $usuarios_tarea = $db->query("SELECT id, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo");
                                                                                    while ($usuario_opt = $usuarios_tarea->fetch_assoc()) {
                                                                                        $selected = ($usuario_opt['id'] == $tarea['fk_usuario']) ? 'selected' : '';
                                                                                        echo '<option value="' . $usuario_opt['id'] . '" ' . $selected . '>' . htmlspecialchars($usuario_opt['nombre_completo']) . '</option>';
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                            <?php } ?>
                                                                        </div>
                                                                        <div class="col-md-5">
                                                                            <label class="form-label">Descripción de la tarea</label>
                                                                            <?php if ($tarea['fk_estatus'] == 2) { ?>
                                                                                <div class="form-control" style="min-height: 60px; background-color: #e9ecef; white-space: pre-wrap;"><?= htmlspecialchars($tarea['tarea']) ?></div>
                                                                            <?php } elseif ($puede_editar_descripcion) { ?>
                                                                                <textarea class="form-control tarea-descripcion-existente" name="tarea_existente_descripcion[<?= $tarea['pk_tarea'] ?>]" rows="2"><?= htmlspecialchars($tarea['tarea']) ?></textarea>
                                                                            <?php } else { ?>
                                                                                <textarea class="form-control" rows="2" readonly><?= htmlspecialchars($tarea['tarea']) ?></textarea>
                                                                            <?php } ?>
                                                                        </div>
                                                                        <div class="col-md-3 d-flex align-items-end flex-column">
                                                                            <span class="badge <?= $estado_badge ?> mb-2"><?= $estado_texto ?></span>
                                                                            <?php if ($puede_cambiar_estatus) { ?>
                                                                                <div class="dropdown dropdown-estatus" style="width: 100%;">
                                                                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button"
                                                                                        id="dropdownEstatus<?= $tarea['pk_tarea'] ?>"
                                                                                        data-bs-toggle="dropdown"
                                                                                        aria-expanded="false"
                                                                                        style="width: 100%;">
                                                                                        <i class="bi bi-pencil-square"></i> Cambiar estatus
                                                                                    </button>
                                                                                    <ul class="dropdown-menu" aria-labelledby="dropdownEstatus<?= $tarea['pk_tarea'] ?>">
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="0"
                                                                                                data-estatus-nombre="No iniciada">
                                                                                                <i class="bi bi-hourglass text-secondary"></i> No iniciada
                                                                                            </a></li>
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="3"
                                                                                                data-estatus-nombre="Pendiente">
                                                                                                <i class="bi bi-clock text-info"></i> Pendiente
                                                                                            </a></li>
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="1"
                                                                                                data-estatus-nombre="En proceso">
                                                                                                <i class="bi bi-arrow-repeat text-warning"></i> En proceso
                                                                                            </a></li>
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="2"
                                                                                                data-estatus-nombre="Concluida">
                                                                                                <i class="bi bi-check-circle text-success"></i> Concluida
                                                                                            </a></li>
                                                                                    </ul>
                                                                                </div>
                                                                            <?php } ?>
                                                                            <?php if ($puede_eliminar) { ?>
                                                                                <button type="button" class="btn btn-danger btn-sm btnEliminarTareaExistente"
                                                                                    data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                    data-bs-toggle="tooltip"
                                                                                    data-bs-placement="top"
                                                                                    title="Eliminar tarea">
                                                                                    <i class="bi bi-trash"></i>
                                                                                </button>
                                                                            <?php } ?>
                                                                        </div>
                                                                    <?php } else { ?>
                                                                        <div class="col-md-9">
                                                                            <label class="form-label"><strong>Tarea asignada:</strong></label>
                                                                            <div class="form-control" style="min-height: 60px; background-color: #e9ecef; white-space: pre-wrap;"><?= htmlspecialchars($tarea['tarea']) ?></div>
                                                                            <div class="mt-2">
                                                                                <small class="text-muted">larin Asignada a: <?= htmlspecialchars($tarea['nombre_completo']) ?></small>
                                                                            </div>
                                                                            <small class="text-muted">Asignada el: <?= date('d/m/Y', strtotime($tarea['fecha'])) ?></small>
                                                                        </div>
                                                                        <div class="col-md-3 d-flex align-items-center flex-column justify-content-center">
                                                                            <span class="badge <?= $estado_badge ?> mb-3" style="font-size: 1rem;"><?= $estado_texto ?></span>
                                                                            <?php if ($puede_cambiar_estatus) { ?>
                                                                                <div class="dropdown dropdown-estatus" style="width: 100%;">
                                                                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button"
                                                                                        id="dropdownEstatus<?= $tarea['pk_tarea'] ?>"
                                                                                        data-bs-toggle="dropdown"
                                                                                        aria-expanded="false"
                                                                                        style="width: 100%;">
                                                                                        <i class="bi bi-pencil-square"></i> Cambiar estatus
                                                                                    </button>
                                                                                    <ul class="dropdown-menu" aria-labelledby="dropdownEstatus<?= $tarea['pk_tarea'] ?>">
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="0"
                                                                                                data-estatus-nombre="No iniciada">
                                                                                                <i class="bi bi-hourglass text-secondary"></i> No iniciada
                                                                                            </a></li>
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="3"
                                                                                                data-estatus-nombre="Pendiente">
                                                                                                <i class="bi bi-clock text-info"></i> Pendiente
                                                                                            </a></li>
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="1"
                                                                                                data-estatus-nombre="En proceso">
                                                                                                <i class="bi bi-arrow-repeat text-warning"></i> En proceso
                                                                                            </a></li>
                                                                                        <li><a class="dropdown-item btnCambiarEstatus" href="#"
                                                                                                data-tarea-id="<?= $tarea['pk_tarea'] ?>"
                                                                                                data-estatus="2"
                                                                                                data-estatus-nombre="Concluida">
                                                                                                <i class="bi bi-check-circle text-success"></i> Concluida
                                                                                            </a></li>
                                                                                    </ul>
                                                                                </div>
                                                                            <?php } ?>
                                                                        </div>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                                <!-- Contenedor para nuevas tareas -->
                                                <?php if (($es_admin || $es_creador) && !$es_monitor): ?>
                                                    <div id="tareasContainer" class="mt-3">
                                                        <!-- Las nuevas tareas se agregarán dinámicamente aquí -->
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                                    <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-secondary me-md-2">Cancelar</a>
                                    <?php if ($es_monitor): ?>
                                        <button type="button" class="btn btn-morena" disabled>No tienes permisos de edición.</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-morena">Actualizar Solicitud</button>
                                    <?php endif; ?>
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
            document.getElementById('formEditar').addEventListener('submit', function(e) {
                const estadoSelect = document.getElementById('estado');
                if (estadoSelect.value === 'Concluida') {
                    const tareasNoConcluidas = document.querySelectorAll('.badge.bg-secondary, .badge.bg-warning, .badge.bg-info');
                    if (tareasNoConcluidas.length > 0) {
                        e.preventDefault();
                        swal({
                            title: "No se puede concluir",
                            text: 'No se puede marcar como "Concluida" mientras existan tareas sin finalizar. Por favor, concluye todas las tareas asignadas primero.',
                            icon: "warning",
                            button: "Entendido"
                        });
                        return false;
                    }
                }
            });
            flatpickr.localize(flatpickr.l10ns.es);
            flatpickr("#fecha_peticion, #fecha_seguimiento, #fecha_vencimiento", {
                dateFormat: "Y-m-d",
                allowInput: true,
                locale: "es"
            });
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

                if ($('#etiquetas').length && $('#etiquetas').is('select')) {
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
                }
                $('.tarea-usuario-existente').select2({
                    placeholder: "-- Seleccionar usuario --",
                    language: {
                        noResults: function() {
                            return "No se encontraron resultados";
                        }
                    }
                });
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
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
                $(`.tarea-item[data-tarea-id="${tareaCounter}"] .tarea-usuario`).select2({
                    placeholder: "-- Seleccionar usuario --"
                });
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
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
                $(this).blur();
                tareaCard.remove();
            });
            $(document).on('click', '.btnEliminarTareaExistente', function() {
                const btnEliminar = $(this);
                const tareaId = btnEliminar.data('tarea-id');
                const tareaCard = $(`.tarea-existente[data-tarea-id="${tareaId}"]`);
                const tooltipInstance = bootstrap.Tooltip.getInstance(this);
                if (tooltipInstance) {
                    tooltipInstance.dispose();
                }
                swal({
                        title: "¿Estás seguro?",
                        text: "¿Deseas eliminar esta tarea?",
                        icon: "warning",
                        buttons: {
                            cancel: {
                                text: "Cancelar",
                                value: null,
                                visible: true,
                                className: "btn btn-secondary",
                                closeModal: true,
                            },
                            confirm: {
                                text: "Aceptar",
                                color: "blue",
                                value: true,
                                visible: true,
                                className: "btn btn-success",
                                closeModal: true
                            }
                        },
                        dangerMode: true,
                    })
                    .then((willDelete) => {
                        if (willDelete) {
                            btnEliminar.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i>');
                            $.ajax({
                                url: 'eliminar_tarea.php',
                                type: 'POST',
                                data: {
                                    tarea_id: tareaId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        tareaCard.fadeOut(300, function() {
                                            $(this).remove();
                                        });
                                        swal({
                                            title: "¡Éxito!",
                                            text: "Tarea eliminada exitosamente",
                                            icon: "success",
                                            button: "Aceptar"
                                        });
                                    } else {
                                        swal({
                                            title: "Error",
                                            text: response.message,
                                            icon: "error",
                                            button: "Aceptar"
                                        });
                                        btnEliminar.prop('disabled', false).html('<i class="bi bi-trash"></i>');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    swal({
                                        title: "Error",
                                        text: "Error al eliminar la tarea: " + error,
                                        icon: "error",
                                        button: "Aceptar"
                                    });
                                    btnEliminar.prop('disabled', false).html('<i class="bi bi-trash"></i>');
                                }
                            });
                        }
                    });
            });
            $(document).on('click', '.btnCambiarEstatus', function(e) {
                e.preventDefault();
                const btnEstatus = $(this);
                const tareaId = btnEstatus.data('tarea-id');
                const nuevoEstatus = btnEstatus.data('estatus');
                const estatusNombre = btnEstatus.data('estatus-nombre');
                const tareaCard = $(`.tarea-existente[data-tarea-id="${tareaId}"]`);
                swal({
                        title: "¿Estás seguro?",
                        text: `¿Deseas cambiar el estatus de esta tarea a "${estatusNombre}"?`,
                        icon: "info",
                        buttons: {
                            cancel: {
                                text: "Cancelar",
                                value: null,
                                visible: true,
                                className: "btn btn-secondary",
                                closeModal: true,
                            },
                            confirm: {
                                text: "Confirmar",
                                value: true,
                                visible: true,
                                className: "btn btn-success",
                                closeModal: true
                            }
                        }
                    })
                    .then((willChange) => {
                        if (willChange) {
                            const dropdownBtn = tareaCard.find('.dropdown-toggle');
                            const originalText = dropdownBtn.html();
                            dropdownBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Procesando...');
                            $.ajax({
                                url: 'concluir_tarea.php',
                                type: 'POST',
                                data: {
                                    tarea_id: tareaId,
                                    estatus: nuevoEstatus
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        swal({
                                            title: "¡Éxito!",
                                            text: `Tarea actualizada a "${estatusNombre}" exitosamente`,
                                            icon: "success",
                                            button: "Aceptar"
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        swal({
                                            title: "Error",
                                            text: response.message,
                                            icon: "error",
                                            button: "Aceptar"
                                        });
                                        dropdownBtn.prop('disabled', false).html(originalText);
                                    }
                                },
                                error: function(xhr, status, error) {
                                    swal({
                                        title: "Error",
                                        text: "Error al actualizar la tarea: " + error,
                                        icon: "error",
                                        button: "Aceptar"
                                    });
                                    dropdownBtn.prop('disabled', false).html(originalText);
                                }
                            });
                        }
                    });
            });
            $(document).on('input', '.tarea-descripcion-existente', function() {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(start, end);
            });
        </script>
    </body>

    </html>
