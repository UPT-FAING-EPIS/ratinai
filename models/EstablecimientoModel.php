<?php
require_once __DIR__ . '/../config/config.php';

class EstablecimientoModel {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    /** Datos agregados del panel global, sin exponer consultas a la plantilla. */
    public function obtenerPanelGlobal(): array {
        $db = $this->db;
        return [
            'indicadores' => [
                'establecimientos' => (int)$db->query('SELECT COUNT(*) FROM establecimientos')->fetchColumn(),
                'medicos' => (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=1")->fetchColumn(),
                'administradores' => (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='ADM' AND activo=1")->fetchColumn(),
                'solicitudes' => (int)$db->query("SELECT COUNT(*) FROM solicitudes_establecimiento WHERE estado='pendiente'")->fetchColumn(),
                'analisis' => (int)$db->query('SELECT COUNT(*) FROM analisis_retinales')->fetchColumn(),
                'informes' => (int)$db->query("SELECT COUNT(*) FROM informes_clinicos WHERE estado='aprobado'")->fetchColumn(),
            ],
            'usuariosPorRol' => $db->query("SELECT rol_codigo AS etiqueta, COUNT(*) AS total FROM usuarios WHERE activo=1 GROUP BY rol_codigo ORDER BY rol_codigo")->fetchAll(PDO::FETCH_ASSOC),
            'solicitudesPorEstado' => $db->query("SELECT estado AS etiqueta, COUNT(*) AS total FROM solicitudes_establecimiento GROUP BY estado ORDER BY FIELD(estado,'pendiente','aprobado','rechazado')")->fetchAll(PDO::FETCH_ASSOC),
            'actividadMensual' => $db->query("SELECT DATE_FORMAT(fecha_analisis,'%Y-%m') AS periodo, COUNT(*) AS total FROM analisis_retinales WHERE fecha_analisis>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY periodo ORDER BY periodo")->fetchAll(PDO::FETCH_ASSOC),
            'establecimientosActivos' => $db->query("SELECT e.nombre, COUNT(a.id) AS analisis, COUNT(DISTINCT u.id) AS medicos FROM establecimientos e LEFT JOIN usuarios u ON u.establecimiento_id=e.id AND u.rol_codigo='MED' AND u.activo=1 LEFT JOIN analisis_retinales a ON a.id_medico=u.id GROUP BY e.id,e.nombre ORDER BY analisis DESC,e.nombre LIMIT 8")->fetchAll(PDO::FETCH_ASSOC),
            'actividadReciente' => $db->query("SELECT a.fecha_analisis,a.resultado_principal,a.version_modelo,e.nombre AS establecimiento FROM analisis_retinales a JOIN usuarios u ON u.id=a.id_medico LEFT JOIN establecimientos e ON e.id=u.establecimiento_id ORDER BY a.fecha_analisis DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC),
            'integraciones' => $db->query("SELECT COALESCE(SUM(estado='completada'),0) AS completadas,COALESCE(SUM(estado='fallida'),0) AS fallidas,COALESCE(SUM(estado='pendiente'),0) AS pendientes FROM sincronizaciones_informes")->fetch(PDO::FETCH_ASSOC),
        ];
    }

    /** Listado de centros y cantidad de médicos activos. */
    public function listarConMedicos(): array {
        return $this->db->query("SELECT e.id,e.nombre,e.direccion,e.ruc,CASE WHEN e.id_usuario IS NULL THEN 'sin_responsable' ELSE 'activo' END AS estado,COUNT(u.id) AS medicos FROM establecimientos e LEFT JOIN usuarios u ON u.establecimiento_id=e.id AND u.rol_codigo='MED' AND u.activo=1 GROUP BY e.id ORDER BY e.nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Métricas del detalle de un establecimiento. */
    public function obtenerMetricasDetalle(int $id): array {
        $consultar = function (string $sql) use ($id) {
            $consulta = $this->db->prepare($sql);
            $consulta->execute([$id]);
            return $consulta;
        };
        return [
            'resumen' => $consultar("SELECT COUNT(DISTINCT a.id_paciente) AS pacientes,COUNT(DISTINCT a.id) AS analisis,COUNT(DISTINCT CASE WHEN i.estado='aprobado' THEN i.id END) AS informes,MAX(a.fecha_analisis) AS ultima_actividad FROM usuarios u LEFT JOIN analisis_retinales a ON a.id_medico=u.id LEFT JOIN informes_clinicos i ON i.id_analisis=a.id WHERE u.establecimiento_id=?")->fetch(PDO::FETCH_ASSOC),
            'integracion' => $consultar('SELECT proveedor,ultima_prueba_estado,ultima_prueba_fecha FROM configuraciones_almacenamiento WHERE id_establecimiento=? LIMIT 1')->fetch(PDO::FETCH_ASSOC),
            'modeloUso' => $consultar('SELECT SUM(CASE WHEN a.alerta_anomalia=1 THEN 1 ELSE 0 END) AS alertas,AVG(a.probabilidad_principal) AS confianza_media,AVG(a.tiempo_analisis) AS tiempo_medio FROM analisis_retinales a INNER JOIN usuarios u ON u.id=a.id_medico WHERE u.establecimiento_id=?')->fetch(PDO::FETCH_ASSOC),
            'actividad' => $consultar('SELECT DATE(a.fecha_analisis) AS fecha,COUNT(*) AS total FROM analisis_retinales a INNER JOIN usuarios u ON u.id=a.id_medico WHERE u.establecimiento_id=? AND a.fecha_analisis >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(a.fecha_analisis) ORDER BY fecha')->fetchAll(PDO::FETCH_ASSOC),
            'resultadosModelo' => $consultar("SELECT COALESCE(NULLIF(a.resultado_principal,''),'Sin resultado') AS etiqueta,COUNT(*) AS total FROM analisis_retinales a INNER JOIN usuarios u ON u.id=a.id_medico WHERE u.establecimiento_id=? GROUP BY etiqueta ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** Datos del panel institucional limitados a los establecimientos del administrador. */
    public function obtenerPanelAdministrador(array $ids, int $idAdministrador): array {
        $datos = ['indicadores' => ['establecimientos'=>count($ids),'medicos'=>0,'pacientes'=>0,'analisis'=>0,'informes'=>0,'pendientes'=>0],
            'actividad'=>[], 'resultados'=>[], 'medicos'=>[], 'sincronizaciones'=>[], 'analisisRecientes'=>[]];
        if ($ids === []) return $datos;
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $consultar = function (string $sql, array $parametros = []) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($parametros);
            return $stmt;
        };
        $datos['indicadores']['medicos'] = (int)$consultar("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=1 AND establecimiento_id IN ($marcadores)", $ids)->fetchColumn();
        $datos['indicadores']['pacientes'] = (int)$consultar("SELECT COUNT(DISTINCT a.id_paciente) FROM analisis_retinales a JOIN usuarios u ON u.id=a.id_medico WHERE u.establecimiento_id IN ($marcadores)", $ids)->fetchColumn();
        $datos['indicadores']['analisis'] = (int)$consultar("SELECT COUNT(*) FROM analisis_retinales a JOIN usuarios u ON u.id=a.id_medico WHERE u.establecimiento_id IN ($marcadores)", $ids)->fetchColumn();
        $datos['indicadores']['informes'] = (int)$consultar("SELECT COUNT(*) FROM informes_clinicos i JOIN analisis_retinales a ON a.id=i.id_analisis JOIN usuarios u ON u.id=a.id_medico WHERE i.estado='aprobado' AND u.establecimiento_id IN ($marcadores)", $ids)->fetchColumn();
        $datos['indicadores']['pendientes'] = (int)$consultar("SELECT COUNT(*) FROM solicitudes_establecimiento WHERE estado='pendiente' AND id_usuario_solicitante=?", [$idAdministrador])->fetchColumn();
        $datos['actividad'] = $consultar("SELECT DATE_FORMAT(a.fecha_analisis,'%Y-%m') AS periodo,COUNT(*) AS total FROM analisis_retinales a JOIN usuarios u ON u.id=a.id_medico WHERE u.establecimiento_id IN ($marcadores) AND a.fecha_analisis>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) GROUP BY periodo ORDER BY periodo", $ids)->fetchAll(PDO::FETCH_ASSOC);
        $datos['resultados'] = $consultar("SELECT COALESCE(a.resultado_principal,'Sin resultado') AS etiqueta,COUNT(*) AS total FROM analisis_retinales a JOIN usuarios u ON u.id=a.id_medico WHERE u.establecimiento_id IN ($marcadores) GROUP BY etiqueta ORDER BY total DESC", $ids)->fetchAll(PDO::FETCH_ASSOC);
        $datos['medicos'] = $consultar("SELECT u.nombre,u.cmp,COUNT(a.id) AS analisis,MAX(a.fecha_analisis) AS ultima_actividad FROM usuarios u LEFT JOIN analisis_retinales a ON a.id_medico=u.id WHERE u.rol_codigo='MED' AND u.activo=1 AND u.establecimiento_id IN ($marcadores) GROUP BY u.id,u.nombre,u.cmp ORDER BY analisis DESC,u.nombre LIMIT 8", $ids)->fetchAll(PDO::FETCH_ASSOC);
        $datos['sincronizaciones'] = $consultar("SELECT estado,COUNT(*) AS total FROM sincronizaciones_informes WHERE id_establecimiento IN ($marcadores) GROUP BY estado", $ids)->fetchAll(PDO::FETCH_ASSOC);
        $datos['analisisRecientes'] = $consultar("SELECT a.fecha_analisis,a.resultado_principal,u.nombre AS medico,e.nombre AS establecimiento FROM analisis_retinales a JOIN usuarios u ON u.id=a.id_medico JOIN establecimientos e ON e.id=u.establecimiento_id WHERE u.establecimiento_id IN ($marcadores) ORDER BY a.fecha_analisis DESC LIMIT 8", $ids)->fetchAll(PDO::FETCH_ASSOC);
        return $datos;
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM establecimientos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByOwnerId($userId) {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, direccion, latitud, longitud, tipo, ruc FROM establecimientos WHERE id_usuario = :uid ORDER BY id ASC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza los datos editables de un establecimiento.
     */
    public function update(int $id, string $nombre, string $direccion, string $tipo, string $ruc, ?float $latitud = null, ?float $longitud = null, bool $updateCoordinates = false): bool {
        // Conserva compatibilidad con integraciones existentes que actualizan sólo los cinco datos originales.
        if (!$updateCoordinates) {
            $stmt = $this->db->prepare(
                "UPDATE establecimientos SET nombre = ?, direccion = ?, tipo = ?, ruc = ? WHERE id = ?"
            );
            return $stmt->execute([$nombre, $direccion, $tipo, $ruc, $id]);
        }
        $stmt = $this->db->prepare(
            "UPDATE establecimientos SET nombre = ?, direccion = ?, tipo = ?, ruc = ?, latitud = ?, longitud = ? WHERE id = ?"
        );
        return $stmt->execute([$nombre, $direccion, $tipo, $ruc, $latitud, $longitud, $id]);
    }

    /**
     * Retorna el administrador/titular del establecimiento.
     * Prioriza id_usuario; si no existe, busca por establecimiento_id + rol ADM.
     */
    public function getAdminByEstablecimiento(int $id): array {
        // Primero: por id_usuario del establecimiento (dueño directo)
        $stmt = $this->db->prepare("SELECT id_usuario FROM establecimientos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['id_usuario'])) {
            $stmt2 = $this->db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt2->execute([$row['id_usuario']]);
            $owner = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($owner) return [$owner];
        }

        // Fallback: administradores asociados al establecimiento
        $stmt3 = $this->db->prepare(
            "SELECT * FROM usuarios WHERE establecimiento_id = ? AND rol_codigo = 'ADM'"
        );
        $stmt3->execute([$id]);
        return $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna los médicos activos e inactivos del establecimiento.
     */
    public function getMedicosByEstablecimiento(int $id): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM usuarios WHERE establecimiento_id = ? AND rol_codigo = 'MED'"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
