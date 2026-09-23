<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: text/html; charset=UTF-8');

if (!$auth->canAccessVisitasEscuelas()) {
    http_response_code(403);
    echo '<div class="alert alert-danger mb-0">No tienes permisos para consultar este seguimiento.</div>';
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-warning mb-0">No se recibió una visita válida.</div>';
    exit();
}

$db = new Database();
$id = (int)$_GET['id'];

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fechaCorta($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00') {
        return '';
    }

    return date('d/m/Y', strtotime($fecha));
}

function fechaHoraCorta($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00 00:00:00') {
        return '';
    }

    return date('d/m/Y H:i', strtotime($fecha));
}

function turnoTexto($turno) {
    $turnos = [
        '100' => 'MATUTINO',
        '200' => 'VESPERTINO',
        '300' => 'NOCTURNA',
        '400' => 'DISCONTINUA'
    ];

    return $turnos[(string)$turno] ?? 'ND';
}

function estatusTareaTexto($estatus) {
    $estatuses = [
        0 => 'No iniciada',
        1 => 'En proceso',
        2 => 'Concluida',
        3 => 'Pendiente'
    ];

    return $estatuses[(int)$estatus] ?? 'ND';
}

function badgeSolicitud($estado) {
    if ($estado === 'Concluida') {
        return 'success';
    }
    if ($estado === 'Pendiente') {
        return 'warning';
    }
    if ($estado === 'En proceso') {
        return 'primary';
    }

    return 'secondary';
}

function badgeTarea($estatus) {
    $estatus = (int)$estatus;
    if ($estatus === 2) {
        return 'success';
    }
    if ($estatus === 3) {
        return 'warning';
    }
    if ($estatus === 1) {
        return 'primary';
    }

    return 'secondary';
}

$stmt = $db->prepare("SELECT v.*, c.NOMBRECT, c.N_NIVEL, c.TURNO, c.N_MUNICIPIO, c.N_LOCALIDAD,
        c.DOMICILIO, c.ENTRECALLE, c.YCALLE, c.NUMEXT, c.COLONIA, c.CODPOST
    FROM visitasescuelas v
    LEFT JOIN cct c ON c.CLAVECCT = v.cct
    WHERE v.id = ? AND v.eliminado = 0
    LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$visita = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visita) {
    http_response_code(404);
    echo '<div class="alert alert-warning mb-0">No se encontró la visita solicitada.</div>';
    exit();
}

$cct = strtoupper(trim($visita['cct'] ?? ''));
$solicitudes = [];

