<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../models/IntegracionModel.php';
require_once __DIR__ . '/../models/EstablecimientoModel.php';
require_once __DIR__ . '/../models/AnalisisModel.php';
require_once __DIR__ . '/../models/InformeClinicoModel.php';
require_once __DIR__ . '/../services/ServicioPdfClinico.php';
require_once __DIR__ . '/../services/ServicioAlmacenamientoInstitucionalLocal.php';
require_once __DIR__ . '/../services/ServicioAlmacenamientoInforme.php';
require_once __DIR__ . '/../services/ServicioOAuthDocumental.php';

class IntegracionController
{
    private const VIGENCIA_ESTADO_OAUTH = 600;
    private IntegracionModel $modelo;

    public function __construct(?IntegracionModel $modelo = null)
    {
        $this->modelo = $modelo ?? new IntegracionModel();
    }

    public function probar(): void
    {
        if (!$this->solicitudAdminValida()) return;
        $idEstablecimiento = (int) ($_POST['id_establecimiento'] ?? 0);
        if (!$this->administraEstablecimiento($idEstablecimiento)) {
            $this->responder(['success' => false, 'error' => 'Establecimiento no permitido.'], 403);
            return;
        }

        $configuracion = $this->modelo->obtenerConfiguracion($idEstablecimiento);
        $proveedor = (string) ($configuracion['proveedor'] ?? 'local');
        if (!in_array($proveedor, ['google_drive', 'onedrive'], true)) {
            $this->responder(['success' => false, 'error' => 'Conecte Google Drive o OneDrive para comprobar la cuenta.'], 422);
            return;
        }
        try {
            $servicio = new ServicioOAuthDocumental();
            $autorizacion = $servicio->descifrar((string) $configuracion['configuracion_cifrada']);
            $tokensVigentes = $servicio->renovarSiNecesario($proveedor, $autorizacion['tokens']);
            if ($tokensVigentes !== $autorizacion['tokens']) {
                $autorizacion['tokens'] = $tokensVigentes;
                $this->modelo->actualizarConfiguracionCifrada($idEstablecimiento, $proveedor, $servicio->cifrar($autorizacion));
            }
            $servicio->comprobarYPreparar($proveedor, $tokensVigentes);
            $mensaje = 'Conexión correcta con ' . $proveedor . ' (' . $autorizacion['correo'] . ').';
            $this->modelo->registrarEstado($idEstablecimiento, true, $mensaje);
            $this->responder(['success' => true, 'mensaje' => $mensaje]);
        } catch (Throwable $error) {
            $this->modelo->registrarEstado($idEstablecimiento, false, $error->getMessage());
            $this->responder(['success' => false, 'error' => $error->getMessage()], 502);
        }
    }

