<?php
// Redirige automáticamente al login o dashboard según sesión
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    header('Location: dashboard_admin.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
