<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!$auth->canAccessVisitasEscuelas()) {
    header("Location: ../../login.php");
    exit();
}

$db = new Database();
$filtroCct = isset($_GET['cct']) ? sanitizeInput($_GET['cct']) : '';
$filtroEscuela = isset($_GET['escuela']) ? sanitizeInput($_GET['escuela']) : '';
$filtroNivel = isset($_GET['nivel']) ? sanitizeInput($_GET['nivel']) : '';
$filtroMunicipio = isset($_GET['municipio']) ? sanitizeInput($_GET['municipio']) : '';
$filtroLocalidad = isset($_GET['localidad']) ? sanitizeInput($_GET['localidad']) : '';
$filtroEstatus = isset($_GET['estatus']) ? sanitizeInput($_GET['estatus']) : '';
$hayFiltros = $filtroCct !== ''
    || $filtroEscuela !== ''
    || $filtroNivel !== ''
    || $filtroMunicipio !== ''
    || $filtroLocalidad !== ''
    || $filtroEstatus !== '';

$sql = "SELECT s.id, s.folio, s.cct, s.NOMBRECT AS solicitud_escuela, s.N_MUNICIPIO AS solicitud_municipio,
        s.N_LOCALIDAD AS solicitud_localidad, s.solicitante, s.ap1, s.ap2, s.fecha_peticion,
        s.estado, s.prioridad, s.compromiso, c.NOMBRECT, c.N_NIVEL, c.TURNO, c.N_MUNICIPIO,
        c.N_LOCALIDAD, c.DOMICILIO, c.ENTRECALLE, c.YCALLE, c.NUMEXT, c.COLONIA, c.CODPOST
    FROM solicitudes s
    LEFT JOIN cct c ON c.CLAVECCT = s.cct
    WHERE (s.eliminado IS NULL OR s.eliminado = 0)
        AND s.cct IS NOT NULL
        AND TRIM(s.cct) <> ''";
$params = [];
$types = '';

if ($filtroCct !== '') {
    $sql .= " AND s.cct LIKE ?";
    $params[] = '%' . strtoupper($filtroCct) . '%';
    $types .= 's';
}

if ($filtroEscuela !== '') {
    $sql .= " AND (c.NOMBRECT LIKE ? OR s.NOMBRECT LIKE ?)";
    $term = '%' . $filtroEscuela . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}

if ($filtroNivel !== '') {
    $sql .= " AND c.N_NIVEL LIKE ?";
    $params[] = '%' . $filtroNivel . '%';
    $types .= 's';
}

if ($filtroMunicipio !== '') {
    $sql .= " AND (c.N_MUNICIPIO LIKE ? OR s.N_MUNICIPIO LIKE ?)";
    $term = '%' . $filtroMunicipio . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}

if ($filtroLocalidad !== '') {
    $sql .= " AND (c.N_LOCALIDAD LIKE ? OR s.N_LOCALIDAD LIKE ?)";
    $term = '%' . $filtroLocalidad . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}

if ($filtroEstatus !== '') {
    $sql .= " AND s.estado = ?";
    $params[] = $filtroEstatus;
    $types .= 's';
}

$solicitudes = null;
$totalSolicitudes = 0;
if ($hayFiltros) {
    $sqlTotal = "SELECT COUNT(*) AS total FROM (" . $sql . ") AS consulta_total";
    $stmtTotal = $db->prepare($sqlTotal);
    if (!empty($params)) {
        $stmtTotal->bind_param($types, ...$params);
    }
    $stmtTotal->execute();
    $totalRow = $stmtTotal->get_result()->fetch_assoc();
    $totalSolicitudes = (int)($totalRow['total'] ?? 0);
    $stmtTotal->close();

    $sql .= " ORDER BY s.fecha_peticion DESC, s.id DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $solicitudes = $stmt->get_result();
}

