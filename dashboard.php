<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
//echo $_SESSION['rol']; 

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        :root {
            --morena-guinda: #8D2D44;
            --morena-guinda-dark: #5f1830;
            --morena-guinda-soft: #f6edf0;
            --morena-dorado: #b38e5d;
            --morena-ink: #2b2024;
            --morena-muted: #6f6468;
        }

        body {
            background: #f7f5f2;
            color: var(--morena-ink);
        }

        .dashboard-shell {
            padding: 32px 0 48px;
        }

        .welcome-band {
            background: linear-gradient(135deg, var(--morena-guinda-dark), var(--morena-guinda));
            border-radius: 8px;
            color: #fff;
            overflow: hidden;
            position: relative;
            box-shadow: 0 16px 36px rgba(95, 24, 48, .18);
        }

        .welcome-band::after {
            content: "";
            position: absolute;
            inset: 0 0 0 auto;
            width: 34%;
            background:
                linear-gradient(135deg, transparent 0 48%, rgba(179, 142, 93, .28) 48% 52%, transparent 52%),
                linear-gradient(45deg, transparent 0 42%, rgba(255, 255, 255, .10) 42% 46%, transparent 46%);
            opacity: .75;
        }

        .welcome-content {
            padding: 30px;
            position: relative;
            z-index: 1;
        }

        .welcome-kicker {
            align-items: center;
            color: rgba(255, 255, 255, .82);
            display: flex;
            font-size: .84rem;
            font-weight: 700;
            gap: 8px;
            letter-spacing: .04em;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .welcome-title {
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: 800;
            line-height: 1.08;
            margin: 0;
        }

        .welcome-text {
            color: rgba(255, 255, 255, .86);
            font-size: 1rem;
            margin: 12px 0 0;
            max-width: 680px;
        }

        .role-chip {
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 999px;
            color: #fff;
            display: inline-flex;
            font-weight: 700;
            gap: 8px;
            padding: 8px 14px;
        }

        .section-title {
            color: var(--morena-guinda-dark);
            font-size: 1.05rem;
            font-weight: 800;
            margin: 28px 0 14px;
        }

        .module-card {
            background: #fff;
            border: 1px solid #e9dfdf;
            border-left: 5px solid var(--morena-guinda);
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(43, 32, 36, .07);
            height: 100%;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .module-card:hover {
            border-color: #decaca;
            box-shadow: 0 18px 34px rgba(43, 32, 36, .11);
            transform: translateY(-2px);
        }

        .module-card.secondary {
            border-left-color: var(--morena-dorado);
        }

        .module-card.accent {
            border-left-color: #6f7f52;
        }

        .module-body {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-height: 210px;
            padding: 22px;
        }

        .module-icon {
            align-items: center;
            background: var(--morena-guinda-soft);
            border-radius: 8px;
            color: var(--morena-guinda);
            display: inline-flex;
            font-size: 1.35rem;
            height: 46px;
            justify-content: center;
            width: 46px;
        }

        .module-card.secondary .module-icon {
            background: #f5efe6;
            color: #8a673b;
        }

        .module-card.accent .module-icon {
            background: #edf1e7;
            color: #56653e;
        }

        .module-title {
            color: var(--morena-ink);
            font-size: 1.08rem;
            font-weight: 800;
            margin: 0;
        }

        .module-text {
            color: var(--morena-muted);
            flex: 1;
            font-size: .94rem;
            margin: 0;
        }

        .module-link {
            align-items: center;
            align-self: flex-start;
            background: var(--morena-guinda);
            border: 0;
            border-radius: 6px;
            color: #fff;
            display: inline-flex;
            font-weight: 700;
            gap: 8px;
            padding: 9px 14px;
            text-decoration: none;
        }

        .module-link:hover,
        .module-link:focus {
            background: var(--morena-guinda-dark);
            color: #fff;
        }

        .quick-strip {
            background: #fff;
            border: 1px solid #e9dfdf;
            border-radius: 8px;
            color: var(--morena-muted);
            padding: 16px 18px;
        }

        @media (max-width: 767.98px) {
            .welcome-content {
                padding: 24px;
            }

            .welcome-band::after {
                width: 58%;
                opacity: .35;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <main class="dashboard-shell">
        <div class="container">
            <section class="welcome-band">
                <div class="welcome-content">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-4">
                        <div>
                            <div class="welcome-kicker">
                                <i class="bi bi-grid-1x2-fill"></i>
                                Panel institucional
                            </div>
                            <h1 class="welcome-title">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?></h1>
                            <p class="welcome-text">Seleccione el módulo que necesita para dar seguimiento a solicitudes, reportes y administración del sistema.</p>
                        </div>
                        <div class="role-chip">
                            <i class="bi bi-person-badge"></i>
                            <?php echo htmlspecialchars(nombreRol($_SESSION['rol'])); ?>
                        </div>
                    </div>
                </div>
            </section>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h2 class="section-title">Módulos disponibles</h2>
            </div>

            <div class="row g-4">
                <?php if (!$auth->isProtocolo()): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="module-card">
                            <div class="module-body">
                                <div class="module-icon">
                                    <i class="bi bi-inbox"></i>
                                </div>
                                <div>
                                    <h3 class="module-title">Gestión de Solicitudes</h3>
                                    <p class="module-text">Administre las solicitudes y peticiones del sistema.</p>
                                </div>
                                <a href="modules/solicitudes/" class="module-link">
                                    Acceder <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($auth->isAdmin() && !$auth->isMonitor()): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="module-card secondary">
                            <div class="module-body">
                                <div class="module-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <h3 class="module-title">Gestión de Usuarios</h3>
                                    <p class="module-text">Administre los usuarios del sistema.</p>
                                </div>
                                <a href="modules/usuarios/" class="module-link">
                                    Acceder <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="module-card accent">
                            <div class="module-body">
                                <div class="module-icon">
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                </div>
                                <div>
                                    <h3 class="module-title">Reportes</h3>
                                    <p class="module-text">Genere reportes de las solicitudes.</p>
                                </div>
                                <a href="modules/solicitudes/exportar.php" class="module-link">
                                    Acceder <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

                <?php if ($auth->canAccessVisitasEscuelas() && !$auth->isMonitor()): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="module-card">
                            <div class="module-body">
                                <div class="module-icon">
                                    <i class="bi bi-building-check"></i>
                                </div>
                                <div>
                                    <h3 class="module-title">Visitas Escuelas</h3>
                                    <p class="module-text">Registre y consulte las visitas del Secretario a planteles educativos.</p>
                                </div>
                                <a href="modules/visitas_escuelas/" class="module-link">
                                    Acceder <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$auth->isProtocolo() && !$auth->isAtencionCiudadana()): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="module-card secondary">
                            <div class="module-body">
                                <div class="module-icon">
                                    <i class="bi bi-bar-chart-line"></i>
                                </div>
                                <div>
                                    <h3 class="module-title">Estadísticas</h3>
                                    <p class="module-text">Visualice los gráficos con datos por prioridad, estado y categoría.</p>
                                </div>
                                <a href="modules/solicitudes/reportes.php" class="module-link">
                                    Ver Estadísticas <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="quick-strip mt-4">
                <i class="bi bi-shield-check me-2 text-success"></i>
                Sesión activa como <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>.
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
