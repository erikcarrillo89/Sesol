<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}
require_once '../../includes/fpdf/fpdf.php';
class PDF extends FPDF {

    // Helper para codificación
    public function encode($text) {
        return mb_convert_encoding($text, "ISO-8859-1", "UTF-8");
    }
    public $widths;
    public $aligns;
    public function Header() {
        $this->SetFont('Arial', 'B', 12);
        $this->Image('../../assets/img/logo_segey.png', 10, 7, 80, 0, 'PNG');
        $this->Ln(3);
        $this->Cell(90, 4, '', 0, 0, 'C');
        $this->Cell(97, 4, $this->encode('SECRETARÍA DE EDUCACIÓN'), 0, 0, 'L');
        $this->Cell(69, 4, '', 0, 0, 'C');
        $this->Ln(5);
        $this->Cell(90, 4, '', 0, 0, 'C');
        $this->Cell(97, 4, $this->encode('SOLICITUDES - SESOL'), 0, 0, 'L');
        $this->Cell(69, 4, '', 0, 1, 'C');
        $this->Ln(3);
    }
    public function Footer() {
        $this->AliasNbPages();
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(256, 4, $this->encode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    public function SetWidths($w) {
        //Set the array of column widths
        $this->widths = $w;
    }
    public function SetAligns($a) {
        //Set the array of column alignments
        $this->aligns = $a;
    }
    public function Row($data) {
        //Calculate the height of the row
        $nb = 0;
        for ($i = 0; $i < count($data); $i++)
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        $h = max(3.5, 3.5 * $nb); // Altura mínima 3.5, máxima según contenido
        //Issue a page break first if needed
        $this->CheckPageBreak($h);
        //Draw the cells of the row
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            //Save the current position
            $x = $this->GetX();
            $y = $this->GetY();
            //Draw the border
            $this->Rect($x, $y, $w, $h);
            //Print the text
            $this->MultiCell($w, 3.5, $data[$i], 0, $a);
            //Put the position to the right of the cell
            $this->SetXY($x + $w, $y);
        }
        //Go to the next line
        $this->Ln($h);
    }
    public function CheckPageBreak($h) {
        //If the height h would cause an overflow, add a new page immediately
        if ($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation, $this->CurPageSize);
    }
    public function NbLines($w, $txt) {
        //Computes the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
                $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }
}
$db = new Database();
$usuario_id = $_SESSION['user_id'];
$usuario_rol = $_SESSION['rol'];
$condicionAtencionCiudadana = condicionAtencionCiudadanaSQL('s', 'c');
// Exportar múltiples solicitudes basadas en filtros
// Aplicar mismos filtros que en index.php
$busqueda       = isset($_GET['busqueda'])     ? sanitizeInput($_GET['busqueda'])      : '';
$fechaInicio    = isset($_GET['fecha_inicio']) ? sanitizeInput($_GET['fecha_inicio'])  : '';
$fechaFin       = isset($_GET['fecha_fin'])    ? sanitizeInput($_GET['fecha_fin'])     : '';
$prioridadFiltro = isset($_GET['prioridad'])    ? sanitizeInput($_GET['prioridad'])     : '';
$estadoFiltro   = isset($_GET['estado'])       ? sanitizeInput($_GET['estado'])        : '';
$categoriaFiltro   = isset($_GET['categoria']) ? sanitizeInput($_GET['categoria'])     : '';
$municipioLocalidadFiltro = isset($_GET['municipio_localidad']) ? sanitizeInput($_GET['municipio_localidad']) : '';
$etiquetasFiltro = isset($_GET['etiquetas']) ? ($_GET['etiquetas']) : [];
$SITE_URL = SITE_URL;
$sql = "SELECT s.*,
                UPPER(s.cct) AS cct,
                u.nombre_completo  AS usuario_nombre,
                ua.nombre_completo AS asignado_nombre,
                ua2.nombre_completo AS asignado2_nombre,
                ua3.nombre_completo AS asignado3_nombre,
                ua4.nombre_completo AS asignado4_nombre,
                ua5.nombre_completo AS asignado5_nombre,
                IF(e.etiqueta IS NOT NULL, CONCAT(c.categoria, ' \n\nEtiquetas: ', GROUP_CONCAT(DISTINCT e.etiqueta SEPARATOR ', ')), c.categoria) AS categoria,
                COUNT(st.pk_tarea) AS total_tareas,
               SUM(CASE WHEN st.fk_estatus = 2 THEN 1 ELSE 0 END) AS tareas_completadas,
               CASE
                   WHEN COUNT(st.pk_tarea) > 0 THEN
                       ROUND((SUM(CASE WHEN st.fk_estatus = 2 THEN 1 ELSE 0 END) / COUNT(st.pk_tarea)) * 100, 0)
                   ELSE 0
               END AS porcentaje_completado,
                REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(s.descripcion, CONCAT('\\\\', 'r', '\\\\', 'n'), ' '),
                                CONCAT(CHAR(13), CHAR(10)), ' '),
                            CHAR(10), ' '),
                        CHAR(9), ' ') AS descripcion2,
                GROUP_CONCAT( DISTINCT
                    CONCAT(uc.nombre_completo,' - ',
                        IF(cs.fecha_comentario IS NULL || cs.fecha_comentario = '0000-00-00','N/D\\n', CONCAT(DATE_FORMAT(cs.fecha_comentario,'%d/%m/%Y'),'\\n') ),
                        '',
                            REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(cs.comentario, CONCAT('\\\\', 'r', '\\\\', 'n'), ' '),
                                CONCAT(CHAR(13), CHAR(10)), ' '),
                            CHAR(10), ' '),
                        CHAR(9), ' ')
                            ) SEPARATOR ' \\n') AS comentarios
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
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage('L', 'Letter');
$pdf->Ln(2);
// Encabezados de tabla
$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(158, 157, 157);
$headers = [
     ['#', 10],
    ['Folio', 10],
    ['Categoría', 15],
    ['CCT', 25],
    ['Solicitante', 15],
    ['Fecha Sol.', 15],
    ['Estado', 15],
    ['Asignación', 30],
    ['Compromiso', 30],
    ['Descripción', 50],
    ['Comentarios', 50]
];
foreach ($headers as $header) {
    $pdf->Cell($header[1], 4, $pdf->encode($header[0]), 1, 0, 'C', 1);
}
$pdf->Ln();
$pdf->SetWidths([10, 10, 15, 25, 15, 15, 15, 30, 30, 50, 50]);
$pdf->SetAligns(['C','C', 'L', 'L', 'L', 'C', 'C', 'L', 'L', 'L', 'L']);
$pdf->SetFont('Arial', '', 6);

