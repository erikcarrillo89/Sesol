<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/navbar.php';

if (!$auth->isLoggedIn()) {
    header("Location: ../../login.php");
    exit();
}

$usuario_id = $_SESSION['user_id'];
$usuario_rol = $_SESSION['rol'];
$nombre_completo = $_SESSION['nombre_completo'];

$db = new Database();

$where = '';
$params = [];
$types = '';
if ($usuario_rol === 'usuario') {
    $where = "AND (asignado_id = ? OR asignado2_id = ? OR asignado3_id = ? OR asignado4_id = ? OR asignado5_id = ?)";
    $params = [$usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id];
    $types = 'iiiii';
}

if ($usuario_rol === 'especial') {
    $where = "AND (
            s.fk_categoria IN (
                SELECT fk_categoria FROM usuarios_categorias WHERE fk_usuario = ? 
            )
            OR s.id IN (
                SELECT sr.fk_solicitud FROM solicitudes_rel sr
                INNER JOIN usuarios_etiquetas ue ON sr.fk_etiqueta = ue.fk_etiqueta
                WHERE ue.fk_usuario = ?
            )
        )";
    $params = [$usuario_id, $usuario_id]; 
    $types = 'ii';
}

function getGroupedData($db, $campo, $where, $params, $types) {
    $query = "SELECT $campo, COUNT(*) AS total FROM solicitudes AS s INNER JOIN cat_categorias AS c ON s.fk_categoria = c.pk_categoria WHERE s.eliminado != 1 $where GROUP BY $campo";
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

function getTotalSolicitudes($db, $where, $params, $types) {
    $query = "SELECT COUNT(*) AS total FROM solicitudes AS s WHERE s.eliminado != 1 $where";
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return $row['total'];
}

$totalSolicitudes = getTotalSolicitudes($db, $where, $params, $types);

$prioridadData = [];
$prioridadLabels = [];
$totalPrioridad = 0;
$res = getGroupedData($db, 's.prioridad', $where, $params, $types);
while ($row = $res->fetch_assoc()) {
    $prioridadLabels[] = $row['prioridad'];
    $prioridadData[] = $row['total'];
    $totalPrioridad += $row['total'];
}

$estadoData = [];
$estadoLabels = [];
$totalEstado = 0;
$res = getGroupedData($db, 's.estado', $where, $params, $types);
while ($row = $res->fetch_assoc()) {
    $estadoLabels[] = $row['estado'];
    $estadoData[] = $row['total'];
    $totalEstado += $row['total'];
}

$categoriaData = [];
$categoriaLabels = [];
$totalCategoria = 0;
$res = getGroupedData($db, 'c.categoria', $where, $params, $types);
while ($row = $res->fetch_assoc()) {
    $categoriaLabels[] = $row['categoria'];
    $categoriaData[] = $row['total'];
    $totalCategoria += $row['total'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reportes del sistema SESOL</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    @media print {
      .no-print {
        display: none !important;
      }
      .print-page {
        page-break-after: always;
      }
    }
    .contenedor-central {
      max-width: 900px;
      margin: auto;
    }
  </style>
</head>
<body>
<div class="container mt-4 contenedor-central text-center">

  <div class="no-print mb-4 d-flex justify-content-between">
    <a class="btn btn-secondary" href="../../dashboard.php">🏠 Volver al Dashboard</a>
    <button class="btn btn-primary" onclick="window.print()">🖨 Imprimir Reporte</button>
  </div>

  <h2 class="mb-2">Reportes del sistema SESOL</h2>
  <p class="text-muted mb-1">Fecha de generación del reporte: <?= date('d/m/Y H:i') ?></p>
  <p class="text-muted mb-1">Usuario: <?= htmlspecialchars($nombre_completo ?? '') ?> (<?= $usuario_rol ?>)</p>
  <p class="text-muted mb-5">Total de solicitudes visibles: <?= $totalSolicitudes ?></p>

  <!-- Prioridad -->
  <div class="mb-5 print-page">
    <h4>Distribución por Prioridad</h4>
    <div class="row justify-content-center">
      <div class="col-md-6">
        <canvas id="prioridadChart"></canvas>
      </div>
    </div>
    <table class="table table-bordered table-sm table-striped mt-3 mx-auto" style="max-width: 500px;">
      <thead class="table-light"><tr><th>Prioridad</th><th>Cantidad</th><th>%</th></tr></thead>
      <tbody>
      <?php foreach ($prioridadLabels as $i => $label): ?>
        <tr>
          <td><?= htmlspecialchars($label ?? '') ?></td>
          <td><?= $prioridadData[$i] ?></td>
          <td><?= round(($prioridadData[$i] / $totalPrioridad) * 100, 1) ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Estado -->
  <div class="mb-5 print-page">
    <h4>Distribución por Estado</h4>
    <div class="row justify-content-center">
      <div class="col-md-6">
        <canvas id="estadoChart"></canvas>
      </div>
    </div>
    <table class="table table-bordered table-sm table-striped mt-3 mx-auto" style="max-width: 500px;">
      <thead class="table-light"><tr><th>Estado</th><th>Cantidad</th><th>%</th></tr></thead>
      <tbody>
      <?php foreach ($estadoLabels as $i => $label): ?>
        <tr>
          <td><?= htmlspecialchars($label ?? '') ?></td>
          <td><?= $estadoData[$i] ?></td>
          <td><?= round(($estadoData[$i] / $totalEstado) * 100, 1) ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Categoría -->
  <div class="mb-5 print-page">
    <h4>Distribución por Categoría</h4>
    <div class="row justify-content-center">
      <div class="col-md-6">
        <canvas id="categoriaChart"></canvas>
      </div>
    </div>
    <table class="table table-bordered table-sm table-striped mt-3 mx-auto" style="max-width: 500px;">
      <thead class="table-light"><tr><th>Categoría</th><th>Cantidad</th><th>%</th></tr></thead>
      <tbody>
      <?php foreach ($categoriaLabels as $i => $label): ?>
        <tr>
          <td><?= htmlspecialchars($label ?? '') ?></td>
          <td><?= $categoriaData[$i] ?></td>
          <td><?= round(($categoriaData[$i] / $totalCategoria) * 100, 1) ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>

const prioridadLabels = <?= json_encode($prioridadLabels) ?>;
const prioridadData = <?= json_encode($prioridadData) ?>;
const colorPrioridadMap = {
  'Baja': '#059de8',
  'Media': '#e89105',
  'Alta': '#e84105'
};
const prioridadColors = prioridadLabels.map(label => colorPrioridadMap[label] || '#CCCCCC');

const prioridadChart = new Chart(document.getElementById('prioridadChart'), {
  type: 'pie',
  data: {
    labels: prioridadLabels,
    datasets: [{
      data: prioridadData,
      backgroundColor: prioridadColors
    }]
  }
});

const estadoColorMap = {
  'En proceso': '#FFA500',   // Naranja
  'No iniciada': '#FF6347',  // Rojo
  'Pendiente': '#A9ADB0 ',    // Gris
  'Concluida': '#90EE90'     // Verde
};

const estadoLabels = <?= json_encode($estadoLabels) ?>;
const estadoData = <?= json_encode($estadoData) ?>;
const estadoColors = estadoLabels.map(label => estadoColorMap[label] || '#CCCCCC');

const estadoChart = new Chart(document.getElementById('estadoChart'), {
  type: 'pie',
  data: {
    labels: estadoLabels,
    datasets: [{
      data: estadoData,
      backgroundColor: estadoColors
    }]
  }
});


const categoriaLabels = <?= json_encode($categoriaLabels) ?>;
const categoriaData = <?= json_encode($categoriaData) ?>;

function generarColoresUnicos(n) {
  const colores = [];
  for (let i = 0; i < n; i++) {
    const hue = Math.floor(360 * i / n);
    colores.push(`hsl(${hue}, 70%, 60%)`);
  }
  return colores;
}

const categoriaColors = generarColoresUnicos(categoriaLabels.length);

const categoriaChart = new Chart(document.getElementById('categoriaChart'), {
  type: 'pie',
  data: {
    labels: categoriaLabels,
    datasets: [{
      data: categoriaData,
      backgroundColor: categoriaColors
    }]
  }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
</body>
</html>
