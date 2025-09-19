<?php
require_once __DIR__ . '/env_loader.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;


    public function __construct() {
        error_log("Database constructor iniciado");
        
        if (!empty($_ENV['MYSQL_URL'])) {
            error_log("Usando MYSQL_URL: " . $_ENV['MYSQL_URL']);
            // Parsear la URL de Railway
            $url = parse_url($_ENV['MYSQL_URL']);
            $this->host = $url['host'];
            $this->db_name = ltrim($url['path'], '/');
            $this->username = $url['user'];
            $this->password = $url['pass'];
            error_log("Parsed - Host: {$this->host}, DB: {$this->db_name}, User: {$this->username}");
        } else {
            error_log("Usando variables individuales del .env");
            $this->host = $_ENV['DB_HOST'];
            $this->db_name = $_ENV['DB_NAME'];
            $this->username = $_ENV['DB_USER'];
            $this->password = $_ENV['DB_PASS'];
            error_log("Env vars - Host: {$this->host}, DB: {$this->db_name}, User: {$this->username}");
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            error_log("Intentando conectar a: mysql:host={$this->host};dbname={$this->db_name}");
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("Conexión exitosa a la base de datos");
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            die("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
