<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/fpdf/fpdf.php';

if (!$auth->canAccessVisitasEscuelas()) {
    header("Location: ../../login.php");
    exit();
}

$cct = strtoupper(trim(sanitizeInput($_GET['cct'] ?? '')));
if ($cct === '') {
    header("Location: consulta.php");
    exit();
}

$db = new Database();

function cctPdfFecha($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00') {
        return '';
    }
    return date('d/m/Y', strtotime($fecha));
}

function cctPdfTexto($texto) {
    $texto = html_entity_decode((string)($texto ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $texto);
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    return trim($texto);
}

function cctPdfTurno($turno) {
    $turnos = [
        '100' => 'MATUTINO',
        '200' => 'VESPERTINO',
        '300' => 'NOCTURNA',
        '400' => 'DISCONTINUA'
    ];
    return $turnos[(string)$turno] ?? 'ND';
}

function cctPdfEstatusTarea($estatus) {
    $estatuses = [
        0 => 'No iniciada',
        1 => 'En proceso',
        2 => 'Concluida',
        3 => 'Pendiente'
    ];
    return $estatuses[(int)$estatus] ?? 'ND';
}

class PDFSeguimientoCct extends FPDF {
    public function e($text) {
        return mb_convert_encoding(cctPdfTexto($text), 'ISO-8859-1', 'UTF-8');
    }

    public function Header() {
        $this->Image('../../assets/img/logo_segey.png', 12, 8, 58, 0, 'PNG');
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(95, 24, 48);
        $this->SetXY(76, 10);
        $this->Cell(198, 5, $this->e('SECRETARÍA DE EDUCACIÓN'), 0, 1, 'R');
        $this->SetX(76);
        $this->Cell(198, 5, $this->e('CONSULTA DE SOLICITUDES POR ESCUELA'), 0, 1, 'R');
        $this->SetDrawColor(141, 45, 68);
        $this->Line(12, 24, 285, 24);
        $this->Ln(12);
    }

    public function Footer() {
        $this->SetY(-13);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(273, 4, $this->e('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function CheckSpace($height) {
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation, $this->CurPageSize);
        }
    }

    public function Section($title) {
        $this->CheckSpace(9);
        $this->SetFillColor(141, 45, 68);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(273, 6, $this->e($title), 0, 1, 'L', true);
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
    }

    public function Grid($items, $columns = 4) {
        $w = 273 / $columns;
        $h = 13;
        $i = 0;
        foreach ($items as $item) {
            if ($i % $columns === 0) {
                $this->CheckSpace($h);
            }
            $x = $this->GetX();
            $y = $this->GetY();
            $this->SetDrawColor(225, 218, 218);
            $this->Rect($x, $y, $w, $h);
            $this->SetXY($x + 2, $y + 2);
            $this->SetFont('Arial', 'B', 6.8);
            $this->SetTextColor(95, 24, 48);
            $this->Cell($w - 4, 3, $this->e($item[0]), 0, 2, 'L');
            $this->SetFont('Arial', '', 7.3);
            $this->SetTextColor(0, 0, 0);
            $this->MultiCell($w - 4, 3.5, $this->e($item[1]), 0, 'L');
            $this->SetXY($x + $w, $y);
            $i++;
            if ($i % $columns === 0) {
                $this->Ln($h);
            }
        }
        if ($i % $columns !== 0) {
            $this->Ln($h);
        }
        $this->Ln(2);
    }

    public function Block($label, $text) {
        $text = cctPdfTexto($text);
        if ($text === '') {
            $text = 'Sin información.';
        }
        $this->CheckSpace(13);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(95, 24, 48);
        $this->Cell(273, 4, $this->e($label), 0, 1, 'L');
        $this->SetFont('Arial', '', 7.3);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(273, 3.8, $this->e($text), 0, 'L');
        $this->Ln(1);
    }

    public function Item($title, $text, $meta = '') {
        $this->CheckSpace(13);
        $this->SetFont('Arial', 'B', 7.2);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(273, 3.6, $this->e('- ' . $title), 0, 'L');
        if ($meta !== '') {
            $this->SetFont('Arial', '', 6.8);
            $this->SetTextColor(95, 95, 95);
            $this->MultiCell(273, 3.4, $this->e($meta), 0, 'L');
        }
        if (cctPdfTexto($text) !== '') {
            $this->SetFont('Arial', '', 7.1);
            $this->SetTextColor(0, 0, 0);
            $this->MultiCell(273, 3.6, $this->e($text), 0, 'L');
        }
        $this->Ln(1);
    }
}

$stmtCct = $db->prepare("SELECT CLAVECCT, NOMBRECT, N_NIVEL, TURNO, N_MUNICIPIO, N_LOCALIDAD,
        DOMICILIO, ENTRECALLE, YCALLE, NUMEXT, COLONIA, CODPOST
    FROM cct
    WHERE CLAVECCT = ?
    LIMIT 1");
$stmtCct->bind_param("s", $cct);
$stmtCct->execute();
$escuela = $stmtCct->get_result()->fetch_assoc();
$stmtCct->close();

$solicitudes = [];
$stmtSolicitudes = $db->prepare("SELECT s.id, s.folio, s.cct, s.NOMBRECT, s.N_MUNICIPIO, s.N_LOCALIDAD,
        s.solicitante, s.ap1, s.ap2, s.fecha_peticion, s.tipo_procedencia, s.compromiso,
        s.descripcion, s.indicaciones_secretario, s.fecha_seguimiento, s.responsable_seguimiento,
        s.fecha_vencimiento, s.prioridad, s.estado, cat.categoria,
        u.nombre_completo AS usuario_nombre,
        ua.nombre_completo AS asignado_nombre,
        ua2.nombre_completo AS asignado2_nombre,
        ua3.nombre_completo AS asignado3_nombre,
        ua4.nombre_completo AS asignado4_nombre,
        ua5.nombre_completo AS asignado5_nombre
    FROM solicitudes s
    LEFT JOIN cat_categorias cat ON cat.pk_categoria = s.fk_categoria
    LEFT JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN usuarios ua ON ua.id = s.asignado_id
    LEFT JOIN usuarios ua2 ON ua2.id = s.asignado2_id
    LEFT JOIN usuarios ua3 ON ua3.id = s.asignado3_id
    LEFT JOIN usuarios ua4 ON ua4.id = s.asignado4_id
    LEFT JOIN usuarios ua5 ON ua5.id = s.asignado5_id
    WHERE UPPER(TRIM(s.cct)) = ?
        AND (s.eliminado IS NULL OR s.eliminado = 0)
    ORDER BY s.fecha_peticion DESC, s.id DESC");
$stmtSolicitudes->bind_param("s", $cct);
$stmtSolicitudes->execute();
$resultSolicitudes = $stmtSolicitudes->get_result();
while ($solicitud = $resultSolicitudes->fetch_assoc()) {
    $solicitud['tareas'] = [];
    $solicitud['comentarios'] = [];
    $solicitudes[(int)$solicitud['id']] = $solicitud;
}
$stmtSolicitudes->close();

if (!empty($solicitudes)) {
    $idsSeguros = implode(',', array_map('intval', array_keys($solicitudes)));
    $tareas = $db->query("SELECT st.fk_solicitud, st.tarea, st.fecha, st.fecha_cierre, st.fk_estatus,
            u.nombre_completo AS usuario_nombre
        FROM solicitudes_tareas st
        LEFT JOIN usuarios u ON u.id = st.fk_usuario
        WHERE st.fk_solicitud IN ($idsSeguros)
        ORDER BY st.fecha DESC, st.pk_tarea DESC");
    while ($tarea = $tareas->fetch_assoc()) {
        $solicitudes[(int)$tarea['fk_solicitud']]['tareas'][] = $tarea;
    }

    $comentarios = $db->query("SELECT cs.solicitud_id, cs.fecha_comentario, cs.comentario, cs.archivo,
            u.nombre_completo AS usuario_nombre
        FROM comentarios_seguimiento cs
        LEFT JOIN usuarios u ON u.id = cs.usuario_id
        WHERE cs.solicitud_id IN ($idsSeguros)
        ORDER BY cs.fecha_comentario DESC, cs.id DESC");
    while ($comentario = $comentarios->fetch_assoc()) {
        $solicitudes[(int)$comentario['solicitud_id']]['comentarios'][] = $comentario;
    }
}

$direccion = array_filter([
    trim($escuela['DOMICILIO'] ?? ''),
    !empty($escuela['NUMEXT']) ? 'NUM. EXT. ' . trim($escuela['NUMEXT']) : '',
    !empty($escuela['COLONIA']) ? 'COL. ' . trim($escuela['COLONIA']) : '',
    !empty($escuela['CODPOST']) ? 'C.P. ' . trim($escuela['CODPOST']) : ''
]);
$entreCalles = array_filter([
    !empty($escuela['ENTRECALLE']) ? 'ENTRE ' . trim($escuela['ENTRECALLE']) : '',
    !empty($escuela['YCALLE']) ? 'Y ' . trim($escuela['YCALLE']) : ''
]);

$pdf = new PDFSeguimientoCct('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(12, 28, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(95, 24, 48);
$pdf->Cell(273, 7, $pdf->e('Seguimiento por CCT'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(273, 4, $pdf->e('Generado: ' . date('d/m/Y H:i')), 0, 1, 'L');
$pdf->Ln(3);

$pdf->Section('Datos de la escuela');
$pdf->Grid([
    ['CCT', $cct],
    ['Escuela', $escuela['NOMBRECT'] ?? 'Sin información de catálogo'],
    ['Nivel', $escuela['N_NIVEL'] ?? ''],
    ['Turno', cctPdfTurno($escuela['TURNO'] ?? '')],
    ['Municipio', $escuela['N_MUNICIPIO'] ?? ''],
    ['Localidad', $escuela['N_LOCALIDAD'] ?? ''],
    ['Dirección', implode(', ', $direccion)],
    ['Entre calles', implode(' ', $entreCalles)],
    ['Total folios', count($solicitudes)],
], 3);

$pdf->Section('Folios relacionados');
if (empty($solicitudes)) {
    $pdf->Block('Resultado', 'No se encontraron solicitudes registradas con este CCT.');
}

foreach ($solicitudes as $solicitud) {
    $solicitante = trim(implode(' ', array_filter([
        $solicitud['solicitante'] ?? '',
        $solicitud['ap1'] ?? '',
        $solicitud['ap2'] ?? ''
    ])));
    $asignados = array_filter([
        $solicitud['asignado_nombre'] ?? '',
        $solicitud['asignado2_nombre'] ?? '',
        $solicitud['asignado3_nombre'] ?? '',
        $solicitud['asignado4_nombre'] ?? '',
        $solicitud['asignado5_nombre'] ?? ''
    ]);

    $pdf->CheckSpace(30);
    $pdf->SetFillColor(246, 237, 240);
    $pdf->SetTextColor(95, 24, 48);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(273, 6, $pdf->e('Folio ' . $solicitud['folio'] . ' | ' . $solicitud['estado']), 0, 1, 'L', true);
    $pdf->Ln(1);
    $pdf->Grid([
        ['Solicitante', $solicitante],
        ['Fecha petición', cctPdfFecha($solicitud['fecha_peticion'])],
        ['Estatus', $solicitud['estado']],
        ['Prioridad', $solicitud['prioridad']],
        ['Categoría', $solicitud['categoria']],
        ['Procedencia', $solicitud['tipo_procedencia']],
        ['Capturó', $solicitud['usuario_nombre']],
        ['Asignado a', implode(', ', $asignados)],
    ], 4);
    $pdf->Block('Compromiso', $solicitud['compromiso']);
    $pdf->Block('Descripción', $solicitud['descripcion']);
    if (!empty($solicitud['indicaciones_secretario'])) {
        $pdf->Block('Indicaciones del Secretario', $solicitud['indicaciones_secretario']);
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(95, 24, 48);
    $pdf->Cell(273, 5, $pdf->e('Tareas'), 0, 1, 'L');
    if (empty($solicitud['tareas'])) {
        $pdf->Item('Sin tareas registradas.', '');
    } else {
        foreach ($solicitud['tareas'] as $tarea) {
            $meta = 'Inicio: ' . cctPdfFecha($tarea['fecha']);
            if (!empty($tarea['fecha_cierre'])) {
                $meta .= ' | Cierre: ' . cctPdfFecha($tarea['fecha_cierre']);
            }
            $pdf->Item(($tarea['usuario_nombre'] ?: 'Sin usuario') . ' | ' . cctPdfEstatusTarea($tarea['fk_estatus']), $tarea['tarea'], $meta);
        }
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(95, 24, 48);
    $pdf->Cell(273, 5, $pdf->e('Comentarios de seguimiento'), 0, 1, 'L');
    if (empty($solicitud['comentarios'])) {
        $pdf->Item('Sin comentarios registrados.', '');
    } else {
        foreach ($solicitud['comentarios'] as $comentario) {
            $meta = 'Fecha: ' . cctPdfFecha($comentario['fecha_comentario']);
            if (!empty($comentario['archivo'])) {
                $meta .= ' | Archivo: ' . $comentario['archivo'];
            }
            $pdf->Item($comentario['usuario_nombre'] ?: 'Sin usuario', $comentario['comentario'], $meta);
        }
    }
    $pdf->Ln(3);
}

$filename = 'Seguimiento_CCT_' . preg_replace('/[^A-Z0-9_-]/i', '_', $cct) . '.pdf';
$pdf->Output($filename, 'D');
