<?php
require_once 'includes/database.php';
require_once 'includes/functions.php';

$pdo = getDBConnection();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    
    if (empty($email) || empty($password) || empty($confirm)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        $stmtCheck = $pdo->prepare("SELECT id, email FROM admins WHERE TRIM(email) = TRIM(?)");
        $stmtCheck->execute([$email]);
        $admin = $stmtCheck->fetch();
        
        if ($admin) {
            $hash = hashPassword($password);
            $stmtUpdate = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            $stmtUpdate->execute([$hash, $admin['id']]);
            $success = 'Contraseña configurada correctamente. Ahora podés iniciar sesión.';
        } else {
            $error = 'No se encontró ningún admin con ese email';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Admin - Sistema de Votaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4">Configurar Contraseña Admin</h3>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                            <a href="admin/index.php" class="btn btn-primary w-100">Ir al Login</a>
                        <?php elseif ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <?php if (!$success): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email del Admin</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña</label>
                                <input type="password" name="password" class="form-control" minlength="8" required>
                                <small class="text-muted">Mínimo 8 caracteres</small>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirmar Contraseña</label>
                                <input type="password" name="confirm" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Guardar Contraseña</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
