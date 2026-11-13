<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/AnalisisModel.php';
require_once __DIR__ . '/../models/PacienteModel.php';
require_once __DIR__ . '/../models/CarpetaModel.php';
require_once __DIR__ . '/../models/InformeClinicoModel.php';
require_once __DIR__ . '/../services/ServicioBorradorClinicoLocal.php';
require_once __DIR__ . '/../services/ServicioBorradorClinicoOpenAI.php';
require_once __DIR__ . '/../services/ServicioPdfClinico.php';
require_once __DIR__ . '/../services/ServicioAlmacenamientoInstitucionalLocal.php';
require_once __DIR__ . '/../services/ServicioAlmacenamientoInforme.php';

class AnalisisController
{
    /** Reúne la actividad clínica y las tareas pendientes del panel médico. */
    public static function datosPanelMedico(int $idMedico): array
    {
        $analisis = new AnalisisModel();
        $informes = new InformeClinicoModel();
        $pendientes = $informes->obtenerPendientesMedico($idMedico);
        $aprobados = 0;
        try {
            $aprobados = $informes->contarAprobadosMedico($idMedico);
        } catch (Throwable $error) {
            error_log('No se pudo contar informes médicos: ' . $error->getMessage());
        }
        return [
            'kpis' => $analisis->getKPIsMedico($idMedico),
            'distribucion' => $analisis->getDistribucionResultados($idMedico),
            'actividad' => $analisis->getActividadUltimos7Dias($idMedico),
            'recientes' => $analisis->getAnalisisRecientes($idMedico, 8),
            'criticos' => $analisis->getCasosCriticosRecientes($idMedico, 8),
            'pendientes' => $pendientes,
            'informesAprobados' => $aprobados,
        ];
    }

    public static function casosParaSeguimiento(int $idMedico): array
    {
        return (new AnalisisModel())->getSeguimientoCasosCriticos($idMedico, 50);
    }
    private const TAMANO_MAXIMO_IMAGEN = 10485760;
    private const TIEMPO_MAXIMO_SERVICIO = 30;
    private const LONGITUD_MAXIMA_INFORME = 12000;
    private const LONGITUD_MAXIMA_MOTIVO = 255;
    private const LONGITUD_MAXIMA_COMENTARIO = 1000;
    private const OJOS_PERMITIDOS = ['derecho', 'izquierdo'];
    private const VALORACIONES_PERMITIDAS = ['coincido', 'discrepo', 'evaluar_despues'];
    private const RESULTADOS_CLINICOS_PERMITIDOS = ['diabetes', 'glaucoma', 'catarata', 'normal'];
    private const PROBABILIDAD_MINIMA = 0.0;
    private const PROBABILIDAD_MAXIMA = 100.0;

    private AnalisisModel $model;
    private InformeClinicoModel $modeloInforme;

    public function __construct(?AnalisisModel $modeloAnalisis = null, ?InformeClinicoModel $modeloInforme = null)
    {
        $this->model = $modeloAnalisis ?? new AnalisisModel();
        $this->modeloInforme = $modeloInforme ?? new InformeClinicoModel();
    }

