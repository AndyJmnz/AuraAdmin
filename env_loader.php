<?php
error_log("=== ENV_LOADER.PHP INICIADO ===");
// Cargar variables de entorno desde .env
require_once __DIR__ . '/vendor/autoload.php';

error_log("Autoload cargado");

use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    error_log("Variables de entorno cargadas exitosamente");
} catch (Exception $e) {
    error_log("ERROR cargando variables de entorno: " . $e->getMessage());
    throw $e;
}
