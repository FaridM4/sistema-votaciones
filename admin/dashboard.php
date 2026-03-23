<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
requireLogin();

$pdo = getDBConnection();

$stmtElecciones = $pdo->query("SELECT * FROM elecciones ORDER BY created_at DESC");
$elecciones = $stmtElecciones->fetchAll();

$stmtCandidatos = $pdo->query("SELECT COUNT(*) as total FROM candidatos");
$totalCandidatos = $stmtCandidatos->fetch()['total'];

$stmtVotos = $pdo->query("SELECT COUNT(*) as total FROM votos");
$totalVotos = $stmtVotos->fetch()['total'];

$stmtActivas = $pdo->query("SELECT COUNT(*) as total FROM elecciones WHERE estado = 'activa'");
$eleccionesActivas = $stmtActivas->fetch()['total'];

$stmtRecientes = $pdo->query("SELECT la.*, a.nombre as admin_nombre FROM logs_auditoria la LEFT JOIN admins a ON la.admin_id = a.id ORDER BY la.created_at DESC LIMIT 10");
$logsRecientes = $stmtRecientes->fetchAll();

$stmtAdmins = $pdo->query("SELECT * FROM admins WHERE activo = TRUE");
$admins = $stmtAdmins->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Votaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-check me-2"></i>Sistema de Votaciones
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="elecciones.php"><i class="bi bi-calendar-event me-1"></i>Elecciones</a></li>
                    <li class="nav-item"><a class="nav-link" href="candidatos.php"><i class="bi bi-people me-1"></i>Candidatos</a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php"><i class="bi bi-bar-chart me-1"></i>Reportes</a></li>
                    <?php if ($_SESSION['admin_rol'] === 'superadmin'): ?>
                    <li class="nav-item"><a class="nav-link" href="admins.php"><i class="bi bi-person-badge me-1"></i>Admins</a></li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i><?= $_SESSION['admin_nombre'] ?>
                    </span>
                    <a href="logout.php" class="btn btn-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="bi bi-speedometer2 me-2"></i>Panel de Control</h2>
                <p class="text-muted">Resumen general del sistema</p>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Elecciones Activas</h6>
                                <h2 class="mb-0"><?= $eleccionesActivas ?></h2>
                            </div>
                            <i class="bi bi-calendar-check" style="font-size: 2.5rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Total Votos</h6>
                                <h2 class="mb-0"><?= $totalVotos ?></h2>
                            </div>
                            <i class="bi bi-check-circle" style="font-size: 2.5rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Candidatos</h6>
                                <h2 class="mb-0"><?= $totalCandidatos ?></h2>
                            </div>
                            <i class="bi bi-people-fill" style="font-size: 2.5rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Total Elecciones</h6>
                                <h2 class="mb-0"><?= count($elecciones) ?></h2>
                            </div>
                            <i class="bi bi-folder" style="font-size: 2.5rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Elecciones Recientes</h5>
                        <a href="elecciones.php" class="btn btn-sm btn-primary">Ver todas</a>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Período</th>
                                    <th>Estado</th>
                                    <th>Candidatos</th>
                                    <th>Votos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($elecciones, 0, 5) as $eleccion): ?>
                                <?php
                                    $stmtCand = $pdo->prepare("SELECT COUNT(*) as cant FROM candidatos WHERE eleccion_id = ?");
                                    $stmtCand->execute([$eleccion['id']]);
                                    $cantCand = $stmtCand->fetch()['cant'];
                                    
                                    $stmtVotos = $pdo->prepare("SELECT COUNT(*) as cant FROM votos WHERE eleccion_id = ?");
                                    $stmtVotos->execute([$eleccion['id']]);
                                    $cantVotos = $stmtVotos->fetch()['cant'];
                                    
                                    $estadoClass = [
                                        'borrador' => 'secondary',
                                        'activa' => 'success',
                                        'cerrada' => 'warning',
                                        'publicada' => 'info'
                                    ];
                                ?>
                                <tr>
                                    <td><?= $eleccion['nombre'] ?></td>
                                    <td><?= formatDate($eleccion['fecha_inicio']) ?> - <?= formatDate($eleccion['fecha_fin']) ?></td>
                                    <td><span class="badge bg-<?= $estadoClass[$eleccion['estado']] ?>"><?= ucfirst($eleccion['estado']) ?></span></td>
                                    <td><?= $cantCand ?></td>
                                    <td><?= $cantVotos ?></td>
                                    <td>
                                        <a href="elecciones.php?action=edit&id=<?= $eleccion['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <a href="candidatos.php?eleccion=<?= $eleccion['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-people"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($elecciones)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No hay elecciones creadas</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Actividad Reciente</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($logsRecientes as $log): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <small><?= $log['accion'] ?></small>
                                    <small class="text-muted"><?= formatDate($log['created_at']) ?></small>
                                </div>
                                <small class="text-muted"><?= $log['admin_nombre'] ?? 'Sistema' ?></small>
                            </li>
                            <?php endforeach; ?>
                            <?php if (empty($logsRecientes)): ?>
                            <li class="list-group-item text-center text-muted">Sin actividad reciente</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
