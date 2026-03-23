<ul class="navbar-nav me-auto">
    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
    <li class="nav-item"><a class="nav-link" href="elecciones.php"><i class="bi bi-calendar-event me-1"></i>Elecciones</a></li>
    <li class="nav-item"><a class="nav-link" href="candidatos.php"><i class="bi bi-people me-1"></i>Candidatos</a></li>
    <li class="nav-item"><a class="nav-link" href="reportes.php"><i class="bi bi-bar-chart me-1"></i>Reportes</a></li>
    <?php if (isset($_SESSION['admin_rol']) && $_SESSION['admin_rol'] === 'superadmin'): ?>
    <li class="nav-item"><a class="nav-link" href="admins.php"><i class="bi bi-person-badge me-1"></i>Admins</a></li>
    <?php endif; ?>
</ul>
<div class="d-flex">
    <span class="navbar-text me-3">
        <i class="bi bi-person-circle me-1"></i><?= $_SESSION['admin_nombre'] ?? '' ?>
    </span>
    <a href="logout.php" class="btn btn-light btn-sm">
        <i class="bi bi-box-arrow-right"></i> Salir
    </a>
</div>
