<?php
// Configuración básica del sistema
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params(7200);
session_start();

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'TU_USUARIO');
define('DB_PASS', 'TU_PASSWORD');
define('DB_NAME', 'TU_BASE_DE_DATOS');

// Configuración del sistema
define('SITE_NAME', 'Sistema de Gestión de Solicitudes');
define('SITE_URL', 'http://localhost/sesol');

// Configuración de zona horaria
date_default_timezone_set('America/Merida');

// Mostrar errores (solo en desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir funciones
require_once 'functions.php';
?>
