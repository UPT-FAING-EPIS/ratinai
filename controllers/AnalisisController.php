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
                'id_carpeta' => $this->obtenerCarpetaPermitida((int) ($_POST['id_carpeta'] ?? 0)),
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
            $textoGenerado = (new ServicioBorradorClinicoOpenAI())->generar($analisis, $controles);
            $this->modeloInforme->guardarBorrador($idAnalisis, $idMedico, $textoGenerado, $textoGenerado);
            $this->responderJson([
                'success' => true,
                'informe' => $this->modeloInforme->obtenerPorAnalisis($idAnalisis, $idMedico),
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

    /** Comprobación temporal de las consultas previas al borrador, sin devolver datos clínicos. */
    public function diagnosticarBorrador(): void
    {
        if (!$this->validarSolicitudMedicaPost()) {
            return;
        }
        $idAnalisis = (int) ($_POST['id_analisis'] ?? 0);
        $idMedico = (int) $_SESSION['user_id'];
        $etapa = 'analisis';
        try {
            $analisis = $this->model->obtenerPorId($idAnalisis, $idMedico);
            if (!$this->analisisAptoParaInforme($analisis)) {
                $this->responderJson(['success' => false, 'etapa' => $etapa, 'error' => 'Análisis no apto'], 422);
                return;
            }
            $etapa = 'informe_existente';
            $informe = $this->modeloInforme->obtenerPorAnalisis($idAnalisis, $idMedico);
            $etapa = 'controles_previos';
            $controles = $this->model->obtenerControlesPrevios($idAnalisis, $idMedico);
            $this->responderJson(['success' => true, 'informe_existe' => $informe !== null, 'controles' => count($controles)]);
        } catch (Throwable $error) {
            error_log('RetinAI diagnosticarBorrador (' . $etapa . '): ' . $error->getMessage());
            $this->responderJson(['success' => false, 'etapa' => $etapa, 'codigo' => (string) $error->getCode()], 500);
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
            $this->responderJson(['success' => false, 'error' => 'Los controles deben pertenecer al mismo paciente y al mismo ojo.'], 422);
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
        $urlServicio = rtrim((string) env_value('ANALYSIS_API_URL', ''), '/');
        $claveServicio = (string) env_value('ANALYSIS_API_KEY', '');
        $rutaCertificado = __DIR__ . '/../certs/retinai-ai-ca.crt';
        if ($urlServicio === '' || $claveServicio === '' || !is_readable($rutaCertificado)) {
            throw new RuntimeException('El servicio de análisis no está configurado. Contacte al administrador.');
        }
        $conexion = curl_init($urlServicio . '/api/analizar');
        curl_setopt_array($conexion, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => new CURLFile($rutaImagen, (string) mime_content_type($rutaImagen), $nombreOriginal)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIEMPO_MAXIMO_SERVICIO,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $claveServicio],
            CURLOPT_CAINFO => $rutaCertificado,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $respuesta = curl_exec($conexion);
        $codigoHttp = curl_getinfo($conexion, CURLINFO_RESPONSE_CODE);
        $errorConexion = curl_error($conexion);
        curl_close($conexion);
        if ($errorConexion !== '' || $respuesta === false || $codigoHttp !== 200) {
            error_log('RetinAI CNN HTTP ' . $codigoHttp . ' transporte: ' . $errorConexion);
            throw new RuntimeException('No se pudo completar el análisis con el servicio CNN (HTTP ' . $codigoHttp . ').');
        }
        $salida = json_decode($respuesta, true);
        if (!is_array($salida)) {
            throw new RuntimeException('El servicio CNN devolvió una respuesta inválida.');
        }
        return $salida;
    }

    private function normalizarSalidaModelo(array $salida): array
    {
        if ((string) env_value('ANALYSIS_CONTRACT_VERSION', 'v1') === 'v1') {
            return $this->normalizarSalidaModeloV1($salida);
        }

        $validacion = $salida['validacion'] ?? null;
        if (!is_array($validacion)
            || !array_key_exists('es_retinografia', $validacion)
            || !array_key_exists('es_evaluable', $validacion)
            || !isset($salida['tiempo_analisis'])
            || !is_numeric($salida['tiempo_analisis'])
            || (float) $salida['tiempo_analisis'] < self::PROBABILIDAD_MINIMA
            || trim((string) ($salida['modelo_version'] ?? '')) === '') {
            throw new RuntimeException('La versión del modelo no cumple el contrato de validación RF-10.');
        }
        $esRetinografia = filter_var($validacion['es_retinografia'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $esEvaluable = filter_var($validacion['es_evaluable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($esRetinografia === null || $esEvaluable === null) {
            throw new RuntimeException('El servicio CNN devolvió estados de validación inválidos.');
        }

        $probabilidadRetinografia = $this->obtenerProbabilidadObligatoria(
            $validacion,
            'probabilidad_retinografia',
            'retinografía'
        );
        $probabilidadCalidad = $this->obtenerProbabilidadObligatoria(
            $validacion,
            'probabilidad_calidad',
            'calidad'
        );
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
        } elseif (trim((string) ($validacion['motivo_rechazo'] ?? '')) === '') {
            throw new RuntimeException('El servicio CNN rechazó la imagen sin indicar el motivo.');
        }

        return [
            'version_modelo' => (string) $salida['modelo_version'],
            'es_retinografia' => $esRetinografia ? 1 : 0,
            'probabilidad_retinografia' => $probabilidadRetinografia,
            'es_evaluable' => $esEvaluable ? 1 : 0,
            'probabilidad_calidad' => $probabilidadCalidad,
            'motivo_rechazo' => $aceptada ? null : trim((string) $validacion['motivo_rechazo']),
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

    private function obtenerCarpetaPermitida(int $idCarpeta): ?int
    {
        if ($idCarpeta <= 0) {
            return null;
        }
        $carpeta = (new CarpetaModel())->obtenerPorId($idCarpeta);
        return $carpeta && (int) $carpeta['id_medico'] === (int) $_SESSION['user_id'] ? $idCarpeta : null;
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

if (isset($_GET['action'])) {
    $controlador = new AnalisisController();
    switch ($_GET['action']) {
        case 'analizar': $controlador->analizar(); break;
        case 'registrar_final': $controlador->registrar_final(); break;
        case 'generar_borrador': $controlador->generarBorrador(); break;
        case 'diagnosticar_borrador': $controlador->diagnosticarBorrador(); break;
        case 'guardar_borrador': $controlador->guardarBorrador(); break;
        case 'aprobar_informe': $controlador->aprobarInforme(); break;
        case 'valorar_resultado': $controlador->valorarResultado(); break;
        case 'comparar': $controlador->comparar(); break;
        case 'descargar_informe': $controlador->descargarInforme(); break;
        case 'datos_pdf': $controlador->datosPdf(); break;
        default: http_response_code(404);
    }
}
