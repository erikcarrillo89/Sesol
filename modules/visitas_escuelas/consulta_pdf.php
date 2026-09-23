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

$db = new Database();
$filtroCct = isset($_GET['cct']) ? sanitizeInput($_GET['cct']) : '';
$filtroEscuela = isset($_GET['escuela']) ? sanitizeInput($_GET['escuela']) : '';
$filtroNivel = isset($_GET['nivel']) ? sanitizeInput($_GET['nivel']) : '';
$filtroMunicipio = isset($_GET['municipio']) ? sanitizeInput($_GET['municipio']) : '';
$filtroLocalidad = isset($_GET['localidad']) ? sanitizeInput($_GET['localidad']) : '';
$filtroEstatus = isset($_GET['estatus']) ? sanitizeInput($_GET['estatus']) : '';
$hayFiltros = $filtroCct !== '' || $filtroEscuela !== '' || $filtroNivel !== '' || $filtroMunicipio !== '' || $filtroLocalidad !== '' || $filtroEstatus !== '';

if (!$hayFiltros) {
    header("Location: consulta.php");
    exit();
}

function consultaPdfTexto($texto) {
    $texto = html_entity_decode((string)($texto ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $texto);
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    return trim($texto);
}

function consultaPdfFecha($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00') {
        return '';
    }
    return date('d/m/Y', strtotime($fecha));
}

function consultaPdfTurno($turno) {
    $turnos = [
        '100' => 'MATUTINO',
        '200' => 'VESPERTINO',
        '300' => 'NOCTURNA',
        '400' => 'DISCONTINUA'
    ];
    return $turnos[(string)$turno] ?? 'ND';
}

function consultaPdfEstatusTarea($estatus) {
    $estatuses = [
        0 => 'No iniciada',
        1 => 'En proceso',
        2 => 'Concluida',
        3 => 'Pendiente'
    ];
    return $estatuses[(int)$estatus] ?? 'ND';
}

class PDFConsultaSesol extends FPDF {
    public function e($text) {
        return mb_convert_encoding(consultaPdfTexto($text), 'ISO-8859-1', 'UTF-8');
    }

    public function Header() {
        $this->Image('../../assets/img/logo_segey.png', 12, 8, 58, 0, 'PNG');
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(95, 24, 48);
        $this->SetXY(76, 10);
        $this->Cell(198, 5, $this->e('SECRETARÍA DE EDUCACIÓN'), 0, 1, 'R');
        $this->SetX(76);
        $this->Cell(198, 5, $this->e('CONSULTA DE FOLIOS SESOL'), 0, 1, 'R');
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
        $text = consultaPdfTexto($text);
        if ($text === '') {
            $text = 'Sin información.';
        }
        $this->CheckSpace(13);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(95, 24, 48);
        $this->Cell(273, 4, $this->e($label), 0, 1, 'L');
        $this->SetFont('Arial', '', 7.2);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(273, 3.7, $this->e($text), 0, 'L');
        $this->Ln(1);
    }

    public function Item($title, $text, $meta = '') {
        $this->CheckSpace(12);
        $this->SetFont('Arial', 'B', 7.1);
        $this->SetTextColor(0, 0, 0);
        $this->MultiCell(273, 3.5, $this->e('- ' . $title), 0, 'L');
        if ($meta !== '') {
            $this->SetFont('Arial', '', 6.8);
            $this->SetTextColor(95, 95, 95);
            $this->MultiCell(273, 3.4, $this->e($meta), 0, 'L');
        }
        if (consultaPdfTexto($text) !== '') {
            $this->SetFont('Arial', '', 7);
            $this->SetTextColor(0, 0, 0);
            $this->MultiCell(273, 3.5, $this->e($text), 0, 'L');
        }
        $this->Ln(1);
    }
}

