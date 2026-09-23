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
$returnParam = urlencode($returnUrl);

// Obtener la solicitud con información del usuario creador
$stmt = $db->prepare("SELECT s.*,
                      c.categoria,
                      u.nombre_completo as usuario_nombre,
                      ua.nombre_completo as asignado_nombre,
                      ua2.nombre_completo as asignado2_nombre,
                      ua3.nombre_completo as asignado3_nombre,
                      ua4.nombre_completo as asignado4_nombre,
                      ua5.nombre_completo as asignado5_nombre
                      FROM solicitudes AS s
                      INNER JOIN cat_categorias AS c ON c.pk_categoria = s.fk_categoria
                      JOIN usuarios AS u ON s.usuario_id = u.id
                      LEFT JOIN usuarios AS ua ON s.asignado_id = ua.id
                      LEFT JOIN usuarios AS ua2 ON s.asignado2_id = ua2.id
                      LEFT JOIN usuarios AS ua3 ON s.asignado3_id = ua3.id
                      LEFT JOIN usuarios AS ua4 ON s.asignado4_id = ua4.id
                      LEFT JOIN usuarios AS ua5 ON s.asignado5_id = ua5.id
                      WHERE s.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$solicitud = $result->fetch_assoc();

if (!$solicitud) {
    header("Location: index.php");
    exit();
}

$usuario_id = $_SESSION['user_id'];
$usuario_rol = $_SESSION['rol'];

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
        header("Location: index.php?error=" . urlencode("No tienes permiso para ver esta solicitud"));
        exit();
    }
}

$usuariosRelacionados = array_filter([
    $solicitud['asignado_id'] ?? null,
    $solicitud['asignado2_id'] ?? null,
    $solicitud['asignado3_id'] ?? null,
    $solicitud['asignado4_id'] ?? null,
    $solicitud['asignado5_id'] ?? null
]);
$es_admin = ($usuario_rol === 'admin');
$es_creador = ($solicitud['usuario_id'] == $usuario_id);
$es_asignado = in_array($usuario_id, $usuariosRelacionados, true);

