<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!$auth->canAccessVisitasEscuelas()) {
    header("Location: ../../login.php");
    exit();
}

$db = new Database();
$error = isset($_GET['error']) ? sanitizeInput($_GET['error']) : '';
$success = isset($_GET['success']) ? sanitizeInput($_GET['success']) : '';
$filtroCct = isset($_GET['cct']) ? sanitizeInput($_GET['cct']) : '';
$filtroEscuela = isset($_GET['escuela']) ? sanitizeInput($_GET['escuela']) : '';
$filtroNivel = isset($_GET['nivel']) ? sanitizeInput($_GET['nivel']) : '';
$filtroMunicipio = isset($_GET['municipio']) ? sanitizeInput($_GET['municipio']) : '';
$filtroLocalidad = isset($_GET['localidad']) ? sanitizeInput($_GET['localidad']) : '';
$filtroEstatus = isset($_GET['estatus']) ? sanitizeInput($_GET['estatus']) : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = sanitizeInput($_POST['accion'] ?? 'crear');
    $visita_id = isset($_POST['visita_id']) ? (int)$_POST['visita_id'] : 0;
    $fecha_visita = sanitizeInput($_POST['fecha_visita'] ?? '');
    $cct = sanitizeInput($_POST['cct'] ?? '');
    $observaciones = sanitizeInput($_POST['observaciones'] ?? '');
    $estatus = sanitizeInput($_POST['estatus'] ?? 'Programada');
    $usuario_id = $_SESSION['user_id'];
    $cct_valido = false;

    if ($cct !== '') {
        $cct = strtoupper($cct);
        $stmt_cct = $db->prepare("SELECT CLAVECCT FROM cct WHERE CLAVECCT = ? AND STATUS = 1 LIMIT 1");
        $stmt_cct->bind_param("s", $cct);
        $stmt_cct->execute();
        $cct_result = $stmt_cct->get_result();
        $cct_valido = $cct_result->num_rows > 0;
        $stmt_cct->close();
    }

    if (empty($fecha_visita) || empty($cct)) {
        $error = "La fecha y el CCT son obligatorios.";
    } elseif ($accion === 'editar' && $visita_id <= 0) {
        $error = "No se recibió la visita que se desea editar.";
    } elseif (!$cct_valido) {
        $error = "Selecciona un CCT activo del catálogo para registrar la visita.";
    } else {
        if ($accion === 'editar' && $visita_id > 0) {
            $stmt = $db->prepare("UPDATE visitasescuelas SET fecha_visita = ?, cct = ?, observaciones = ?, estatus = ? WHERE id = ? AND eliminado = 0");
            $stmt->bind_param("ssssi", $fecha_visita, $cct, $observaciones, $estatus, $visita_id);
            $mensaje_ok = "Visita actualizada correctamente para el CCT: " . $cct;
            $mensaje_error = "Error al actualizar la visita: ";
        } else {
            $stmt = $db->prepare("INSERT INTO visitasescuelas (
                fecha_visita,
                cct,
                observaciones,
                estatus,
                usuario_id
            ) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "ssssi",
                $fecha_visita,
                $cct,
                $observaciones,
                $estatus,
                $usuario_id
            );
            $mensaje_ok = "Visita registrada correctamente para el CCT: " . $cct;
            $mensaje_error = "Error al registrar la visita: ";
        }

        if ($stmt->execute()) {
            header("Location: index.php?success=" . urlencode($mensaje_ok));
            exit();
        } else {
            $error = $mensaje_error . $stmt->error;
        }
        $stmt->close();
    }
}

$sqlVisitas = "SELECT v.*, u.nombre_completo AS usuario_nombre, c.NOMBRECT, c.N_NIVEL, c.TURNO, c.N_MUNICIPIO, c.N_LOCALIDAD,
        c.DOMICILIO, c.ENTRECALLE, c.YCALLE, c.NUMEXT, c.COLONIA, c.CODPOST
    FROM visitasescuelas v
    LEFT JOIN usuarios u ON u.id = v.usuario_id
    LEFT JOIN cct c ON c.CLAVECCT = v.cct
    WHERE v.eliminado = 0";
