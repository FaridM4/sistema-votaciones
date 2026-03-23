<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
requireLogin();

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize($_POST['nombre']);
    $descripcion = sanitize($_POST['descripcion']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $reglas = sanitize($_POST['reglas']);
    $estado = $_POST['estado'];
    
    if (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
        $error = 'La fecha de fin debe ser posterior a la fecha de inicio';
    } else {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM elecciones WHERE id = ?");
            $stmt->execute([$id]);
            $eleccionAnterior = $stmt->fetch();
            
            $stmt = $pdo->prepare("UPDATE elecciones SET nombre=?, descripcion=?, fecha_inicio=?, fecha_fin=?, reglas=?, estado=? WHERE id=?");
            $stmt->execute([$nombre, $descripcion, $fecha_inicio, $fecha_fin, $reglas, $estado, $id]);
            
            logAuditoria($pdo, $_SESSION['admin_id'], 'ACTUALIZAR_ELECCION', 'elecciones', $id, $eleccionAnterior, $_POST);
            $success = 'Elección actualizada correctamente';
        } else {
            $stmt = $pdo->prepare("INSERT INTO elecciones (nombre, descripcion, fecha_inicio, fecha_fin, reglas, estado, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $fecha_inicio, $fecha_fin, $reglas, $estado, $_SESSION['admin_id']]);
            $newId = $pdo->lastInsertId();
            
            logAuditoria($pdo, $_SESSION['admin_id'], 'CREAR_ELECCION', 'elecciones', $newId, null, $_POST);
            $success = 'Elección creada correctamente';
        }
        $action = 'list';
    }
}

if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM elecciones WHERE id = ?");
    $stmt->execute([$id]);
    $eleccion = $stmt->fetch();
    
    if ($eleccion) {
        $stmt = $pdo->prepare("DELETE FROM elecciones WHERE id = ?");
        $stmt->execute([$id]);
        logAuditoria($pdo, $_SESSION['admin_id'], 'ELIMINAR_ELECCION', 'elecciones', $id, $eleccion);
        $success = 'Elección eliminada correctamente';
    }
    $action = 'list';
}

if ($action === 'toggle' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM elecciones WHERE id = ?");
    $stmt->execute([$id]);
    $eleccion = $stmt->fetch();
    
    $nuevoEstado = $eleccion['estado'] === 'activa' ? 'cerrada' : 'activa';
    $stmt = $pdo->prepare("UPDATE elecciones SET estado = ? WHERE id = ?");
    $stmt->execute([$nuevoEstado, $id]);
    
    logAuditoria($pdo, $_SESSION['admin_id'], $nuevoEstado === 'activa' ? 'ACTIVAR_VOTACION' : 'CERRAR_VOTACION', 'elecciones', $id, $eleccion);
    $success = $nuevoEstado === 'activa' ? 'Votación activada' : 'Votación cerrada';
    $action = 'list';
}

$eleccionEdit = null;
if (($action === 'edit' || $action === 'new') && $id) {
    $stmt = $pdo->prepare("SELECT * FROM elecciones WHERE id = ?");
    $stmt->execute([$id]);
    $eleccionEdit = $stmt->fetch();
} elseif ($action === 'new') {
    $eleccionEdit = ['nombre' => '', 'descripcion' => '', 'fecha_inicio' => date('Y-m-d\TH:i'), 'fecha_fin' => date('Y-m-d\TH:i', strtotime('+7 days')), 'reglas' => '', 'estado' => 'borrador'];
}

if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM elecciones ORDER BY created_at DESC");
    $elecciones = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'list' ? 'Elecciones' : ($id ? 'Editar' : 'Nueva') ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check me-2"></i>Sistema de Votaciones</a>
            <?php include '../includes/navbar.php'; ?>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <?php if ($action === 'list'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-calendar-event me-2"></i>Elecciones</h3>
            <a href="?action=new" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Nueva Elección</a>
        </div>

        <div class="card">
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
                        <?php foreach ($elecciones as $e): ?>
                        <?php
                            $stmtCand = $pdo->prepare("SELECT COUNT(*) FROM candidatos WHERE eleccion_id = ?");
                            $stmtCand->execute([$e['id']]);
                            $cantCand = $stmtCand->fetchColumn();
                            
                            $stmtVotos = $pdo->prepare("SELECT COUNT(*) FROM votos WHERE eleccion_id = ?");
                            $stmtVotos->execute([$e['id']]);
                            $cantVotos = $stmtVotos->fetchColumn();
                            
                            $estadoClass = ['borrador' => 'secondary', 'activa' => 'success', 'cerrada' => 'warning', 'publicada' => 'info'];
                        ?>
                        <tr>
                            <td><strong><?= $e['nombre'] ?></strong><br><small class="text-muted"><?= substr($e['descripcion'], 0, 50) ?>...</small></td>
                            <td><?= formatDate($e['fecha_inicio']) ?><br><small class="text-muted"><?= formatDate($e['fecha_fin']) ?></small></td>
                            <td><span class="badge bg-<?= $estadoClass[$e['estado']] ?>"><?= ucfirst($e['estado']) ?></span></td>
                            <td><?= $cantCand ?></td>
                            <td><?= $cantVotos ?></td>
                            <td>
                                <a href="?action=edit&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                <a href="candidatos.php?eleccion=<?= $e['id'] ?>" class="btn btn-sm btn-outline-success" title="Candidatos"><i class="bi bi-people"></i></a>
                                <a href="?action=toggle&id=<?= $e['id'] ?>" class="btn btn-sm btn-<?= $e['estado'] === 'activa' ? 'outline-warning' : 'outline-success' ?>" title="<?= $e['estado'] === 'activa' ? 'Cerrar' : 'Activar' ?>">
                                    <i class="bi bi-<?= $e['estado'] === 'activa' ? 'stop-circle' : 'play-circle' ?>"></i>
                                </a>
                                <a href="?action=delete&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar esta elección?')" title="Eliminar"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($elecciones)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay elecciones creadas. <a href="?action=new">Crear primera elección</a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-<?= $id ? 'pencil' : 'plus-circle' ?> me-2"></i><?= $id ? 'Editar' : 'Nueva' ?> Elección</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=save<?= $id ? '&id=' . $id : '' ?>">
                            <div class="mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="nombre" class="form-control" value="<?= $eleccionEdit['nombre'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="3"><?= $eleccionEdit['descripcion'] ?? '' ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fecha Inicio *</label>
                                    <input type="datetime-local" name="fecha_inicio" class="form-control" value="<?= $eleccionEdit['fecha_inicio'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fecha Fin *</label>
                                    <input type="datetime-local" name="fecha_fin" class="form-control" value="<?= $eleccionEdit['fecha_fin'] ?? '' ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reglas</label>
                                <textarea name="reglas" class="form-control" rows="4"><?= $eleccionEdit['reglas'] ?? '' ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="borrador" <?= ($eleccionEdit['estado'] ?? '') === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                                    <option value="activa" <?= ($eleccionEdit['estado'] ?? '') === 'activa' ? 'selected' : '' ?>>Activa</option>
                                    <option value="cerrada" <?= ($eleccionEdit['estado'] ?? '') === 'cerrada' ? 'selected' : '' ?>>Cerrada</option>
                                    <option value="publicada" <?= ($eleccionEdit['estado'] ?? '') === 'publicada' ? 'selected' : '' ?>>Publicada</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Guardar</button>
                                <a href="elecciones.php" class="btn btn-secondary"><i class="bi bi-x-circle me-2"></i>Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