$sql = "SELECT s.id, s.folio, s.cct, s.NOMBRECT AS solicitud_escuela, s.N_MUNICIPIO AS solicitud_municipio,
        s.N_LOCALIDAD AS solicitud_localidad, s.solicitante, s.ap1, s.ap2, s.fecha_peticion,
        s.estado, s.prioridad, s.compromiso, s.descripcion, s.indicaciones_secretario,
        s.fecha_seguimiento, s.responsable_seguimiento, s.fecha_vencimiento, s.tipo_procedencia,
        cat.categoria, u.nombre_completo AS usuario_nombre,
        ua.nombre_completo AS asignado_nombre, ua2.nombre_completo AS asignado2_nombre,
        ua3.nombre_completo AS asignado3_nombre, ua4.nombre_completo AS asignado4_nombre,
        ua5.nombre_completo AS asignado5_nombre,
        c.NOMBRECT, c.N_NIVEL, c.TURNO, c.N_MUNICIPIO, c.N_LOCALIDAD,
        c.DOMICILIO, c.ENTRECALLE, c.YCALLE, c.NUMEXT, c.COLONIA, c.CODPOST
    FROM solicitudes s
    LEFT JOIN cct c ON c.CLAVECCT = s.cct
    LEFT JOIN cat_categorias cat ON cat.pk_categoria = s.fk_categoria
    LEFT JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN usuarios ua ON ua.id = s.asignado_id
    LEFT JOIN usuarios ua2 ON ua2.id = s.asignado2_id
    LEFT JOIN usuarios ua3 ON ua3.id = s.asignado3_id
    LEFT JOIN usuarios ua4 ON ua4.id = s.asignado4_id
    LEFT JOIN usuarios ua5 ON ua5.id = s.asignado5_id
    WHERE (s.eliminado IS NULL OR s.eliminado = 0)
        AND s.cct IS NOT NULL
        AND TRIM(s.cct) <> ''";
$params = [];
$types = '';

if ($filtroCct !== '') {
    $sql .= " AND s.cct LIKE ?";
    $params[] = '%' . strtoupper($filtroCct) . '%';
    $types .= 's';
}
if ($filtroEscuela !== '') {
    $sql .= " AND (c.NOMBRECT LIKE ? OR s.NOMBRECT LIKE ?)";
    $term = '%' . $filtroEscuela . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}
if ($filtroNivel !== '') {
    $sql .= " AND c.N_NIVEL LIKE ?";
    $params[] = '%' . $filtroNivel . '%';
    $types .= 's';
}
if ($filtroMunicipio !== '') {
    $sql .= " AND (c.N_MUNICIPIO LIKE ? OR s.N_MUNICIPIO LIKE ?)";
    $term = '%' . $filtroMunicipio . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}
if ($filtroLocalidad !== '') {
    $sql .= " AND (c.N_LOCALIDAD LIKE ? OR s.N_LOCALIDAD LIKE ?)";
    $term = '%' . $filtroLocalidad . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}
if ($filtroEstatus !== '') {
    $sql .= " AND s.estado = ?";
    $params[] = $filtroEstatus;
    $types .= 's';
}

$sql .= " ORDER BY s.fecha_peticion DESC, s.id DESC";
$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$solicitudes = [];
while ($row = $result->fetch_assoc()) {
    $row['tareas'] = [];
    $row['comentarios'] = [];
    $solicitudes[(int)$row['id']] = $row;
}
$stmt->close();

