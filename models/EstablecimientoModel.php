<?php
require_once __DIR__ . '/../config/config.php';

class EstablecimientoModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM establecimientos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByOwnerId($userId) {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, direccion, tipo, ruc FROM establecimientos WHERE id_usuario = :uid ORDER BY id ASC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
