<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}

$db = new Database();
$usuario_id = $_SESSION['user_id'];
$usuario_rol = $_SESSION['rol'];

// Configuración de paginación
$registrosPorPagina = 10;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$inicio = ($pagina - 1) * $registrosPorPagina;

// Búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? sanitizeInput($_GET['busqueda']) : '';
$fechaInicio = isset($_GET['fecha_inicio']) ? sanitizeInput($_GET['fecha_inicio']) : '';
$fechaFin = isset($_GET['fecha_fin']) ? sanitizeInput($_GET['fecha_fin']) : '';
$prioridadFiltro = isset($_GET['prioridad']) ? sanitizeInput($_GET['prioridad']) : '';
$estadoFiltro = isset($_GET['estado']) ? sanitizeInput($_GET['estado']) : '';
$categoriaFiltro = isset($_GET['categoria']) ? sanitizeInput($_GET['categoria']) : '';
$municipioLocalidadFiltro = isset($_GET['municipio_localidad']) ? sanitizeInput($_GET['municipio_localidad']) : '';
$etiquetasFiltro = isset($_GET['etiquetas']) ? $_GET['etiquetas'] : [];
$returnParams = $_GET;
unset($returnParams['success'], $returnParams['error']);
$returnUrl = 'index.php' . (!empty($returnParams) ? '?' . http_build_query($returnParams) : '');
$returnParam = urlencode($returnUrl);

$solicitudesSinComentarioUsuario = [];
$solicitudesRecientesUsuario = [];
$solicitudesAtencionSinRespuesta = [];
$conteosAtencionEstados = [
    'Pendiente' => 0,
    'En proceso' => 0,
    'Concluida' => 0
];
$condicionAtencionCiudadana = condicionAtencionCiudadanaSQL('s', 'c');

if ($usuario_rol === 'usuario') {
    $filtroSolicitudesUsuario = " AND (
        s.asignado_id = ? OR
        s.asignado2_id = ? OR
        s.asignado3_id = ? OR
        s.asignado4_id = ? OR
        s.asignado5_id = ? OR
        s.usuario_id = ?
    )";

    $sqlSinComentarioUsuario = "SELECT DISTINCT s.id, s.folio
        FROM solicitudes AS s
        WHERE (s.eliminado IS NULL OR s.eliminado = 0)
        AND NOT EXISTS (
            SELECT 1
            FROM comentarios_seguimiento AS cs
            WHERE cs.solicitud_id = s.id
            AND cs.usuario_id = ?
        )
        {$filtroSolicitudesUsuario}
        ORDER BY s.fecha_peticion DESC, s.id DESC";
    $paramsSinComentarioUsuario = array_merge([$usuario_id], array_fill(0, 6, $usuario_id));
    $stmtSinComentarioUsuario = $db->prepare($sqlSinComentarioUsuario);
    $stmtSinComentarioUsuario->bind_param('iiiiiii', ...$paramsSinComentarioUsuario);
    $stmtSinComentarioUsuario->execute();
    $solicitudesSinComentarioUsuario = $stmtSinComentarioUsuario->get_result()->fetch_all(MYSQLI_ASSOC);

    $sqlRecientesUsuario = "SELECT DISTINCT s.id, s.folio
        FROM solicitudes AS s
        WHERE (s.eliminado IS NULL OR s.eliminado = 0)
        AND s.fecha_peticion >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
        {$filtroSolicitudesUsuario}
        ORDER BY s.fecha_peticion DESC, s.id DESC";
    $paramsRecientesUsuario = array_fill(0, 6, $usuario_id);
    $stmtRecientesUsuario = $db->prepare($sqlRecientesUsuario);
    $stmtRecientesUsuario->bind_param('iiiiii', ...$paramsRecientesUsuario);
    $stmtRecientesUsuario->execute();
    $solicitudesRecientesUsuario = $stmtRecientesUsuario->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($usuario_rol === 'atencion_ciudadana') {
    $sqlAtencionSinRespuesta = "SELECT DISTINCT s.id, s.folio, DATEDIFF(CURDATE(), s.fecha_peticion) AS dias
        FROM solicitudes AS s
        INNER JOIN cat_categorias AS c ON c.pk_categoria = s.fk_categoria
        WHERE (s.eliminado IS NULL OR s.eliminado = 0)
        AND {$condicionAtencionCiudadana}
        AND s.estado = 'No iniciada'
        AND DATEDIFF(CURDATE(), s.fecha_peticion) > 10
        AND NOT EXISTS (
            SELECT 1
            FROM comentarios_seguimiento AS cs
            WHERE cs.solicitud_id = s.id
        )
        ORDER BY s.fecha_peticion ASC, s.id ASC";
    $solicitudesAtencionSinRespuesta = $db->query($sqlAtencionSinRespuesta)->fetch_all(MYSQLI_ASSOC);

    $sqlConteosAtencionEstados = "SELECT s.estado, COUNT(DISTINCT s.id) AS total
        FROM solicitudes AS s
        INNER JOIN cat_categorias AS c ON c.pk_categoria = s.fk_categoria
        WHERE (s.eliminado IS NULL OR s.eliminado = 0)
        AND {$condicionAtencionCiudadana}
        AND s.estado IN ('Pendiente', 'En proceso', 'Concluida')
        GROUP BY s.estado";
    $resultConteosAtencionEstados = $db->query($sqlConteosAtencionEstados);
    while ($conteoEstado = $resultConteosAtencionEstados->fetch_assoc()) {
        $conteosAtencionEstados[$conteoEstado['estado']] = (int)$conteoEstado['total'];
    }
}

