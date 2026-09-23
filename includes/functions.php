<?php
// Funciones útiles para el sistema
require_once 'config.php';
require_once 'db.php';

function mTL($texto) {
    $texto = preg_replace_callback('/&[A-Z]+;/', function($match) {
        return strtolower($match[0]);
    }, $texto);

    $prev_text = '';
    $intentos = 0;
    while ($texto !== $prev_text && $intentos < 10) {
        $prev_text = $texto;
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $intentos++;
    }

    $texto = preg_replace('/\brn\b/i', "\n", $texto);
    $texto = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $texto);

    return trim($texto); // Ya no hace nl2br
}



/*function generarFolio() {
    $db = new Database();
    $result = $db->query("SELECT COUNT(*) as total FROM solicitudes");
    $row = $result->fetch_assoc();
    $numero = $row['total'] + 1;
    return 'S' . str_pad($numero, 3, '0', STR_PAD_LEFT);
}*/

function generarFolio() {
    $db = new Database();
    $result = $db->query("SELECT folio FROM solicitudes ORDER BY id DESC LIMIT 1");
    $row = $result->fetch_assoc();
    
    if ($row && isset($row['folio'])) {
        $ultimoNumero = intval(substr($row['folio'], 1)); // quitar la "S" y convertir a número
        $nuevoNumero = $ultimoNumero + 1;
    } else {
        $nuevoNumero = 1; // Si no hay registros aún
    }
    
    return 'S' . str_pad($nuevoNumero, 3, '0', STR_PAD_LEFT);
}

function sanitizeInput($data) {
    $db = new Database();
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $db->escapeString($data);
}

function exportToExcel($data, $filename) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    $flag = false;
    foreach($data as $row) {
        if(!$flag) {
            // Mostrar los nombres de las columnas
            echo implode("\t", array_keys($row)) . "\n";
            $flag = true;
        }
        // Limpiar datos y asegurar compatibilidad con Excel
        array_walk($row, function(&$str) {
            $str = preg_replace("/\t/", "\\t", $str);
            $str = preg_replace("/\r?\n/", "\\n", $str);
            if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
        });
        echo implode("\t", array_values($row)) . "\n";
    }
    exit();
}

function formatDateForDB($date) {
    if (empty($date)) return null;
    return date('Y-m-d', strtotime($date));
}

function formatDateForDisplay($date) {
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

function validarSolicitante($solicitante) {
    return !empty(trim($solicitante));
}

function sanitizeSolicitante($input) {
    $input = trim($input);
    $input = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/u', '', $input);
    return substr($input, 0, 100); // Limitar a 100 caracteres
}

function condicionAtencionCiudadanaSQL($solicitudAlias = 's', $categoriaAlias = 'c') {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $solicitudAlias) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $categoriaAlias)) {
        return '1=0';
    }

    $nombresAtencion = "('atención ciudadana', 'atencion ciudadana')";

    return "(
        LOWER(TRIM({$categoriaAlias}.categoria)) IN {$nombresAtencion}
        OR EXISTS (
            SELECT 1
            FROM solicitudes_rel AS sr_ac
            INNER JOIN cat_etiquetas AS et_ac ON et_ac.pk_etiqueta = sr_ac.fk_etiqueta
            WHERE sr_ac.fk_solicitud = {$solicitudAlias}.id
            AND LOWER(TRIM(et_ac.etiqueta)) IN {$nombresAtencion}
        )
    )";
}

function nombreRol($rol) {
    $roles = [
        'admin' => 'Administrador',
        'usuario' => 'Usuario',
        'especial' => 'Especial',
        'protocolo' => 'Protocolo',
        'atencion_ciudadana' => 'Atención Ciudadana',
        'monitor' => 'Monitor'
    ];

    return $roles[$rol] ?? ucfirst((string)$rol);
}

?>
