<?php
error_log("=== INDEX.PHP INICIADO ===");
// Redirige automáticamente al login o dashboard según sesión
session_start();
error_log("Sesión iniciada correctamente");

if (isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    error_log("Redirigiendo a dashboard");
    header('Location: dashboard_admin.php');
    exit;
} else {
    error_log("Redirigiendo a login");
    header('Location: login.php');
    exit;
}