if (!empty($solicitudes)) {
    $ids = implode(',', array_map('intval', array_keys($solicitudes)));
    $tareas = $db->query("SELECT st.fk_solicitud, st.tarea, st.fecha, st.fecha_cierre, st.fk_estatus,
            u.nombre_completo AS usuario_nombre
        FROM solicitudes_tareas st
        LEFT JOIN usuarios u ON u.id = st.fk_usuario
        WHERE st.fk_solicitud IN ($ids)
        ORDER BY st.fecha DESC, st.pk_tarea DESC");
    while ($tarea = $tareas->fetch_assoc()) {
        $solicitudes[(int)$tarea['fk_solicitud']]['tareas'][] = $tarea;
    }

    $comentarios = $db->query("SELECT cs.solicitud_id, cs.fecha_comentario, cs.comentario, cs.archivo,
            u.nombre_completo AS usuario_nombre
        FROM comentarios_seguimiento cs
        LEFT JOIN usuarios u ON u.id = cs.usuario_id
        WHERE cs.solicitud_id IN ($ids)
        ORDER BY cs.fecha_comentario DESC, cs.id DESC");
    while ($comentario = $comentarios->fetch_assoc()) {
        $solicitudes[(int)$comentario['solicitud_id']]['comentarios'][] = $comentario;
    }
}

$pdf = new PDFConsultaSesol('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(12, 28, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(95, 24, 48);
$pdf->Cell(273, 7, $pdf->e('Folios SESOL con informe de seguimiento'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(273, 4, $pdf->e('Generado: ' . date('d/m/Y H:i') . ' | Total folios: ' . count($solicitudes)), 0, 1, 'L');
$pdf->Ln(3);

$pdf->Section('Filtros aplicados');
$pdf->Grid([
    ['CCT', $filtroCct ?: 'Todos'],
    ['Escuela', $filtroEscuela ?: 'Todas'],
    ['Nivel', $filtroNivel ?: 'Todos'],
    ['Municipio', $filtroMunicipio ?: 'Todos'],
    ['Localidad', $filtroLocalidad ?: 'Todas'],
    ['Estatus', $filtroEstatus ?: 'Todos'],
], 3);

if (empty($solicitudes)) {
    $pdf->Section('Resultado');
    $pdf->Block('Consulta', 'No hay solicitudes con los filtros seleccionados.');
}

foreach ($solicitudes as $solicitud) {
    $nombreEscuela = $solicitud['NOMBRECT'] ?: $solicitud['solicitud_escuela'];
    $municipio = $solicitud['N_MUNICIPIO'] ?: $solicitud['solicitud_municipio'];
    $localidad = $solicitud['N_LOCALIDAD'] ?: $solicitud['solicitud_localidad'];
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
    $direccion = array_filter([
        trim($solicitud['DOMICILIO'] ?? ''),
        !empty($solicitud['NUMEXT']) ? 'NUM. EXT. ' . trim($solicitud['NUMEXT']) : '',
        !empty($solicitud['COLONIA']) ? 'COL. ' . trim($solicitud['COLONIA']) : '',
        !empty($solicitud['CODPOST']) ? 'C.P. ' . trim($solicitud['CODPOST']) : ''
    ]);

    $pdf->CheckSpace(30);
    $pdf->SetFillColor(246, 237, 240);
    $pdf->SetTextColor(95, 24, 48);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(273, 6, $pdf->e('Folio ' . $solicitud['folio'] . ' | ' . $solicitud['estado']), 0, 1, 'L', true);
    $pdf->Ln(1);
    $pdf->Grid([
        ['CCT', $solicitud['cct']],
        ['Escuela', $nombreEscuela],
        ['Nivel', $solicitud['N_NIVEL']],
        ['Turno', consultaPdfTurno($solicitud['TURNO'] ?? '')],
        ['Municipio', $municipio],
        ['Localidad', $localidad],
        ['Dirección', implode(', ', $direccion)],
        ['Categoría', $solicitud['categoria']],
        ['Solicitante', $solicitante],
        ['Fecha petición', consultaPdfFecha($solicitud['fecha_peticion'])],
        ['Prioridad', $solicitud['prioridad']],
        ['Procedencia', $solicitud['tipo_procedencia']],
        ['Capturó', $solicitud['usuario_nombre']],
        ['Asignado a', implode(', ', $asignados)],
        ['Fecha seguimiento', consultaPdfFecha($solicitud['fecha_seguimiento'])],
        ['Fecha vencimiento', consultaPdfFecha($solicitud['fecha_vencimiento'])],
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
            $meta = 'Inicio: ' . consultaPdfFecha($tarea['fecha']);
            if (!empty($tarea['fecha_cierre'])) {
                $meta .= ' | Cierre: ' . consultaPdfFecha($tarea['fecha_cierre']);
            }
            $pdf->Item(($tarea['usuario_nombre'] ?: 'Sin usuario') . ' | ' . consultaPdfEstatusTarea($tarea['fk_estatus']), $tarea['tarea'], $meta);
        }
    }

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(95, 24, 48);
    $pdf->Cell(273, 5, $pdf->e('Comentarios de seguimiento'), 0, 1, 'L');
    if (empty($solicitud['comentarios'])) {
        $pdf->Item('Sin comentarios registrados.', '');
    } else {
        foreach ($solicitud['comentarios'] as $comentario) {
            $meta = 'Fecha: ' . consultaPdfFecha($comentario['fecha_comentario']);
            if (!empty($comentario['archivo'])) {
                $meta .= ' | Archivo: ' . $comentario['archivo'];
            }
            $pdf->Item($comentario['usuario_nombre'] ?: 'Sin usuario', $comentario['comentario'], $meta);
        }
    }
    $pdf->Ln(3);
}

$filename = 'Consulta_Folios_SESOL_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'D');
