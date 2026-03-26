<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
requireLogin();

$pdo = getDBConnection();
$eleccionId = $_GET['eleccion'] ?? null;
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// ─── Control de roles ────────────────────────────────────────────────────────
// Solo admin y superadmin pueden modificar candidatos
$puedeEditar = in_array($_SESSION['admin_rol'], ['admin', 'superadmin']);

// Si alguien sin permiso intenta una acción de escritura, redirigir
if (!$puedeEditar && in_array($action, ['save', 'delete', 'toggle', 'new', 'edit'])) {
    $error  = 'No tienes permisos para realizar esta acción.';
    $action = 'list';
}
// ─────────────────────────────────────────────────────────────────────────────

$stmtElecciones = $pdo->query("SELECT id, nombre FROM elecciones ORDER BY nombre");
$elecciones = $stmtElecciones->fetchAll();

if (!$eleccionId && $action === 'list' && !empty($elecciones)) {
    $eleccionId = $elecciones[0]['id'];
}

$eleccionActual = null;
if ($eleccionId) {
    $stmt = $pdo->prepare("SELECT * FROM elecciones WHERE id = ?");
    $stmt->execute([$eleccionId]);
    $eleccionActual = $stmt->fetch();
}

// ─── Guardar (crear o editar) ─────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST' && $puedeEditar) {
    $nombre      = sanitize($_POST['nombre']);
    $descripcion = sanitize($_POST['descripcion']);
    $propuesta   = sanitize($_POST['propuesta']);
    $categoria   = sanitize($_POST['categoria']);
    $orden       = intval($_POST['orden']);
    $eleccionId  = intval($_POST['eleccion_id']);

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM candidatos WHERE id = ?");
        $stmt->execute([$id]);
        $candAnterior = $stmt->fetch();

        $stmt = $pdo->prepare("UPDATE candidatos SET nombre=?, descripcion=?, propuesta=?, categoria=?, orden=? WHERE id=?");
        $stmt->execute([$nombre, $descripcion, $propuesta, $categoria, $orden, $id]);

        logAuditoria($pdo, $_SESSION['admin_id'], 'ACTUALIZAR_CANDIDATO', 'candidatos', $id, $candAnterior, $_POST);
        $success = 'Candidato actualizado correctamente';
    } else {
        $stmt = $pdo->prepare("INSERT INTO candidatos (eleccion_id, nombre, descripcion, propuesta, categoria, orden) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$eleccionId, $nombre, $descripcion, $propuesta, $categoria, $orden]);
        $newId = $pdo->lastInsertId();

        logAuditoria($pdo, $_SESSION['admin_id'], 'CREAR_CANDIDATO', 'candidatos', $newId, null, $_POST);
        $success = 'Candidato creado correctamente';
    }
    $action = 'list';
}

// ─── Eliminar ────────────────────────────────────────────────────────────────
if ($action === 'delete' && $id && $puedeEditar) {
    $stmt = $pdo->prepare("SELECT * FROM candidatos WHERE id = ?");
    $stmt->execute([$id]);
    $candidato = $stmt->fetch();

    if ($candidato) {
        $stmt = $pdo->prepare("DELETE FROM candidatos WHERE id = ?");
        $stmt->execute([$id]);
        logAuditoria($pdo, $_SESSION['admin_id'], 'ELIMINAR_CANDIDATO', 'candidatos', $id, $candidato);
        $success = 'Candidato eliminado correctamente';
    }
    $action = 'list';
}

