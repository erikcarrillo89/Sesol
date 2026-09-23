<?php
// Autenticación de usuarios
require_once 'config.php';
require_once 'db.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->enforceProtocoloAccess();
        $this->enforceAtencionCiudadanaAccess();
    }

    private function enforceProtocoloAccess() {
        if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'protocolo') {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
        $archivo = basename($script);
        $permitidosSolicitudes = [
            'buscar_cct.php',
            'buscar_ubicaciones_cct.php'
        ];

        if (strpos($script, '/modules/solicitudes/') !== false && !in_array($archivo, $permitidosSolicitudes, true)) {
            header("Location: /sesol/modules/visitas_escuelas/");
            exit();
        }
    }

    private function enforceAtencionCiudadanaAccess() {
        if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'atencion_ciudadana') {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
        $modulosRestringidos = [
            '/modules/visitas_escuelas/',
            '/modules/usuarios/',
            '/modules/categorias/',
            '/modules/etiquetas/'
        ];

        foreach ($modulosRestringidos as $moduloRestringido) {
            if (strpos($script, $moduloRestringido) !== false) {
                header("Location: /sesol/modules/solicitudes/");
                exit();
            }
        }

        if (strpos($script, '/modules/solicitudes/reportes') !== false) {
            header("Location: /sesol/modules/solicitudes/");
            exit();
        }
    }

    
    
    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT id, username, password, nombre_completo, rol FROM usuarios WHERE username = ? AND activo = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nombre_completo'] = $user['nombre_completo'];
                $_SESSION['rol'] = $user['rol'];
                return true;
            }
        }
        return false;
    }
        

       /* public function login($username, $password) {
            $stmt = $this->db->prepare("SELECT id, username, password, nombre_completo, rol 
                                       FROM usuarios 
                                       WHERE username = ? AND activo = 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
        
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                // Comparación directa (sin password_verify)
                if ($password === $user['password']) {  // ← Cambio clave aquí
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nombre_completo'] = $user['nombre_completo'];
                    $_SESSION['rol'] = $user['rol'];
                    return true;
                }
            }
            return false;
        }    
            */

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return $this->isLoggedIn() && in_array($_SESSION['rol'], ['admin', 'monitor'], true);
    }

    public function isMonitor() {
        return $this->isLoggedIn() && $_SESSION['rol'] === 'monitor';
    }

    public function isProtocolo() {
        return $this->isLoggedIn() && $_SESSION['rol'] === 'protocolo';
    }

    public function isAtencionCiudadana() {
        return $this->isLoggedIn() && $_SESSION['rol'] === 'atencion_ciudadana';
    }

    public function canAccessVisitasEscuelas() {
        return $this->isAdmin() || $this->isProtocolo();
    }

    public function logout() {
        session_unset();
        session_destroy();
    }

    public function getUser($id) {
        $stmt = $this->db->prepare("SELECT id, username, nombre_completo, email, rol FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

$auth = new Auth();
?>
