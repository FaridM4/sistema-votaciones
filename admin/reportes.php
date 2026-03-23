<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
requireLogin();

$pdo = getDBConnection();

$stmtElecciones = $pdo->query("SELECT id, nombre FROM elecciones WHERE estado IN ('activa', 'cerrada', 'publicada') ORDER BY nombre");
$elecciones = $stmtElecciones->fetchAll();

$eleccionId = $_POST['eleccion_id'] ?? $_GET['eleccion'] ?? null;
$resultados = [];
$eleccionSeleccionada = null;

if ($eleccionId) {
    $stmt = $pdo->prepare("SELECT e.*, COUNT(DISTINCT c.id) as candidatos, COUNT(v.id) as votos FROM elecciones e LEFT JOIN candidatos c ON c.eleccion_id = e.id LEFT JOIN votos v ON v.eleccion_id = e.id WHERE e.id = ? GROUP BY e.id");
    $stmt->execute([$eleccionId]);
    $eleccionSeleccionada = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT c.id, c.nombre, c.descripcion, c.categoria, c.votos_count, c.orden,
               (SELECT COUNT(*) FROM votos WHERE candidato_id = c.id) as total_votos
        FROM candidatos c
        WHERE c.eleccion_id = ? AND c.activo = TRUE
        ORDER BY total_votos DESC, c.orden
    ");
    $stmt->execute([$eleccionId]);
    $resultados = $stmt->fetchAll();
}

if (isset($_GET['export']) && $_GET['export'] === 'csv' && $eleccionId) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=resultados_eleccion_' . $eleccionId . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Orden', 'Candidato', 'Categoría', 'Votos']);
    
    foreach ($resultados as $r) {
        fputcsv($output, [$r['orden'], $r['nombre'], $r['categoria'] ?: '-', $r['total_votos']]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .bar-container { height: 24px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .bar-fill { height: 100%; background: linear-gradient(90deg, #0d6efd, #0dcaf0); transition: width 0.5s ease; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check me-2"></i>Sistema de Votaciones</a>
            <?php include '../includes/navbar.php'; ?>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <h3><i class="bi bi-bar-chart me-2"></i>Reportes y Estadísticas</h3>
        
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0">Seleccionar Elección</h6></div>
                    <div class="card-body">
                        <form method="GET" class="mb-3">
                            <select name="eleccion" class="form-select mb-3" onchange="this.form.submit()">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($elecciones as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= $eleccionId == $e['id'] ? 'selected' : '' ?>><?= $e['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($eleccionSeleccionada): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Estado:</span>
                            <span class="badge bg-<?= $eleccionSeleccionada['estado'] === 'activa' ? 'success' : 'secondary' ?>"><?= ucfirst($eleccionSeleccionada['estado']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Período:</span>
                            <small><?= formatDate($eleccionSeleccionada['fecha_inicio']) ?> - <?= formatDate($eleccionSeleccionada['fecha_fin']) ?></small>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Candidatos:</span>
                            <strong><?= $eleccionSeleccionada['candidatos'] ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Votos:</span>
                            <strong><?= $eleccionSeleccionada['votos'] ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if ($eleccionId && $eleccionSeleccionada): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Resultados</h6>
                        <a href="?eleccion=<?= $eleccionId ?>&export=csv" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Exportar CSV</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($resultados)): ?>
                        <?php 
                        $maxVotos = max(array_column($resultados, 'total_votos')); 
                        $participacion = $eleccionSeleccionada['candidatos'] > 0 ? round(($eleccionSeleccionada['votos'] / $maxVotos) * 100, 1) : 0;
                        ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Posición</th>
                                    <th>Candidato</th>
                                    <th>Votos</th>
                                    <th>Porcentaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $i => $r): ?>
                                <?php $porcentaje = $eleccionSeleccionada['votos'] > 0 ? round(($r['total_votos'] / $eleccionSeleccionada['votos']) * 100, 1) : 0; ?>
                                <tr class="<?= $i === 0 && $r['total_votos'] > 0 ? 'table-warning' : '' ?>">
                                    <td><?= $i + 1 ?>°</td>
                                    <td>
                                        <strong><?= $r['nombre'] ?></strong>
                                        <?php if ($r['categoria']): ?>
                                        <br><small class="text-muted"><?= $r['categoria'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= $r['total_votos'] ?></strong></td>
                                    <td style="min-width: 200px;">
                                        <div class="bar-container">
                                            <div class="bar-fill" style="width: <?= $porcentaje ?>%"></div>
                                        </div>
                                        <small><?= $porcentaje ?>%</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">No hay candidatos registrados o aún no hay votos</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Resumen</h6></div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h4><?= count($resultados) ?></h4>
                                <small class="text-muted">Candidatos Activos</small>
                            </div>
                            <div class="col-md-4">
                                <h4><?= $eleccionSeleccionada['votos'] ?></h4>
                                <small class="text-muted">Total Votos</small>
                            </div>
                            <div class="col-md-4">
                                <h4><?= $eleccionSeleccionada['votos'] > 0 ? round(array_sum(array_column($resultados, 'total_votos')) / $eleccionSeleccionada['votos'], 1) : 0 ?></h4>
                                <small class="text-muted">Promedio por Candidato</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-bar-chart" style="font-size: 3rem;"></i>
                        <p class="mt-3">Selecciona una elección para ver los resultados</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