$paramsVisitas = [];
$typesVisitas = '';

if ($filtroCct !== '') {
    $sqlVisitas .= " AND v.cct LIKE ?";
    $paramsVisitas[] = '%' . strtoupper($filtroCct) . '%';
    $typesVisitas .= 's';
}

if ($filtroEscuela !== '') {
    $sqlVisitas .= " AND c.NOMBRECT LIKE ?";
    $paramsVisitas[] = '%' . $filtroEscuela . '%';
    $typesVisitas .= 's';
}

if ($filtroNivel !== '') {
    $sqlVisitas .= " AND c.N_NIVEL LIKE ?";
    $paramsVisitas[] = '%' . $filtroNivel . '%';
    $typesVisitas .= 's';
}

if ($filtroMunicipio !== '') {
    $sqlVisitas .= " AND c.N_MUNICIPIO LIKE ?";
    $paramsVisitas[] = '%' . $filtroMunicipio . '%';
    $typesVisitas .= 's';
}

if ($filtroLocalidad !== '') {
    $sqlVisitas .= " AND c.N_LOCALIDAD LIKE ?";
    $paramsVisitas[] = '%' . $filtroLocalidad . '%';
    $typesVisitas .= 's';
}

if ($filtroEstatus !== '') {
    $sqlVisitas .= " AND v.estatus = ?";
    $paramsVisitas[] = $filtroEstatus;
    $typesVisitas .= 's';
}

