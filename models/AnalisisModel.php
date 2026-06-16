<?php
require_once __DIR__ . '/../config/config.php';

class AnalisisModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function registrarAnalisis($data) {
        $stmt = $this->db->prepare("
            INSERT INTO analisis_retinales 
            (id_medico, id_paciente, imagen_path, resultado_principal, probabilidad_principal, 
             probabilidad_normal, probabilidad_diabetes, probabilidad_glaucoma, probabilidad_catarata, 
             alerta_anomalia, es_referencial, tiempo_analisis)
            VALUES 
            (:id_medico, :id_paciente, :imagen_path, :resultado_principal, :probabilidad_principal, 
             :probabilidad_normal, :probabilidad_diabetes, :probabilidad_glaucoma, :probabilidad_catarata, 
             :alerta_anomalia, :es_referencial, :tiempo_analisis)
        ");

        $stmt->bindParam(':id_medico', $data['id_medico']);
        $stmt->bindParam(':id_paciente', $data['id_paciente']);
        $stmt->bindParam(':imagen_path', $data['imagen_path']);
        $stmt->bindParam(':resultado_principal', $data['resultado_principal']);
        $stmt->bindParam(':probabilidad_principal', $data['probabilidad_principal']);
        $stmt->bindParam(':probabilidad_normal', $data['probabilidad_normal']);
        $stmt->bindParam(':probabilidad_diabetes', $data['probabilidad_diabetes']);
        $stmt->bindParam(':probabilidad_glaucoma', $data['probabilidad_glaucoma']);
        $stmt->bindParam(':probabilidad_catarata', $data['probabilidad_catarata']);
        $stmt->bindParam(':alerta_anomalia', $data['alerta_anomalia']);
        $stmt->bindParam(':es_referencial', $data['es_referencial']);
        $stmt->bindParam(':tiempo_analisis', $data['tiempo_analisis']);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function obtenerHistorialMedico($id_medico) {
        $stmt = $this->db->prepare("
            SELECT a.*, p.codigo_paciente, p.dni 
            FROM analisis_retinales a
            LEFT JOIN pacientes p ON a.id_paciente = p.id
            WHERE a.id_medico = :id_medico
            ORDER BY a.fecha_analisis DESC
        ");
        $stmt->bindParam(':id_medico', $id_medico);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un análisis por su ID, verificando que pertenezca al médico indicado.
     * Incluye nombre del médico, CMP y datos del paciente.
     */
    public function obtenerPorId($id_analisis, $id_medico) {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   u.nombre  AS nombre_medico,
                   u.cmp     AS cmp_medico,
                   u.especialidad AS especialidad_medico,
                   p.codigo_paciente, p.dni AS dni_paciente
            FROM analisis_retinales a
            INNER JOIN usuarios u ON u.id = a.id_medico
            LEFT JOIN  pacientes p ON p.id = a.id_paciente
            WHERE a.id = :id_analisis
              AND a.id_medico = :id_medico
            LIMIT 1
        ");
        $stmt->bindParam(':id_analisis', $id_analisis, PDO::PARAM_INT);
        $stmt->bindParam(':id_medico',   $id_medico,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