$stmtSolicitudes = $db->prepare("SELECT s.id, s.folio, s.cct, s.NOMBRECT, s.N_MUNICIPIO, s.N_LOCALIDAD,
        s.solicitante, s.ap1, s.ap2, s.fecha_peticion, s.tipo_procedencia, s.compromiso,
        s.descripcion, s.indicaciones_secretario, s.fecha_seguimiento, s.responsable_seguimiento,
        s.fecha_vencimiento, s.prioridad, s.estado, cat.categoria,
        u.nombre_completo AS usuario_nombre,
        ua.nombre_completo AS asignado_nombre,
        ua2.nombre_completo AS asignado2_nombre,
        ua3.nombre_completo AS asignado3_nombre,
        ua4.nombre_completo AS asignado4_nombre,
        ua5.nombre_completo AS asignado5_nombre
    FROM solicitudes s
    LEFT JOIN cat_categorias cat ON cat.pk_categoria = s.fk_categoria
    LEFT JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN usuarios ua ON ua.id = s.asignado_id
    LEFT JOIN usuarios ua2 ON ua2.id = s.asignado2_id
    LEFT JOIN usuarios ua3 ON ua3.id = s.asignado3_id
    LEFT JOIN usuarios ua4 ON ua4.id = s.asignado4_id
    LEFT JOIN usuarios ua5 ON ua5.id = s.asignado5_id
    WHERE UPPER(TRIM(s.cct)) = ?
        AND (s.eliminado IS NULL OR s.eliminado = 0)
    ORDER BY s.fecha_peticion DESC, s.id DESC");
$stmtSolicitudes->bind_param("s", $cct);
$stmtSolicitudes->execute();
$resultSolicitudes = $stmtSolicitudes->get_result();
while ($solicitud = $resultSolicitudes->fetch_assoc()) {
    $solicitud['tareas'] = [];
    $solicitud['comentarios'] = [];
    $solicitudes[(int)$solicitud['id']] = $solicitud;
}
$stmtSolicitudes->close();

if (!empty($solicitudes)) {
    $ids = array_keys($solicitudes);
    $idsSeguros = implode(',', array_map('intval', $ids));

    $tareas = $db->query("SELECT st.pk_tarea, st.fk_solicitud, st.tarea, st.fecha, st.fecha_cierre,
            st.fk_estatus, u.nombre_completo AS usuario_nombre
        FROM solicitudes_tareas st
        LEFT JOIN usuarios u ON u.id = st.fk_usuario
        WHERE st.fk_solicitud IN ($idsSeguros)
        ORDER BY st.fecha DESC, st.pk_tarea DESC");
    while ($tarea = $tareas->fetch_assoc()) {
        $solicitudId = (int)$tarea['fk_solicitud'];
        if (isset($solicitudes[$solicitudId])) {
            $solicitudes[$solicitudId]['tareas'][] = $tarea;
        }
    }

    $comentarios = $db->query("SELECT cs.id, cs.solicitud_id, cs.fecha_comentario, cs.comentario,
            cs.archivo, cs.fecha_creacion, u.nombre_completo AS usuario_nombre
        FROM comentarios_seguimiento cs
        LEFT JOIN usuarios u ON u.id = cs.usuario_id
        WHERE cs.solicitud_id IN ($idsSeguros)
        ORDER BY cs.fecha_comentario DESC, cs.id DESC");
    while ($comentario = $comentarios->fetch_assoc()) {
        $solicitudId = (int)$comentario['solicitud_id'];
        if (isset($solicitudes[$solicitudId])) {
            $solicitudes[$solicitudId]['comentarios'][] = $comentario;
        }
    }
}

$direccion = array_filter([
    trim($visita['DOMICILIO'] ?? ''),
    !empty($visita['NUMEXT']) ? 'NUM. EXT. ' . trim($visita['NUMEXT']) : '',
    !empty($visita['COLONIA']) ? 'COL. ' . trim($visita['COLONIA']) : '',
    !empty($visita['CODPOST']) ? 'C.P. ' . trim($visita['CODPOST']) : ''
]);
$entreCalles = array_filter([
    !empty($visita['ENTRECALLE']) ? 'ENTRE ' . trim($visita['ENTRECALLE']) : '',
    !empty($visita['YCALLE']) ? 'Y ' . trim($visita['YCALLE']) : ''
]);
?>
<div class="seguimiento-summary mb-3">
    <div class="row g-3">
        <div class="col-md-3">
            <span class="seguimiento-label">Fecha de visita</span>
            <span class="seguimiento-value"><?php echo h(fechaCorta($visita['fecha_visita'])); ?></span>
        </div>
        <div class="col-md-3">
            <span class="seguimiento-label">Estatus visita</span>
            <span class="seguimiento-value"><?php echo h($visita['estatus']); ?></span>
        </div>
        <div class="col-md-3">
            <span class="seguimiento-label">CCT</span>
            <span class="seguimiento-value"><?php echo h($visita['cct']); ?></span>
        </div>
        <div class="col-md-3">
            <span class="seguimiento-label">Turno</span>
            <span class="seguimiento-value"><?php echo h(turnoTexto($visita['TURNO'] ?? '')); ?></span>
        </div>
        <div class="col-md-6">
            <span class="seguimiento-label">Escuela</span>
            <span class="seguimiento-value"><?php echo h($visita['NOMBRECT'] ?: 'Sin información de catálogo'); ?></span>
        </div>
        <div class="col-md-3">
            <span class="seguimiento-label">Nivel</span>
            <span class="seguimiento-value"><?php echo h($visita['N_NIVEL']); ?></span>
        </div>
        <div class="col-md-3">
            <span class="seguimiento-label">Municipio</span>
            <span class="seguimiento-value"><?php echo h($visita['N_MUNICIPIO']); ?></span>
        </div>
        <div class="col-md-3">
            <span class="seguimiento-label">Localidad</span>
            <span class="seguimiento-value"><?php echo h($visita['N_LOCALIDAD']); ?></span>
        </div>
        <div class="col-md-8">
            <span class="seguimiento-label">Dirección</span>
            <span class="seguimiento-value"><?php echo h(implode(', ', $direccion)); ?></span>
            <?php if (!empty($entreCalles)): ?>
                <div class="text-muted small"><?php echo h(implode(' ', $entreCalles)); ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <span class="seguimiento-label">Observaciones visita</span>
            <span class="seguimiento-value"><?php echo h($visita['observaciones']); ?></span>
        </div>
    </div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
    <h6 class="mb-0 fw-bold text-uppercase">Folios relacionados al CCT</h6>
    <span class="badge bg-secondary align-self-md-center"><?php echo count($solicitudes); ?> folio(s)</span>
</div>

<?php if (empty($solicitudes)): ?>
    <div class="alert alert-info mb-0">No se encontraron solicitudes registradas con este CCT.</div>
<?php endif; ?>

<?php foreach ($solicitudes as $solicitud): ?>
    <?php
    $solicitante = trim(implode(' ', array_filter([
        $solicitud['solicitante'] ?? '',
        $solicitud['ap1'] ?? '',
        $solicitud['ap2'] ?? ''
    ])));
    $asignados = array_filter([
        $solicitud['asignado_nombre'] ?? '',
        $solicitud['asignado2_nombre'] ?? '',
        $solicitud['asignado3_nombre'] ?? '',
        $solicitud['asignado4_nombre'] ?? '',
        $solicitud['asignado5_nombre'] ?? ''
    ]);
    ?>
    <div class="folio-card">
        <div class="folio-card-header">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                <div>
                    <div class="fw-bold">
                        Folio <?php echo h($solicitud['folio']); ?>
                        <span class="badge bg-<?php echo h(badgeSolicitud($solicitud['estado'])); ?> ms-1"><?php echo h($solicitud['estado']); ?></span>
                    </div>
                    <div class="text-muted small"><?php echo h($solicitud['categoria']); ?> | Prioridad: <?php echo h($solicitud['prioridad']); ?></div>
                </div>
                <a class="btn btn-outline-secondary btn-sm align-self-start" href="../solicitudes/ver.php?id=<?php echo (int)$solicitud['id']; ?>">
                    <i class="bi bi-box-arrow-up-right"></i> Ver folio
                </a>
            </div>
        </div>
        <div class="p-3">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <span class="seguimiento-label">Solicitante</span>
                    <span class="seguimiento-value"><?php echo h($solicitante); ?></span>
                </div>
                <div class="col-md-2">
                    <span class="seguimiento-label">Fecha</span>
                    <span class="seguimiento-value"><?php echo h(fechaCorta($solicitud['fecha_peticion'])); ?></span>
                </div>
                <div class="col-md-3">
                    <span class="seguimiento-label">Procedencia</span>
                    <span class="seguimiento-value"><?php echo h($solicitud['tipo_procedencia']); ?></span>
                </div>
                <div class="col-md-3">
                    <span class="seguimiento-label">Asignado a</span>
                    <span class="seguimiento-value"><?php echo h(implode(', ', $asignados)); ?></span>
                </div>
                <div class="col-md-4">
                    <span class="seguimiento-label">Fecha seguimiento</span>
                    <span class="seguimiento-value"><?php echo h(fechaCorta($solicitud['fecha_seguimiento'])); ?></span>
                </div>
                <div class="col-md-4">
                    <span class="seguimiento-label">Responsable seguimiento</span>
                    <span class="seguimiento-value"><?php echo h($solicitud['responsable_seguimiento']); ?></span>
                </div>
                <div class="col-md-4">
                    <span class="seguimiento-label">Fecha vencimiento</span>
                    <span class="seguimiento-value"><?php echo h(fechaCorta($solicitud['fecha_vencimiento'])); ?></span>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <span class="seguimiento-label">Compromiso</span>
                    <div class="seguimiento-text small"><?php echo h($solicitud['compromiso']); ?></div>
                </div>
                <div class="col-md-6">
                    <span class="seguimiento-label">Descripción</span>
                    <div class="seguimiento-text small"><?php echo h($solicitud['descripcion']); ?></div>
                </div>
                <?php if (!empty($solicitud['indicaciones_secretario'])): ?>
                    <div class="col-md-12">
                        <span class="seguimiento-label">Indicaciones del Secretario</span>
                        <div class="seguimiento-text small"><?php echo h($solicitud['indicaciones_secretario']); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <hr>
            <div class="row g-3">
                <div class="col-lg-6">
                    <h6 class="fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Tareas</h6>
                    <?php if (empty($solicitud['tareas'])): ?>
                        <div class="text-muted small">Sin tareas registradas.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($solicitud['tareas'] as $tarea): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong class="small"><?php echo h($tarea['usuario_nombre']); ?></strong>
                                        <span class="badge bg-<?php echo h(badgeTarea($tarea['fk_estatus'])); ?>"><?php echo h(estatusTareaTexto($tarea['fk_estatus'])); ?></span>
                                    </div>
                                    <div class="seguimiento-text small"><?php echo h($tarea['tarea']); ?></div>
                                    <div class="text-muted small">
                                        Inicio: <?php echo h(fechaHoraCorta($tarea['fecha'])); ?>
                                        <?php if (!empty($tarea['fecha_cierre'])): ?>
                                            | Cierre: <?php echo h(fechaHoraCorta($tarea['fecha_cierre'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-bold mb-2"><i class="bi bi-chat-left-text me-1"></i>Comentarios de seguimiento</h6>
                    <?php if (empty($solicitud['comentarios'])): ?>
                        <div class="text-muted small">Sin comentarios registrados.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($solicitud['comentarios'] as $comentario): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong class="small"><?php echo h($comentario['usuario_nombre']); ?></strong>
                                        <span class="text-muted small"><?php echo h(fechaCorta($comentario['fecha_comentario'])); ?></span>
                                    </div>
                                    <div class="seguimiento-text small"><?php echo h($comentario['comentario']); ?></div>
                                    <?php if (!empty($comentario['archivo'])): ?>
                                        <a class="small" href="../../uploads/comentarios/<?php echo rawurlencode($comentario['archivo']); ?>" target="_blank">
                                            <i class="bi bi-paperclip"></i> Archivo adjunto
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