    /** Ejecuta solamente los filtros CNN de contenido y calidad. */
    public function validarImagen(): void
    {
        if (!$this->esMetodo('POST')) {
            $this->responderJson(['success' => false, 'error' => 'Método no permitido'], 405);
            return;
        }
        if (!$this->sesionMedicaValida()) {
            $this->responderJson([
                'success' => false,
                'error' => 'Su sesión ha expirado. Por favor inicie sesión nuevamente.',
                'expired' => true,
            ], 401);
            return;
        }
        if ((string) env_value('ANALYSIS_CONTRACT_VERSION', 'v1') !== 'v2') {
            $this->responderJson([
                'success' => false,
                'error' => 'La aplicación todavía no está configurada para el contrato CNN v2.',
            ], 503);
            return;
        }
        if (!servicioAnalisisRemotoDisponible()) {
            $this->responderJson([
                'success' => false,
                'ai_disabled' => true,
                'error' => 'El servicio CNN v2 no está configurado. No se realizará una validación simulada.',
            ], 503);
            return;
        }

        $archivo = $_FILES['imagen'] ?? null;
        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->responderJson(['success' => false, 'error' => 'Error al subir la imagen'], 422);
            return;
        }
        $extension = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $this->responderJson(['success' => false, 'error' => 'Formato no permitido. Use JPG o PNG.'], 415);
            return;
        }
        if ((int) ($archivo['size'] ?? 0) > self::TAMANO_MAXIMO_IMAGEN) {
            $this->responderJson(['success' => false, 'error' => 'El archivo supera el máximo de 10 MB.'], 413);
            return;
        }

        try {
            $salida = $this->solicitarServicio(
                (string) $archivo['tmp_name'],
                (string) $archivo['name'],
                '/api/validar'
            );
            $validacion = $this->normalizarValidacionModelo($salida);
            $this->responderJson([
                'success' => true,
                'data' => [
                    'modelo_version' => (string) $salida['modelo_version'],
                    'uso_academico' => (bool) ($salida['uso_academico'] ?? false),
                    'advertencia_modelo' => $salida['advertencia_modelo'] ?? null,
                    'validacion' => $validacion,
                    'tiempo_analisis' => (float) $salida['tiempo_analisis'],
                ],
            ]);
        } catch (Throwable $error) {
            error_log('RetinAI validar imagen: ' . $error->getMessage());
            $this->responderJson(['success' => false, 'error' => $error->getMessage()], 422);
        }
    }

    public function analizar(): void
    {
        if (!$this->esMetodo('POST')) {
            $this->responderJson(['success' => false, 'error' => 'Método no permitido'], 405);
            return;
        }
        if (!$this->sesionMedicaValida()) {
            $this->responderJson([
                'success' => false,
                'error' => 'Su sesión ha expirado. Por favor inicie sesión nuevamente.',
                'expired' => true,
            ], 401);
            return;
        }
        if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $this->responderJson(['success' => false, 'error' => 'Error al subir la imagen'], 422);
            return;
        }

        $archivo = $_FILES['imagen'];
        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $this->responderJson([
                'success' => false,
                'error' => 'Formato no permitido. Por favor sube una imagen en formato JPG o PNG.',
            ], 415);
            return;
        }
        if ((int) $archivo['size'] > self::TAMANO_MAXIMO_IMAGEN) {
            $this->responderJson([
                'success' => false,
                'error' => 'El archivo supera el tamaño máximo permitido de 10 MB.',
            ], 413);
            return;
        }
        if (!servicioAnalisisRemotoDisponible()) {
            $this->responderJson([
                'success' => false,
                'ai_disabled' => true,
                'error' => 'El servicio CNN remoto no está configurado. No se generará ningún resultado clínico simulado.',
            ], 503);
            return;
        }

        $dniPaciente = trim((string) ($_POST['dni_paciente'] ?? ''));
        $ojo = trim((string) ($_POST['ojo'] ?? ''));
        if (!preg_match('/^\d{8}$/', $dniPaciente)) {
            $this->responderJson(['success' => false, 'error' => 'Ingrese un DNI válido de 8 dígitos.'], 422);
            return;
        }
        if (!in_array($ojo, self::OJOS_PERMITIDOS, true)) {
            $this->responderJson(['success' => false, 'error' => 'Seleccione el ojo de la retinografía.'], 422);
            return;
        }

        $modeloPaciente = new PacienteModel();
        $paciente = $modeloPaciente->buscarPorDNI($dniPaciente);
        if ($paciente === false) {
            $idPacienteCreado = $modeloPaciente->registrarPaciente($dniPaciente);
            $paciente = $modeloPaciente->buscarPorDNI($dniPaciente);
            if (!$idPacienteCreado || $paciente === false) {
                $this->responderJson(['success' => false, 'error' => 'No se pudo identificar al paciente.'], 500);
                return;
            }
        }

        $directorioCarga = __DIR__ . '/../assets/uploads/retinografias/';
        if (!is_dir($directorioCarga) && !mkdir($directorioCarga, 0750, true) && !is_dir($directorioCarga)) {
            $this->responderJson(['success' => false, 'error' => 'No se pudo preparar el almacenamiento de imágenes.'], 500);
            return;
        }

        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaDestino = $directorioCarga . $nombreArchivo;
        $rutaRelativa = 'assets/uploads/retinografias/' . $nombreArchivo;
        if (!move_uploaded_file((string) $archivo['tmp_name'], $rutaDestino)) {
            $this->responderJson(['success' => false, 'error' => 'Error al guardar la imagen en el servidor'], 500);
            return;
        }

        try {
            $salidaModelo = $this->solicitarAnalisis($rutaDestino, (string) $archivo['name']);
            $datosPersistencia = $this->normalizarSalidaModelo($salidaModelo);
            $datosPersistencia += [
                'id_medico' => (int) $_SESSION['user_id'],
                'id_paciente' => (int) $paciente['id'],
                'id_carpeta' => $this->obtenerCarpetaPermitida(
                    (int) ($_POST['id_carpeta'] ?? 0),
                    (int) $paciente['id']
                ),
                'ojo' => $ojo,
                'fecha_captura' => $this->normalizarFechaCaptura((string) ($_POST['fecha_captura'] ?? '')),
                'imagen_path' => $rutaRelativa,
                'hash_imagen' => hash_file('sha256', $rutaDestino),
                'diagnostico_medico' => null,
                'salida_original_json' => json_encode($salidaModelo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];

            $idAnalisis = $this->model->registrarAnalisis($datosPersistencia);
            if (!$idAnalisis) {
                throw new RuntimeException('No se pudo registrar el análisis.');
            }

            if ((string) env_value('ANALYSIS_CONTRACT_VERSION', 'v1') === 'v1') {
                $salidaModelo['validacion'] = null;
            }
            $salidaModelo['id_analisis'] = (int) $idAnalisis;
            $this->responderJson(['success' => true, 'data' => $salidaModelo, 'imagen_path' => $rutaRelativa]);
        } catch (Throwable $error) {
            if (is_file($rutaDestino)) {
                unlink($rutaDestino);
            }
            error_log('RetinAI analizar: ' . $error->getMessage());
            $this->responderJson(['success' => false, 'error' => $error->getMessage()], 422);
        }
    }

    public function registrar_final(): void
    {
        if (!$this->validarSolicitudMedicaPost()) {
            return;
        }
        $idAnalisis = (int) ($_POST['id_analisis'] ?? 0);
        $diagnostico = trim((string) ($_POST['diagnostico_medico'] ?? ''));
        if ($idAnalisis <= 0) {
            $this->responderJson(['success' => false, 'error' => 'ID de análisis inválido.'], 422);
            return;
        }
        $actualizado = $this->model->actualizarDiagnostico(
            $idAnalisis,
            (int) $_SESSION['user_id'],
            $diagnostico === '' ? null : $diagnostico
        );
        if (!$actualizado) {
            $this->responderJson(['success' => false, 'error' => 'Análisis no encontrado.'], 404);
            return;
        }
        $this->responderJson(['success' => true, 'id_analisis' => $idAnalisis]);
    }

    public function generarBorrador(): void
    {
        if (!$this->validarSolicitudMedicaPost()) {
            return;
        }
        $idAnalisis = (int) ($_POST['id_analisis'] ?? 0);
        $idMedico = (int) $_SESSION['user_id'];
        try {
            $analisis = $this->model->obtenerPorId($idAnalisis, $idMedico);
            if (!$this->analisisAptoParaInforme($analisis)) {
                $this->responderJson(['success' => false, 'error' => 'Solo se puede generar un informe para una retinografía evaluable.'], 422);
                return;
            }
            $informeExistente = $this->modeloInforme->obtenerPorAnalisis($idAnalisis, $idMedico);
            if ($informeExistente !== null) {
                $this->responderJson(['success' => true, 'informe' => $informeExistente]);
                return;
            }
            $controles = $this->model->obtenerControlesPrevios($idAnalisis, $idMedico);
            $advertenciaBorrador = null;
            $textoGenerado = (new ServicioBorradorClinicoLocal())->generar($analisis, $controles);
            if (filter_var(env_value('REPORT_AI_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)
                && trim((string) env_value('OPENAI_API_KEY', '')) !== '') {
                try {
                    $textoGenerado = (new ServicioBorradorClinicoOpenAI())->generar($analisis, $controles);
                } catch (Throwable $errorOpenAI) {
                    error_log('RetinAI borrador OpenAI; se usó respaldo local: ' . $errorOpenAI->getMessage());
                    $advertenciaBorrador = 'OpenAI no estuvo disponible. Se generó un borrador comparativo local con datos verificados de la base de datos.';
                }
            } else {
                $advertenciaBorrador = 'OpenAI no está configurado. Se generó un borrador comparativo local con datos verificados de la base de datos.';
            }
            $this->modeloInforme->guardarBorrador($idAnalisis, $idMedico, $textoGenerado, $textoGenerado);
            $this->responderJson([
                'success' => true,
                'informe' => $this->modeloInforme->obtenerPorAnalisis($idAnalisis, $idMedico),
                'advertencia_borrador' => $advertenciaBorrador,
            ]);
        } catch (Throwable $error) {
            error_log('RetinAI generarBorrador: ' . $error->getMessage());
            $this->responderJson([
                'success' => false,
                'error' => $error instanceof PDOException
                    ? 'No se pudo consultar o guardar el informe en la base de datos.'
                    : $error->getMessage(),
                'diagnostico' => $error instanceof PDOException ? 'DB-' . $error->getCode() : null,
            ], 422);
        }
    }

    public function guardarBorrador(): void
    {
        if (!$this->validarSolicitudMedicaPost()) {
            return;
        }
        $idAnalisis = (int) ($_POST['id_analisis'] ?? 0);
        $texto = trim((string) ($_POST['texto_informe'] ?? ''));
        if ($texto === '' || mb_strlen($texto) > self::LONGITUD_MAXIMA_INFORME) {
            $this->responderJson(['success' => false, 'error' => 'El borrador debe contener entre 1 y 12 000 caracteres.'], 422);
            return;
        }
        if (!$this->modeloInforme->actualizarTextoBorrador($idAnalisis, (int) $_SESSION['user_id'], $texto)) {
            $this->responderJson(['success' => false, 'error' => 'No existe un borrador editable para este análisis.'], 404);
            return;
        }
        $this->responderJson(['success' => true]);
    }

    public function aprobarInforme(): void
    {
        if (!$this->validarSolicitudMedicaPost()) {
            return;
        }
        $idAnalisis = (int) ($_POST['id_analisis'] ?? 0);
        $texto = trim((string) ($_POST['texto_informe'] ?? ''));
        $idMedico = (int) $_SESSION['user_id'];
        if ($texto === '' || mb_strlen($texto) > self::LONGITUD_MAXIMA_INFORME) {
            $this->responderJson(['success' => false, 'error' => 'El informe debe contener entre 1 y 12 000 caracteres.'], 422);
            return;
        }
        $analisis = $this->model->obtenerPorId($idAnalisis, $idMedico);
        if (!$this->analisisAptoParaInforme($analisis)) {
            $this->responderJson(['success' => false, 'error' => 'El análisis no es apto para aprobación.'], 422);
            return;
        }

        try {
            $borrador = $this->modeloInforme->obtenerPorAnalisis($idAnalisis, $idMedico);
            if ($borrador === null || $borrador['estado'] !== 'borrador') {
                throw new RuntimeException('No existe un borrador editable para aprobar.');
            }

            $fechaAprobacion = date('Y-m-d H:i:s');
            $vistaPreviaInforme = $borrador;
            $vistaPreviaInforme['texto_editado'] = $texto;
            $vistaPreviaInforme['fecha_aprobacion'] = $fechaAprobacion;
            $contenidoPdf = (new ServicioPdfClinico())->generar($analisis, $vistaPreviaInforme);
            $informe = $this->modeloInforme->aprobar($idAnalisis, $idMedico, $texto, $fechaAprobacion);
            try {
                $almacenamiento = (new ServicioAlmacenamientoInforme())->guardar(
                    (int) $analisis['establecimiento_id'],
                    (int) $informe['id'],
                    (string) ($analisis['codigo_paciente'] ?: 'SIN_CODIGO'),
                    $contenidoPdf,
                    [
                        'medico' => (string) ($analisis['nombre_medico'] ?? 'Medico'),
                        'carpeta' => (string) ($analisis['nombre_carpeta'] ?? 'Sin carpeta'),
                        'ojo' => (string) ($analisis['ojo'] ?? ''),
                    ]
                );
                $estadoSincronizacion = $almacenamiento['estado'];
            } catch (Throwable $errorAlmacenamiento) {
                $estadoSincronizacion = 'fallida';
            }
            $this->responderJson([
                'success' => true,
                'id_informe' => (int) $informe['id'],
                'estado_sincronizacion' => $estadoSincronizacion,
            ]);
        } catch (Throwable $error) {
            $this->responderJson(['success' => false, 'error' => $error->getMessage()], 409);
        }
    }

    public function valorarResultado(): void
    {
        if (!$this->validarSolicitudMedicaPost()) {
            return;
        }
        $idAnalisis = (int) ($_POST['id_analisis'] ?? 0);
        $valoracion = trim((string) ($_POST['valoracion'] ?? ''));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $comentario = trim((string) ($_POST['comentario'] ?? ''));
        if (!in_array($valoracion, self::VALORACIONES_PERMITIDAS, true)) {
            $this->responderJson(['success' => false, 'error' => 'Valoración no permitida.'], 422);
            return;
        }
        if ($valoracion === 'discrepo' && $motivo === '') {
            $this->responderJson(['success' => false, 'error' => 'Indique el motivo de la discrepancia.'], 422);
            return;
        }
        try {
            $this->modeloInforme->guardarValoracion(
                $idAnalisis,
                (int) $_SESSION['user_id'],
                $valoracion,
                $motivo === '' ? null : mb_substr($motivo, 0, self::LONGITUD_MAXIMA_MOTIVO),
                $comentario === '' ? null : mb_substr($comentario, 0, self::LONGITUD_MAXIMA_COMENTARIO)
            );
            $this->responderJson(['success' => true]);
        } catch (Throwable $error) {
            $this->responderJson(['success' => false, 'error' => $error->getMessage()], 403);
        }
    }

    public function comparar(): void
    {
        if (!$this->sesionMedicaValida()) {
            $this->responderJson(['success' => false, 'error' => 'Sesión inválida', 'expired' => true], 401);
            return;
        }
        $comparacion = $this->model->compararControles(
            (int) ($_GET['id_primero'] ?? 0),
            (int) ($_GET['id_segundo'] ?? 0),
            (int) $_SESSION['user_id']
        );
        if ($comparacion === null) {
            $this->responderJson(['success' => false, 'error' => 'Los controles deben pertenecer al mismo paciente, la misma carpeta y el mismo ojo.'], 422);
            return;
        }
        $this->responderJson(['success' => true] + $comparacion);
    }

    public function descargarInforme(): void
    {
        if (!$this->sesionMedicaValida()) {
            http_response_code(401);
            return;
        }
        $idAnalisis = (int) ($_GET['id_analisis'] ?? 0);
        $informe = $this->modeloInforme->obtenerPorAnalisis($idAnalisis, (int) $_SESSION['user_id']);
        if ($informe === null || $informe['estado'] !== 'aprobado' || empty($informe['ruta_pdf'])) {
            http_response_code(404);
            return;
        }
        $rutaBase = realpath(__DIR__ . '/../almacenamiento/informes');
        $rutaPdf = realpath(__DIR__ . '/../' . $informe['ruta_pdf']);
        $prefijoPermitido = $rutaBase === false ? '' : $rutaBase . DIRECTORY_SEPARATOR;
        if ($rutaBase === false || $rutaPdf === false || !str_starts_with($rutaPdf, $prefijoPermitido) || !is_file($rutaPdf)) {
            http_response_code(404);
            return;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="RetinAI_Informe_' . (int) $informe['id'] . '.pdf"');
        header('Content-Length: ' . filesize($rutaPdf));
        readfile($rutaPdf);
    }

    public function datosPdf(): void
    {
        if (!$this->sesionMedicaValida()) {
            $this->responderJson(['success' => false, 'error' => 'Sesión inválida', 'expired' => true], 401);
            return;
        }
        $analisis = $this->model->obtenerPorId(
            (int) ($_GET['id_analisis'] ?? 0),
            (int) $_SESSION['user_id']
        );
        if ($analisis === false) {
            $this->responderJson(['success' => false, 'error' => 'Análisis no encontrado'], 404);
            return;
        }
        $this->responderJson(['success' => true, 'analisis' => $analisis]);
    }

    private function solicitarAnalisis(string $rutaImagen, string $nombreOriginal): array
    {
        return $this->solicitarServicio($rutaImagen, $nombreOriginal, '/api/analizar');
    }

    private function solicitarServicio(string $rutaImagen, string $nombreOriginal, string $endpoint): array
    {
        $urlServicio = rtrim((string) env_value('ANALYSIS_API_URL', ''), '/');
        $claveServicio = (string) env_value('ANALYSIS_API_KEY', '');
        $rutaCertificado = __DIR__ . '/../certs/retinai-ai-ca.crt';
        $partesUrl = parse_url($urlServicio);
        $host = strtolower((string) ($partesUrl['host'] ?? ''));
        $servicioLocal = ANALYSIS_PROVIDER === 'local'
            && ($partesUrl['scheme'] ?? '') === 'http'
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
        if ($urlServicio === '' || $claveServicio === '' || (!$servicioLocal && !is_readable($rutaCertificado))) {
            throw new RuntimeException('El servicio de análisis no está configurado. Contacte al administrador.');
        }
        $conexion = curl_init($urlServicio . $endpoint);
        curl_setopt_array($conexion, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => new CURLFile($rutaImagen, (string) mime_content_type($rutaImagen), $nombreOriginal)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIEMPO_MAXIMO_SERVICIO,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $claveServicio],
        ]);
        if (!$servicioLocal) {
            curl_setopt_array($conexion, [
                CURLOPT_CAINFO => $rutaCertificado,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
        }
        $respuesta = curl_exec($conexion);
        $codigoHttp = curl_getinfo($conexion, CURLINFO_RESPONSE_CODE);
        $errorConexion = curl_error($conexion);
        curl_close($conexion);
        $salida = is_string($respuesta) ? json_decode($respuesta, true) : null;
        if ($errorConexion !== '' || $respuesta === false || $codigoHttp !== 200) {
            $detalle = is_array($salida) ? trim((string) ($salida['detail'] ?? '')) : '';
            error_log('RetinAI CNN HTTP ' . $codigoHttp . ' transporte: ' . $errorConexion . ' detalle: ' . $detalle);
            throw new RuntimeException($detalle !== ''
                ? 'El servicio CNN rechazó la solicitud: ' . $detalle
                : 'No se pudo conectar con el servicio CNN (HTTP ' . $codigoHttp . ').');
        }
        if (!is_array($salida)) {
            throw new RuntimeException('El servicio CNN devolvió una respuesta inválida.');
        }
        return $salida;
    }

    /** Valida y normaliza el contrato común de los filtros CNN v2. */
    private function normalizarValidacionModelo(array $salida): array
    {
        $validacion = $salida['validacion'] ?? null;
        if (!is_array($validacion)
            || !array_key_exists('es_retinografia', $validacion)
            || !array_key_exists('es_evaluable', $validacion)
            || !isset($salida['tiempo_analisis'])
            || !is_numeric($salida['tiempo_analisis'])
            || (float) $salida['tiempo_analisis'] < self::PROBABILIDAD_MINIMA
            || trim((string) ($salida['modelo_version'] ?? '')) === '') {
            throw new RuntimeException('El servicio CNN v2 devolvió una validación incompleta.');
        }

        $esRetinografia = filter_var($validacion['es_retinografia'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $esEvaluable = filter_var($validacion['es_evaluable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($esRetinografia === null || $esEvaluable === null || (!$esRetinografia && $esEvaluable)) {
            throw new RuntimeException('El servicio CNN devolvió estados de validación incoherentes.');
        }

        $motivo = trim((string) ($validacion['motivo_rechazo'] ?? ''));
        if ((!$esRetinografia || !$esEvaluable) && $motivo === '') {
            throw new RuntimeException('El servicio CNN rechazó la imagen sin indicar el motivo.');
        }

        return [
            'es_retinografia' => $esRetinografia,
            'probabilidad_retinografia' => $this->obtenerProbabilidadObligatoria(
                $validacion,
                'probabilidad_retinografia',
                'retinografía'
            ),
            'es_evaluable' => $esEvaluable,
            'probabilidad_calidad' => $this->obtenerProbabilidadObligatoria(
                $validacion,
                'probabilidad_calidad',
                'calidad'
            ),
            'motivo_rechazo' => $esRetinografia && $esEvaluable ? null : $motivo,
        ];
    }

    private function normalizarSalidaModelo(array $salida): array
    {
        if ((string) env_value('ANALYSIS_CONTRACT_VERSION', 'v1') === 'v1') {
            return $this->normalizarSalidaModeloV1($salida);
        }

        $validacion = $this->normalizarValidacionModelo($salida);
        $esRetinografia = $validacion['es_retinografia'];
        $esEvaluable = $validacion['es_evaluable'];
        $probabilidadRetinografia = $validacion['probabilidad_retinografia'];
        $probabilidadCalidad = $validacion['probabilidad_calidad'];
        $aceptada = $esRetinografia && $esEvaluable;

        $resultadoPrincipal = null;
        $probabilidadPrincipal = null;
        $probabilidadesClinicas = [
            'normal' => null,
            'diabetes' => null,
            'glaucoma' => null,
            'catarata' => null,
        ];
        $alertaAnomalia = false;
        if ($aceptada) {
            $resultadoPrincipal = trim((string) ($salida['resultado_principal'] ?? ''));
            if (!in_array($resultadoPrincipal, self::RESULTADOS_CLINICOS_PERMITIDOS, true)
                || !is_array($salida['probabilidades'] ?? null)
                || !array_key_exists('alerta_anomalia', $salida)) {
                throw new RuntimeException('La salida clínica del modelo está incompleta.');
            }
            $probabilidadPrincipal = $this->obtenerProbabilidadObligatoria(
                $salida,
                'probabilidad_principal',
                'resultado principal'
            );
            foreach (array_keys($probabilidadesClinicas) as $categoria) {
                $probabilidadesClinicas[$categoria] = $this->obtenerProbabilidadObligatoria(
                    $salida['probabilidades'],
                    $categoria,
                    $categoria
                );
            }
            $alertaAnomalia = filter_var(
                $salida['alerta_anomalia'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            if ($alertaAnomalia === null) {
                throw new RuntimeException('El servicio CNN devolvió una alerta de anomalía inválida.');
            }
        }

        return [
            'version_modelo' => (string) $salida['modelo_version'],
            'es_retinografia' => $esRetinografia ? 1 : 0,
            'probabilidad_retinografia' => $probabilidadRetinografia,
            'es_evaluable' => $esEvaluable ? 1 : 0,
            'probabilidad_calidad' => $probabilidadCalidad,
            'motivo_rechazo' => $aceptada ? null : $validacion['motivo_rechazo'],
            'resultado_principal' => $resultadoPrincipal,
            'probabilidad_principal' => $probabilidadPrincipal,
            'probabilidad_normal' => $probabilidadesClinicas['normal'],
            'probabilidad_diabetes' => $probabilidadesClinicas['diabetes'],
            'probabilidad_glaucoma' => $probabilidadesClinicas['glaucoma'],
            'probabilidad_catarata' => $probabilidadesClinicas['catarata'],
            'alerta_anomalia' => $alertaAnomalia ? 1 : 0,
            'es_referencial' => 1,
            'tiempo_analisis' => (float) $salida['tiempo_analisis'],
        ];
    }

    /** Conserva la salida clínica real de la CNN v1 sin atribuirle validaciones que no ejecuta. */
    private function normalizarSalidaModeloV1(array $salida): array
    {
        $resultadoPrincipal = trim((string) ($salida['resultado_principal'] ?? ''));
        $probabilidades = $salida['probabilidades'] ?? null;
        if (!in_array($resultadoPrincipal, self::RESULTADOS_CLINICOS_PERMITIDOS, true)
            || !is_array($probabilidades)
            || !isset($salida['tiempo_analisis'])
            || !is_numeric($salida['tiempo_analisis'])
            || (float) $salida['tiempo_analisis'] < self::PROBABILIDAD_MINIMA
            || trim((string) ($salida['modelo_version'] ?? '')) === '') {
            throw new RuntimeException('La CNN v1 devolvió una respuesta incompleta.');
        }

        $probabilidadesClinicas = [];
        foreach (self::RESULTADOS_CLINICOS_PERMITIDOS as $categoria) {
            $probabilidadesClinicas[$categoria] = $this->obtenerProbabilidadObligatoria(
                $probabilidades,
                $categoria,
                $categoria
            );
        }

        $alertaAnomalia = filter_var($salida['alerta_anomalia'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($alertaAnomalia === null) {
            throw new RuntimeException('La CNN v1 devolvió una alerta de anomalía inválida.');
        }

        return [
            'version_modelo' => (string) $salida['modelo_version'],
            'es_retinografia' => null,
            'probabilidad_retinografia' => null,
            'es_evaluable' => null,
            'probabilidad_calidad' => null,
            'motivo_rechazo' => null,
            'resultado_principal' => $resultadoPrincipal,
            'probabilidad_principal' => $this->obtenerProbabilidadObligatoria($salida, 'probabilidad_principal', 'resultado principal'),
            'probabilidad_normal' => $probabilidadesClinicas['normal'],
            'probabilidad_diabetes' => $probabilidadesClinicas['diabetes'],
            'probabilidad_glaucoma' => $probabilidadesClinicas['glaucoma'],
            'probabilidad_catarata' => $probabilidadesClinicas['catarata'],
            'alerta_anomalia' => $alertaAnomalia ? 1 : 0,
            'es_referencial' => 1,
            'tiempo_analisis' => (float) $salida['tiempo_analisis'],
        ];
    }

    /** Obtiene una probabilidad requerida y rechaza respuestas parciales o fuera de rango. */
    private function obtenerProbabilidadObligatoria(array $contenedor, string $clave, string $etiqueta): float
    {
        if (!array_key_exists($clave, $contenedor) || !is_numeric($contenedor[$clave])) {
            throw new RuntimeException("El servicio CNN no informó la probabilidad de {$etiqueta}.");
        }

        $probabilidad = (float) $contenedor[$clave];
        if ($probabilidad < self::PROBABILIDAD_MINIMA || $probabilidad > self::PROBABILIDAD_MAXIMA) {
            throw new RuntimeException("La probabilidad de {$etiqueta} está fuera del rango permitido.");
        }

        return $probabilidad;
    }

    private function obtenerCarpetaPermitida(int $idCarpeta, int $idPaciente): ?int
    {
        if ($idCarpeta <= 0) {
            return null;
        }
        $carpeta = (new CarpetaModel())->obtenerPorId($idCarpeta);
        if (!$carpeta
            || (int) $carpeta['id_medico'] !== (int) $_SESSION['user_id']
            || (int) $carpeta['id_paciente'] !== $idPaciente) {
            throw new RuntimeException('La carpeta seleccionada no pertenece al paciente identificado.');
        }

        return $idCarpeta;
    }

    private function normalizarFechaCaptura(string $fecha): string
    {
        if ($fecha === '') {
            return date('Y-m-d H:i:s');
        }
        $marcaTiempo = strtotime($fecha);
        return $marcaTiempo === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $marcaTiempo);
    }

    private function analisisAptoParaInforme($analisis): bool
    {
        return is_array($analisis)
            && $analisis['resultado_principal'] !== null
            && $analisis['probabilidad_principal'] !== null
            && trim((string) ($analisis['version_modelo'] ?? '')) !== ''
            && ($analisis['es_retinografia'] === null || (int) $analisis['es_retinografia'] === 1)
            && ($analisis['es_evaluable'] === null || (int) $analisis['es_evaluable'] === 1);
    }

    private function validarSolicitudMedicaPost(): bool
    {
        if (!$this->esMetodo('POST')) {
            $this->responderJson(['success' => false, 'error' => 'Método no permitido'], 405);
            return false;
        }
        if (!$this->sesionMedicaValida()) {
            $this->responderJson(['success' => false, 'error' => 'Sesión expirada', 'expired' => true], 401);
            return false;
        }
        return true;
    }

    private function sesionMedicaValida(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['rol_codigo']) && $_SESSION['rol_codigo'] === 'MED';
    }

    private function esMetodo(string $metodo): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === $metodo;
    }

    private function responderJson(array $contenido, int $codigoHttp = 200): void
    {
        http_response_code($codigoHttp);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__) && isset($_GET['action'])) {
    $controlador = new AnalisisController();
    switch ($_GET['action']) {
        case 'validar_imagen': $controlador->validarImagen(); break;
        case 'analizar': $controlador->analizar(); break;
        case 'registrar_final': $controlador->registrar_final(); break;
        case 'generar_borrador': $controlador->generarBorrador(); break;
        case 'guardar_borrador': $controlador->guardarBorrador(); break;
        case 'aprobar_informe': $controlador->aprobarInforme(); break;
        case 'valorar_resultado': $controlador->valorarResultado(); break;
        case 'comparar': $controlador->comparar(); break;
        case 'descargar_informe': $controlador->descargarInforme(); break;
        case 'datos_pdf': $controlador->datosPdf(); break;
        default: http_response_code(404);
    }
}
