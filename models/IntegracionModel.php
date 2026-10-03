<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

class IntegracionModel
{
    private const LONGITUD_MAXIMA_MENSAJE = 500;
    private const LIMITE_SINCRONIZACIONES = 100;
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        $this->conexion = $conexion ?? (new Database())->getConnection();
    }

    public function obtenerConfiguracion(int $idEstablecimiento): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM configuraciones_almacenamiento WHERE id_establecimiento = :id LIMIT 1'
        );
        $consulta->execute(['id' => $idEstablecimiento]);
        $configuracion = $consulta->fetch(PDO::FETCH_ASSOC);

        return $configuracion === false ? null : $configuracion;
    }

    /** Un registro por establecimiento garantiza un único proveedor activo. */
    public function guardarAutorizacion(int $idEstablecimiento, string $proveedor, string $configuracionCifrada, string $correo): void
    {
        $consulta = $this->conexion->prepare(
            "INSERT INTO configuraciones_almacenamiento
                (id_establecimiento, proveedor, configuracion_cifrada, activo, ultima_prueba_estado, ultima_prueba_mensaje, ultima_prueba_fecha)
             VALUES (:id, :proveedor, :configuracion, 1, 'correcta', :mensaje, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE proveedor = VALUES(proveedor), configuracion_cifrada = VALUES(configuracion_cifrada),
                activo = 1, ultima_prueba_estado = 'correcta', ultima_prueba_mensaje = VALUES(ultima_prueba_mensaje),
                ultima_prueba_fecha = CURRENT_TIMESTAMP"
        );
        $consulta->execute([
            'id' => $idEstablecimiento,
            'proveedor' => $proveedor,
            'configuracion' => $configuracionCifrada,
            'mensaje' => mb_substr('Conectado: ' . $correo, 0, self::LONGITUD_MAXIMA_MENSAJE),
        ]);
    }

    public function actualizarConfiguracionCifrada(int $idEstablecimiento, string $proveedor, string $configuracionCifrada): void
    {
        $consulta = $this->conexion->prepare(
            'UPDATE configuraciones_almacenamiento SET configuracion_cifrada = :configuracion
             WHERE id_establecimiento = :id AND proveedor = :proveedor AND activo = 1'
        );
        $consulta->execute(['configuracion' => $configuracionCifrada, 'id' => $idEstablecimiento, 'proveedor' => $proveedor]);
        if ($consulta->rowCount() === 0) {
            throw new RuntimeException('La autorización documental cambió durante la operación.');
        }
    }

    public function registrarEstado(int $idEstablecimiento, bool $correcta, string $mensaje): void
    {
        $consulta = $this->conexion->prepare(
            'UPDATE configuraciones_almacenamiento
             SET ultima_prueba_estado = :estado, ultima_prueba_mensaje = :mensaje, ultima_prueba_fecha = CURRENT_TIMESTAMP
             WHERE id_establecimiento = :id'
        );
        $consulta->execute([
            'estado' => $correcta ? 'correcta' : 'fallida',
            'mensaje' => mb_substr($mensaje, 0, self::LONGITUD_MAXIMA_MENSAJE),
            'id' => $idEstablecimiento,
        ]);
    }

    public function registrarPrueba(int $idEstablecimiento, bool $correcta, string $mensaje): void
    {
        $consulta = $this->conexion->prepare(
            "INSERT INTO configuraciones_almacenamiento
                (id_establecimiento, proveedor, activo, ultima_prueba_estado, ultima_prueba_mensaje, ultima_prueba_fecha)
             VALUES
                (:id_establecimiento, 'local', 1, :estado, :mensaje, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                ultima_prueba_estado = VALUES(ultima_prueba_estado),
                ultima_prueba_mensaje = VALUES(ultima_prueba_mensaje), ultima_prueba_fecha = CURRENT_TIMESTAMP"
        );
        $consulta->execute([
            'id_establecimiento' => $idEstablecimiento,
            'estado' => $correcta ? 'correcta' : 'fallida',
            'mensaje' => mb_substr($mensaje, 0, self::LONGITUD_MAXIMA_MENSAJE),
        ]);
    }

    public function listarSincronizaciones(int $idEstablecimiento): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT s.id, s.estado, s.proveedor, s.intentos, s.ultimo_error, s.ruta_remota,
                    s.fecha_actualizacion, s.fecha_completada, i.id AS id_informe,
                    i.version, p.codigo_paciente
             FROM sincronizaciones_informes s
             INNER JOIN informes_clinicos i ON i.id = s.id_informe
             INNER JOIN analisis_retinales a ON a.id = i.id_analisis
             LEFT JOIN pacientes p ON p.id = a.id_paciente
             WHERE s.id_establecimiento = :id_establecimiento
             ORDER BY s.fecha_actualizacion DESC
             LIMIT ' . self::LIMITE_SINCRONIZACIONES
        );
        $consulta->execute(['id_establecimiento' => $idEstablecimiento]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerSincronizacion(int $idSincronizacion, int $idEstablecimiento): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT s.*, i.id AS id_informe, i.id_analisis, i.texto_editado, i.version,
                    i.fecha_aprobacion, p.codigo_paciente
             FROM sincronizaciones_informes s
             INNER JOIN informes_clinicos i ON i.id = s.id_informe
             INNER JOIN analisis_retinales a ON a.id = i.id_analisis
             LEFT JOIN pacientes p ON p.id = a.id_paciente
             WHERE s.id = :id AND s.id_establecimiento = :id_establecimiento
             LIMIT 1'
        );
        $consulta->execute(['id' => $idSincronizacion, 'id_establecimiento' => $idEstablecimiento]);
        $sincronizacion = $consulta->fetch(PDO::FETCH_ASSOC);

        return $sincronizacion === false ? null : $sincronizacion;
    }

    public function obtenerResumenGlobal(): array
    {
        return $this->conexion->query(
            "SELECT e.id, e.nombre,
                    COALESCE(c.proveedor, 'sin_configurar') AS proveedor,
                    COALESCE(c.ultima_prueba_estado, 'sin_probar') AS estado_conexion,
                    SUM(CASE WHEN s.estado = 'completada' THEN 1 ELSE 0 END) AS completadas,
                    SUM(CASE WHEN s.estado = 'fallida' THEN 1 ELSE 0 END) AS fallidas,
                    SUM(CASE WHEN s.estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes
             FROM establecimientos e
             LEFT JOIN configuraciones_almacenamiento c ON c.id_establecimiento = e.id
             LEFT JOIN sincronizaciones_informes s ON s.id_establecimiento = e.id
             GROUP BY e.id, e.nombre, c.proveedor, c.ultima_prueba_estado
             ORDER BY e.nombre"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarEstadosPorEstablecimientos(array $idsEstablecimientos): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsEstablecimientos))));
        if ($ids === []) {
            return ['completadas' => 0, 'fallidas' => 0, 'pendientes' => 0];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $consulta = $this->conexion->prepare(
            "SELECT
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) AS completadas,
                SUM(CASE WHEN estado = 'fallida' THEN 1 ELSE 0 END) AS fallidas,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes
             FROM sincronizaciones_informes
             WHERE id_establecimiento IN ({$marcadores})"
        );
        $consulta->execute($ids);
        $resultado = $consulta->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'completadas' => (int) ($resultado['completadas'] ?? 0),
            'fallidas' => (int) ($resultado['fallidas'] ?? 0),
            'pendientes' => (int) ($resultado['pendientes'] ?? 0),
        ];
    }

    public function obtenerCalidadModelo(): array
    {
        return $this->conexion->query(
            "SELECT COALESCE(a.version_modelo, 'no_registrada') AS version_modelo,
                    COALESCE(e.nombre, 'Sin establecimiento') AS establecimiento,
                    COUNT(DISTINCT a.id) AS total_analisis,
                    COUNT(DISTINCT CASE WHEN v.valoracion IN ('coincido','discrepo') THEN v.id END) AS evaluados,
                    COUNT(DISTINCT CASE WHEN v.valoracion = 'coincido' THEN v.id END) AS coincidencias,
                    COUNT(DISTINCT CASE WHEN v.valoracion = 'discrepo' THEN v.id END) AS discrepancias,
                    COUNT(DISTINCT CASE WHEN v.id IS NULL OR v.valoracion = 'evaluar_despues' THEN a.id END) AS sin_evaluar
             FROM analisis_retinales a
             INNER JOIN usuarios u ON u.id = a.id_medico
             LEFT JOIN establecimientos e ON e.id = u.establecimiento_id
             LEFT JOIN valoraciones_ia v ON v.id_analisis = a.id
             WHERE (a.es_retinografia = 1 OR a.es_retinografia IS NULL)
               AND (a.es_evaluable = 1 OR a.es_evaluable IS NULL)
               AND a.resultado_principal IS NOT NULL
               AND a.version_modelo IS NOT NULL
               AND a.version_modelo <> 'demostracion-local-no-clinica'
             GROUP BY a.version_modelo, e.id, e.nombre
             ORDER BY a.version_modelo, e.nombre"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