$consecutivo = 1; // Inicializar el contador


while ($solicitud = $solicitudes->fetch_assoc()) {
    // Construir nombre completo
    $partes = array_filter([
        trim($solicitud['ap1'] ?? ''),
        trim($solicitud['ap2'] ?? ''),
        trim($solicitud['solicitante'] ?? '')
    ]);
    $nombre = implode(' ', $partes);

    // Listar las asignaciones
    $lista_asignados = array_filter([
        trim($solicitud['asignado_nombre'] ?? ''),
        trim($solicitud['asignado2_nombre'] ?? ''),
        trim($solicitud['asignado3_nombre'] ?? ''),
        trim($solicitud['asignado4_nombre'] ?? ''),
        trim($solicitud['asignado5_nombre'] ?? '')
    ]);
    $asignados = implode(';', $lista_asignados);

    $cct_detalle = trim($solicitud['cct'] ?? '');
    $datos_cct = array_filter([
        trim($solicitud['NOMBRECT'] ?? ''),
        trim(($solicitud['N_MUNICIPIO'] ?? '') . ' / ' . ($solicitud['N_LOCALIDAD'] ?? ''), ' /')
    ]);
    if (!empty($datos_cct)) {
        $cct_detalle .= "\n" . implode("\n", $datos_cct);
    }

    // Concatenar el consecutivo con un salto de línea y el folio
    $folio_consecutivo = $consecutivo;
 
    $pdf->row([
        $pdf->encode($folio_consecutivo),
        $pdf->encode($solicitud['folio'] ?? ''),
        $pdf->encode($solicitud['categoria'] ?? ''),
        $pdf->encode($cct_detalle),
        $pdf->encode($nombre),
        !empty($solicitud['fecha_peticion']) && $solicitud['fecha_peticion'] != '0000-00-00' ? date('d/m/Y', strtotime($solicitud['fecha_peticion'])) : '',
       ($solicitud['total_tareas'] ?? 0) > 0 ? $pdf->encode($solicitud['estado'] ?? '')."\n".$solicitud['porcentaje_completado'].'%' : $pdf->encode($solicitud['estado'] ?? ''),
        //$pdf->encode($solicitud['asignados'] ?? ''),
        $pdf->encode($asignados),
        $pdf->encode($solicitud['compromiso'] ?? ''),
        $pdf->encode($solicitud['descripcion2'] ?? ''),
        $pdf->encode($solicitud['comentarios'] ?? '')
    ]);
    // INICIO DE LA MODIFICACIÓN
    $consecutivo++; // Incrementar el contador
    // FIN DE LA MODIFICACIÓN
}
$filename = "Solicitudes_" . date('Y-m-d') . ".pdf";
//$pdf->Output();
$pdf->Output($filename, 'D');
