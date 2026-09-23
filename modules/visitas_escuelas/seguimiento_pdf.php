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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$db = new Database();
$id = (int)$_GET['id'];

function pdfFechaCorta($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00') {
        return '';
    }

    return date('d/m/Y', strtotime($fecha));
}

function pdfFechaHoraCorta($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00 00:00:00') {
        return '';
    }

    return date('d/m/Y H:i', strtotime($fecha));
}

function pdfTurnoTexto($turno) {
    $turnos = [
        '100' => 'MATUTINO',
        '200' => 'VESPERTINO',
        '300' => 'NOCTURNA',
        '400' => 'DISCONTINUA'
    ];

    return $turnos[(string)$turno] ?? 'ND';
}

function pdfEstatusTareaTexto($estatus) {
    $estatuses = [
        0 => 'No iniciada',
        1 => 'En proceso',
        2 => 'Concluida',
        3 => 'Pendiente'
    ];

    return $estatuses[(int)$estatus] ?? 'ND';
}

function pdfLimpiarTexto($texto) {
    $texto = html_entity_decode((string)($texto ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $texto);
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    return trim($texto);
}

class PDFSeguimientoVisita extends FPDF {
    public function encode($text) {
        return mb_convert_encoding(pdfLimpiarTexto($text), 'ISO-8859-1', 'UTF-8');
    }

    public function Header() {
        $this->Image('../../assets/img/logo_segey.png', 12, 8, 58, 0, 'PNG');
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(95, 24, 48);
        $this->SetXY(76, 10);
        $this->Cell(198, 5, $this->encode('SECRETARÍA DE EDUCACIÓN'), 0, 1, 'R');
        $this->SetX(76);
        $this->Cell(198, 5, $this->encode('VISITAS ESCUELAS - SEGUIMIENTO'), 0, 1, 'R');
        $this->SetDrawColor(141, 45, 68);
        $this->Line(12, 24, 285, 24);
        $this->Ln(12);
    }

    public function Footer() {
        $this->SetY(-13);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(273, 4, $this->encode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function CheckSpace($height) {
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation, $this->CurPageSize);
        }
    }

    public function SectionTitle($title) {
        $this->CheckSpace(10);
        $this->SetFillColor(141, 45, 68);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(273, 6, $this->encode($title), 0, 1, 'L', true);
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
    }

    public function InfoGrid($items, $columns = 4) {
        $usableWidth = 273;
        $cellWidth = $usableWidth / $columns;
        $rowHeight = 13;
        $index = 0;

        foreach ($items as $item) {
            if ($index % $columns === 0) {
                $this->CheckSpace($rowHeight);
            }

            $x = $this->GetX();
            $y = $this->GetY();
            $this->SetDrawColor(225, 218, 218);
            $this->Rect($x, $y, $cellWidth, $rowHeight);
            $this->SetXY($x + 2, $y + 2);
            $this->SetFont('Arial', 'B', 6.8);
            $this->SetTextColor(95, 24, 48);
            $this->Cell($cellWidth - 4, 3, $this->encode($item[0]), 0, 2, 'L');
            $this->SetFont('Arial', '', 7.5);
            $this->SetTextColor(0, 0, 0);
            $this->MultiCell($cellWidth - 4, 3.5, $this->encode($item[1]), 0, 'L');
            $this->SetXY($x + $cellWidth, $y);
            $index++;

            if ($index % $columns === 0) {
                $this->Ln($rowHeight);
            }
        }

        if ($index % $columns !== 0) {
            $this->Ln($rowHeight);
        }
        $this->Ln(2);
    }

    public function TextBlock($label, $text) {
        $text = pdfLimpiarTexto($text);
        if ($text === '') {
            $text = 'Sin información.';
        }

        $lines = max(1, ceil($this->GetStringWidth($this->encode($text)) / 255));
        $this->CheckSpace(8 + ($lines * 4));
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(95, 24, 48);
        $this->Cell(273, 4, $this->encode($label), 0, 1, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(273, 4, $this->encode($text), 0, 'L');
        $this->Ln(1);
    }

    public function ListItem($title, $text, $meta = '') {
        $this->CheckSpace(14);
        $this->SetFont('Arial', 'B', 7.3);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(273, 3.8, $this->encode('- ' . $title), 0, 'L');
        if ($meta !== '') {
            $this->SetFont('Arial', '', 6.8);
            $this->SetTextColor(95, 95, 95);
            $this->MultiCell(273, 3.5, $this->encode($meta), 0, 'L');
        }
        $this->SetFont('Arial', '', 7.2);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(273, 3.8, $this->encode($text), 0, 'L');
        $this->Ln(1);
    }
}

$stmt = $db->prepare("SELECT v.*, c.NOMBRECT, c.N_NIVEL, c.TURNO, c.N_MUNICIPIO, c.N_LOCALIDAD,
        c.DOMICILIO, c.ENTRECALLE, c.YCALLE, c.NUMEXT, c.COLONIA, c.CODPOST
    FROM visitasescuelas v
    LEFT JOIN cct c ON c.CLAVECCT = v.cct
    WHERE v.id = ? AND v.eliminado = 0
    LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$visita = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visita) {
    header("Location: index.php?error=" . urlencode("No se encontró la visita solicitada."));
    exit();
}

$cct = strtoupper(trim($visita['cct'] ?? ''));
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

    $tareas = $db->query("SELECT st.pk_tarea, st.fk_solicitud, st.tarea, st.fecha, st.fecha_cierre,
            st.fk_estatus, u.nombre_completo AS usuario_nombre
        FROM solicitudes_tareas st
        LEFT JOIN usuarios u ON u.id = st.fk_usuario
        WHERE st.fk_solicitud IN ($idsSeguros)
        ORDER BY st.fecha DESC, st.pk_tarea DESC");
    while ($tarea = $tareas->fetch_assoc()) {
        $solicitudId = (int)$tarea['fk_solicitud'];
        if (isset($solicitudes[$solicitudId])) {
            $solicitudes[$solicitudId]['tareas'][] = $tarea;
        }
    }

    $comentarios = $db->query("SELECT cs.id, cs.solicitud_id, cs.fecha_comentario, cs.comentario,
            cs.archivo, cs.fecha_creacion, u.nombre_completo AS usuario_nombre
        FROM comentarios_seguimiento cs
        LEFT JOIN usuarios u ON u.id = cs.usuario_id
        WHERE cs.solicitud_id IN ($idsSeguros)
        ORDER BY cs.fecha_comentario DESC, cs.id DESC");
    while ($comentario = $comentarios->fetch_assoc()) {
        $solicitudId = (int)$comentario['solicitud_id'];
        if (isset($solicitudes[$solicitudId])) {
            $solicitudes[$solicitudId]['comentarios'][] = $comentario;
        }
    }
}

$direccion = array_filter([
    trim($visita['DOMICILIO'] ?? ''),
    !empty($visita['NUMEXT']) ? 'NUM. EXT. ' . trim($visita['NUMEXT']) : '',
    !empty($visita['COLONIA']) ? 'COL. ' . trim($visita['COLONIA']) : '',
    !empty($visita['CODPOST']) ? 'C.P. ' . trim($visita['CODPOST']) : ''
]);
$entreCalles = array_filter([
    !empty($visita['ENTRECALLE']) ? 'ENTRE ' . trim($visita['ENTRECALLE']) : '',
    !empty($visita['YCALLE']) ? 'Y ' . trim($visita['YCALLE']) : ''
]);

$pdf = new PDFSeguimientoVisita('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(12, 28, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(95, 24, 48);
$pdf->Cell(273, 7, $pdf->encode('Seguimiento de visita escolar'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(273, 4, $pdf->encode('Generado: ' . date('d/m/Y H:i')), 0, 1, 'L');
$pdf->Ln(3);

$pdf->SectionTitle('Datos de la visita');
$pdf->InfoGrid([
    ['Fecha de visita', pdfFechaCorta($visita['fecha_visita'])],
    ['Estatus visita', $visita['estatus']],
    ['CCT', $visita['cct']],
    ['Turno', pdfTurnoTexto($visita['TURNO'] ?? '')],
    ['Escuela', $visita['NOMBRECT'] ?: 'Sin información de catálogo'],
    ['Nivel', $visita['N_NIVEL']],
    ['Municipio', $visita['N_MUNICIPIO']],
    ['Localidad', $visita['N_LOCALIDAD']],
    ['Dirección', implode(', ', $direccion)],
    ['Entre calles', implode(' ', $entreCalles)],
    ['Total folios relacionados', count($solicitudes)],
    ['CCT consultado', $cct],
], 4);
$pdf->TextBlock('Observaciones de la visita', $visita['observaciones']);

$pdf->SectionTitle('Folios relacionados al CCT');
if (empty($solicitudes)) {
    $pdf->TextBlock('Resultado', 'No se encontraron solicitudes registradas con este CCT.');
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
    $pdf->Cell(273, 6, $pdf->encode('Folio ' . $solicitud['folio'] . ' | ' . $solicitud['estado']), 0, 1, 'L', true);
    $pdf->Ln(1);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->InfoGrid([
        ['Solicitante', $solicitante],
        ['Fecha petición', pdfFechaCorta($solicitud['fecha_peticion'])],
        ['Estatus', $solicitud['estado']],
        ['Prioridad', $solicitud['prioridad']],
        ['Categoría', $solicitud['categoria']],
        ['Procedencia', $solicitud['tipo_procedencia']],
        ['Capturó', $solicitud['usuario_nombre']],
        ['Asignado a', implode(', ', $asignados)],
        ['Fecha seguimiento', pdfFechaCorta($solicitud['fecha_seguimiento'])],
        ['Responsable seguimiento', $solicitud['responsable_seguimiento']],
        ['Fecha vencimiento', pdfFechaCorta($solicitud['fecha_vencimiento'])],
        ['CCT solicitud', $solicitud['cct']],
    ], 4);

    $pdf->TextBlock('Compromiso', $solicitud['compromiso']);
    $pdf->TextBlock('Descripción', $solicitud['descripcion']);
    if (!empty($solicitud['indicaciones_secretario'])) {
        $pdf->TextBlock('Indicaciones del Secretario', $solicitud['indicaciones_secretario']);
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(95, 24, 48);
    $pdf->Cell(273, 5, $pdf->encode('Tareas'), 0, 1, 'L');
    if (empty($solicitud['tareas'])) {
        $pdf->ListItem('Sin tareas registradas.', '', '');
    } else {
        foreach ($solicitud['tareas'] as $tarea) {
            $meta = 'Inicio: ' . pdfFechaHoraCorta($tarea['fecha']);
            if (!empty($tarea['fecha_cierre'])) {
                $meta .= ' | Cierre: ' . pdfFechaHoraCorta($tarea['fecha_cierre']);
            }
            $pdf->ListItem(
                ($tarea['usuario_nombre'] ?: 'Sin usuario') . ' | ' . pdfEstatusTareaTexto($tarea['fk_estatus']),
                $tarea['tarea'],
                $meta
            );
        }
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(95, 24, 48);
    $pdf->Cell(273, 5, $pdf->encode('Comentarios de seguimiento'), 0, 1, 'L');
    if (empty($solicitud['comentarios'])) {
        $pdf->ListItem('Sin comentarios registrados.', '', '');
    } else {
        foreach ($solicitud['comentarios'] as $comentario) {
            $meta = 'Fecha: ' . pdfFechaCorta($comentario['fecha_comentario']);
            if (!empty($comentario['archivo'])) {
                $meta .= ' | Archivo: ' . $comentario['archivo'];
            }
            $pdf->ListItem(
                $comentario['usuario_nombre'] ?: 'Sin usuario',
                $comentario['comentario'],
                $meta
            );
        }
    }

    $pdf->Ln(3);
}

$filename = 'Seguimiento_Visita_' . preg_replace('/[^A-Z0-9_-]/i', '_', $cct) . '_' . $id . '.pdf';
$pdf->Output($filename, 'D');
