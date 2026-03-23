<?php
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../index.php');
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function logAuditoria($pdo, $adminId, $accion, $tabla = null, $registroId = null, $datosAnteriores = null, $datosNuevos = null) {
    $stmt = $pdo->prepare("INSERT INTO logs_auditoria (admin_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $adminId,
        $accion,
        $tabla,
        $registroId,
        $datosAnteriores ? json_encode($datosAnteriores) : null,
        $datosNuevos ? json_encode($datosNuevos) : null,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function getRealIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

function enviarEmail($para, $asunto, $mensajeHTML) {
    require_once __DIR__ . '/mail_config.php';
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
        'Reply-To: ' . SMTP_FROM,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($para, $asunto, $mensajeHTML, implode("\r\n", $headers));
}

function notificarNuevoVoto($pdo, $eleccionNombre, $candidatoNombre, $ipVotante) {
    require_once __DIR__ . '/mail_config.php';
    
    if (!NOTIFICAR_VOTOS) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM votos WHERE eleccion_id = (SELECT id FROM elecciones WHERE nombre = ?)");
    $stmt->execute([$eleccionNombre]);
    $totalVotos = $stmt->fetch()['total'];
    
    $asunto = "📊 Nuevo voto registrado - $eleccionNombre";
    
    $mensaje = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .body { padding: 30px; }
            .stat { display: inline-block; background: #f8f9fa; padding: 15px 25px; border-radius: 8px; margin: 10px 5px; text-align: center; }
            .stat-number { font-size: 24px; font-weight: bold; color: #667eea; }
            .stat-label { font-size: 12px; color: #666; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0;'><i class='bi bi-check-circle'></i> Nuevo Voto</h1>
            </div>
            <div class='body'>
                <p>Se ha registrado un nuevo voto en el sistema.</p>
                
                <h3>Detalles del voto:</h3>
                <ul>
                    <li><strong>Elección:</strong> $eleccionNombre</li>
                    <li><strong>Candidato:</strong> $candidatoNombre</li>
                    <li><strong>IP del votante:</strong> $ipVotante</li>
                    <li><strong>Fecha/Hora:</strong> " . date('d/m/Y H:i:s') . "</li>
                </ul>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <div class='stat'>
                        <div class='stat-number'>$totalVotos</div>
                        <div class='stat-label'>TOTAL VOTOS</div>
                    </div>
                </div>
                
                <p style='margin-top: 20px;'>
                    <a href='" . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) : '') . "/admin/dashboard.php' 
                       style='display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>
                        Ver Panel de Control
                    </a>
                </p>
            </div>
            <div class='footer'>
                <p>Sistema de Votaciones - Notificación automática</p>
            </div>
        </div>
    </body>
    </html>";
    
    return enviarEmail(ADMIN_EMAIL, $asunto, $mensaje);
}
