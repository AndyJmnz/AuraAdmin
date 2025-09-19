<?php
// debug.php - Script de diagnóstico
echo "<h1>Diagnóstico AuraAdmin</h1>";

echo "<h2>Variables de entorno:</h2>";
echo "<pre>";
echo "MYSQL_URL: " . ($_ENV['MYSQL_URL'] ?? 'No definida') . "\n";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'No definida') . "\n";
echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'No definida') . "\n";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'No definida') . "\n";
echo "PORT: " . ($_ENV['PORT'] ?? 'No definida') . "\n";
echo "</pre>";

echo "<h2>Test de conexión:</h2>";
try {
    require_once __DIR__ . '/Database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    echo "<p style='color: green;'>✅ Conexión exitosa a la base de datos</p>";
    
    // Test básico
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "<p style='color: green;'>✅ Query test exitosa: " . $result['test'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error de conexión: " . $e->getMessage() . "</p>";
}

echo "<h2>Info PHP:</h2>";
phpinfo();
?>