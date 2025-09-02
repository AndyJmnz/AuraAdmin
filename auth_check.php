<?php
// auth_check.php - Incluir este archivo en todas las páginas administrativas

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación y rol de administrador
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Función para cerrar sesión
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Manejar logout si se solicita
if (isset($_GET['logout'])) {
    logout();
}
?>