// ─── Toggle activo/inactivo ───────────────────────────────────────────────────
if ($action === 'toggle' && $id && $puedeEditar) {
    $stmt = $pdo->prepare("UPDATE candidatos SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    logAuditoria($pdo, $_SESSION['admin_id'], 'TOGGLE_CANDIDATO', 'candidatos', $id);
    $success = 'Estado actualizado';
    $action  = 'list';
}

// ─── Listado ──────────────────────────────────────────────────────────────────
$candidatos = [];
if ($eleccionId) {
    $stmt = $pdo->prepare("SELECT * FROM candidatos WHERE eleccion_id = ? ORDER BY orden, nombre");
    $stmt->execute([$eleccionId]);
    $candidatos = $stmt->fetchAll();
}

// ─── Formulario edición ───────────────────────────────────────────────────────
$candidatoEdit = null;
if (($action === 'edit' || $action === 'new') && $id) {
    $stmt = $pdo->prepare("SELECT * FROM candidatos WHERE id = ?");
    $stmt->execute([$id]);
    $candidatoEdit = $stmt->fetch();
} elseif ($action === 'new') {
    $candidatoEdit = ['nombre' => '', 'descripcion' => '', 'propuesta' => '', 'categoria' => '', 'orden' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatos - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-check me-2"></i>Sistema de Votaciones
            </a>
            <?php include '../includes/navbar.php'; ?>
        </div>
    </nav>

    <div class="container-fluid mt-4">

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= $success ?></div>
        <?php endif; ?>

        <!-- Aviso de solo lectura para usuarios sin permiso -->
        <?php if (!$puedeEditar): ?>
            <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-eye me-2 fs-5"></i>
                <div>
                    Estás en <strong>modo lectura</strong>. Solo los administradores pueden agregar, editar o eliminar candidatos.
                </div>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="bi bi-people me-2"></i>Candidatos</h3>

            <!-- Botón "Nuevo Candidato" solo para admin/superadmin -->
            <?php if ($puedeEditar && $eleccionActual && $eleccionActual['estado'] !== 'publicada'): ?>
                <a href="?action=new&eleccion=<?= $eleccionId ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Candidato
                </a>
            <?php elseif ($puedeEditar && $eleccionActual && $eleccionActual['estado'] === 'publicada'): ?>
                <span class="btn btn-secondary disabled">
                    <i class="bi bi-lock me-2"></i>Elección publicada
                </span>
            <?php endif; ?>
        </div>

        <div class="row">

            <!-- ── Sidebar: lista de elecciones ────────────────────────── -->
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Seleccionar Elección</h6></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($elecciones as $e): ?>
                            <a href="?eleccion=<?= $e['id'] ?>"
                               class="list-group-item list-group-item-action <?= $eleccionId == $e['id'] ? 'active' : '' ?>">
                                <?= $e['nombre'] ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($elecciones)): ?>
                            <div class="list-group-item text-muted">No hay elecciones</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Información del rol actual -->
                <div class="card">
                    <div class="card-body py-2 px-3">
                        <small class="text-muted d-block mb-1">Tu rol actual:</small>
                        <?php if ($_SESSION['admin_rol'] === 'superadmin'): ?>
                            <span class="badge bg-danger"><i class="bi bi-shield-fill-check me-1"></i>Super Administrador</span>
                        <?php elseif ($_SESSION['admin_rol'] === 'admin'): ?>
                            <span class="badge bg-primary"><i class="bi bi-person-badge me-1"></i>Administrador</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-eye me-1"></i><?= ucfirst($_SESSION['admin_rol']) ?></span>
                        <?php endif; ?>
                        <small class="d-block mt-1 text-muted">
                            <?= $puedeEditar ? 'Puedes gestionar candidatos.' : 'Solo puedes ver candidatos.' ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- ── Contenido principal ──────────────────────────────────── -->
            <div class="col-md-9">

                <?php if ($eleccionActual): ?>
                    <div class="card mb-3">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= $eleccionActual['nombre'] ?></strong>
                                    <span class="badge bg-<?= $eleccionActual['estado'] === 'activa' ? 'success' : 'secondary' ?> ms-2">
                                        <?= ucfirst($eleccionActual['estado']) ?>
                                    </span>
                                </div>
                                <span class="badge bg-primary"><?= count($candidatos) ?> candidatos</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                <!-- ── Tabla de candidatos ─────────────────────────────── -->
                <div class="card">
                    <div class="card-body">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Descripción</th>
                                    <th>Votos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidatos as $c): ?>
                                <tr class="<?= $c['activo'] ? '' : 'table-secondary' ?>">
                                    <td><?= $c['orden'] ?></td>
                                    <td><strong><?= $c['nombre'] ?></strong></td>
                                    <td><?= $c['categoria'] ?: '-' ?></td>
                                    <td><?= substr($c['descripcion'], 0, 50) ?>...</td>
                                    <td><span class="badge bg-info"><?= $c['votos_count'] ?></span></td>
                                    <td>
                                        <?= $c['activo']
                                            ? '<span class="badge bg-success">Activo</span>'
                                            : '<span class="badge bg-secondary">Inactivo</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($puedeEditar && $eleccionActual['estado'] !== 'publicada'): ?>
                                            <!-- Admin/Superadmin: acciones completas -->
                                            <a href="?action=edit&id=<?= $c['id'] ?>&eleccion=<?= $eleccionId ?>"
                                               class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?action=toggle&id=<?= $c['id'] ?>&eleccion=<?= $eleccionId ?>"
                                               class="btn btn-sm btn-<?= $c['activo'] ? 'outline-warning' : 'outline-success' ?>"
                                               title="<?= $c['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                <i class="bi bi-<?= $c['activo'] ? 'eye-slash' : 'eye' ?>"></i>
                                            </a>
                                            <a href="?action=delete&id=<?= $c['id'] ?>&eleccion=<?= $eleccionId ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('¿Eliminar a <?= $c['nombre'] ?>?')"
                                               title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php elseif ($puedeEditar && $eleccionActual['estado'] === 'publicada'): ?>
                                            <span class="text-muted"><i class="bi bi-lock"></i> Publicada</span>
                                        <?php else: ?>
                                            <!-- Solo lectura -->
                                            <span class="text-muted"><i class="bi bi-eye"></i> Solo lectura</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($candidatos)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <?php if ($puedeEditar): ?>
                                            No hay candidatos.
                                            <a href="?action=new&eleccion=<?= $eleccionId ?>">Agregar el primero</a>
                                        <?php else: ?>
                                            No hay candidatos registrados en esta elección.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php elseif ($puedeEditar): ?>
                <!-- ── Formulario crear/editar (solo admin/superadmin) ──── -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-<?= $id ? 'pencil' : 'plus-circle' ?> me-2"></i>
                            <?= $id ? 'Editar' : 'Nuevo' ?> Candidato
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=save<?= $id ? '&id=' . $id : '' ?>&eleccion=<?= $eleccionId ?>">
                            <input type="hidden" name="eleccion_id" value="<?= $eleccionId ?>">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" name="nombre" class="form-control"
                                           value="<?= $candidatoEdit['nombre'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Orden</label>
                                    <input type="number" name="orden" class="form-control"
                                           value="<?= $candidatoEdit['orden'] ?? 0 ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Categoría</label>
                                <input type="text" name="categoria" class="form-control"
                                       value="<?= $candidatoEdit['categoria'] ?? '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="2"><?= $candidatoEdit['descripcion'] ?? '' ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Propuesta</label>
                                <textarea name="propuesta" class="form-control" rows="4"><?= $candidatoEdit['propuesta'] ?? '' ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Guardar
                                </button>
                                <a href="?eleccion=<?= $eleccionId ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /col-md-9 -->
        </div><!-- /row -->
    </div><!-- /container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
