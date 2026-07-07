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

    /**
     * Recupera el codigo unico de historial de un paciente por DNI, validando
     * que exista al menos un analisis asociado al medico en sesion.
     */
    public function recuperarCodigoHistorialPorDNI($dni, $id_medico) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT
                p.id,
                p.dni,
                p.codigo_paciente
            FROM pacientes p
            INNER JOIN analisis_retinales a ON a.id_paciente = p.id
            WHERE p.dni = :dni
              AND a.id_medico = :id_medico
            LIMIT 1
        ");
        $stmt->bindParam(':dni', $dni);
        $stmt->bindParam(':id_medico', $id_medico, PDO::PARAM_INT);
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

    /**
     * Obtiene todos los pacientes que tienen análisis realizados por un médico,
     * junto con la cantidad de carpetas y de análisis totales.
     */
    public function listarPacientesConAnalisisMedico($id_medico) {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.dni,
                p.codigo_paciente,
                p.nombres,
                p.apellidos,
                p.fecha_registro,
                COUNT(DISTINCT a.id)          AS total_analisis,
                COUNT(DISTINCT c.id)          AS total_carpetas,
                MAX(a.fecha_analisis)         AS ultimo_analisis,
                SUM(a.alerta_anomalia)        AS total_alertas
            FROM pacientes p
            INNER JOIN analisis_retinales a ON a.id_paciente = p.id AND a.id_medico = :id_medico
            LEFT JOIN  carpetas_paciente  c ON c.id_paciente = p.id AND c.id_medico = :id_medico2
            GROUP BY p.id
            ORDER BY ultimo_analisis DESC
        ");
        $stmt->bindParam(':id_medico',  $id_medico, PDO::PARAM_INT);
        $stmt->bindParam(':id_medico2', $id_medico, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el detalle completo de un paciente (carpetas + análisis) para un médico.
     */
    public function obtenerDetallePaciente($id_paciente, $id_medico) {
        // Carpetas del paciente para este médico
        $stmtC = $this->db->prepare("
            SELECT c.*, COUNT(a.id) AS total_analisis
            FROM carpetas_paciente c
            LEFT JOIN analisis_retinales a ON a.id_carpeta = c.id
            WHERE c.id_paciente = :id_paciente AND c.id_medico = :id_medico
            GROUP BY c.id
            ORDER BY c.fecha_creacion DESC
        ");
        $stmtC->bindParam(':id_paciente', $id_paciente, PDO::PARAM_INT);
        $stmtC->bindParam(':id_medico',   $id_medico,   PDO::PARAM_INT);
        $stmtC->execute();
        $carpetas = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        // Análisis sin carpeta para este médico y paciente
        $stmtA = $this->db->prepare("
            SELECT a.id, a.fecha_analisis, a.resultado_principal,
                   a.probabilidad_principal, a.alerta_anomalia
            FROM analisis_retinales a
            WHERE a.id_paciente = :id_paciente
              AND a.id_medico   = :id_medico
              AND a.id_carpeta  IS NULL
            ORDER BY a.fecha_analisis DESC
        ");
        $stmtA->bindParam(':id_paciente', $id_paciente, PDO::PARAM_INT);
        $stmtA->bindParam(':id_medico',   $id_medico,   PDO::PARAM_INT);
        $stmtA->execute();
        $sinCarpeta = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        return ['carpetas' => $carpetas, 'sin_carpeta' => $sinCarpeta];
    }
}