function turnoConsulta($turno) {
    $turnos = [
        '100' => 'MATUTINO',
        '200' => 'VESPERTINO',
        '300' => 'NOCTURNA',
        '400' => 'DISCONTINUA'
    ];
    return $turnos[$turno ?? ''] ?? 'ND';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Visitas Escuelas - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <style>
        :root {
            --morena-guinda: #8D2D44;
            --morena-guinda-dark: #5f1830;
            --morena-border: #e9dfdf;
            --morena-ink: #2b2024;
            --morena-muted: #6f6468;
            --morena-soft: #f6edf0;
        }

        body {
            background: #f7f5f2;
            color: var(--morena-ink);
        }

        .visitas-shell {
            padding: 28px 0 46px;
        }

        .page-band {
            background: linear-gradient(135deg, var(--morena-guinda-dark), var(--morena-guinda));
            border-radius: 8px;
            box-shadow: 0 16px 36px rgba(95, 24, 48, .18);
            color: #fff;
        }

        .page-band-content {
            padding: 18px 22px;
        }

        .page-kicker {
            color: rgba(255, 255, 255, .82);
            font-size: .82rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .page-title {
            font-size: clamp(1.35rem, 2.2vw, 1.8rem);
            font-weight: 800;
            margin: 0;
        }

        .page-subtitle {
            color: rgba(255, 255, 255, .86);
            font-size: .92rem;
            margin: 6px 0 0;
        }

        .btn-morena {
            background-color: var(--morena-guinda) !important;
            border-color: var(--morena-guinda) !important;
            color: #fff !important;
            font-weight: 700;
        }

        .filter-card,
        .table-card {
            background: #fff;
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(43, 32, 36, .06);
        }

        .filter-card {
            margin-bottom: 18px;
            padding: 16px;
        }

        .filter-card .form-label {
            color: var(--morena-muted);
            font-size: .76rem;
            font-weight: 800;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .filter-card .select2-container,
        .modal .select2-container {
            width: 100% !important;
        }

        .table-card-header {
            align-items: center;
            border-bottom: 1px solid var(--morena-border);
            color: var(--morena-guinda-dark);
            display: flex;
            font-weight: 800;
            gap: 8px;
            padding: 16px 22px;
        }

        .visitas-table thead th {
            background: var(--morena-guinda) !important;
            color: #fff;
            font-size: .82rem;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .visitas-table tbody td {
            vertical-align: middle;
        }

        .school-meta {
            color: var(--morena-muted);
            font-size: .76rem;
            line-height: 1.25;
            text-transform: uppercase;
        }

        .seguimiento-summary {
            background: var(--morena-soft);
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            padding: 14px;
        }

        .seguimiento-label {
            color: var(--morena-muted);
            display: block;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .seguimiento-value {
            font-size: .92rem;
            font-weight: 700;
        }

        .folio-card {
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            margin-bottom: 14px;
        }

        .folio-card-header {
            background: #fbfaf8;
            border-bottom: 1px solid var(--morena-border);
            padding: 12px 14px;
        }

        .seguimiento-text {
            white-space: pre-line;
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <main class="visitas-shell">
        <div class="container">
            <section class="page-band mb-4">
                <div class="page-band-content">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
                        <div>
                            <div class="page-kicker"><i class="bi bi-search"></i> Consulta institucional</div>
                            <h1 class="page-title">Consulta de Solicitudes por Escuela</h1>
                            <p class="page-subtitle">Consulte solicitudes relacionadas con escuelas sin registrar una visita.</p>
                        </div>
                        <a href="index.php" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Visitas Escuelas
                        </a>
                    </div>
                </div>
            </section>

            <form method="GET" class="filter-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="filtro_cct" class="form-label">CCT</label>
                        <select class="form-control filtro-cct-select" id="filtro_cct" name="cct" data-campo="cct" data-placeholder="Buscar CCT">
                            <?php if ($filtroCct !== ''): ?>
                                <option value="<?php echo htmlspecialchars($filtroCct); ?>" selected><?php echo htmlspecialchars($filtroCct); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filtro_escuela" class="form-label">Nombre escuela</label>
                        <select class="form-control filtro-cct-select" id="filtro_escuela" name="escuela" data-campo="escuela" data-placeholder="Buscar escuela">
                            <?php if ($filtroEscuela !== ''): ?>
                                <option value="<?php echo htmlspecialchars($filtroEscuela); ?>" selected><?php echo htmlspecialchars($filtroEscuela); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filtro_nivel" class="form-label">Nivel</label>
                        <select class="form-control filtro-cct-select" id="filtro_nivel" name="nivel" data-campo="nivel" data-placeholder="Buscar nivel">
                            <?php if ($filtroNivel !== ''): ?>
                                <option value="<?php echo htmlspecialchars($filtroNivel); ?>" selected><?php echo htmlspecialchars($filtroNivel); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filtro_municipio" class="form-label">Municipio</label>
                        <select class="form-control filtro-cct-select" id="filtro_municipio" name="municipio" data-campo="municipio" data-placeholder="Buscar municipio">
                            <?php if ($filtroMunicipio !== ''): ?>
                                <option value="<?php echo htmlspecialchars($filtroMunicipio); ?>" selected><?php echo htmlspecialchars($filtroMunicipio); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filtro_localidad" class="form-label">Localidad</label>
                        <select class="form-control filtro-cct-select" id="filtro_localidad" name="localidad" data-campo="localidad" data-placeholder="Buscar localidad">
                            <?php if ($filtroLocalidad !== ''): ?>
                                <option value="<?php echo htmlspecialchars($filtroLocalidad); ?>" selected><?php echo htmlspecialchars($filtroLocalidad); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filtro_estatus" class="form-label">Estatus</label>
                        <select class="form-select" id="filtro_estatus" name="estatus">
                            <option value="">Todos</option>
                            <option value="No iniciada" <?php echo $filtroEstatus === 'No iniciada' ? 'selected' : ''; ?>>No iniciada</option>
                            <option value="En proceso" <?php echo $filtroEstatus === 'En proceso' ? 'selected' : ''; ?>>En proceso</option>
                            <option value="Concluida" <?php echo $filtroEstatus === 'Concluida' ? 'selected' : ''; ?>>Concluida</option>
                            <option value="Pendiente" <?php echo $filtroEstatus === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-morena flex-fill">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="consulta.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-card">
                <div class="table-card-header">
                    <i class="bi bi-table"></i>
                    Solicitudes encontradas
                    <span class="badge bg-secondary ms-auto">
                        <?php echo $hayFiltros ? $totalSolicitudes : 0; ?> total
                    </span>
                    <?php if ($hayFiltros && $totalSolicitudes > 100): ?>
                        <span class="badge bg-warning text-dark ms-2">Mostrando 100</span>
                    <?php endif; ?>
                    <?php if ($hayFiltros): ?>
                        <a href="consulta_pdf.php?<?php echo htmlspecialchars(http_build_query($_GET)); ?>" class="btn btn-outline-danger btn-sm ms-2" target="_blank" rel="noopener">
                            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-danger btn-sm ms-2" disabled>
                            <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover visitas-table mb-0">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>CCT / Escuela</th>
                                    <th>Municipio</th>
                                    <th>Solicitante</th>
                                    <th>Fecha</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$hayFiltros): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Use los filtros para iniciar la consulta.</td>
                                    </tr>
                                <?php elseif ($solicitudes && $solicitudes->num_rows > 0): ?>
                                    <?php while ($solicitud = $solicitudes->fetch_assoc()): ?>
                                        <?php
                                        $nombreEscuela = $solicitud['NOMBRECT'] ?: $solicitud['solicitud_escuela'];
                                        $municipio = $solicitud['N_MUNICIPIO'] ?: $solicitud['solicitud_municipio'];
                                        $localidad = $solicitud['N_LOCALIDAD'] ?: $solicitud['solicitud_localidad'];
                                        $solicitante = trim(implode(' ', array_filter([
                                            $solicitud['solicitante'] ?? '',
                                            $solicitud['ap1'] ?? '',
                                            $solicitud['ap2'] ?? ''
                                        ])));
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($solicitud['folio']); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($solicitud['cct']); ?></strong>
                                                <div class="school-meta"><?php echo htmlspecialchars($nombreEscuela); ?></div>
                                                <div class="school-meta">NIVEL: <?php echo htmlspecialchars($solicitud['N_NIVEL'] ?? ''); ?></div>
                                                <div class="school-meta">TURNO: <?php echo htmlspecialchars(turnoConsulta($solicitud['TURNO'] ?? '')); ?></div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($municipio); ?>
                                                <div class="school-meta"><?php echo htmlspecialchars($localidad); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($solicitante); ?></td>
                                            <td><?php echo !empty($solicitud['fecha_peticion']) ? date('d/m/Y', strtotime($solicitud['fecha_peticion'])) : ''; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $solicitud['estado'] === 'Concluida' ? 'success' : ($solicitud['estado'] === 'En proceso' ? 'primary' : ($solicitud['estado'] === 'Pendiente' ? 'warning' : 'secondary')); ?>">
                                                    <?php echo htmlspecialchars($solicitud['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button"
                                                    class="btn btn-info btn-sm btn-seguimiento-cct"
                                                    title="Seguimiento"
                                                    data-cct="<?php echo htmlspecialchars($solicitud['cct']); ?>">
                                                    <i class="bi bi-clipboard-data"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No hay solicitudes con los filtros seleccionados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="modalSeguimientoCct" tabindex="-1" aria-labelledby="modalSeguimientoCctLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSeguimientoCctLabel">
                        <i class="bi bi-clipboard-data me-2"></i>Seguimiento
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <a href="#" class="btn btn-outline-danger btn-sm" id="seguimientoCctPdfBtn" target="_blank" rel="noopener">
                            <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                        </a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="seguimientoCctContenido">
                    <div class="text-center text-muted py-4">Seleccione un CCT para consultar su seguimiento.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.filtro-cct-select').each(function() {
                const select = $(this);
                select.select2({
                    ajax: {
                        url: 'buscar_filtros_cct.php',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                campo: select.data('campo'),
                                q: params.term || ''
                            };
                        },
                        processResults: function(data) {
                            return data;
                        },
                        cache: true
                    },
                    allowClear: true,
                    minimumInputLength: 2,
                    placeholder: select.data('placeholder') || 'Buscar',
                    width: '100%',
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
            });

            $('.btn-seguimiento-cct').on('click', function() {
                const cct = $(this).data('cct') || '';
                $('#modalSeguimientoCctLabel')
                    .html('<i class="bi bi-clipboard-data me-2"></i>')
                    .append(document.createTextNode(`Seguimiento ${cct}`));
                $('#seguimientoCctPdfBtn').attr('href', `seguimiento_cct_pdf.php?cct=${encodeURIComponent(cct)}`);
                $('#seguimientoCctContenido').html(`
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border text-secondary mb-2" role="status" aria-hidden="true"></div>
                        <div>Cargando seguimiento...</div>
                    </div>
                `);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSeguimientoCct')).show();

                $.get('seguimiento_cct.php', { cct: cct })
                    .done(function(html) {
                        $('#seguimientoCctContenido').html(html);
                    })
                    .fail(function() {
                        $('#seguimientoCctContenido').html('<div class="alert alert-danger mb-0">No fue posible cargar el seguimiento del CCT.</div>');
                    });
            });
        });
    </script>
</body>

</html>