// Construir consulta SQL con filtros
$inner = "";
$campo = "";
$group = "GROUP BY s.id";
if (count($etiquetasFiltro) > 0) {
    $inner = " INNER JOIN solicitudes_rel AS sr ON s.id = sr.fk_solicitud
                INNER JOIN cat_etiquetas AS e ON e.pk_etiqueta = sr.fk_etiqueta";
    $campo = ", GROUP_CONCAT(DISTINCT e.etiqueta SEPARATOR ', ') AS etiqueta";
    $group = " GROUP BY s.id";
}
$sql = "SELECT SQL_CALC_FOUND_ROWS s.id, s.folio, c.categoria, s.cct, s.N_MUNICIPIO, s.N_LOCALIDAD, s.NOMBRECT, s.solicitante,s.ap1,s.ap2, s.fecha_peticion, s.prioridad,
               s.estado, s.compromiso, s.descripcion, IF(s.fecha_vencimiento = '0000-00-00', 'N/D', s.fecha_vencimiento) AS fecha_vencimiento, u.nombre_completo AS usuario,
               COUNT(st.pk_tarea) as total_tareas,
               SUM(CASE WHEN st.fk_estatus = 2 THEN 1 ELSE 0 END) as tareas_completadas,
               CASE
                   WHEN COUNT(st.pk_tarea) > 0 THEN
                       ROUND((SUM(CASE WHEN st.fk_estatus = 2 THEN 1 ELSE 0 END) / COUNT(st.pk_tarea)) * 100, 0)
                   ELSE 0
               END as porcentaje_completado
               {$campo}
        FROM solicitudes AS s
        INNER JOIN cat_categorias AS c ON c.pk_categoria = s.fk_categoria
        {$inner}
        LEFT JOIN solicitudes_tareas st ON s.id = st.fk_solicitud
        JOIN usuarios AS u ON s.usuario_id = u.id
        WHERE 1=1";

$params = [];
$types = '';

// Filtro por rol
if ($usuario_rol === 'usuario') {
    $sql .= " AND (
        s.asignado_id = ? OR
        s.asignado2_id = ? OR
        s.asignado3_id = ? OR
        s.asignado4_id = ? OR
        s.asignado5_id = ? OR
        s.usuario_id = ?
    )";
    $params[] = $usuario_id;
    $params[] = $usuario_id;
    $params[] = $usuario_id;
    $params[] = $usuario_id;
    $params[] = $usuario_id;
    $params[] = $usuario_id;
    $types .= 'iiiiii';
}

// Filtro por rol especial - solo ve solicitudes de sus categorías/etiquetas
if ($usuario_rol === 'especial') {
    $sql .= " AND (
        s.fk_categoria IN (
            SELECT fk_categoria FROM usuarios_categorias WHERE fk_usuario = ?
        )
        OR s.id IN (
            SELECT sr.fk_solicitud FROM solicitudes_rel sr
            INNER JOIN usuarios_etiquetas ue ON sr.fk_etiqueta = ue.fk_etiqueta
            WHERE ue.fk_usuario = ?
        )
    )";
    $params[] = $usuario_id;
    $params[] = $usuario_id;
    $types .= 'ii';
}

