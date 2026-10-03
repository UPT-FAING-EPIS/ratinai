<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class InformeClinicoModel
{
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        $this->conexion = $conexion ?? (new Database())->getConnection();
    }

    public function obtenerPorAnalisis(int $idAnalisis, int $idMedico): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT i.*
             FROM informes_clinicos i
             INNER JOIN analisis_retinales a ON a.id = i.id_analisis
             WHERE i.id_analisis = :id_analisis AND a.id_medico = :id_medico
             LIMIT 1'
        );
        $consulta->execute(['id_analisis' => $idAnalisis, 'id_medico' => $idMedico]);
        $informe = $consulta->fetch(PDO::FETCH_ASSOC);

        return $informe === false ? null : $informe;
    }

    public function guardarBorrador(
        int $idAnalisis,
        int $idMedico,
        string $textoGenerado,
        string $textoEditado
    ): int {
        $consulta = $this->conexion->prepare(
            "INSERT INTO informes_clinicos
                (id_analisis, texto_generado, texto_editado, estado, id_autor_borrador)
             VALUES
                (:id_analisis, :texto_generado, :texto_editado, 'borrador', :id_autor_borrador)
             ON DUPLICATE KEY UPDATE
                texto_editado = IF(estado = 'borrador', VALUES(texto_editado), texto_editado),
                texto_generado = IF(estado = 'borrador', VALUES(texto_generado), texto_generado),
                id_autor_borrador = IF(estado = 'borrador', VALUES(id_autor_borrador), id_autor_borrador)"
        );
        $consulta->execute([
            'id_analisis' => $idAnalisis,
            'texto_generado' => $textoGenerado,
            'texto_editado' => $textoEditado,
            'id_autor_borrador' => $idMedico,
        ]);

        $informe = $this->obtenerPorAnalisis($idAnalisis, $idMedico);
        if ($informe === null) {
            throw new RuntimeException('No se pudo recuperar el borrador guardado.');
        }

        return (int) $informe['id'];
    }

    public function actualizarTextoBorrador(int $idAnalisis, int $idMedico, string $textoEditado): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE informes_clinicos i
             INNER JOIN analisis_retinales a ON a.id = i.id_analisis
             SET i.texto_editado = :texto_editado
             WHERE i.id_analisis = :id_analisis
               AND a.id_medico = :id_medico
               AND i.estado = 'borrador'"
        );
        $consulta->execute([
            'texto_editado' => $textoEditado,
            'id_analisis' => $idAnalisis,
            'id_medico' => $idMedico,
        ]);

        return $consulta->rowCount() > 0;
    }

    public function aprobar(
        int $idAnalisis,
        int $idMedico,
        string $textoEditado,
        string $fechaAprobacion
    ): array
    {
        $consulta = $this->conexion->prepare(
            "UPDATE informes_clinicos i
             INNER JOIN analisis_retinales a ON a.id = i.id_analisis
             SET i.texto_editado = :texto_editado,
                 i.estado = 'aprobado',
                 i.id_aprobador = :id_aprobador,
                 i.fecha_aprobacion = :fecha_aprobacion
             WHERE i.id_analisis = :id_analisis
               AND a.id_medico = :id_medico
               AND i.estado = 'borrador'"
        );
        $consulta->execute([
            'texto_editado' => $textoEditado,
            'id_aprobador' => $idMedico,
            'fecha_aprobacion' => $fechaAprobacion,
            'id_analisis' => $idAnalisis,
            'id_medico' => $idMedico,
        ]);

        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('El informe no pudo aprobarse o ya estaba aprobado.');
        }

        $informe = $this->obtenerPorAnalisis($idAnalisis, $idMedico);
        if ($informe === null || $informe['estado'] !== 'aprobado') {
            throw new RuntimeException('El informe no pudo aprobarse o ya estaba aprobado.');
        }

        return $informe;
    }

    public function registrarArchivo(int $idInforme, string $rutaPdf, string $hashPdf): void
    {
        $consulta = $this->conexion->prepare(
            'UPDATE informes_clinicos SET ruta_pdf = :ruta_pdf, hash_pdf = :hash_pdf WHERE id = :id'
        );
        $consulta->execute(['ruta_pdf' => $rutaPdf, 'hash_pdf' => $hashPdf, 'id' => $idInforme]);
    }

    public function guardarValoracion(
        int $idAnalisis,
        int $idMedico,
        string $valoracion,
        ?string $motivo,
        ?string $comentario
    ): void {
        if (!$this->analisisPerteneceAlMedico($idAnalisis, $idMedico)) {
            throw new RuntimeException('El análisis no pertenece al médico autenticado.');
        }

        $consulta = $this->conexion->prepare(
            'INSERT INTO valoraciones_ia
                (id_analisis, id_medico, valoracion, motivo, comentario)
             SELECT :id_analisis, :id_medico, :valoracion, :motivo, :comentario
             FROM analisis_retinales
             WHERE id = :id_analisis_permitido AND id_medico = :id_medico_permitido
             ON DUPLICATE KEY UPDATE
                valoracion = VALUES(valoracion),
                motivo = VALUES(motivo),
                comentario = VALUES(comentario),
                id_medico = VALUES(id_medico)'
        );
        $consulta->execute([
            'id_analisis' => $idAnalisis,
            'id_medico' => $idMedico,
            'valoracion' => $valoracion,
            'motivo' => $motivo,
            'comentario' => $comentario,
            'id_analisis_permitido' => $idAnalisis,
            'id_medico_permitido' => $idMedico,
        ]);

    }

    private function analisisPerteneceAlMedico(int $idAnalisis, int $idMedico): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT COUNT(*) FROM analisis_retinales WHERE id = :id_analisis AND id_medico = :id_medico'
        );
        $consulta->execute(['id_analisis' => $idAnalisis, 'id_medico' => $idMedico]);

        return (int) $consulta->fetchColumn() === 1;
    }

    public function crearSincronizacionLocal(int $idInforme, int $idEstablecimiento): array
    {
        return $this->crearSincronizacion($idInforme, $idEstablecimiento, 'local');
    }

    public function crearSincronizacion(int $idInforme, int $idEstablecimiento, string $proveedor): array
    {
        $claveIdempotencia = hash('sha256', "informe:{$idInforme}:establecimiento:{$idEstablecimiento}:{$proveedor}");
        $consulta = $this->conexion->prepare(
            "INSERT INTO sincronizaciones_informes
                (id_informe, id_establecimiento, proveedor, clave_idempotencia, estado)
             VALUES
                (:id_informe, :id_establecimiento, :proveedor, :clave_idempotencia, 'pendiente')
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $consulta->execute([
            'id_informe' => $idInforme,
            'id_establecimiento' => $idEstablecimiento,
            'proveedor' => $proveedor,
            'clave_idempotencia' => $claveIdempotencia,
        ]);

        $idSincronizacion = (int) $this->conexion->lastInsertId();
        if ($idSincronizacion === 0) {
            $consultaId = $this->conexion->prepare(
                'SELECT id FROM sincronizaciones_informes WHERE clave_idempotencia = :clave LIMIT 1'
            );
            $consultaId->execute(['clave' => $claveIdempotencia]);
            $idSincronizacion = (int) $consultaId->fetchColumn();
        }

        return ['id' => $idSincronizacion, 'clave_idempotencia' => $claveIdempotencia];
    }

    public function completarSincronizacion(int $idSincronizacion, string $rutaRemota): void
    {
        $consulta = $this->conexion->prepare(
            "UPDATE sincronizaciones_informes
             SET estado = 'completada', ruta_remota = :ruta_remota,
                 intentos = intentos + 1, ultimo_error = NULL, fecha_completada = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $consulta->execute(['ruta_remota' => $rutaRemota, 'id' => $idSincronizacion]);
    }

    public function fallarSincronizacion(int $idSincronizacion, string $mensaje): void
    {
        $consulta = $this->conexion->prepare(
            "UPDATE sincronizaciones_informes
             SET estado = 'fallida', intentos = intentos + 1, ultimo_error = :ultimo_error
             WHERE id = :id"
        );
        $consulta->execute(['ultimo_error' => $mensaje, 'id' => $idSincronizacion]);
    }

    public function obtenerPendientesMedico(int $idMedico): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT a.id, a.fecha_analisis, a.ojo, a.resultado_principal,
                    p.codigo_paciente, i.estado AS estado_informe,
                    COALESCE(v.valoracion, 'sin_evaluar') AS estado_valoracion
             FROM analisis_retinales a
             LEFT JOIN pacientes p ON p.id = a.id_paciente
             LEFT JOIN informes_clinicos i ON i.id_analisis = a.id
             LEFT JOIN valoraciones_ia v ON v.id_analisis = a.id
             WHERE a.id_medico = :id_medico
               AND (a.es_retinografia = 1 OR a.es_retinografia IS NULL)
               AND (a.es_evaluable = 1 OR a.es_evaluable IS NULL)
               AND a.resultado_principal IS NOT NULL
               AND a.version_modelo IS NOT NULL
               AND (i.estado = 'borrador' OR i.id IS NULL OR v.id IS NULL OR v.valoracion = 'evaluar_despues')
             ORDER BY a.fecha_analisis DESC
             LIMIT 20"
        );
        $consulta->execute(['id_medico' => $idMedico]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }
}