    public function iniciarAutorizacion(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || ($_SESSION['rol_codigo'] ?? '') !== 'ADM') {
            http_response_code(403);
            return;
        }
        $idEstablecimiento = (int) ($_GET['establecimiento'] ?? 0);
        $proveedor = (string) ($_GET['proveedor'] ?? '');
        if (!$this->administraEstablecimiento($idEstablecimiento) || !in_array($proveedor, ['google_drive', 'onedrive'], true)) {
            http_response_code(403);
            return;
        }
        try {
            $estado = bin2hex(random_bytes(24));
            $_SESSION['oauth_documental'] = [
                'estado' => $estado,
                'proveedor' => $proveedor,
                'establecimiento' => $idEstablecimiento,
                'vence_en' => time() + self::VIGENCIA_ESTADO_OAUTH,
            ];
            $url = (new ServicioOAuthDocumental())->construirUrlAutorizacion($proveedor, $estado);
            header('Location: ' . $url, true, 302);
        } catch (Throwable $error) {
            http_response_code(503);
            echo htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    public function procesarRetorno(string $proveedor): void
    {
        $pendiente = $_SESSION['oauth_documental'] ?? null;
        unset($_SESSION['oauth_documental']);
        $idEstablecimiento = (int) ($pendiente['establecimiento'] ?? 0);
        $retorno = APP_URL . '/views/admin/integraciones.php?establecimiento=' . $idEstablecimiento;
        if (!is_array($pendiente)
            || ($pendiente['proveedor'] ?? '') !== $proveedor
            || (int) ($pendiente['vence_en'] ?? 0) < time()
            || !hash_equals((string) ($pendiente['estado'] ?? ''), (string) ($_GET['state'] ?? ''))
            || ($_SESSION['rol_codigo'] ?? '') !== 'ADM'
            || !$this->administraEstablecimiento($idEstablecimiento)
            || empty($_GET['code'])) {
            header('Location: ' . $retorno . '&oauth=error', true, 302);
            return;
        }
        try {
            $servicio = new ServicioOAuthDocumental();
            $tokens = $servicio->intercambiarCodigo($proveedor, (string) $_GET['code']);
            $correo = $servicio->obtenerCorreo($proveedor, $tokens);
            $servicio->comprobarYPreparar($proveedor, $tokens);
            $this->modelo->guardarAutorizacion($idEstablecimiento, $proveedor, $servicio->cifrar([
                'correo' => $correo,
                'tokens' => $tokens,
            ]), $correo);
            header('Location: ' . $retorno . '&oauth=conectado', true, 302);
        } catch (Throwable $error) {
            error_log('Falló la autorización documental: ' . $error->getMessage());
            header('Location: ' . $retorno . '&oauth=error', true, 302);
        }
    }

    public function reintentar(): void
    {
        if (!$this->solicitudAdminValida()) return;
        $idEstablecimiento = (int) ($_POST['id_establecimiento'] ?? 0);
        $idSincronizacion = (int) ($_POST['id_sincronizacion'] ?? 0);
        if (!$this->administraEstablecimiento($idEstablecimiento)) {
            $this->responder(['success' => false, 'error' => 'Establecimiento no permitido.'], 403);
            return;
        }
        $sincronizacion = $this->modelo->obtenerSincronizacion($idSincronizacion, $idEstablecimiento);
        if ($sincronizacion === null) {
            $this->responder(['success' => false, 'error' => 'Sincronización no encontrada.'], 404);
            return;
        }

        try {
            $analisis = (new AnalisisModel())->obtenerPorId(
                (int) $sincronizacion['id_analisis'],
                (int) $this->obtenerIdMedicoDelAnalisis((int) $sincronizacion['id_analisis'])
            );
            if (!is_array($analisis)) throw new RuntimeException('No se encontró el análisis fuente.');
            $datosInforme = $sincronizacion;
            $datosInforme['id'] = $sincronizacion['id_informe'];
            $contenidoPdf = (new ServicioPdfClinico())->generar($analisis, $datosInforme);
            $configuracion = $this->modelo->obtenerConfiguracion($idEstablecimiento);
            if ($sincronizacion['proveedor'] === 'local') {
                $rutaLocal = (new ServicioAlmacenamientoInstitucionalLocal())->guardar(
                    (string) ($sincronizacion['codigo_paciente'] ?: 'SIN_CODIGO'),
                    (int) $sincronizacion['id_informe'],
                    $contenidoPdf
                );
                $modeloInforme = new InformeClinicoModel();
                $modeloInforme->registrarArchivo((int) $sincronizacion['id_informe'], $rutaLocal, hash('sha256', $contenidoPdf));
                $modeloInforme->completarSincronizacion($idSincronizacion, $rutaLocal);
                $this->responder(['success' => true, 'mensaje' => 'Copia local recuperada correctamente.']);
                return;
            }
            if (($configuracion['proveedor'] ?? '') !== $sincronizacion['proveedor']) {
                throw new RuntimeException('El proveedor del informe ya no está activo en este establecimiento.');
            }
            $almacenamiento = (new ServicioAlmacenamientoInforme())->guardar(
                $idEstablecimiento,
                (int) $sincronizacion['id_informe'],
                (string) ($sincronizacion['codigo_paciente'] ?: 'SIN_CODIGO'),
                $contenidoPdf
            );
            if ($almacenamiento['estado'] !== 'completada') {
                $this->responder(['success' => false, 'error' => 'El PDF se conservó localmente; la sincronización documental falló.'], 502);
                return;
            }
            $this->responder(['success' => true, 'mensaje' => 'Informe sincronizado correctamente.']);
        } catch (Throwable $error) {
            (new InformeClinicoModel())->fallarSincronizacion($idSincronizacion, $error->getMessage());
            $this->responder(['success' => false, 'error' => $error->getMessage()], 500);
        }
    }

    private function obtenerIdMedicoDelAnalisis(int $idAnalisis): int
    {
        $conexion = (new Database())->getConnection();
        $consulta = $conexion->prepare('SELECT id_medico FROM analisis_retinales WHERE id = :id LIMIT 1');
        $consulta->execute(['id' => $idAnalisis]);
        return (int) $consulta->fetchColumn();
    }

    private function administraEstablecimiento(int $idEstablecimiento): bool
    {
        $idUsuario = (int) ($_SESSION['user_id'] ?? 0);
        $establecimientos = (new EstablecimientoModel())->getByOwnerId($idUsuario);
        $permitidos = array_map('intval', array_column($establecimientos, 'id'));
        $asignado = (int) ($_SESSION['establecimiento_id'] ?? 0);
        if ($asignado > 0) $permitidos[] = $asignado;

        return $idEstablecimiento > 0 && in_array($idEstablecimiento, array_unique($permitidos), true);
    }

    private function solicitudAdminValida(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->responder(['success' => false, 'error' => 'Método no permitido.'], 405);
            return false;
        }
        if (($_SESSION['rol_codigo'] ?? '') !== 'ADM') {
            $this->responder(['success' => false, 'error' => 'Sesión no autorizada.', 'expired' => true], 401);
            return false;
        }
        return true;
    }

    private function responder(array $contenido, int $codigo = 200): void
    {
        http_response_code($codigo);
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (isset($_GET['action'])) {
    $controlador = new IntegracionController();
    if ($_GET['action'] === 'iniciar') $controlador->iniciarAutorizacion();
    if ($_GET['action'] === 'probar') $controlador->probar();
    if ($_GET['action'] === 'reintentar') $controlador->reintentar();
}
