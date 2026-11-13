<?php
require_once __DIR__ . '/../config/config.php';

class SolicitudModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getSolicitudesByContactEmailOrOwner($email, $userId) {
        $q = $this->db->prepare(
            "SELECT id, nombre_centro, direccion, latitud, longitud, tipo, ruc, estado, fecha_solicitud
             FROM solicitudes_establecimiento
             WHERE correo_contacto = :correo OR id_usuario_solicitante = :uid
             ORDER BY fecha_solicitud DESC"
        );
        $q->execute([':correo' => $email, ':uid' => $userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllSolicitudesConOrigen() {
        return $this->db->query(
            "SELECT s.id, s.nombre_centro, s.direccion, s.latitud, s.longitud, s.tipo, s.ruc,
                    s.dni_titular, s.nombres_titular, s.apellidos_titular, s.telefono, s.correo_contacto,
                    s.evidencia_1, s.evidencia_1_nombre, s.evidencia_2, s.evidencia_2_nombre,
                    s.estado, s.fecha_solicitud,
                    s.id_usuario_solicitante,
                    u.nombre AS nombre_usuario_solicitante
             FROM solicitudes_establecimiento s
             LEFT JOIN usuarios u ON u.id = s.id_usuario_solicitante
             ORDER BY FIELD(s.estado,'pendiente','aprobado','rechazado'), s.fecha_solicitud DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPendientes() {
        return (int)$this->db->query(
            "SELECT COUNT(*) FROM solicitudes_establecimiento WHERE estado='pendiente'"
        )->fetchColumn();
    }

    /** Recupera el expediente con los datos del usuario que lo presentó. */
    public function obtenerDetalleConSolicitante(int $id): ?array {
        $consulta = $this->db->prepare('SELECT s.*, u.nombre AS nombre_solicitante, u.correo AS correo_solicitante FROM solicitudes_establecimiento s LEFT JOIN usuarios u ON u.id=s.id_usuario_solicitante WHERE s.id=? LIMIT 1');
        $consulta->execute([$id]);
        return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Listado compacto usado en la administración de establecimientos. */
    public function listarParaAdministracion(): array {
        return $this->db->query("SELECT id, nombre_centro, direccion, tipo, ruc, dni_titular, nombres_titular, apellidos_titular, telefono, correo_contacto, evidencia_1, evidencia_1_nombre, evidencia_2, evidencia_2_nombre, estado, fecha_solicitud FROM solicitudes_establecimiento ORDER BY FIELD(estado,'pendiente','aprobado','rechazado'), fecha_solicitud DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarPendientesPorSolicitante(int $idUsuario): int {
        $consulta = $this->db->prepare("SELECT COUNT(*) FROM solicitudes_establecimiento WHERE estado='pendiente' AND id_usuario_solicitante=?");
        $consulta->execute([$idUsuario]);
        return (int)$consulta->fetchColumn();
    }
}
