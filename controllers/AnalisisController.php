<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/AnalisisModel.php';
require_once __DIR__ . '/../models/PacienteModel.php';

class AnalisisController {
    private $model;

    public function __construct() {
        $this->model = new AnalisisModel();
    }

    public function analizar() {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }

        if (!isset($_SESSION['user_id']) || $_SESSION['rol_codigo'] !== 'MED') {
            echo json_encode(['success' => false, 'error' => 'Su sesión ha expirado. Por favor inicie sesión nuevamente.', 'expired' => true]);
            return;
        }

        $id_medico = $_SESSION['user_id'];
        $dni_paciente = trim($_POST['dni_paciente'] ?? '');

        $id_paciente = null;
        if (!empty($dni_paciente) && strlen($dni_paciente) === 8 && ctype_digit($dni_paciente)) {
            $pacModel = new PacienteModel();
            $paciente = $pacModel->buscarPorDNI($dni_paciente);
            if ($paciente) {
                $id_paciente = $paciente['id'];
            }
        }

        if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error al subir la imagen']);
            return;
        }

        $file = $_FILES['imagen'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            echo json_encode(['success' => false, 'error' => 'Formato no permitido. Por favor sube una imagen en formato JPG o PNG.']);
            return;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'El archivo supera el tamaño máximo permitido de 10 MB.']);
            return;
        }

        $uploadDir = __DIR__ . '/../assets/uploads/retinografias/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('retina_') . '.' . $ext;
        $destPath = $uploadDir . $filename;
        $relPath = 'assets/uploads/retinografias/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'error' => 'Error al guardar la imagen en el servidor']);
            return;
        }

        // --- LLAMADA AL MODELO EXTERNO ---
        $ip_ec2 = '18.119.14.223';
        $ch = curl_init("http://{$ip_ec2}:8000/api/analizar");
        $curlFile = new CURLFile($destPath, mime_content_type($destPath), $file['name']);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $curlFile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30, // Permitimos que tarde lo necesario, el frontend mostrará advertencia a los 5s
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || !$response) {
            echo json_encode(['success' => false, 'error' => 'No se pudo completar el análisis. Por favor intente nuevamente o contacte al administrador.']);
            return;
        }

        $resJson = json_decode($response, true);
        if (!$resJson || !isset($resJson['resultado_principal'])) {
            echo json_encode(['success' => false, 'error' => 'Respuesta inválida del modelo CNN']);
            return;
        }

        $data = [
            'id_medico' => $id_medico,
            'id_paciente' => $id_paciente,
            'imagen_path' => $relPath,
            'resultado_principal' => $resJson['resultado_principal'],
            'probabilidad_principal' => $resJson['probabilidad_principal'] ?? ($resJson['probabilidades'][$resJson['resultado_principal']] ?? 0),
            'probabilidad_normal' => $resJson['probabilidades']['normal'] ?? 0,
            'probabilidad_diabetes' => $resJson['probabilidades']['diabetes'] ?? 0,
            'probabilidad_glaucoma' => $resJson['probabilidades']['glaucoma'] ?? 0,
            'probabilidad_catarata' => $resJson['probabilidades']['catarata'] ?? 0,
            'alerta_anomalia' => $resJson['alerta_anomalia'] ? 1 : 0,
            'es_referencial' => $resJson['es_referencial'] ? 1 : 0,
            'tiempo_analisis' => $resJson['tiempo_analisis'] ?? null
        ];

        $idAnalisis = $this->model->registrarAnalisis($data);

        if (!$idAnalisis) {
            echo json_encode(['success' => false, 'error' => 'Error al registrar análisis en base de datos']);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $resJson,
            'id_analisis' => $idAnalisis
        ]);
    }

    /**
     * Devuelve en JSON los datos de un análisis para generar el PDF desde el frontend.
     * Requiere GET param: id_analisis
     */
    public function datosPdf() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id']) || $_SESSION['rol_codigo'] !== 'MED') {
            echo json_encode(['success' => false, 'error' => 'Sesión inválida', 'expired' => true]);
            return;
        }

        $id_analisis = (int)($_GET['id_analisis'] ?? 0);
        if ($id_analisis <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de análisis inválido']);
            return;
        }

        $id_medico = (int)$_SESSION['user_id'];
        $analisis  = $this->model->obtenerPorId($id_analisis, $id_medico);

        if (!$analisis) {
            echo json_encode(['success' => false, 'error' => 'Análisis no encontrado']);
            return;
        }

        // Convertir imagen a base64 para incrustarla en el PDF sin problemas de ruta
        $imagePath = __DIR__ . '/../' . $analisis['imagen_path'];
        $imageB64  = '';
        if (file_exists($imagePath)) {
            $mime     = mime_content_type($imagePath);
            $imageB64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($imagePath));
        }

        echo json_encode([
            'success'   => true,
            'analisis'  => [
                'id'                     => $analisis['id'],
                'nombre_medico'          => $analisis['nombre_medico'],
                'cmp_medico'             => $analisis['cmp_medico'],
                'especialidad_medico'    => $analisis['especialidad_medico'],
                'fecha_analisis'         => $analisis['fecha_analisis'],
                'resultado_principal'    => $analisis['resultado_principal'],
                'probabilidad_principal' => (float)$analisis['probabilidad_principal'],
                'probabilidad_normal'    => (float)$analisis['probabilidad_normal'],
                'probabilidad_diabetes'  => (float)$analisis['probabilidad_diabetes'],
                'probabilidad_glaucoma'  => (float)$analisis['probabilidad_glaucoma'],
                'probabilidad_catarata'  => (float)$analisis['probabilidad_catarata'],
                'alerta_anomalia'        => (bool)$analisis['alerta_anomalia'],
                'codigo_paciente'        => $analisis['codigo_paciente'] ?? null,
                'dni_paciente'           => $analisis['dni_paciente'] ?? null,
                'imagen_b64'             => $imageB64,
            ]
        ]);
    }
}

if (isset($_GET['action'])) {
    $controller = new AnalisisController();
    switch ($_GET['action']) {
        case 'analizar':   $controller->analizar();   break;
        case 'datos_pdf':  $controller->datosPdf();   break;
    }
}
