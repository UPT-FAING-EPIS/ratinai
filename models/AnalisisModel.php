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
            (id_medico, id_paciente, id_carpeta, ojo, fecha_captura, version_modelo,
             es_retinografia, probabilidad_retinografia, es_evaluable, probabilidad_calidad,
             motivo_rechazo, salida_original_json, hash_imagen,
             imagen_path, resultado_principal, probabilidad_principal,
             probabilidad_normal, probabilidad_diabetes, probabilidad_glaucoma, probabilidad_catarata,
             diagnostico_medico, alerta_anomalia, es_referencial, tiempo_analisis)
            VALUES 
            (:id_medico, :id_paciente, :id_carpeta, :ojo, :fecha_captura, :version_modelo,
             :es_retinografia, :probabilidad_retinografia, :es_evaluable, :probabilidad_calidad,
             :motivo_rechazo, :salida_original_json, :hash_imagen,
             :imagen_path, :resultado_principal, :probabilidad_principal,
             :probabilidad_normal, :probabilidad_diabetes, :probabilidad_glaucoma, :probabilidad_catarata,
             :diagnostico_medico, :alerta_anomalia, :es_referencial, :tiempo_analisis)
        ");

        $valores = [
            'id_medico' => $data['id_medico'],
            'id_paciente' => $data['id_paciente'],
            'id_carpeta' => $data['id_carpeta'],
            'ojo' => $data['ojo'] ?? null,
            'fecha_captura' => $data['fecha_captura'] ?? null,
            'version_modelo' => $data['version_modelo'] ?? null,
            'es_retinografia' => $data['es_retinografia'] ?? null,
            'probabilidad_retinografia' => $data['probabilidad_retinografia'] ?? null,
            'es_evaluable' => $data['es_evaluable'] ?? null,
            'probabilidad_calidad' => $data['probabilidad_calidad'] ?? null,
            'motivo_rechazo' => $data['motivo_rechazo'] ?? null,
            'salida_original_json' => $data['salida_original_json'] ?? null,
            'hash_imagen' => $data['hash_imagen'] ?? null,
            'imagen_path' => $data['imagen_path'],
            'resultado_principal' => $data['resultado_principal'],
            'probabilidad_principal' => $data['probabilidad_principal'],
            'probabilidad_normal' => $data['probabilidad_normal'],
            'probabilidad_diabetes' => $data['probabilidad_diabetes'],
            'probabilidad_glaucoma' => $data['probabilidad_glaucoma'],
            'probabilidad_catarata' => $data['probabilidad_catarata'],
            'diagnostico_medico' => $data['diagnostico_medico'],
            'alerta_anomalia' => $data['alerta_anomalia'],
            'es_referencial' => $data['es_referencial'],
            'tiempo_analisis' => $data['tiempo_analisis'],
        ];

        if ($stmt->execute($valores)) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function obtenerHistorialMedico($id_medico) {
        $stmt = $this->db->prepare("
            SELECT a.*, 
                   p.codigo_paciente, p.dni,
                   c.nombre AS nombre_carpeta
            FROM analisis_retinales a
            LEFT JOIN pacientes         p ON a.id_paciente = p.id
            LEFT JOIN carpetas_paciente c ON a.id_carpeta  = c.id
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
                   u.establecimiento_id,
                   p.codigo_paciente, p.dni AS dni_paciente,
                   i.id AS id_informe, i.estado AS estado_informe, i.version AS version_informe,
                   i.texto_generado, i.texto_editado, i.fecha_aprobacion,
                   i.ruta_pdf, i.hash_pdf,
                   v.valoracion, v.motivo AS motivo_valoracion, v.comentario AS comentario_valoracion,
                   s.estado AS estado_sincronizacion, s.ultimo_error AS error_sincronizacion
            FROM analisis_retinales a
            INNER JOIN usuarios u ON u.id = a.id_medico
            LEFT JOIN  pacientes p ON p.id = a.id_paciente
            LEFT JOIN informes_clinicos i ON i.id_analisis = a.id
            LEFT JOIN valoraciones_ia v ON v.id_analisis = a.id
            LEFT JOIN sincronizaciones_informes s ON s.id = (
                SELECT s2.id FROM sincronizaciones_informes s2
                WHERE s2.id_informe = i.id
                ORDER BY CASE WHEN s2.proveedor = 'local' THEN 1 ELSE 0 END, s2.id DESC
                LIMIT 1
            )
            WHERE a.id = :id_analisis
              AND a.id_medico = :id_medico
            LIMIT 1
        ");
        $stmt->bindParam(':id_analisis', $id_analisis, PDO::PARAM_INT);
        $stmt->bindParam(':id_medico',   $id_medico,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getKPIsMedico($id_medico) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT id_paciente) as total_pacientes,
                COUNT(id) as total_analisis,
                SUM(CASE WHEN alerta_anomalia = 1 THEN 1 ELSE 0 END) as total_alertas
            FROM analisis_retinales
            WHERE id_medico = :id_medico
        ");
        $stmt->bindParam(':id_medico', $id_medico, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDistribucionResultados($id_medico) {
        $stmt = $this->db->prepare("
            SELECT resultado_principal, COUNT(id) as total 
            FROM analisis_retinales 
            WHERE id_medico = :id_medico 
            GROUP BY resultado_principal
        ");
        $stmt->bindParam(':id_medico', $id_medico, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActividadUltimos7Dias($id_medico) {
        $stmt = $this->db->prepare("
            SELECT DATE(fecha_analisis) as fecha, COUNT(id) as total 
            FROM analisis_retinales 
            WHERE id_medico = :id_medico 
              AND fecha_analisis >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(fecha_analisis) 
            ORDER BY fecha ASC
        ");
        $stmt->bindParam(':id_medico', $id_medico, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAnalisisRecientes($id_medico, $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT a.id, a.fecha_analisis, a.resultado_principal, a.probabilidad_principal, a.alerta_anomalia, p.dni, p.codigo_paciente 
            FROM analisis_retinales a 
            LEFT JOIN pacientes p ON a.id_paciente = p.id 
            WHERE a.id_medico = :id_medico 
            ORDER BY a.fecha_analisis DESC 
            LIMIT :lim
        ");
        $stmt->bindParam(':id_medico', $id_medico, PDO::PARAM_INT);
        $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCasosCriticosRecientes($id_medico, $limit = 5) {
        $stmt = $this->db->prepare("
            SELECT a.id, a.fecha_analisis, a.resultado_principal, a.probabilidad_principal, p.dni, p.codigo_paciente 
            FROM analisis_retinales a 
            LEFT JOIN pacientes p ON a.id_paciente = p.id 
            WHERE a.id_medico = :id_medico 
              AND a.alerta_anomalia = 1 
            ORDER BY a.fecha_analisis DESC 
            LIMIT :lim
        ");
        $stmt->bindParam(':id_medico', $id_medico, PDO::PARAM_INT);
        $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSeguimientoCasosCriticos($id_medico, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT
                a.id,
                a.id_paciente,
                a.fecha_analisis,
                a.resultado_principal,
                a.probabilidad_principal,
                a.diagnostico_medico,
                p.dni,
                p.codigo_paciente
            FROM analisis_retinales a
            LEFT JOIN pacientes p ON a.id_paciente = p.id
            WHERE a.id_medico = :id_medico
              AND a.alerta_anomalia = 1
            ORDER BY a.probabilidad_principal DESC, a.fecha_analisis DESC
            LIMIT :lim
        ");
        $stmt->bindParam(':id_medico', $id_medico, PDO::PARAM_INT);
        $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve controles anteriores del mismo paciente y ojo para construir un borrador trazable.
     */
    public function obtenerControlesPrevios(int $idAnalisis, int $idMedico, int $limite = 5): array
    {
        $consulta = $this->db->prepare(
            'SELECT previo.id, previo.fecha_analisis, previo.fecha_captura, previo.ojo,
                    previo.resultado_principal, previo.probabilidad_principal,
                    previo.probabilidad_normal, previo.probabilidad_diabetes,
                    previo.probabilidad_glaucoma, previo.probabilidad_catarata,
                    previo.version_modelo, previo.imagen_path
             FROM analisis_retinales actual
             INNER JOIN analisis_retinales previo
                ON previo.id_paciente = actual.id_paciente
               AND previo.ojo = actual.ojo
               AND previo.id <> actual.id
               AND previo.fecha_analisis < actual.fecha_analisis
             WHERE actual.id = :id_analisis
               AND actual.id_medico = :id_medico
               AND previo.id_medico = :id_medico_previo
               AND (previo.es_retinografia = 1 OR previo.es_retinografia IS NULL)
               AND (previo.es_evaluable = 1 OR previo.es_evaluable IS NULL)
               AND previo.resultado_principal IS NOT NULL
             ORDER BY previo.fecha_analisis DESC
             LIMIT :limite'
        );
        $consulta->bindValue(':id_analisis', $idAnalisis, PDO::PARAM_INT);
        $consulta->bindValue(':id_medico', $idMedico, PDO::PARAM_INT);
        $consulta->bindValue(':id_medico_previo', $idMedico, PDO::PARAM_INT);
        $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
        $consulta->execute();

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function compararControles(int $idAnalisisPrimero, int $idAnalisisSegundo, int $idMedico): ?array
    {
        $consulta = $this->db->prepare(
            'SELECT a.id, a.id_paciente, a.ojo, a.fecha_analisis, a.fecha_captura,
                    a.version_modelo, a.resultado_principal, a.probabilidad_principal,
                    a.probabilidad_normal, a.probabilidad_diabetes,
                    a.probabilidad_glaucoma, a.probabilidad_catarata, a.imagen_path
             FROM analisis_retinales a
             WHERE a.id_medico = :id_medico
               AND a.id IN (:id_primero, :id_segundo)
               AND (a.es_retinografia = 1 OR a.es_retinografia IS NULL)
               AND (a.es_evaluable = 1 OR a.es_evaluable IS NULL)
               AND a.resultado_principal IS NOT NULL
             ORDER BY a.fecha_analisis ASC'
        );
        $consulta->execute([
            'id_medico' => $idMedico,
            'id_primero' => $idAnalisisPrimero,
            'id_segundo' => $idAnalisisSegundo,
        ]);
        $controles = $consulta->fetchAll(PDO::FETCH_ASSOC);
        if (count($controles) !== 2) {
            return null;
        }

        if ((int) $controles[0]['id_paciente'] !== (int) $controles[1]['id_paciente']
            || $controles[0]['ojo'] !== $controles[1]['ojo']) {
            return null;
        }

        return [
            'controles' => $controles,
            'versiones_diferentes' => $controles[0]['version_modelo'] !== $controles[1]['version_modelo'],
        ];
    }

    public function actualizarDiagnostico(int $idAnalisis, int $idMedico, ?string $diagnostico): bool
    {
        $consulta = $this->db->prepare(
            'UPDATE analisis_retinales
             SET diagnostico_medico = :diagnostico
             WHERE id = :id_analisis AND id_medico = :id_medico'
        );
        $consulta->execute([
            'diagnostico' => $diagnostico,
            'id_analisis' => $idAnalisis,
            'id_medico' => $idMedico,
        ]);

        return $consulta->rowCount() > 0;
    }
}
