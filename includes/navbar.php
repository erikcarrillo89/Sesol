<nav class="navbar navbar-expand-lg navbar-dark bg-dark" style="background-color: #8D2D44 !important;">
    <div class="container">
        <a class="navbar-brand" href="/sesol/dashboard.php"><?php echo SITE_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (!$auth->isProtocolo()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/sesol/modules/solicitudes/">Solicitudes</a>
                    </li>
                <?php endif; ?>
                <?php if ($auth->canAccessVisitasEscuelas()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/sesol/modules/visitas_escuelas/">Visitas Escuelas</a>
                    </li>
                <?php endif; ?>
                <?php if ($auth->isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            Catálogos
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/sesol/modules/usuarios/">Usuarios</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="/sesol/modules/categorias/">Categorías</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="/sesol/modules/etiquetas/">Etiquetas</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <?php echo $_SESSION['nombre_completo']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">Mi perfil</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="/sesol/logout.php">Cerrar sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
