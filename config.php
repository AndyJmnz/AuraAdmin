<?php
// config.php - Configuración de la base de datos


require_once __DIR__ . '/Database.php';
$db = new Database();
$pdo = $db->getConnection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Función para sanitizar datos de entrada
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Función para validar email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para generar hash de password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
?>