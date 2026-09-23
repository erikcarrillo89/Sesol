<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if (!$auth->canAccessVisitasEscuelas()) {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$db = new Database();
$id = (int)$_GET['id'];
$error = '';

$stmt = $db->prepare("SELECT v.*, c.NOMBRECT, c.N_MUNICIPIO, c.N_LOCALIDAD
    FROM visitasescuelas v
    LEFT JOIN cct c ON c.CLAVECCT = v.cct
    WHERE v.id = ? AND v.eliminado = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$visita = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visita) {
    header("Location: index.php?error=" . urlencode("Visita no encontrada"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_visita = sanitizeInput($_POST['fecha_visita'] ?? '');
    $cct = sanitizeInput($_POST['cct'] ?? '');
    $observaciones = sanitizeInput($_POST['observaciones'] ?? '');
    $estatus = sanitizeInput($_POST['estatus'] ?? 'Programada');
    $cct_valido = false;

    if ($cct !== '') {
        $cct = strtoupper($cct);
        $stmt_cct = $db->prepare("SELECT CLAVECCT FROM cct WHERE CLAVECCT = ? AND STATUS = 1 LIMIT 1");
        $stmt_cct->bind_param("s", $cct);
        $stmt_cct->execute();
        $cct_valido = $stmt_cct->get_result()->num_rows > 0;
        $stmt_cct->close();
    }

    if (empty($fecha_visita) || empty($cct)) {
        $error = "La fecha y el CCT son obligatorios.";
    } elseif (!$cct_valido) {
        $error = "Selecciona un CCT activo del catálogo para actualizar la visita.";
    } else {
        $stmt = $db->prepare("UPDATE visitasescuelas SET fecha_visita = ?, cct = ?, observaciones = ?, estatus = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $fecha_visita, $cct, $observaciones, $estatus, $id);
        if ($stmt->execute()) {
            header("Location: index.php?success=" . urlencode("Visita actualizada correctamente para el CCT: " . $cct));
            exit();
        }
        $error = "Error al actualizar la visita: " . $stmt->error;
        $stmt->close();
    }

    $visita['fecha_visita'] = $fecha_visita;
    $visita['cct'] = $cct;
    $visita['observaciones'] = $observaciones;
    $visita['estatus'] = $estatus;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Visita Escuela - <?php echo SITE_NAME; ?></title>
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
            --morena-muted: #6f6468;
        }

        body {
            background: #f7f5f2;
        }

        .edit-shell {
            padding: 28px 0 46px;
        }

        .section-card {
            background: #fff;
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(43, 32, 36, .07);
        }

        .section-header {
            border-bottom: 1px solid var(--morena-border);
            border-left: 5px solid var(--morena-guinda);
            color: var(--morena-guinda-dark);
            font-weight: 800;
            padding: 16px 22px;
        }

        .section-card .card-body {
            padding: 22px;
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

        .school-meta {
            color: var(--morena-muted);
            font-size: .76rem;
            line-height: 1.25;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <main class="edit-shell">
        <div class="container">
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-pencil-square me-2"></i>
                    Editar visita escuela
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="fecha_visita" class="form-label">Fecha de visita <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fecha_visita" name="fecha_visita" required
                                    value="<?php echo htmlspecialchars($visita['fecha_visita'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="estatus" class="form-label">Estatus</label>
                                <select class="form-select" id="estatus" name="estatus">
                                    <?php foreach (['Programada', 'Realizada', 'Cancelada'] as $estatusOpcion) { ?>
                                        <option value="<?php echo $estatusOpcion; ?>" <?php echo ($visita['estatus'] ?? '') === $estatusOpcion ? 'selected' : ''; ?>>
                                            <?php echo $estatusOpcion; ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cct" class="form-label">CCT <span class="text-danger">*</span></label>
                                <select class="form-control upper" id="cct" name="cct" required>
                                    <option value="<?php echo htmlspecialchars($visita['cct'] ?? ''); ?>" selected>
                                        <?php echo htmlspecialchars($visita['cct'] ?? ''); ?>
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <div id="cctInfo" class="school-meta">
                                    <?php
                                    if (!empty($visita['NOMBRECT'])) {
                                        echo htmlspecialchars($visita['NOMBRECT'] . ' - ' . ($visita['N_MUNICIPIO'] ?? '') . ' / ' . ($visita['N_LOCALIDAD'] ?? ''));
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="4"><?php echo htmlspecialchars($visita['observaciones'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-morena">
                                <i class="bi bi-save"></i> Guardar cambios
                            </button>
                        </div>
                    </form>
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
        flatpickr.localize(flatpickr.l10ns.es);
        flatpickr("#fecha_visita", {
            dateFormat: "Y-m-d",
            allowInput: true,
            locale: "es"
        });

        $(document).ready(function() {
            $('#cct').select2({
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

            $('#cct').on('select2:select', function(e) {
                const data = e.params.data || {};
                $('#cctInfo').text(data.NOMBRECT ? `${data.NOMBRECT} - ${data.N_MUNICIPIO || ''} / ${data.N_LOCALIDAD || ''}` : '');
            });
        });
    </script>
</body>

</html>
