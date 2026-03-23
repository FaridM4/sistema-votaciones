<?php
session_start();
require_once 'includes/database.php';
require_once 'includes/functions.php';

$pdo = getDBConnection();

$stmt = $pdo->query("SELECT * FROM elecciones WHERE estado = 'activa' AND fecha_inicio <= NOW() AND fecha_fin >= NOW() LIMIT 1");
$eleccionActiva = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eleccionActiva) {
    $candidatoId = intval($_POST['candidato_id'] ?? 0);
    $hashVotante = hash('sha256', getRealIP() . $_SERVER['HTTP_USER_AGENT'] . date('Y-m-d'));
    
    if ($candidatoId) {
        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM votos WHERE eleccion_id = ? AND hash_votante = ?");
            $stmtCheck->execute([$eleccionActiva['id'], $hashVotante]);
            
            if ($stmtCheck->fetch()) {
                $mensaje = ['tipo' => 'warning', 'texto' => 'Ya has emitido tu voto en esta elección'];
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO votos (candidato_id, eleccion_id, ip_votante, hash_votante) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute([$candidatoId, $eleccionActiva['id'], getRealIP(), $hashVotante]);
                
                $stmtUpdate = $pdo->prepare("UPDATE candidatos SET votos_count = votos_count + 1 WHERE id = ?");
                $stmtUpdate->execute([$candidatoId]);
                
                $stmtCand = $pdo->prepare("SELECT nombre FROM candidatos WHERE id = ?");
                $stmtCand->execute([$candidatoId]);
                $nombreCandidato = $stmtCand->fetch()['nombre'];
                
                notificarNuevoVoto($pdo, $eleccionActiva['nombre'], $nombreCandidato, getRealIP());
                
                $mensaje = ['tipo' => 'success', 'texto' => '¡Tu voto ha sido registrado correctamente!'];
            }
        } catch (PDOException $e) {
            $mensaje = ['tipo' => 'danger', 'texto' => 'Error al registrar el voto'];
        }
    }
}

if ($eleccionActiva) {
    $stmtCandidatos = $pdo->prepare("SELECT * FROM candidatos WHERE eleccion_id = ? AND activo = TRUE ORDER BY orden, nombre");
    $stmtCandidatos->execute([$eleccionActiva['id']]);
    $candidatos = $stmtCandidatos->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Votaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .vote-card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .candidate-card { transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border: 2px solid transparent; }
        .candidate-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .candidate-card.selected { border-color: #667eea; background: #f8f9ff; }
        .candidate-card input[type="radio"] { display: none; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="bi bi-shield-check me-2"></i>Sistema de Votaciones</a>
        </div>
    </nav>

    <div class="container py-5">
        <?php if (isset($mensaje)): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?> alert-dismissible fade show" role="alert">
            <?= $mensaje['texto'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($eleccionActiva): ?>
        <div class="vote-card p-5">
            <div class="text-center mb-5">
                <h2><i class="bi bi-bullhorn me-2"></i><?= $eleccionActiva['nombre'] ?></h2>
                <p class="text-muted"><?= $eleccionActiva['descripcion'] ?></p>
                <small class="badge bg-success"><i class="bi bi-clock me-1"></i>Votación activa hasta <?= formatDate($eleccionActiva['fecha_fin']) ?></small>
            </div>

            <?php if (!empty($eleccionActiva['reglas'])): ?>
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle me-2"></i><strong>Reglas:</strong> <?= nl2br($eleccionActiva['reglas']) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($candidatos)): ?>
            <form method="POST" id="votarForm">
                <div class="row g-4">
                    <?php foreach ($candidatos as $c): ?>
                    <div class="col-md-6 col-lg-4">
                        <label class="candidate-card card h-100">
                            <div class="card-body text-center">
                                <input type="radio" name="candidato_id" value="<?= $c['id'] ?>" required>
                                <div class="mb-3">
                                    <i class="bi bi-person-circle" style="font-size: 4rem; color: #667eea;"></i>
                                </div>
                                <h5 class="card-title"><?= $c['nombre'] ?></h5>
                                <?php if ($c['categoria']): ?>
                                <span class="badge bg-secondary mb-2"><?= $c['categoria'] ?></span>
                                <?php endif; ?>
                                <?php if ($c['descripcion']): ?>
                                <p class="card-text small text-muted"><?= substr($c['descripcion'], 0, 100) ?>...</p>
                                <?php endif; ?>
                                <?php if ($c['propuesta']): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#propuesta<?= $c['id'] ?>">
                                    Ver propuesta
                                </button>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>

                    <?php if ($c['propuesta']): ?>
                    <div class="modal fade" id="propuesta<?= $c['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?= $c['nombre'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <h6>Propuesta:</h6>
                                    <p><?= nl2br($c['propuesta']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-5">
                    <button type="submit" class="btn btn-primary btn-lg px-5" id="btnVotar" disabled>
                        <i class="bi bi-check2-circle me-2"></i>Emitir Voto
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people" style="font-size: 3rem;"></i>
                <p class="mt-3">No hay candidatos registrados para esta elección</p>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="vote-card p-5 text-center">
            <i class="bi bi-calendar-x" style="font-size: 5rem; color: #ccc;"></i>
            <h2 class="mt-4">No hay votaciones activas</h2>
            <p class="text-muted">En este momento no hay ninguna elección abierta. Consulta más tarde.</p>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="admin/index.php" class="btn btn-outline-light">
                <i class="bi bi-gear me-2"></i>Panel de Administración
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.candidate-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('btnVotar').disabled = false;
            });
        });
    </script>
</body>
</html>
