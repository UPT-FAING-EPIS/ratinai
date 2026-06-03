<?php
require_once __DIR__ . '/../config/config.php';

class MaestroModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getEspecialidades() {
        $qEsp = $this->db->query(
            "SELECT codigo, descripcion FROM maestro WHERE tipo='TIPO_ESPECIALIDAD' AND descripcion != 'Otro' ORDER BY orden ASC"
        );
        return $qEsp->fetchAll(PDO::FETCH_ASSOC);
    }
}