$tareasQuery = $db->query("SELECT
    st.pk_tarea,
    st.fk_usuario,
    st.tarea,
    st.fecha,
    st.fecha_cierre,
    st.fk_estatus,
    u.nombre_completo as usuario_nombre
FROM solicitudes_tareas st
JOIN usuarios u ON st.fk_usuario = u.id
WHERE st.fk_solicitud = $id
ORDER BY st.fecha DESC");
$tareas = [];
while ($tarea = $tareasQuery->fetch_assoc()) {
    $tareas[] = $tarea;
}
$totalTareas = count($tareas);
$tareasCompletadas = 0;
foreach ($tareas as $tarea) {
    if ($tarea['fk_estatus'] == 2) {
        $tareasCompletadas++;
    }
}
$porcentajeCompletado = $totalTareas > 0 ? round(($tareasCompletadas / $totalTareas) * 100, 0) : 0;
$tareasFiltradas = [];
// Admin, creador o usuarios relacionados con la solicitud ven todas las tareas; otros solo las suyas
if ($es_admin || $es_creador || $es_asignado) {
    $tareasFiltradas = $tareas;
} else {
    foreach ($tareas as $tarea) {
        if ($tarea['fk_usuario'] == $usuario_id) {
            $tareasFiltradas[] = $tarea;
        }
    }
}

$encabezadoTareas = 'Mis tareas asignadas';
if ($es_admin) {
    $encabezadoTareas = 'Todas las tareas asignadas';
} elseif ($es_creador || $es_asignado) {
    $encabezadoTareas = 'Tareas de la solicitud';
}
// Obtener comentarios de seguimiento
$comentarios = $db->query("SELECT c.*, u.nombre_completo as usuario_nombre
                           FROM comentarios_seguimiento c
                           JOIN usuarios AS u ON c.usuario_id = u.id
                           WHERE c.solicitud_id = $id
                           ORDER BY c.fecha_comentario DESC, c.id DESC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Solicitud - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <style>
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
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card solicitud-card">
                    <div class="card-header solicitud-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Detalles de la Solicitud: <?php echo htmlspecialchars($solicitud['folio'] ?? ''); ?></h4>
                            <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-4">Folio:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['folio']  ?? ''); ?></dd>

                                    <dt class="col-sm-4">Categoría:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['categoria']  ?? ''); ?></dd>
                                    <dt class="col-sm-4">CCT:</dt>
                                    <dd class="col-sm-8 upper"><?php echo htmlspecialchars($solicitud['cct']  ?? ''); ?></dd>

                                    <dt class="col-sm-4">Solicitante:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['solicitante']  ?? ''); ?></dd>
                                    <dt class="col-sm-4">Primer apellido:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['ap1'] ?? ''); ?></dd>
                                    <dt class="col-sm-4">Segundo apellido:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['ap2'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Fecha Petición:</dt>
                                    <dd class="col-sm-8"><?php echo date('d/m/Y', strtotime($solicitud['fecha_peticion'] ?? '')); ?></dd>

                                    <dt class="col-sm-4">Tipo Procedencia:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['tipo_procedencia'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Prioridad:</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-<?php
                                                                echo $solicitud['prioridad'] === 'Alta' ? 'danger' : ($solicitud['prioridad'] === 'Media' ? 'warning' : 'success');
                                                                ?>">
                                            <?php echo $solicitud['prioridad']; ?>
                                        </span>
                                    </dd>

                                    <dt class="col-sm-4">Estado:</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-<?php
                                                                echo $solicitud['estado'] === 'Concluida' ? 'success' : ($solicitud['estado'] === 'En proceso' ? 'primary' : ($solicitud['estado'] === 'Pendiente' ? 'warning' : 'secondary'));
                                                                ?>">
                                            <?php echo $solicitud['estado']; ?>
                                        </span>
                                    </dd>

                                    <dt class="col-sm-4">Instruido:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['instruido'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Solicitado:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['solicitado'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Etiquetas:</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        $etiquetas = [];
                                        $stmt = $db->prepare("SELECT fk_etiqueta FROM solicitudes_rel WHERE fk_solicitud = ?");
                                        $stmt->bind_param("i", $id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        while ($row = $result->fetch_assoc()) {
                                            $etiquetas[] = $row['fk_etiqueta'];
                                        }
                                        $stmt->close();
                                        if (count($etiquetas) > 0) {
                                            $stmt = $db->prepare("SELECT etiqueta FROM cat_etiquetas WHERE pk_etiqueta IN (" . implode(',', $etiquetas) . ")");
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            while ($row = $result->fetch_assoc()) {
                                                echo '<span class="badge bg-secondary">' . htmlspecialchars($row['etiqueta'] ?? '') . '</span> ';
                                            }
                                            $stmt->close();
                                        }
                                        ?>
                                    </dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-4">Atendió:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['atendio'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Teléfono:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['telefono'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Fecha Seguimiento:</dt>
                                    <dd class="col-sm-8"><?php echo !empty($solicitud['fecha_seguimiento'] ?? '') ? date('d/m/Y', strtotime($solicitud['fecha_seguimiento'])) : ''; ?></dd>

                                    <dt class="col-sm-4">Responsable Seguimiento:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['responsable_seguimiento'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Fecha Vencimiento:</dt>
                                    <dd class="col-sm-8">
                                        <?php if (!empty($solicitud['fecha_vencimiento'])): ?>
                                            <?php
                                            $fechaVencimiento = new DateTime($solicitud['fecha_vencimiento']);
                                            $hoy = new DateTime();
                                            $diferencia = $hoy->diff($fechaVencimiento);
                                            $dias = $diferencia->days;

                                            $clase = '';
                                            if ($fechaVencimiento < $hoy && $solicitud['estado'] !== 'Concluida') {
                                                $clase = 'text-danger fw-bold';
                                            } elseif ($dias <= 3 && $solicitud['estado'] !== 'Concluida') {
                                                $clase = 'text-warning fw-bold';
                                            }
                                            ?>
                                            <span class="<?php echo $clase; ?>">
                                                <?php echo date('d/m/Y', strtotime($solicitud['fecha_vencimiento'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </dd>

                                    <dt class="col-sm-4">Creada por:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($solicitud['usuario_nombre'] ?? ''); ?></dd>

                                    <dt class="col-sm-4">Asignado a:</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        if (!empty($solicitud['asignado_nombre'])) {
                                            echo htmlspecialchars($solicitud['asignado_nombre'] ?? '');
                                        } else {
                                            echo '<span class="text-muted">No asignado</span>';
                                        }
                                        ?>
                                    </dd>


                                    <dt class="col-sm-4">Asignado 2:</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        if (!empty($solicitud['asignado2_nombre'] ?? '')) {
                                            echo htmlspecialchars($solicitud['asignado2_nombre'] ?? '');
                                        } else {
                                            echo '<span class="text-muted">No asignado</span>';
                                        }
                                        ?>
                                    </dd>

                                    <dt class="col-sm-4">Asignado 3:</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        if (!empty($solicitud['asignado3_nombre'] ?? '')) {
                                            echo htmlspecialchars($solicitud['asignado3_nombre'] ?? '');
                                        } else {
                                            echo '<span class="text-muted">No asignado</span>';
                                        }
                                        ?>
                                    </dd>

                                    <dt class="col-sm-4">Asignado 4:</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        if (!empty($solicitud['asignado4_nombre'] ?? '')) {
                                            echo htmlspecialchars($solicitud['asignado4_nombre'] ?? '');
                                        } else {
                                            echo '<span class="text-muted">No asignado</span>';
                                        }
                                        ?>
                                    </dd>
                                    <dt class="col-sm-4">Asignado 5:</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        if (!empty($solicitud['asignado5_nombre'] ?? '')) {
                                            echo htmlspecialchars($solicitud['asignado5_nombre'] ?? '');
                                        } else {
                                            echo '<span class="text-muted">No asignado</span>';
                                        }
                                        ?>
                                    </dd>

                                    <dt class="col-sm-4">Fecha creación:</dt>
                                    <dd class="col-sm-8"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_creacion'])); ?></dd>
                                </dl>
                            </div>
                        </div>

                        <!-- Sección de Tareas -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">Progreso de tareas</h5>

                            <?php if ($totalTareas > 0): ?>
                                <!-- Barra de progreso -->
                                <div class="mb-3">
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar <?php echo $porcentajeCompletado == 100 ? 'bg-success' : 'bg-info'; ?>"
                                            role="progressbar"
                                            style="width: <?php echo $porcentajeCompletado; ?>%"
                                            aria-valuenow="<?php echo $porcentajeCompletado; ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100">
                                            <?php echo $porcentajeCompletado; ?>%
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo $tareasCompletadas; ?> de <?php echo $totalTareas; ?> tareas completadas</small>
                                </div>

                                <!-- Listado de tareas según rol -->
                                <?php if (count($tareasFiltradas) > 0): ?>
                                    <div class="mt-4">
                                        <h6 class="mb-3"><?php echo $encabezadoTareas; ?></h6>

                                        <?php foreach ($tareasFiltradas as $tarea):
                                            // Definir badges y bordes según el estatus
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

                                            $estado_bordes = [
                                                0 => 'border-secondary',
                                                1 => 'border-warning',
                                                2 => 'border-success',
                                                3 => 'border-info'
                                            ];

                                            $badge_class = $estado_badges[$tarea['fk_estatus']] ?? 'bg-secondary';
                                            $texto_estatus = $estado_textos[$tarea['fk_estatus']] ?? 'Desconocido';
                                            $borde_class = $estado_bordes[$tarea['fk_estatus']] ?? 'border-secondary';
                                        ?>
                                            <div class="card mb-3 <?php echo $borde_class; ?>">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <?php if ($es_admin || $es_creador): ?>
                                                            <!-- Vista de admin o creador -->
                                                            <div class="col-md-8">
                                                                <h6 class="card-title">
                                                                    <i class="bi bi-person-circle"></i>
                                                                    <?php echo htmlspecialchars($tarea['usuario_nombre']); ?>
                                                                    <span class="badge <?php echo $badge_class; ?>">
                                                                        <?php echo $texto_estatus; ?>
                                                                    </span>
                                                                </h6>
                                                                <p class="card-text mb-2"><?php echo nl2br(htmlspecialchars($tarea['tarea'])); ?></p>
                                                            </div>
                                                            <div class="col-md-4 text-end">
                                                                <small class="text-muted">
                                                                    <i class="bi bi-calendar-event"></i>
                                                                    Asignada: <?php echo !empty($tarea['fecha']) ? date('d/m/Y', strtotime($tarea['fecha'])) : 'N/D'; ?>
                                                                </small>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-calendar-check"></i>
                                                                     <?php if (!empty($tarea['fecha_cierre']) && $tarea['fk_estatus'] == 2) {
                                                                        echo "Concluida: ".date('d/m/Y', strtotime($tarea['fecha_cierre']));
                                                                         } else {
                                                                            echo 'No concluida';
                                                                        } ?>
                                                                </small>
                                                            </div>
                                                        <?php else: ?>
                                                            <!-- Vista de usuario normal -->
                                                            <div class="col-md-9">
                                                                <h6 class="card-title">
                                                                    <span class="badge <?php echo $badge_class; ?>" style="font-size: 1rem;">
                                                                        <?php echo $texto_estatus; ?>
                                                                    </span>
                                                                </h6>
                                                                <p class="card-text mb-2"><?php echo nl2br(htmlspecialchars($tarea['tarea'])); ?></p>
                                                                <small class="text-muted d-block">
                                                                    <i class="bi bi-person-circle"></i>
                                                                    Asignada a: <?php echo htmlspecialchars($tarea['usuario_nombre']); ?>
                                                                </small>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-calendar-event"></i>
                                                                    Asignada: <?php echo !empty($tarea['fecha']) ? date('d/m/Y', strtotime($tarea['fecha'])) : 'N/D'; ?>
                                                                </small>
                                                                <small class="text-muted d-block">
                                                                    <i class="bi bi-calendar-check"></i>
                                                                    Concluida: <?php echo !empty($tarea['fecha_cierre']) ? date('d/m/Y', strtotime($tarea['fecha_cierre'])) : 'N/D'; ?>
                                                                </small>
                                                            </div>
                                                            <div class="col-md-3 d-flex align-items-center justify-content-center">
                                                                <?php if ($tarea['fk_estatus'] != 2 && $tarea['fk_usuario'] == $usuario_id): // Permitir cambiar estatus si no está concluida y es su tarea ?>
                                                                    <a href="editar.php?id=<?php echo $id; ?>&return_url=<?php echo $returnParam; ?>" class="btn btn-primary">
                                                                        <i class="bi bi-pencil"></i> Ir a editar
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i>
                                        <?php if ($es_admin): ?>
                                            Esta solicitud tiene <?php echo $totalTareas; ?> tarea(s) registrada(s), pero ninguna está asignada a usuarios activos o la consulta no retornó resultados.
                                        <?php elseif ($es_creador || $es_asignado): ?>
                                            Esta solicitud no tiene tareas registradas.
                                        <?php else: ?>
                                            No tienes tareas asignadas en esta solicitud.
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    No hay tareas registradas para esta solicitud.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Compromiso o Asunto</h5>
                            <p><?php echo mTL($solicitud['compromiso']); ?></p>
                        </div>

                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Descripción</h5>
                            <p><?php echo mTL($solicitud['descripcion']); ?></p>

                        </div>

                        <?php if (!empty($solicitud['indicaciones_secretario'])): ?>
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2">Indicaciones Secretario</h5>
                                <p><?php echo mTL($solicitud['indicaciones_secretario']); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Comentarios de seguimiento -->
                        <div class="mt-5">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Comentarios de Seguimiento</h5>
                                <a href="comentarios/agregar.php?solicitud_id=<?php echo $id; ?>&return_url=<?php echo $returnParam; ?>" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-circle"></i> Agregar Comentario
                                </a>
                            </div>

                            <?php if ($comentarios->num_rows > 0): ?>
                                <div class="list-group">

                                    <?php while ($comentario = $comentarios->fetch_assoc()): ?>
                                        <div class="card mb-3">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($comentario['usuario_nombre']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($comentario['fecha_comentario']); ?></small>
                                                </div>
                                                <div>
                                                    <a href="comentarios/editar.php?id=<?php echo $comentario['id']; ?>&return_url=<?php echo $returnParam; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                        <i class="bi bi-pencil"></i>

                                                    </a>
                                                    <a href="comentarios/eliminar.php?id=<?php echo $comentario['id']; ?>&solicitud_id=<?php echo $id; ?>&return_url=<?php echo $returnParam; ?>"
                                                        class="btn btn-sm btn-outline-danger" title="Eliminar"
                                                        onclick="return confirm('¿Está seguro de eliminar este comentario? También se eliminará el archivo si existe.');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text"><?php echo mTL($comentario['comentario']); ?></p>
                                                <?php if (!empty($comentario['archivo'])): ?>
                                                    <a href="../../uploads/comentarios/<?php echo htmlspecialchars($comentario['archivo']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm mt-2">
                                                        <i class="bi bi-paperclip"></i> Ver archivo adjunto
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>

                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">No hay comentarios de seguimiento para esta solicitud.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-center mt-4 no-print">
                        <button onclick="window.print();" class="btn btn-primary">
                            <i class="fa fa-print"></i> Imprimir hoja completa
                        </button>
                    </div>

                    <style>
                        @media print {
                            @page {
                                size: letter;
                                margin: 10mm;
                                /* Márgenes mínimos */
                            }

                            .no-print {
                                display: none !important;
                            }
                        }
                    </style>


                    <div class="card-footer text-end">

                        <a href="editar.php?id=<?php echo $id; ?>&return_url=<?php echo $returnParam; ?>" class="btn btn-warning me-2">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <a href="exportar.php?id=<?php echo $id; ?>" class="btn btn-success me-2">
                            <i class="bi bi-file-excel"></i> Exportar
                        </a>
                        <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
