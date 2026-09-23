<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if ($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if ($auth->login($username, $password)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
            --morena-border: #e9dfdf;
        }

        body {
            background: #f7f5f2;
            color: var(--morena-ink);
            min-height: 100vh;
        }

        .login-shell {
            align-items: stretch;
            display: flex;
            min-height: 100vh;
            padding: 28px 0;
        }

        .login-layout {
            background: #fff;
            border: 1px solid var(--morena-border);
            border-radius: 8px;
            box-shadow: 0 18px 42px rgba(43, 32, 36, .12);
            overflow: hidden;
        }

        .institutional-panel {
            background: linear-gradient(135deg, var(--morena-guinda-dark), var(--morena-guinda));
            color: #fff;
            min-height: 100%;
            overflow: hidden;
            padding: 36px;
            position: relative;
        }

        .institutional-panel::after {
            content: "";
            position: absolute;
            inset: 0 0 0 auto;
            width: 42%;
            background:
                linear-gradient(135deg, transparent 0 48%, rgba(179, 142, 93, .28) 48% 52%, transparent 52%),
                linear-gradient(45deg, transparent 0 42%, rgba(255, 255, 255, .10) 42% 46%, transparent 46%);
            opacity: .75;
        }

        .institutional-content {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 480px;
            position: relative;
            z-index: 1;
        }

        .panel-kicker {
            align-items: center;
            color: rgba(255, 255, 255, .82);
            display: flex;
            font-size: .82rem;
            font-weight: 800;
            gap: 8px;
            letter-spacing: .04em;
            margin-bottom: 14px;
            text-transform: uppercase;
        }

        .panel-title {
            font-size: clamp(2rem, 4vw, 3.1rem);
            font-weight: 800;
            line-height: 1.05;
            margin: 0;
        }

        .panel-text {
            color: rgba(255, 255, 255, .84);
            font-size: 1rem;
            margin: 16px 0 0;
            max-width: 540px;
        }

        .panel-footer {
            border-top: 1px solid rgba(255, 255, 255, .18);
            color: rgba(255, 255, 255, .78);
            font-size: .9rem;
            margin-top: auto;
            padding-top: 20px;
        }

        .access-panel {
            padding: 38px;
        }

        .login-logo {
            max-width: 178px;
            height: auto;
        }

        .access-title {
            color: var(--morena-guinda-dark);
            font-size: 1.45rem;
            font-weight: 800;
            margin: 22px 0 6px;
        }

        .access-text {
            color: var(--morena-muted);
            margin-bottom: 24px;
        }

        .form-label {
            color: var(--morena-ink);
            font-weight: 700;
        }

        .form-control {
            border-color: #d8cfd2;
            border-radius: 6px;
            padding: 11px 12px;
        }

        .form-control:focus {
            border-color: var(--morena-guinda);
            box-shadow: 0 0 0 .2rem rgba(141, 45, 68, .14);
        }

        .btn-morena {
            background-color: var(--morena-guinda) !important;
            border: 1px solid var(--morena-guinda) !important;
            border-radius: 6px;
            color: #fff !important;
            font-weight: 800;
            padding: 11px 14px;
            box-shadow: 0 2px 0 rgba(95, 24, 48, .35);
        }

        .btn-morena:hover,
        .btn-morena:focus {
            background-color: var(--morena-guinda-dark) !important;
            border-color: var(--morena-guinda-dark) !important;
            color: #fff !important;
        }

        .security-note {
            background: var(--morena-guinda-soft);
            border: 1px solid #ead6dc;
            border-radius: 8px;
            color: var(--morena-guinda-dark);
            font-size: .9rem;
            margin-top: 22px;
            padding: 12px 14px;
        }

        .brand-strip {
            align-items: center;
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .brand-strip img {
            max-height: 42px;
            object-fit: contain;
        }

        @media (max-width: 991.98px) {
            .login-shell {
                padding: 18px 0;
            }

            .institutional-content {
                min-height: 300px;
            }

            .institutional-panel,
            .access-panel {
                padding: 28px;
            }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <div class="container d-flex align-items-center">
            <div class="login-layout w-100">
                <div class="row g-0">
                    <div class="col-lg-7">
                        <section class="institutional-panel">
                            <div class="institutional-content">
                                <div>
                                    <h1 class="panel-title"><?php echo SITE_NAME; ?></h1>
                                    <p class="panel-text">Plataforma para el seguimiento ordenado de solicitudes, compromisos y acciones registradas por las áreas responsables.</p>
                                </div>
                                <div class="panel-footer">
                                    <i class="bi bi-lock me-1"></i>
                                    Ingrese con sus credenciales autorizadas para continuar.
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-lg-5">
                        <section class="access-panel">
                            <div class="text-center">
                                <img src="assets/img/logoguinda.jpg" alt="<?php echo SITE_NAME; ?> Logo" class="login-logo img-fluid">
                            </div>

                            <h2 class="access-title">Iniciar sesión</h2>
                            <p class="access-text">Acceda al panel de gestión con su usuario y contraseña.</p>

                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Usuario</label>
                                    <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                                </div>
                                <button type="submit" class="btn btn-morena w-100">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>
                                    Iniciar sesión
                                </button>
                            </form>

                            <div class="security-note">
                                <i class="bi bi-info-circle me-1"></i>
                                El acceso está reservado para personal autorizado.
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
