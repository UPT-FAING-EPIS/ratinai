<?php
class Database {
    private $host = 'kodama.proxy.rlwy.net';
    private $port = '51343';
    private $dbname = 'railway';
    private $username = 'root';
    private $password = 'pqoacJffGYyQnDqAYSwDBJnjcZWKEczc';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->dbname . ";charset=utf8", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            die("Error de conexión a la base de datos: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'gichevichin2020@gmail.com');
define('SMTP_PASS', 'icvy ilbt twus dsuv');
define('SMTP_FROM', 'gichevichin2020@gmail.com');
define('SMTP_FROM_NAME', 'RetinAI Admins');