// Filtro por rol Atención Ciudadana - solo ve categoría o etiqueta Atención Ciudadana
if ($usuario_rol === 'atencion_ciudadana') {
    $sql .= " AND {$condicionAtencionCiudadana}";
}
// Aplicar filtros de búsqueda
if (!empty($busqueda)) {
    $sql .= " AND (s.folio LIKE ?
                    OR c.categoria LIKE ?
                    OR s.cct LIKE ?
                    OR s.N_MUNICIPIO LIKE ?
                    OR s.N_LOCALIDAD LIKE ?
                    OR s.NOMBRECT LIKE ?
                    OR TRIM(CONCAT(s.ap1, ' ',s.solicitante)) LIKE ?
                    OR TRIM(CONCAT(s.solicitante,' ',s.ap1)) LIKE ?
                    OR TRIM(CONCAT(s.ap1, ' ', s.ap2,' ', s.solicitante)) LIKE ?
                    OR TRIM(CONCAT(s.solicitante,' ',s.ap1,' ',s.ap2)) LIKE ?
                    OR s.solicitante LIKE ?
                    OR s.ap1 LIKE ?
                    OR s.ap2 LIKE ?
                    OR s.compromiso LIKE ?
                  OR s.descripcion LIKE ?
                  OR s.instruido LIKE ?
                  OR s.solicitado LIKE ?
                  OR s.atendio LIKE ?
                  OR s.responsable_seguimiento LIKE ?
                  OR s.indicaciones_secretario LIKE ?)";
    $searchTerm = "%$busqueda%";

    for ($i = 0; $i < 20; $i++) {
        $params[] = $searchTerm;
        $types .= 's';
    }
}

// Aplicar filtro por municipio o localidad
if (!empty($municipioLocalidadFiltro)) {
    $sql .= " AND (s.N_MUNICIPIO LIKE ? OR s.N_LOCALIDAD LIKE ?)";
    $ubicacionTerm = "%$municipioLocalidadFiltro%";
    $params[] = $ubicacionTerm;
    $params[] = $ubicacionTerm;
    $types .= 'ss';
}

// Aplicar filtros de fecha
if (!empty($fechaInicio) && !empty($fechaFin)) {
    $sql .= " AND s.fecha_peticion BETWEEN ? AND ?";
    $params[] = $fechaInicio;
    $params[] = $fechaFin;
    $types .= 'ss';
} elseif (!empty($fechaInicio)) {
    $sql .= " AND s.fecha_peticion >= ?";
    $params[] = $fechaInicio;
    $types .= 's';
} elseif (!empty($fechaFin)) {
    $sql .= " AND s.fecha_peticion <= ?";
    $params[] = $fechaFin;
    $types .= 's';
}

// Aplicar filtro de prioridad
if (!empty($prioridadFiltro)) {
    $sql .= " AND s.prioridad = ?";
    $params[] = $prioridadFiltro;
    $types .= 's';
}

// Aplicar filtro de estado
if (!empty($estadoFiltro)) {
    $sql .= " AND s.estado = ?";
    $params[] = $estadoFiltro;
    $types .= 's';
}

// Aplicar categoria

if (!empty($categoriaFiltro)) {
    $sql .= " AND s.fk_categoria = ?";
    $params[] = $categoriaFiltro;
    $types .= 'i';
}

if (count($etiquetasFiltro) > 0) {
    $sql .= " AND sr.fk_etiqueta IN (" . implode(',', array_fill(0, count($etiquetasFiltro), '?')) . ")";
    $params = array_merge($params, $etiquetasFiltro);
    $types .= str_repeat('i', count($etiquetasFiltro));
}
//Filtro eliminado
$sql .= " AND (s.eliminado IS NULL OR s.eliminado = 0)";
$sql .= "  {$group} ORDER BY
            s.fecha_peticion DESC,
            s.id DESC
          LIMIT ?, ?";

//echo $sql;die;
$params[] = $inicio;
$params[] = $registrosPorPagina;
$types .= 'ii';
// Preparar y ejecutar consulta
$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$solicitudes = $result->fetch_all(MYSQLI_ASSOC);
// Obtener total de registros para paginación
$totalRegistros = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
/**/
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
}

