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

    /**
     * Actualiza los datos editables de un establecimiento.
     */
    public function update(int $id, string $nombre, string $direccion, string $tipo, string $ruc): bool {
        $stmt = $this->db->prepare(
            "UPDATE establecimientos SET nombre = ?, direccion = ?, tipo = ?, ruc = ? WHERE id = ?"
        );
        return $stmt->execute([$nombre, $direccion, $tipo, $ruc, $id]);
    }

    /**
     * Retorna el administrador/titular del establecimiento.
     * Prioriza id_usuario; si no existe, busca por establecimiento_id + rol ADM.
     */
    public function getAdminByEstablecimiento(int $id): array {
        // Primero: por id_usuario del establecimiento (dueño directo)
        $stmt = $this->db->prepare("SELECT id_usuario FROM establecimientos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['id_usuario'])) {
            $stmt2 = $this->db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt2->execute([$row['id_usuario']]);
            $owner = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($owner) return [$owner];
        }

        // Fallback: administradores asociados al establecimiento
        $stmt3 = $this->db->prepare(
            "SELECT * FROM usuarios WHERE establecimiento_id = ? AND rol_codigo = 'ADM'"
        );
        $stmt3->execute([$id]);
        return $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna los médicos activos e inactivos del establecimiento.
     */
    public function getMedicosByEstablecimiento(int $id): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM usuarios WHERE establecimiento_id = ? AND rol_codigo = 'MED'"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
