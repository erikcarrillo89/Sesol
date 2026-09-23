<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}

$db = new Database();
$usuario_id = $_SESSION['user_id'];
$usuario_rol = $_SESSION['rol'];
$condicionAtencionCiudadana = condicionAtencionCiudadanaSQL('s', 'c');
$filtroAtencionCiudadana = $usuario_rol === 'atencion_ciudadana' ? " AND {$condicionAtencionCiudadana}" : "";

// Exportar una sola solicitud
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Obtener la solicitud (AQUÍ YA ESTABAN BIEN LOS ALIAS)
    $solicitud = $db->query("SELECT s.*,
    c.categoria,
    ua.nombre_completo as asignado_nombre,
    ua2.nombre_completo as asignado2_nombre,
    ua3.nombre_completo as asignado3_nombre,
    ua4.nombre_completo as asignado4_nombre,
    ua5.nombre_completo as asignado5_nombre

    FROM solicitudes AS s
    INNER JOIN cat_categorias AS c ON c.pk_categoria = s.fk_categoria
    LEFT JOIN usuarios AS ua  ON s.asignado_id  = ua.id
    LEFT JOIN usuarios AS ua2 ON s.asignado2_id = ua2.id
    LEFT JOIN usuarios AS ua3 ON s.asignado3_id = ua3.id
    LEFT JOIN usuarios AS ua4 ON s.asignado4_id = ua4.id
    LEFT JOIN usuarios AS ua5 ON s.asignado5_id = ua5.id
    WHERE s.id = $id AND s.eliminado != 1 {$filtroAtencionCiudadana}")->fetch_assoc();

    if (!$solicitud) {
        header("Location: index.php?error=Solicitud no encontrada");
        exit();
    }

    // Obtener comentarios
    $comentarios = $db->query("SELECT * FROM comentarios_seguimiento WHERE solicitud_id = $id ORDER BY fecha_comentario DESC");

    // Preparar datos para Excel
    $data = [
        ['Folio', '<top>' . $solicitud['folio'] . '</top>'],
        ['Categoría', '<top>' . $solicitud['categoria'] . '</top>'],
        ['CCT', '<top>' . $solicitud['cct'] . '</top>'],
        ['Solicitante', '<top>' . $solicitud['solicitante'] . '</top>'],
        ['Fecha Petición', !empty($solicitud['fecha_peticion']) ? '<top>' . date('d/m/Y', strtotime($solicitud['fecha_peticion'])) . '</top>' : ''],
        ['Tipo Procedencia', '<top>' . $solicitud['tipo_procedencia'] . '</top>'],
        ['Compromiso/Asunto', '<top>' . $solicitud['compromiso'] . '</top>'],
        ['Instruido', '<top>' . $solicitud['instruido'] . '</top>'],
        ['Solicitado', '<top>' . $solicitud['solicitado'] . '</top>'],
        ['Atendió', '<top>' . $solicitud['atendio'] . '</top>'],
        ['Teléfono', '<top>' . $solicitud['telefono'] . '</top>'],
        ['<top>Descripción</top>', '<top><wraptext>' . $solicitud['descripcion'] . '</wraptext></top>'],
        ['Fecha Seguimiento', !empty($solicitud['fecha_seguimiento']) ? '<top>' . date('d/m/Y', strtotime($solicitud['fecha_seguimiento'])) . '</top>' : ''],
        ['Responsable Seguimiento', '<top>' . $solicitud['responsable_seguimiento'] . '</top>'],
        ['Indicaciones Secretario', '<top>' . $solicitud['indicaciones_secretario'] . '</top>'],
        ['Fecha Vencimiento', !empty($solicitud['fecha_vencimiento']) ? '<top>' . date('d/m/Y', strtotime($solicitud['fecha_vencimiento'])) . '</top>' : ''],
        ['Prioridad', '<top>' . $solicitud['prioridad'] . '</top>'],
        ['Estado', '<top>' . $solicitud['estado'] . '</top>'],
        ['Asignado a', '<top>' . ($solicitud['asignado2_nombre'] ?? 'No asignado') . '</top>'],
        ['Asignado 2', '<top>' . ($solicitud['asignado3_nombre'] ?? 'No asignado') . '</top>'],
        ['Asignado 3', '<top>' . ($solicitud['asignado4_nombre'] ?? 'No asignado') . '</top>'],
        ['Asignado 4', '<top>' . ($solicitud['asignado5_nombre'] ?? 'No asignado') . '</top>'],
        ['', ''],
        ['Comentarios de Seguimiento', '']
    ];

    // Agregar comentarios
    if ($comentarios->num_rows > 0) {
        while ($comentario = $comentarios->fetch_assoc()) {
            $data[] = [
                !empty($comentario['fecha_comentario']) ? '<top>' . date('d/m/Y', strtotime($comentario['fecha_comentario'])) . '</top>' : '',
                '<top><wraptext>' . $comentario['comentario'] . '</wraptext></top>'
            ];
        }
    }

    $filename = "Solicitud_" . $solicitud['folio'] . ".xlsx";

} else {
    // Exportar múltiples solicitudes basadas en filtros

    // Aplicar mismos filtros que en index.php
    $busqueda       = isset($_GET['busqueda'])     ? sanitizeInput($_GET['busqueda'])      : '';
    $fechaInicio    = isset($_GET['fecha_inicio']) ? sanitizeInput($_GET['fecha_inicio'])  : '';
    $fechaFin       = isset($_GET['fecha_fin'])    ? sanitizeInput($_GET['fecha_fin'])     : '';
    $prioridadFiltro= isset($_GET['prioridad'])    ? sanitizeInput($_GET['prioridad'])     : '';
    $estadoFiltro   = isset($_GET['estado'])       ? sanitizeInput($_GET['estado'])        : '';
    $categoriaFiltro   = isset($_GET['categoria']) ? sanitizeInput($_GET['categoria'])     : '';
    $municipioLocalidadFiltro = isset($_GET['municipio_localidad']) ? sanitizeInput($_GET['municipio_localidad']) : '';
    $etiquetasFiltro = isset($_GET['etiquetas']) ? ($_GET['etiquetas']) : [];
    $SITE_URL=SITE_URL;
    $sql = "SELECT s.*,
                   u.nombre_completo  AS usuario_nombre,
                   ua.nombre_completo AS asignado_nombre,
                   ua2.nombre_completo AS asignado2_nombre,
                   ua3.nombre_completo AS asignado3_nombre,
                   ua4.nombre_completo AS asignado4_nombre,
                   ua5.nombre_completo AS asignado5_nombre,
                   c.categoria,
                   GROUP_CONCAT(DISTINCT e.etiqueta SEPARATOR ', ') AS etiquetas,
                   COUNT(st.pk_tarea) AS total_tareas,
               SUM(CASE WHEN st.fk_estatus = 2 THEN 1 ELSE 0 END) AS tareas_completadas,
               CASE
                   WHEN COUNT(st.pk_tarea) > 0 THEN
                       ROUND((SUM(CASE WHEN st.fk_estatus = 2 THEN 1 ELSE 0 END) / COUNT(st.pk_tarea)) * 100, 0)
                   ELSE 0
               END AS porcentaje_completado,
                   GROUP_CONCAT(
                        CONCAT(uc.nombre_completo,' - ',
                            IF(cs.fecha_comentario IS NULL || cs.fecha_comentario = '0000-00-00','N/D\\n', CONCAT(DATE_FORMAT(cs.fecha_comentario,'%d/%m/%Y'),'\\n') ),
                            '',
                             REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(cs.comentario, CONCAT('\\\\', 'r', '\\\\', 'n'), ' '),
                                    CONCAT(CHAR(13), CHAR(10)), ' '),
                                CHAR(10), ' '),
                            CHAR(9), ' '),
                            IF(cs.archivo IS NOT NULL,CONCAT('\\n\\n{$SITE_URL}uploads/comentarios/',cs.archivo),'') ) SEPARATOR '\\n\\n') AS comentarios
            FROM solicitudes AS s
            INNER JOIN usuarios AS u  ON s.usuario_id   = u.id
            INNER JOIN cat_categorias AS c ON c.pk_categoria = s.fk_categoria
            LEFT JOIN solicitudes_tareas st ON s.id = st.fk_solicitud
            LEFT JOIN solicitudes_rel AS sr ON s.id = sr.fk_solicitud
            LEFT JOIN cat_etiquetas AS e ON e.pk_etiqueta = sr.fk_etiqueta
            LEFT JOIN comentarios_seguimiento AS cs ON cs.solicitud_id = s.id
            LEFT JOIN usuarios AS uc  ON uc.id = cs.usuario_id
            LEFT JOIN usuarios AS ua  ON s.asignado_id  = ua.id
            LEFT JOIN usuarios AS ua2 ON s.asignado2_id = ua2.id
            LEFT JOIN usuarios AS ua3 ON s.asignado3_id = ua3.id
            LEFT JOIN usuarios AS ua4 ON s.asignado4_id = ua4.id
            LEFT JOIN usuarios AS ua5 ON s.asignado5_id = ua5.id
            WHERE s.eliminado != 1
            ";
    if ($usuario_rol === 'usuario') {
        $sql .= " AND (s.asignado_id = $usuario_id OR s.asignado2_id = $usuario_id OR s.asignado3_id = $usuario_id OR s.asignado4_id = $usuario_id OR s.asignado5_id = $usuario_id)";
    }
    if ($usuario_rol === 'especial') {    
        $usuario_id = $_SESSION['user_id']; 
        $sql .= " AND (
            s.fk_categoria IN (
                SELECT fk_categoria FROM usuarios_categorias WHERE fk_usuario = ".$usuario_id." 
            )
            OR s.id IN (
                SELECT sr.fk_solicitud FROM solicitudes_rel sr
                INNER JOIN usuarios_etiquetas ue ON sr.fk_etiqueta = ue.fk_etiqueta
                WHERE ue.fk_usuario = ".$usuario_id."
            )
        )";
    }
    if ($usuario_rol === 'atencion_ciudadana') {
        $sql .= " AND {$condicionAtencionCiudadana}";
    }
    if (!empty($busqueda)) {
        $sql .= " AND (s.folio LIKE '%$busqueda%'
                    OR c.categoria LIKE '%$busqueda%'
                    OR s.cct LIKE '%$busqueda%'
                    OR s.N_MUNICIPIO LIKE '%$busqueda%'
                    OR s.N_LOCALIDAD LIKE '%$busqueda%'
                    OR s.NOMBRECT LIKE '%$busqueda%'
                    OR TRIM(CONCAT(s.ap1, ' ',s.solicitante)) LIKE '%$busqueda%'
                    OR TRIM(CONCAT(s.solicitante,' ',s.ap1)) LIKE '%$busqueda%'
                    OR TRIM(CONCAT(s.ap1, ' ', s.ap2,' ', s.solicitante)) LIKE '%$busqueda%'
                    OR TRIM(CONCAT(s.solicitante,' ',s.ap1,' ',s.ap2)) LIKE '%$busqueda%'
                    OR s.solicitante LIKE '%$busqueda%'
                    OR s.ap1 LIKE '%$busqueda%'
                    OR s.ap2 LIKE '%$busqueda%'
                    OR s.compromiso LIKE '%$busqueda%'
                    OR s.descripcion LIKE '%$busqueda%'
                    OR s.instruido LIKE '%$busqueda%'
                    OR s.solicitado LIKE '%$busqueda%'
                    OR s.atendio LIKE '%$busqueda%'
                    OR s.responsable_seguimiento LIKE '%$busqueda%'
                    OR s.indicaciones_secretario LIKE '%$busqueda%')";
    }

    if (!empty($municipioLocalidadFiltro)) {
        $sql .= " AND (s.N_MUNICIPIO LIKE '%$municipioLocalidadFiltro%' OR s.N_LOCALIDAD LIKE '%$municipioLocalidadFiltro%')";
    }

    if (!empty($fechaInicio) && !empty($fechaFin)) {
        $sql .= " AND s.fecha_peticion BETWEEN '$fechaInicio' AND '$fechaFin'";
    } elseif (!empty($fechaInicio)) {
        $sql .= " AND s.fecha_peticion >= '$fechaInicio'";
    } elseif (!empty($fechaFin)) {
        $sql .= " AND s.fecha_peticion <= '$fechaFin'";
    }
    //
    if (!empty($prioridadFiltro)) {
        $sql .= " AND s.prioridad = '$prioridadFiltro'";
    }
    if (!empty($categoriaFiltro)) {
        $sql .= " AND s.fk_categoria = '$categoriaFiltro'";
    }
    if (!empty($estadoFiltro)) {
        $sql .= " AND s.estado = '$estadoFiltro'";
    }
    if (count($etiquetasFiltro) > 0) {
        $sql .= " AND sr.fk_etiqueta IN (" . implode(',', $etiquetasFiltro) . ")";
    }
    $sql .= " GROUP BY s.id ORDER BY s.fecha_peticion DESC";
    $solicitudes = $db->query($sql);

    $data = [
        ['Folio', 'Categoría', 'CCT', 'Solicitante','Petición', 'Compromiso/Asunto', 'Prioridad', 'Estado','Porcentaje de avance',
         'Instruido', 'Solicitado', 'Atendió', 'Teléfono', 'Descripción', 'Fecha Seguimiento',
         'Responsable Seguimiento', 'Fecha Vencimiento', 'Usuario', 'Asignado', 'Asignado 2', 'Asignado 3',
            'Asignado 4','Asignado 5','Etiquetas', 'Comentarios']
    ];

    while ($solicitud = $solicitudes->fetch_assoc()) {
        $nombre = $solicitud['solicitante'];
        if(trim($solicitud['ap1'] ?? '') !== '' && trim($solicitud['ap2'] ?? '') !== '') {
            $nombre = $solicitud['ap1'] . ' ' . $solicitud['ap2'] . ' ' . $nombre;
        }
        if (trim($solicitud['ap1'] ?? '') !== '' && trim($solicitud['ap2'] ?? '') == '') {
            $nombre = $solicitud['ap1'] . ' ' . $nombre;
        }
        if (trim($solicitud['ap2'] ?? '') !== '' && trim($solicitud['ap1'] ?? '') == '') {
            $nombre = $solicitud['ap2'] . ' ' . $nombre;
        }

        $totalTareas = $solicitud['total_tareas'] ?? 0;
        $data[] = [
            '<top>' . $solicitud['folio'] . '</top>',
            '<top>' . $solicitud['categoria'] . '</top>',
            '<top>' . strtoupper($solicitud['cct'] ?? '') . '</top>',
            '<top>' . $nombre . '</top>',
            !empty($solicitud['fecha_peticion']) && $solicitud['fecha_peticion'] != '0000-00-00' ? '<top>' . date('d/m/Y', strtotime($solicitud['fecha_peticion'])) . '</top>' : '',
            '<top>' . $solicitud['compromiso'] . '</top>',
            '<top>' . $solicitud['prioridad'] . '</top>',
            '<top>' . $solicitud['estado'] . '</top>',
            '<top>' . ((int)$totalTareas > 0 ? $solicitud['porcentaje_completado'] . '%' : '') . '</top>',
            '<top>' . $solicitud['instruido'] . '</top>',
            '<top>' . $solicitud['solicitado'] . '</top>',
            '<top>' . $solicitud['atendio'] . '</top>',
            '<top>' . $solicitud['telefono'] . '</top>',
            '<top><wraptext>' . $solicitud['descripcion'] . '</wraptext></top>',
            !empty($solicitud['fecha_seguimiento']) && $solicitud['fecha_seguimiento'] != '0000-00-00' ? '<top>' . date('d/m/Y', strtotime($solicitud['fecha_seguimiento'])) . '</top>' : '',
            '<top>' . $solicitud['responsable_seguimiento'] . '</top>',
            !empty($solicitud['fecha_vencimiento']) && $solicitud['fecha_vencimiento'] != '0000-00-00' ? '<top>' . date('d/m/Y', strtotime($solicitud['fecha_vencimiento'])) . '</top>' : '',
            '<top>' . $solicitud['usuario_nombre'] . '</top>',
            '<top>' . $solicitud['asignado_nombre'] . '</top>',
            '<top>' . $solicitud['asignado2_nombre'] . '</top>',
            '<top>' . $solicitud['asignado3_nombre'] . '</top>',
            '<top>' . $solicitud['asignado4_nombre'] . '</top>',
            '<top>' . $solicitud['asignado5_nombre'] . '</top>',
            '<top>' . $solicitud['etiquetas'] . '</top>',
            '<top><wraptext>' . $solicitud['comentarios'] . '</wraptext></top>',
        ];
    }

    $filename = "Solicitudes_" . date('Y-m-d') . ".xlsx";
}

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->setColWidth(11, 30);
$xlsx->setColWidth(19, 35);
$xlsx->downloadAs($filename);
?>
