<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? AND activo = TRUE");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            if ($admin['bloqueado_hasta'] && strtotime($admin['bloqueado_hasta']) > time()) {
                $error = 'Cuenta bloqueada temporalmente. Intenta más tarde.';
            } elseif (verifyPassword($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_nombre'] = $admin['nombre'];
                $_SESSION['admin_rol'] = $admin['rol'];
                
                $update = $pdo->prepare("UPDATE admins SET ultimo_login = NOW(), intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?");
                $update->execute([$admin['id']]);
                
                logAuditoria($pdo, $admin['id'], 'LOGIN_EXITOSO');
                redirect('dashboard.php');
            } else {
                $intentos = $admin['intentos_fallidos'] + 1;
                $bloqueado = $intentos >= 5 ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
                
                $update = $pdo->prepare("UPDATE admins SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?");
                $update->execute([$intentos, $bloqueado, $admin['id']]);
                
                logAuditoria($pdo, $admin['id'], 'LOGIN_FALLIDO', null, null, null, ['intentos' => $intentos]);
                $error = $intentos >= 5 ? 'Demasiados intentos. Bloqueado por 15 minutos.' : 'Credenciales incorrectas';
            }
        } else {
            $error = 'Credenciales incorrectas';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Votaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 400px; margin: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card card shadow-lg mt-5">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                    <h3 class="mt-2">Panel Admin</h3>
                    <p class="text-muted">Sistema de Votaciones</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logout'])): ?>
                    <div class="alert alert-success">Sesión cerrada correctamente</div>
                <?php endif; ?>
                
                <form method="POST" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
