<?php
require_once __DIR__ . '/../config/config.php';

class DoctorModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function existsByEmail($correo, $exclude_id = null) {
        $sql = "SELECT id FROM usuarios WHERE correo = :correo";
        $params = [':correo' => $correo];
        if ($exclude_id !== null) {
            $sql .= " AND id != :exc";
            $params[':exc'] = $exclude_id;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function existsByCMP($cmp, $exclude_id = null) {
        $sql = "SELECT id FROM usuarios WHERE cmp = :cmp AND rol_codigo = 'MED'";
        $params = [':cmp' => $cmp];
        if ($exclude_id !== null) {
            $sql .= " AND id != :exc";
            $params[':exc'] = $exclude_id;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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

    public function deactivate($id, $est_ids) {
        if (!is_array($est_ids)) $est_ids = [$est_ids];
        if (empty($est_ids)) return false;
        $in = str_repeat('?,', count($est_ids) - 1) . '?';
        $sql = "UPDATE usuarios SET activo=0 WHERE id=? AND establecimiento_id IN ($in) AND rol_codigo='MED'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(array_merge([$id], $est_ids));
    }

    public function findById($id, $est_ids) {
        if (!is_array($est_ids)) $est_ids = [$est_ids];
        if (empty($est_ids)) return null;
        $in = str_repeat('?,', count($est_ids) - 1) . '?';
        $sql = "SELECT * FROM usuarios WHERE id=? AND establecimiento_id IN ($in) AND rol_codigo='MED' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$id], $est_ids));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateDoctor($id, $est_ids, $data) {
        if (!is_array($est_ids)) $est_ids = [$est_ids];
        if (empty($est_ids)) return false;
        $in = str_repeat('?,', count($est_ids) - 1) . '?';
        $sql = "UPDATE usuarios SET nombre=?, correo=?, cmp=?, especialidad=? 
                WHERE id=? AND establecimiento_id IN ($in) AND rol_codigo='MED'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(array_merge([
            $data['nombre'], $data['email'], $data['cmp'], $data['especialidad'], $id
        ], $est_ids));
    }

    public function resetPassword($id, $est_ids, $hash) {
        if (!is_array($est_ids)) $est_ids = [$est_ids];
        if (empty($est_ids)) return false;
        $in = str_repeat('?,', count($est_ids) - 1) . '?';
        $sql = "UPDATE usuarios SET password=?, es_password_temporal=1
                WHERE id=? AND establecimiento_id IN ($in) AND rol_codigo='MED'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(array_merge([$hash, $id], $est_ids));
    }

    public function countPendingByEstablishments($ids_array) {
        if (empty($ids_array)) return 0;
        $in = str_repeat('?,', count($ids_array) - 1) . '?';
        $sql = "SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=0 AND establecimiento_id IN ($in)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids_array);
        return (int)$stmt->fetchColumn();
    }

    public function countActiveByEstablishments($ids_array) {
        if (empty($ids_array)) return 0;
        $in = str_repeat('?,', count($ids_array) - 1) . '?';
        $sql = "SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=1 AND establecimiento_id IN ($in)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids_array);
        return (int)$stmt->fetchColumn();
    }

    public function getActiveDoctorsByEstablishments($ids_array) {
        if (empty($ids_array)) return [];
        $in = str_repeat('?,', count($ids_array) - 1) . '?';
        $sql = "SELECT u.id, u.nombre, u.correo, u.cmp, u.especialidad, u.ultimo_acceso,
                       u.es_password_temporal, u.establecimiento_id,
                       e.nombre AS est_nombre
                FROM usuarios u
                LEFT JOIN establecimientos e ON e.id = u.establecimiento_id
                WHERE u.rol_codigo='MED' AND u.activo=1
                  AND u.establecimiento_id IN ($in)
                ORDER BY e.nombre, u.nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids_array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingDoctorsByEstablishments($ids_array) {
        if (empty($ids_array)) return [];
        $in = str_repeat('?,', count($ids_array) - 1) . '?';
        $sql = "SELECT id, nombre, correo, cmp, especialidad, ultimo_acceso
                FROM usuarios WHERE rol_codigo='MED' AND activo=0 AND establecimiento_id IN ($in)
                ORDER BY nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids_array);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
