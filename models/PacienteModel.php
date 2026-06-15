<?php
require_once __DIR__ . '/../config/config.php';

class PacienteModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function buscarPorDNI($dni) {
        $stmt = $this->db->prepare("SELECT * FROM pacientes WHERE dni = :dni LIMIT 1");
        $stmt->bindParam(':dni', $dni);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarPorCodigo($codigo) {
        $stmt = $this->db->prepare("SELECT * FROM pacientes WHERE codigo_paciente = :codigo LIMIT 1");
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrarPaciente($dni) {
        $codigo = 'PAC-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        // Evitar colisión de código
        while ($this->buscarPorCodigo($codigo)) {
            $codigo = 'PAC-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        $stmt = $this->db->prepare("INSERT INTO pacientes (dni, codigo_paciente) VALUES (:dni, :codigo)");
        $stmt->bindParam(':dni', $dni);
        $stmt->bindParam(':codigo', $codigo);
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }
}
