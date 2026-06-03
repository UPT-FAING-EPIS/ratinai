<?php
require_once __DIR__ . '/../config/config.php';

class SolicitudModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getSolicitudesByContactEmailOrOwner($email, $userId) {
        $q = $this->db->prepare(
            "SELECT id, nombre_centro, direccion, tipo, ruc, estado, fecha_solicitud
             FROM solicitudes_establecimiento
             WHERE correo_contacto = :correo OR id_usuario_solicitante = :uid
             ORDER BY fecha_solicitud DESC"
        );
        $q->execute([':correo' => $email, ':uid' => $userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllSolicitudesConOrigen() {
        return $this->db->query(
            "SELECT s.id, s.nombre_centro, s.direccion, s.tipo, s.ruc,
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
}
