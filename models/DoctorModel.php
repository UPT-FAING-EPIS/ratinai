<?php
require_once __DIR__ . '/../config/config.php';

class DoctorModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function existsByEmail($correo) {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE correo = :correo LIMIT 1");
        $stmt->execute([':correo' => $correo]);
        return (bool)$stmt->fetch();
    }

    public function existsByCMP($cmp) {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE cmp = :cmp AND rol_codigo = 'MED' LIMIT 1");
        $stmt->execute([':cmp' => $cmp]);
        return (bool)$stmt->fetch();
    }

    public function addSpecialty($descripcion) {
        $stmt = $this->db->prepare("SELECT id FROM maestro WHERE tipo='TIPO_ESPECIALIDAD' AND descripcion=:d");
        $stmt->execute([':d' => $descripcion]);
        if (!$stmt->fetch()) {
            $cod_nuevo = 'USR_' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $descripcion), 0, 6));
            $ins = $this->db->prepare(
                "INSERT INTO maestro (tipo, codigo, descripcion, orden) VALUES ('TIPO_ESPECIALIDAD', :cod, :desc, 50)"
            );
            $ins->execute([':cod' => $cod_nuevo, ':desc' => $descripcion]);
        }
    }

    public function create($data) {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (nombre, correo, password, rol_codigo, cmp, especialidad,
                                  establecimiento_id, activo, es_password_temporal)
             VALUES (:nombre, :correo, :pwd, 'MED', :cmp, :esp, :eid, 1, 1)"
        );
        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':correo' => $data['email'],
            ':pwd'    => $data['password'],
            ':cmp'    => $data['cmp'],
            ':esp'    => $data['especialidad'],
            ':eid'    => $data['establecimiento_id'],
        ]);
        return $this->db->lastInsertId();
    }

    public function deactivate($id, $est_id) {
        $stmt = $this->db->prepare("UPDATE usuarios SET activo=0 WHERE id=:id AND establecimiento_id=:eid AND rol_codigo='MED'");
        return $stmt->execute([':id' => $id, ':eid' => $est_id]);
    }
}