// Obtener etiquetas según el rol
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
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <style>
        :root {
            --morena-guinda: #8D2D44;
            --morena-guinda-dark: #5f1830;
            --morena-guinda-soft: #f6edf0;
            --morena-dorado: #b38e5d;
            --morena-ink: #2b2024;
            --morena-muted: #6f6468;
            --morena-border: #e9dfdf;
        }

        body {
            background: #f7f5f2;
            color: var(--morena-ink);
        }

        .solicitudes-shell {
            padding: 28px 0 46px;
        }

        .page-band {
            background: linear-gradient(135deg, var(--morena-guinda-dark), var(--morena-guinda));
            border-radius: 8px;
            box-shadow: 0 16px 36px rgba(95, 24, 48, .18);
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        .page-band::after {
            content: "";
            position: absolute;
            inset: 0 0 0 auto;
            width: 32%;
            background:
                linear-gradient(135deg, transparent 0 48%, rgba(179, 142, 93, .28) 48% 52%, transparent 52%),
                linear-gradient(45deg, transparent 0 42%, rgba(255, 255, 255, .10) 42% 46%, transparent 46%);
            opacity: .75;
        }

        .page-band-content {
            padding: 14px 20px;
            position: relative;
            z-index: 1;
        }

        .page-kicker {
            align-items: center;
            color: rgba(255, 255, 255, .82);
            display: flex;
            font-size: .82rem;
            font-weight: 800;
            gap: 8px;
            letter-spacing: .04em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .page-title {
            font-size: clamp(1.25rem, 2vw, 1.65rem);
            font-weight: 800;
            line-height: 1.15;
            margin: 0;
        }

        .page-subtitle {
            color: rgba(255, 255, 255, .86);
            font-size: .92rem;
            margin: 4px 0 0;
            max-width: 720px;
        }

        .total-chip {
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-weight: 800;
            gap: 8px;
            padding: 7px 12px;
            white-space: nowrap;
        }

        .action-bar,
        .filter-card,
        .table-card,
        .metric-card {
            background: #fff;
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(43, 32, 36, .07);
        }

        .action-bar {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            margin: 18px 0;
            padding: 14px;
        }

        .btn-morena {
            background: var(--morena-guinda);
            border-color: var(--morena-guinda);
            color: #fff;
            font-weight: 700;
        }

        .btn-morena:hover,
        .btn-morena:focus {
            background: var(--morena-guinda-dark);
            border-color: var(--morena-guinda-dark);
            color: #fff;
        }

        .btn-outline-morena {
            border-color: #d8c3c8;
            color: var(--morena-guinda);
            font-weight: 700;
        }

        .btn-outline-morena:hover,
        .btn-outline-morena:focus {
            background: var(--morena-guinda-soft);
            border-color: var(--morena-guinda);
            color: var(--morena-guinda-dark);
        }

        .metric-card {
            border-left: 5px solid var(--morena-guinda);
            height: 100%;
            padding: 18px;
        }

        .metric-card.recent {
            border-left-color: var(--morena-dorado);
        }

        .metric-icon {
            align-items: center;
            background: var(--morena-guinda-soft);
            border-radius: 8px;
            color: var(--morena-guinda);
            display: inline-flex;
            font-size: 1.25rem;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .metric-card.recent .metric-icon {
            background: #f5efe6;
            color: #8a673b;
        }

        .metric-title {
            color: var(--morena-ink);
            font-weight: 800;
        }

        .metric-help {
            color: var(--morena-muted);
            font-size: .88rem;
        }

        .metric-number {
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 800;
            min-width: 58px;
        }

        .metric-status-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            min-width: 260px;
        }

        .metric-status-item {
            background: #fbf8f5;
            border: 1px solid #eadfda;
            border-radius: 8px;
            padding: 8px 10px;
            text-align: center;
        }

        .metric-status-value {
            color: var(--morena-guinda);
            display: block;
            font-size: 1.15rem;
            font-weight: 900;
            line-height: 1.1;
        }

        .metric-status-label {
            color: var(--morena-muted);
            display: block;
            font-size: .78rem;
            font-weight: 700;
            margin-top: 3px;
        }

        .filter-card {
            margin-bottom: 22px;
            overflow: hidden;
        }

        .filter-header {
            align-items: center;
            background: #fff;
            border-bottom: 1px solid var(--morena-border);
            color: var(--morena-guinda-dark);
            display: flex;
            font-weight: 800;
            gap: 10px;
            padding: 16px 18px;
        }

        .filter-card .card-body {
            padding: 18px;
        }

        .form-label {
            color: var(--morena-ink);
            font-size: .88rem;
            font-weight: 700;
        }

        .form-control:focus,
        .form-select:focus,
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--morena-guinda);
            box-shadow: 0 0 0 .2rem rgba(141, 45, 68, .14);
        }

        .table-card {
            overflow: hidden;
        }

        .table-card-header {
            align-items: center;
            border-bottom: 1px solid var(--morena-border);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
            padding: 16px 18px;
        }

        .table-card-title {
            color: var(--morena-guinda-dark);
            font-size: 1rem;
            font-weight: 800;
            margin: 0;
        }

        .table-card .card-body {
            padding: 0;
        }

        .solicitudes-table {
            margin-bottom: 0;
        }

        .solicitudes-table thead th {
            background: var(--morena-guinda) !important;
            border-color: rgba(255, 255, 255, .16);
            color: #fff;
            font-size: .82rem;
            letter-spacing: .02em;
            text-transform: uppercase;
            vertical-align: middle;
            white-space: nowrap;
        }

        .solicitudes-table tbody td {
            vertical-align: middle;
        }

        .cct-meta {
            color: #6c757d;
            font-size: .72rem;
            line-height: 1.2;
            max-width: 120px;
            text-transform: uppercase;
            white-space: normal;
        }

        .cct-warning {
            color: #b02a37;
            font-size: .72rem;
            font-weight: 700;
            line-height: 1.2;
            max-width: 120px;
            white-space: normal;
        }

        .solicitudes-table .btn-group .btn {
            border-radius: 6px !important;
            margin-right: 4px;
        }

        .pagination .page-link {
            color: var(--morena-guinda);
        }

        .pagination .active .page-link {
            background: var(--morena-guinda);
            border-color: var(--morena-guinda);
            color: #fff;
        }

        .total-registros {
            color: var(--morena-muted);
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .page-band-content {
                padding: 16px;
            }

            .page-band::after {
                width: 58%;
                opacity: .35;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <main class="solicitudes-shell">
        <div class="container">
            <section class="page-band mb-3">
                <div class="page-band-content">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-4">
                        <div>
                            <div class="page-kicker">
                                <i class="bi bi-inbox-fill"></i>
                                Módulo de seguimiento
                            </div>
                            <h1 class="page-title">Gestión de Solicitudes</h1>
                            <p class="page-subtitle">Consulte, filtre y atienda los folios registrados en el sistema.</p>
                        </div>
                        <div class="total-chip">
                            <i class="bi bi-list-check"></i>
                            <?php echo $totalRegistros; ?> registros
                        </div>
                    </div>
                </div>
            </section>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success'] ?? ''); ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error'] ?? ''); ?></div>
                <?php endif; ?>

                <?php if ($usuario_rol === 'usuario' || $usuario_rol === 'atencion_ciudadana'): ?>
                    <?php
                    $esAtencionCiudadana = $usuario_rol === 'atencion_ciudadana';
                    $foliosPrimeraMetrica = $esAtencionCiudadana ? $solicitudesAtencionSinRespuesta : $solicitudesSinComentarioUsuario;
                    $collapsePrimeraMetrica = $esAtencionCiudadana ? 'foliosAtencionSinRespuesta' : 'foliosSinComentarioUsuario';
                    ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="metric-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="metric-icon">
                                            <i class="bi bi-chat-left-dots"></i>
                                        </div>
                                        <div>
                                            <div class="metric-title"><?php echo $esAtencionCiudadana ? 'Folios pendientes de respuesta' : 'Folios sin seguimiento del área'; ?></div>
                                            <div class="metric-help"><?php echo $esAtencionCiudadana ? 'Solicitudes con más de 10 días de registradas, con estatus No iniciada y sin comentarios.' : 'Solicitudes activas donde aún no has comentado.'; ?></div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button class="btn btn-morena btn-sm metric-number" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapsePrimeraMetrica; ?>" aria-expanded="false" aria-controls="<?php echo $collapsePrimeraMetrica; ?>" title="Ver folios SESOL">
                                            <?php echo count($foliosPrimeraMetrica); ?>
                                            <i class="bi bi-list-ul ms-1"></i>
                                        </button>
                                        <div class="metric-help mt-1">Clic para ver folios SESOL</div>
                                    </div>
                                </div>
                                <div class="collapse mt-3" id="<?php echo $collapsePrimeraMetrica; ?>">
                                    <?php if (empty($foliosPrimeraMetrica)): ?>
                                        <div class="text-muted small">No hay folios pendientes.</div>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($foliosPrimeraMetrica as $solicitudPendiente): ?>
                                                <a href="ver.php?id=<?php echo $solicitudPendiente['id']; ?>&return_url=<?php echo $returnParam; ?>" class="badge bg-dark text-decoration-none">
                                                    <?php echo htmlspecialchars($solicitudPendiente['folio'] ?? ''); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="metric-card recent">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="metric-icon">
                                            <i class="bi bi-clock-history"></i>
                                        </div>
                                        <div>
                                            <div class="metric-title"><?php echo $esAtencionCiudadana ? 'Resumen Atención Ciudadana' : 'Recientes'; ?></div>
                                            <div class="metric-help"><?php echo $esAtencionCiudadana ? 'Folios clasificados por estatus.' : 'Solicitudes activas de los últimos 5 días.'; ?></div>
                                        </div>
                                    </div>
                                    <?php if ($esAtencionCiudadana): ?>
                                        <div class="metric-status-grid">
                                            <div class="metric-status-item">
                                                <span class="metric-status-value"><?php echo $conteosAtencionEstados['Pendiente']; ?></span>
                                                <span class="metric-status-label">Pendientes</span>
                                            </div>
                                            <div class="metric-status-item">
                                                <span class="metric-status-value"><?php echo $conteosAtencionEstados['En proceso']; ?></span>
                                                <span class="metric-status-label">En proceso</span>
                                            </div>
                                            <div class="metric-status-item">
                                                <span class="metric-status-value"><?php echo $conteosAtencionEstados['Concluida']; ?></span>
                                                <span class="metric-status-label">Concluidos</span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-end">
                                            <button class="btn btn-morena btn-sm metric-number" type="button" data-bs-toggle="collapse" data-bs-target="#foliosRecientesUsuario" aria-expanded="false" aria-controls="foliosRecientesUsuario" title="Ver folios SESOL">
                                                <?php echo count($solicitudesRecientesUsuario); ?>
                                                <i class="bi bi-list-ul ms-1"></i>
                                            </button>
                                            <div class="metric-help mt-1">Clic para ver folios SESOL</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$esAtencionCiudadana): ?>
                                    <div class="collapse mt-3" id="foliosRecientesUsuario">
                                        <?php if (empty($solicitudesRecientesUsuario)): ?>
                                            <div class="text-muted small">No hay folios recientes.</div>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($solicitudesRecientesUsuario as $solicitudReciente): ?>
                                                    <a href="ver.php?id=<?php echo $solicitudReciente['id']; ?>&return_url=<?php echo $returnParam; ?>" class="badge bg-dark text-decoration-none">
                                                        <?php echo htmlspecialchars($solicitudReciente['folio'] ?? ''); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="action-bar">
                    <?php if ($usuario_rol !== 'monitor'): ?>
                        <a href="crear.php" class="btn btn-morena">
                            <i class="bi bi-plus-circle"></i> Nueva Solicitud
                        </a>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="exportar.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-morena">
                            <i class="bi bi-file-excel"></i> Exportar a Excel
                        </a>
                        <a href="exportar2.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-morena">
                            <i class="bi bi-file-excel"></i> Exportar a Excel (etiquetas)
                        </a>
                        <a href="pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-morena">
                            <i class="bi bi-file-pdf"></i> Exportar a PDF
                        </a>
                    </div>
                </div>

                <!-- Filtros de búsqueda -->
                <div class="filter-card">
                    <div class="filter-header">
                        <i class="bi bi-funnel"></i>
                        <span>Filtrar Solicitudes</span>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="busqueda" class="form-label">Buscar en todos los campos</label>
                                    <input type="text" class="form-control" id="busqueda" name="busqueda"
                                        value="<?php echo htmlspecialchars($busqueda ?? ''); ?>"
                                        placeholder="Ingrese término de búsqueda...">
                                </div>

                                <div class="col-md-3">
                                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
                                        value="<?php echo htmlspecialchars($fechaInicio ?? ''); ?>">
                                </div>

                                <div class="col-md-3">
                                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin"
                                        value="<?php echo htmlspecialchars($fechaFin ?? ''); ?>">
                                </div>

                                <div class="col-md-3">
                                    <label for="municipio_localidad" class="form-label">Municipio o localidad</label>
                                    <select class="form-control" id="municipio_localidad" name="municipio_localidad">
                                        <?php if (!empty($municipioLocalidadFiltro)) { ?>
                                            <option value="<?php echo htmlspecialchars($municipioLocalidadFiltro); ?>" selected>
                                                <?php echo htmlspecialchars($municipioLocalidadFiltro); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="prioridad" class="form-label">Prioridad</label>
                                    <select class="form-select" id="prioridad" name="prioridad">
                                        <option value="">Todas</option>
                                        <option value="Alta" <?php echo $prioridadFiltro === 'Alta' ? 'selected' : ''; ?>>Alta</option>
                                        <option value="Media" <?php echo $prioridadFiltro === 'Media' ? 'selected' : ''; ?>>Media</option>
                                        <option value="Baja" <?php echo $prioridadFiltro === 'Baja' ? 'selected' : ''; ?>>Baja</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="estado" class="form-label">Estado</label>
                                    <select class="form-select" id="estado" name="estado">
                                        <option value="">Todos</option>
                                        <option value="No iniciada" <?php echo $estadoFiltro === 'No iniciada' ? 'selected' : ''; ?>>No iniciada</option>
                                        <option value="En proceso" <?php echo $estadoFiltro === 'En proceso' ? 'selected' : ''; ?>>En proceso</option>
                                        <option value="Concluida" <?php echo $estadoFiltro === 'Concluida' ? 'selected' : ''; ?>>Concluida</option>
                                        <option value="Pendiente" <?php echo $estadoFiltro === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="categoria" class="form-label">Categoría</label>
                                    <select class="form-select" id="categoria" name="categoria">
                                        <option value="">Todos</option>
                                        <?php while ($categoria = $categorias->fetch_assoc()) { ?>
                                            <option value="<?= $categoria['pk_categoria'] ?>" <?= $categoria['pk_categoria'] === $categoriaFiltro ? 'selected' : '' ?>><?= $categoria['categoria'] ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="etiquetas" class="form-label">Etiquetas</label>
                                    <select class="form-select" id="etiquetas" name="etiquetas[]" multiple>
                                        <?php while ($etiqueta = $etiquetas->fetch_assoc()) { ?>
                                            <option value="<?= $etiqueta['pk_etiqueta'] ?>" <?= in_array($etiqueta['pk_etiqueta'], $etiquetasFiltro) ? 'selected' : '' ?>><?= $etiqueta['etiqueta'] ?></option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="col-md-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-morena me-2">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                    <a href="index.php" class="btn btn-outline-morena">
                                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de solicitudes -->
                <div class="table-card">
                    <div class="table-card-header">
                        <h2 class="table-card-title">
                            <i class="bi bi-table me-2"></i>
                            Solicitudes registradas
                        </h2>
                        <span class="total-registros">Total: <?php echo $totalRegistros; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover solicitudes-table">
                                <thead>
                                    <tr style="background-color: #8D2D44 !important;">
                                        <th>Folio</th>
                                        <th>Categoría</th>
                                        <th>CCT</th>
                                        <th>Solicitante</th>
                                        <th>Fecha</th>
                                        <th>Compromiso</th>
                                        <th>Prioridad</th>
                                        <th>Estado</th>
                                        <?php /*
                                        <th>Vencimiento</th>
                                        */ ?>
                                        <th>Descripción</th>
                                        <?php if (count($etiquetasFiltro) > 0) { ?>
                                            <th>Etiquetas</th>
                                        <?php } ?>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($solicitudes)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center">No se encontraron solicitudes</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($solicitudes as $solicitud): ?>
                                            <tr class="<?php echo $solicitud['estado'] === 'Concluida' ? 'table-success' : ($solicitud['estado'] === 'Pendiente' ? 'table-warning' : ''); ?>">
                                                <td><?php echo htmlspecialchars($solicitud['folio'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($solicitud['categoria'] ?? ''); ?></td>
                                                <td class="upper">
                                                    <?php
                                                    $ubicacionCct = trim(($solicitud['N_MUNICIPIO'] ?? '') . ' / ' . ($solicitud['N_LOCALIDAD'] ?? ''), ' /');
                                                    $tooltipCct = trim(
                                                        'Escuela: ' . ($solicitud['NOMBRECT'] ?? '') . "\n" .
                                                        'Municipio: ' . ($solicitud['N_MUNICIPIO'] ?? '') . "\n" .
                                                        'Localidad: ' . ($solicitud['N_LOCALIDAD'] ?? '')
                                                    );
                                                    ?>
                                                    <div
                                                        <?php if (!empty($solicitud['NOMBRECT']) || $ubicacionCct !== '') { ?>
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            data-bs-title="<?php echo htmlspecialchars($tooltipCct); ?>"
                                                        <?php } ?>>
                                                        <?php echo htmlspecialchars($solicitud['cct'] ?? ''); ?>
                                                    </div>
                                                    <?php
                                                    if ($ubicacionCct !== '') {
                                                        echo '<div class="cct-meta">' . htmlspecialchars($ubicacionCct) . '</div>';
                                                    } elseif (!empty($solicitud['cct'])) {
                                                        echo '<div class="cct-warning">CCT incorrecto, revisa la solicitud</div>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php
                                                    $nombre = htmlspecialchars($solicitud['solicitante'] ?? '');
                                                    if (trim($solicitud['ap1'] ?? '') !== '' && trim($solicitud['ap2'] ?? '') !== '') {
                                                        $nombre = htmlspecialchars($solicitud['ap1'] ?? '') . ' ' . htmlspecialchars($solicitud['ap2'] ?? '') . ' ' . $nombre;
                                                    }
                                                    if (trim($solicitud['ap1'] ?? '') !== '' && trim($solicitud['ap2'] ?? '') == '') {
                                                        $nombre = htmlspecialchars($solicitud['ap1'] ?? '') . ' ' . $nombre;
                                                    }
                                                    if (trim($solicitud['ap1'] ?? '') == '' && trim($solicitud['ap2'] ?? '') !== '') {
                                                        $nombre = htmlspecialchars($solicitud['ap2'] ?? '') . ' ' . $nombre;
                                                    }
                                                    echo $nombre;
                                                    ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_peticion'] ?? '')); ?></td>
                                                <td><?php echo mTL(substr($solicitud['compromiso'] ?? '', 0, 50)) . (strlen($solicitud['compromiso'] ?? '') > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $solicitud['prioridad'] === 'Alta' ? 'danger' : ($solicitud['prioridad'] === 'Media' ? 'warning' : 'success');
                                                                            ?>">
                                                        <?php echo $solicitud['prioridad']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $solicitud['estado'] === 'Concluida' ? 'success' : ($solicitud['estado'] === 'En proceso' ? 'primary' : ($solicitud['estado'] === 'Pendiente' ? 'warning' : 'secondary'));
                                                                            ?>">
                                                        <?php echo $solicitud['estado']; ?>
                                                    </span>
                                                    <?php if ($solicitud['total_tareas'] > 0): ?>
                                                        <div class="mt-2" style="min-width: 120px;">
                                                            <div class="progress" style="height: 18px;">
                                                                <div class="progress-bar <?php echo $solicitud['porcentaje_completado'] == 100 ? 'bg-success' : 'bg-info'; ?>"
                                                                    role="progressbar"
                                                                    style="width: <?= $solicitud['porcentaje_completado'] ?>%"
                                                                    aria-valuenow="<?= $solicitud['porcentaje_completado'] ?>"
                                                                    aria-valuemin="0"
                                                                    aria-valuemax="100">
                                                                    <?= $solicitud['porcentaje_completado'] ?>%
                                                                </div>
                                                            </div>
                                                            <small class="text-muted"><?= $solicitud['tareas_completadas'] ?>/<?= $solicitud['total_tareas'] ?> tareas</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <?php /*
                                                <td>
                                                    <?php //if ($solicitud['fecha_vencimiento'] != "N/D") { ?>
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
                                                    <?php } else { ?>
                                                        <span class="text-muted"><?= $solicitud['fecha_vencimiento'] ?></span>
                                                    <?php } ?>
                                                </td>
                                                */ ?>

                                                <td><?php echo substr(mTL($solicitud['descripcion']), 0, 50) . (strlen(mTL($solicitud['descripcion'])) > 50 ? '...' : ''); ?></td>
                                                <?php if (count($etiquetasFiltro) > 0) { ?>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($solicitud['etiqueta'] ?? ''); ?></span> </td>
                                                <?php } ?>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="editar.php?id=<?php echo $solicitud['id']; ?>&return_url=<?php echo $returnParam; ?>"
                                                            class="btn btn-sm btn-warning" title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="ver.php?id=<?php echo $solicitud['id']; ?>&return_url=<?php echo $returnParam; ?>"
                                                            class="btn btn-sm btn-info" title="Ver detalles">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="comentarios/?solicitud_id=<?php echo $solicitud['id']; ?>&return_url=<?php echo $returnParam; ?>"
                                                            class="btn btn-sm btn-secondary" title="Comentarios">
                                                            <i class="bi bi-chat-left-text"></i>
                                                        </a>
                                                        <a href="eliminar.php?id=<?php echo $solicitud['id']; ?>&return_url=<?php echo $returnParam; ?>"
                                                            class="btn btn-sm btn-danger" title="Eliminar"
                                                            onclick="return confirmarBorradoEnlace(event, this);">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($totalPaginas > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">
                                                <i class="bi bi-chevron-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $paginaInicio = max(1, $pagina - 2);
                                    $paginaFin = min($totalPaginas, $pagina + 2);

                                    if ($paginaInicio > 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }

                                    for ($i = $paginaInicio; $i <= $paginaFin; $i++): ?>
                                        <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                            <a class="page-link"
                                                href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor;

                                    if ($paginaFin < $totalPaginas) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    ?>

                                    <?php if ($pagina < $totalPaginas): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $totalPaginas])); ?>">
                                                <i class="bi bi-chevron-double-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <span class="ms-3 mt-2">Total: <?php echo $totalRegistros; ?></span>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Configuración de flatpickr para fechas
        flatpickr.localize(flatpickr.l10ns.es);
        flatpickr("#fecha_inicio, #fecha_fin", {
            dateFormat: "Y-m-d",
            allowInput: true
        });
    </script>
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
        $(document).ready(function() {
            $('#municipio_localidad').select2({
                ajax: {
                    url: 'buscar_ubicaciones_cct.php',
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
                placeholder: "Municipio o localidad...",
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
                }
            });

            $('#etiquetas').select2({
                placeholder: "Selecciona la(s) etiqueta(s)",
                language: {
                    noResults: function() {
                        return "No se encontraron resultados";
                    }
                }
            });

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(element) {
                new bootstrap.Tooltip(element);
            });
        });
    </script>
</body>

</html>
