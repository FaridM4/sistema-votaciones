<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
requireLogin();

$pdo = getDBConnection();

if ($_SESSION['admin_rol'] !== 'superadmin') {
    redirect('dashboard.php');
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize($_POST['nombre']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $rol = $_POST['rol'];
    
    if ($id) {
        if (!empty($password)) {
            $passwordHash = hashPassword($password);
            $stmt = $pdo->prepare("UPDATE admins SET nombre=?, email=?, password_hash=?, rol=? WHERE id=?");
            $stmt->execute([$nombre, $email, $passwordHash, $rol, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET nombre=?, email=?, rol=? WHERE id=?");
            $stmt->execute([$nombre, $email, $rol, $id]);
        }
        logAuditoria($pdo, $_SESSION['admin_id'], 'ACTUALIZAR_ADMIN', 'admins', $id);
        $success = 'Admin actualizado';
    } else {
        if (empty($password)) {
            $error = 'La contraseña es obligatoria';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'El email ya está registrado';
            } else {
                $stmt = $pdo->prepare("INSERT INTO admins (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, hashPassword($password), $rol]);
                logAuditoria($pdo, $_SESSION['admin_id'], 'CREAR_ADMIN', 'admins', $pdo->lastInsertId());
                $success = 'Admin creado';
            }
        }
    }
    $action = 'list';
}

if ($action === 'toggle' && $id && $id != $_SESSION['admin_id']) {
    $stmt = $pdo->prepare("UPDATE admins SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    logAuditoria($pdo, $_SESSION['admin_id'], 'TOGGLE_ADMIN', 'admins', $id);
    $success = 'Estado actualizado';
    $action = 'list';
}

if ($action === 'delete' && $id && $id != $_SESSION['admin_id']) {
    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ? AND rol != 'superadmin'");
    $stmt->execute([$id]);
    logAuditoria($pdo, $_SESSION['admin_id'], 'ELIMINAR_ADMIN', 'admins', $id);
    $success = 'Admin eliminado';
    $action = 'list';
}

if ($action === 'list') {
    $stmt = $pdo->query("SELECT id, nombre, email, rol, activo, ultimo_login FROM admins ORDER BY created_at DESC");
    $admins = $stmt->fetchAll();
}

$adminEdit = null;
if (($action === 'edit' || $action === 'new') && $id) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    $adminEdit = $stmt->fetch();
} elseif ($action === 'new') {
    $adminEdit = ['nombre' => '', 'email' => '', 'rol' => 'admin'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admins - Admin</title>
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
            <h3><i class="bi bi-person-badge me-2"></i>Administradores</h3>
            <a href="?action=new" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Nuevo Admin</a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Último Login</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $a): ?>
                        <tr>
                            <td><strong><?= $a['nombre'] ?></strong></td>
                            <td><?= $a['email'] ?></td>
                            <td><span class="badge bg-<?= $a['rol'] === 'superadmin' ? 'danger' : 'primary' ?>"><?= ucfirst($a['rol']) ?></span></td>
                            <td><?= $a['ultimo_login'] ? formatDate($a['ultimo_login']) : 'Nunca' ?></td>
                            <td><?= $a['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                            <td>
                                <a href="?action=edit&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <?php if ($a['id'] != $_SESSION['admin_id'] && $a['rol'] !== 'superadmin'): ?>
                                <a href="?action=toggle&id=<?= $a['id'] ?>" class="btn btn-sm btn-<?= $a['activo'] ? 'outline-warning' : 'outline-success' ?>"><i class="bi bi-<?= $a['activo'] ? 'eye-slash' : 'eye' ?>"></i></a>
                                <a href="?action=delete&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-<?= $id ? 'pencil' : 'plus-circle' ?> me-2"></i><?= $id ? 'Editar' : 'Nuevo' ?> Administrador</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=save<?= $id ? '&id=' . $id : '' ?>">
                            <div class="mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="nombre" class="form-control" value="<?= $adminEdit['nombre'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" value="<?= $adminEdit['email'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña <?= $id ? '(dejar vacío para no cambiar)' : '*' ?></label>
                                <input type="password" name="password" class="form-control" <?= $id ? '' : 'required' ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rol</label>
                                <select name="rol" class="form-select">
                                    <option value="admin" <?= ($adminEdit['rol'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                    <option value="superadmin" <?= ($adminEdit['rol'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Administrador</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Guardar</button>
                                <a href="admins.php" class="btn btn-secondary"><i class="bi bi-x-circle me-2"></i>Cancelar</a>
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