$sqlVisitas .= " ORDER BY v.fecha_visita DESC, v.id DESC LIMIT 50";
$stmtVisitas = $db->prepare($sqlVisitas);
if (!empty($paramsVisitas)) {
    $stmtVisitas->bind_param($typesVisitas, ...$paramsVisitas);
}
$stmtVisitas->execute();
$visitas = $stmtVisitas->get_result();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitas Escuelas - <?php echo SITE_NAME; ?></title>
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
            --morena-border: #e9dfdf;
            --morena-ink: #2b2024;
            --morena-muted: #6f6468;
            --morena-soft: #f6edf0;
            --morena-dorado: #b38e5d;
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
            padding: 18px 22px;
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
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .page-title {
            font-size: clamp(1.35rem, 2.2vw, 1.8rem);
            font-weight: 800;
            line-height: 1.1;
            margin: 0;
        }

        .page-subtitle {
            color: rgba(255, 255, 255, .86);
            font-size: .92rem;
            margin: 6px 0 0;
            max-width: 760px;
        }

        .section-card,
        .table-card {
            background: #fff;
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(43, 32, 36, .07);
        }

        .section-header,
        .table-card-header {
            align-items: center;
            border-bottom: 1px solid var(--morena-border);
            color: var(--morena-guinda-dark);
            display: flex;
            font-weight: 800;
            gap: 8px;
            padding: 16px 22px;
        }

        .section-header {
            border-left: 5px solid var(--morena-guinda);
        }

        .btn-morena {
            background-color: var(--morena-guinda) !important;
            border-color: var(--morena-guinda) !important;
            color: #fff !important;
            font-weight: 700;
        }

        .btn-morena:hover,
        .btn-morena:focus {
            background-color: var(--morena-guinda-dark) !important;
            border-color: var(--morena-guinda-dark) !important;
        }

        .visitas-table thead th {
            background: var(--morena-guinda) !important;
            border-color: rgba(255, 255, 255, .16);
            color: #fff;
            font-size: .82rem;
            text-transform: uppercase;
            vertical-align: middle;
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

        .section-card .card-body {
            padding: 22px;
        }

        .visita-modal {
            max-width: 980px;
        }

        .select2-container--open {
            z-index: 1065;
        }

        .select2-results__option {
            white-space: normal;
        }

        .modal .select2-container {
            width: 100% !important;
        }

        .filter-card .select2-container {
            width: 100% !important;
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

        .filter-card {
            background: #fff;
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(43, 32, 36, .06);
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
                            <div class="page-kicker">
                                <i class="bi bi-building-check"></i>
                                Registro institucional
                            </div>
                            <h1 class="page-title">Visitas Escuelas</h1>
                            <p class="page-subtitle">Capture y consulte las visitas del Secretario a planteles educativos.</p>
                        </div>
                        <a href="../../dashboard.php" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>
            </section>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 mb-3">
                <?php if ($auth->isAdmin()): ?>
                    <a href="consulta.php" class="btn btn-warning fw-bold">
                        <i class="bi bi-search"></i> Consulta
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-morena" data-bs-toggle="modal" data-bs-target="#modalCrearVisita">
                    <i class="bi bi-plus-circle"></i> Nueva visita
                </button>
            </div>

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
                            <option value="Programada" <?php echo $filtroEstatus === 'Programada' ? 'selected' : ''; ?>>Programada</option>
                            <option value="Realizada" <?php echo $filtroEstatus === 'Realizada' ? 'selected' : ''; ?>>Realizada</option>
                            <option value="Cancelada" <?php echo $filtroEstatus === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-morena flex-fill">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary" title="Limpiar filtros">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-card">
                <div class="table-card-header">
                    <i class="bi bi-table"></i>
                    Últimas visitas registradas
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover visitas-table mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>CCT / Escuela</th>
                                    <th>Municipio</th>
                                    <th>Dirección</th>
                                    <th>Observaciones</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($visitas && $visitas->num_rows > 0): ?>
                                    <?php while ($visita = $visitas->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php echo !empty($visita['fecha_visita']) ? date('d/m/Y', strtotime($visita['fecha_visita'])) : ''; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($visita['cct'] ?? ''); ?></strong>
                                                <div class="school-meta"><?php echo htmlspecialchars($visita['NOMBRECT'] ?? ''); ?></div>
                                                <div class="school-meta">NIVEL: <?php echo htmlspecialchars($visita['N_NIVEL'] ?? ''); ?></div>
                                                <?php
                                                $turnos = [
                                                    '100' => 'MATUTINO',
                                                    '200' => 'VESPERTINO',
                                                    '300' => 'NOCTURNA',
                                                    '400' => 'DISCONTINUA'
                                                ];
                                                $turnoTexto = $turnos[$visita['TURNO'] ?? ''] ?? 'ND';
                                                ?>
                                                <div class="school-meta">TURNO: <?php echo htmlspecialchars($turnoTexto); ?></div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($visita['N_MUNICIPIO'] ?? ''); ?>
                                                <div class="school-meta"><?php echo htmlspecialchars($visita['N_LOCALIDAD'] ?? ''); ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                $direccion = array_filter([
                                                    trim($visita['DOMICILIO'] ?? ''),
                                                    !empty($visita['NUMEXT']) ? 'NUM. EXT. ' . trim($visita['NUMEXT']) : '',
                                                    !empty($visita['COLONIA']) ? 'COL. ' . trim($visita['COLONIA']) : '',
                                                    !empty($visita['CODPOST']) ? 'C.P. ' . trim($visita['CODPOST']) : ''
                                                ]);
                                                echo htmlspecialchars(implode(', ', $direccion));
                                                $entreCalles = array_filter([
                                                    !empty($visita['ENTRECALLE']) ? 'ENTRE ' . trim($visita['ENTRECALLE']) : '',
                                                    !empty($visita['YCALLE']) ? 'Y ' . trim($visita['YCALLE']) : ''
                                                ]);
                                                if (!empty($entreCalles)) {
                                                    echo '<div class="school-meta">' . htmlspecialchars(implode(' ', $entreCalles)) . '</div>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(mb_substr($visita['observaciones'] ?? '', 0, 90)); ?><?php echo mb_strlen($visita['observaciones'] ?? '') > 90 ? '...' : ''; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $visita['estatus'] === 'Realizada' ? 'success' : ($visita['estatus'] === 'Cancelada' ? 'secondary' : 'warning'); ?>">
                                                    <?php echo htmlspecialchars($visita['estatus'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button"
                                                        class="btn btn-success btn-editar-visita"
                                                        title="Editar"
                                                        data-id="<?php echo (int)$visita['id']; ?>"
                                                        data-fecha="<?php echo htmlspecialchars($visita['fecha_visita'] ?? ''); ?>"
                                                        data-cct="<?php echo htmlspecialchars($visita['cct'] ?? ''); ?>"
                                                        data-nombrect="<?php echo htmlspecialchars($visita['NOMBRECT'] ?? ''); ?>"
                                                        data-nivel="<?php echo htmlspecialchars($visita['N_NIVEL'] ?? ''); ?>"
                                                        data-municipio="<?php echo htmlspecialchars($visita['N_MUNICIPIO'] ?? ''); ?>"
                                                        data-localidad="<?php echo htmlspecialchars($visita['N_LOCALIDAD'] ?? ''); ?>"
                                                        data-estatus="<?php echo htmlspecialchars($visita['estatus'] ?? 'Programada'); ?>"
                                                        data-observaciones="<?php echo htmlspecialchars($visita['observaciones'] ?? ''); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-info btn-seguimiento-visita"
                                                        title="Seguimiento"
                                                        data-id="<?php echo (int)$visita['id']; ?>"
                                                        data-cct="<?php echo htmlspecialchars($visita['cct'] ?? ''); ?>">
                                                        <i class="bi bi-clipboard-data"></i>
                                                    </button>
                                                    <a href="eliminar.php?id=<?php echo (int)$visita['id']; ?>" class="btn btn-danger delete-visita" title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No hay visitas registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="modalCrearVisita" tabindex="-1" aria-labelledby="modalCrearVisitaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered visita-modal">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="crear">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalCrearVisitaLabel">
                            <i class="bi bi-clipboard-plus me-2"></i>Nueva visita
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fecha_visita_crear" class="form-label">Fecha de visita <span class="text-danger">*</span></label>
                                <input type="date" class="form-control fecha-visita" id="fecha_visita_crear" name="fecha_visita" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="estatus_crear" class="form-label">Estatus</label>
                                <select class="form-select" id="estatus_crear" name="estatus">
                                    <option value="Programada">Programada</option>
                                    <option value="Realizada">Realizada</option>
                                    <option value="Cancelada">Cancelada</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cct_crear" class="form-label">CCT <span class="text-danger">*</span></label>
                                <select class="form-control upper cct-select" id="cct_crear" name="cct" required></select>
                            </div>
                            <div class="col-md-12">
                                <div id="cctInfoCrear" class="school-meta"></div>
                            </div>
                            <div class="col-md-12">
                                <label for="observaciones_crear" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones_crear" name="observaciones" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-morena">
                            <i class="bi bi-save"></i> Guardar visita
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditarVisita" tabindex="-1" aria-labelledby="modalEditarVisitaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered visita-modal">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" id="visita_id_editar" name="visita_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditarVisitaLabel">
                            <i class="bi bi-pencil-square me-2"></i>Editar visita
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="fecha_visita_editar" class="form-label">Fecha de visita <span class="text-danger">*</span></label>
                                <input type="date" class="form-control fecha-visita" id="fecha_visita_editar" name="fecha_visita" required>
                            </div>
                            <div class="col-md-3">
                                <label for="estatus_editar" class="form-label">Estatus</label>
                                <select class="form-select" id="estatus_editar" name="estatus">
                                    <option value="Programada">Programada</option>
                                    <option value="Realizada">Realizada</option>
                                    <option value="Cancelada">Cancelada</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cct_editar" class="form-label">CCT <span class="text-danger">*</span></label>
                                <select class="form-control upper cct-select" id="cct_editar" name="cct" required></select>
                            </div>
                            <div class="col-md-12">
                                <div id="cctInfoEditar" class="school-meta"></div>
                            </div>
                            <div class="col-md-12">
                                <label for="observaciones_editar" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones_editar" name="observaciones" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-morena">
                            <i class="bi bi-save"></i> Guardar cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalSeguimientoVisita" tabindex="-1" aria-labelledby="modalSeguimientoVisitaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered visita-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSeguimientoVisitaLabel">
                        <i class="bi bi-clipboard-data me-2"></i>Seguimiento
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <a href="#" class="btn btn-outline-danger btn-sm" id="seguimientoPdfBtn" target="_blank" rel="noopener">
                            <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                        </a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="seguimientoContenido">
                    <div class="text-center text-muted py-4">Seleccione una visita para consultar su seguimiento.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        flatpickr.localize(flatpickr.l10ns.es);
        flatpickr(".fecha-visita", {
            dateFormat: "Y-m-d",
            allowInput: true,
            locale: "es"
        });

        $(document).ready(function() {
            function inicializarCct(selector, modalSelector, infoSelector) {
                $(selector).select2({
                    dropdownParent: $(modalSelector),
                    ajax: {
                        url: '../solicitudes/buscar_cct.php',
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
                    minimumInputLength: 2,
                    placeholder: "Buscar CCT activo",
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
                    },
                    templateSelection: function(item) {
                        return item.id || item.text || '';
                    }
                });

                $(selector).on('select2:select', function(e) {
                    const data = e.params.data || {};
                    $(infoSelector).text(data.NOMBRECT ? `${data.NOMBRECT} - ${data.N_MUNICIPIO || ''} / ${data.N_LOCALIDAD || ''}` : '');
                });
            }

            inicializarCct('#cct_crear', '#modalCrearVisita', '#cctInfoCrear');
            inicializarCct('#cct_editar', '#modalEditarVisita', '#cctInfoEditar');

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

            $('.btn-editar-visita').on('click', function() {
                const btn = $(this);
                const cct = btn.data('cct') || '';
                const escuela = btn.data('nombrect') || '';
                const municipio = btn.data('municipio') || '';
                const localidad = btn.data('localidad') || '';
                const option = new Option(cct, cct, true, true);

                $('#visita_id_editar').val(btn.data('id') || '');
                $('#fecha_visita_editar').val(btn.data('fecha') || '');
                $('#estatus_editar').val(btn.data('estatus') || 'Programada');
                $('#observaciones_editar').val(btn.data('observaciones') || '');
                $('#cct_editar').empty().append(option).trigger('change');
                $('#cctInfoEditar').text(escuela ? `${escuela} - ${municipio} / ${localidad}` : '');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarVisita')).show();
            });

            $('.btn-seguimiento-visita').on('click', function() {
                const btn = $(this);
                const id = btn.data('id') || '';
                const cct = btn.data('cct') || '';

                $('#modalSeguimientoVisitaLabel')
                    .html('<i class="bi bi-clipboard-data me-2"></i>')
                    .append(document.createTextNode(`Seguimiento ${cct}`));
                $('#seguimientoPdfBtn').attr('href', `seguimiento_pdf.php?id=${encodeURIComponent(id)}`);
                $('#seguimientoContenido').html(`
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border text-secondary mb-2" role="status" aria-hidden="true"></div>
                        <div>Cargando seguimiento...</div>
                    </div>
                `);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSeguimientoVisita')).show();

                $.get('seguimiento.php', { id: id })
                    .done(function(html) {
                        $('#seguimientoContenido').html(html);
                    })
                    .fail(function() {
                        $('#seguimientoContenido').html('<div class="alert alert-danger mb-0">No fue posible cargar el seguimiento de la visita.</div>');
                    });
            });

            $('.delete-visita').on('click', function(event) {
                if (!confirm('¿Deseas eliminar esta visita?')) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>

</html>
