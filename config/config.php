<?php
class Database {
    private $host = 'roundhouse.proxy.rlwy.net';
    private $port = '23598';
    private $dbname = 'railway';
    private $username = 'root';
    private $password = 'gcwGXEGFxoKuBcTAyTJvOGILCPGHsAwm';
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
