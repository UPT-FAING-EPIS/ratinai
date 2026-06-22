<?php
require_once __DIR__ . '/../config/config.php';

class CarpetaModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    /**
     * Lista todas las carpetas de un paciente creadas por un médico específico.
     */
    public function listarPorPaciente($id_paciente, $id_medico) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   COUNT(a.id) AS total_analisis
            FROM carpetas_paciente c
            LEFT JOIN analisis_retinales a ON a.id_carpeta = c.id
            WHERE c.id_paciente = :id_paciente
              AND c.id_medico   = :id_medico
            GROUP BY c.id
            ORDER BY c.fecha_creacion DESC
        ");
        $stmt->bindParam(':id_paciente', $id_paciente, PDO::PARAM_INT);
        $stmt->bindParam(':id_medico',   $id_medico,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea una nueva carpeta para un paciente.
     * Retorna el ID de la carpeta creada o false en caso de error.
     */
    public function crear($id_paciente, $id_medico, $nombre, $descripcion = null) {
        $stmt = $this->db->prepare("
            INSERT INTO carpetas_paciente (id_paciente, id_medico, nombre, descripcion)
            VALUES (:id_paciente, :id_medico, :nombre, :descripcion)
        ");
        $stmt->bindParam(':id_paciente',  $id_paciente,  PDO::PARAM_INT);
        $stmt->bindParam(':id_medico',    $id_medico,    PDO::PARAM_INT);
        $stmt->bindParam(':nombre',       $nombre);
        $stmt->bindParam(':descripcion',  $descripcion);
        if ($stmt->execute()) {
            return (int)$this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Obtiene una carpeta por ID (sin filtro de médico, para uso interno).
     */
    public function obtenerPorId($id) {
        $stmt = $this->db->prepare("SELECT * FROM carpetas_paciente WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los análisis de una carpeta específica.
     */
    public function obtenerAnalisisDeCarpeta($id_carpeta, $id_medico) {
        $stmt = $this->db->prepare("
            SELECT a.id, a.fecha_analisis, a.resultado_principal,
                   a.probabilidad_principal, a.alerta_anomalia
            FROM analisis_retinales a
            WHERE a.id_carpeta = :id_carpeta
              AND a.id_medico  = :id_medico
            ORDER BY a.fecha_analisis DESC
        ");
        $stmt->bindParam(':id_carpeta', $id_carpeta, PDO::PARAM_INT);
        $stmt->bindParam(':id_medico',  $id_medico,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
