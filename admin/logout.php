<?php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    $pdo = getDBConnection();
    logAuditoria($pdo, $_SESSION['admin_id'], 'LOGOUT');
}

session_destroy();
redirect('index.php?logout=1');
