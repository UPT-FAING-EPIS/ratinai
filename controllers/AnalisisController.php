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
        $id_carpeta_input = (int)($_POST['id_carpeta'] ?? 0);

        $id_paciente = null;
        if (!empty($dni_paciente) && strlen($dni_paciente) === 8 && ctype_digit($dni_paciente)) {
            $pacModel = new PacienteModel();
            $paciente = $pacModel->buscarPorDNI($dni_paciente);
            if ($paciente) {
                $id_paciente = $paciente['id'];
            }
        }

        // Validar que la carpeta pertenezca al médico (si se especificó)
        $id_carpeta = null;
        if ($id_carpeta_input > 0) {
            require_once __DIR__ . '/../models/CarpetaModel.php';
            $carpetaModel = new CarpetaModel();
            $carpeta = $carpetaModel->obtenerPorId($id_carpeta_input);
            if ($carpeta && (int)$carpeta['id_medico'] === (int)$id_medico) {
                $id_carpeta = $id_carpeta_input;
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

        echo json_encode([
            'success' => true,
            'data' => $resJson,
            'imagen_path' => $relPath
        ]);
    }

    public function registrar_final() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }
        if (!isset($_SESSION['user_id']) || $_SESSION['rol_codigo'] !== 'MED') {
            echo json_encode(['success' => false, 'error' => 'Sesión expirada', 'expired' => true]);
            return;
        }

        $id_medico = $_SESSION['user_id'];
        $dni_paciente = trim($_POST['dni_paciente'] ?? '');
        $id_carpeta_input = (int)($_POST['id_carpeta'] ?? 0);
        $imagen_path = trim($_POST['imagen_path'] ?? '');
        $diagnostico_medico = trim($_POST['diagnostico_medico'] ?? '');

        if(empty($imagen_path)) {
            echo json_encode(['success' => false, 'error' => 'Falta la imagen analizada']);
            return;
        }

        $id_paciente = null;
        if (!empty($dni_paciente) && strlen($dni_paciente) === 8 && ctype_digit($dni_paciente)) {
            $pacModel = new PacienteModel();
            $paciente = $pacModel->buscarPorDNI($dni_paciente);
            if ($paciente) {
                $id_paciente = $paciente['id'];
            }
        }

        $id_carpeta = null;
        if ($id_carpeta_input > 0) {
            require_once __DIR__ . '/../models/CarpetaModel.php';
            $carpetaModel = new CarpetaModel();
            $carpeta = $carpetaModel->obtenerPorId($id_carpeta_input);
            if ($carpeta && (int)$carpeta['id_medico'] === (int)$id_medico) {
                $id_carpeta = $id_carpeta_input;
            }
        }

        $data = [
            'id_medico'              => $id_medico,
            'id_paciente'            => $id_paciente,
            'id_carpeta'             => $id_carpeta,
            'imagen_path'            => $imagen_path,
            'resultado_principal'    => $_POST['resultado_principal'] ?? 'Desconocido',
            'probabilidad_principal' => (float)($_POST['probabilidad_principal'] ?? 0),
            'probabilidad_normal'    => (float)($_POST['probabilidad_normal'] ?? 0),
            'probabilidad_diabetes'  => (float)($_POST['probabilidad_diabetes'] ?? 0),
            'probabilidad_glaucoma'  => (float)($_POST['probabilidad_glaucoma'] ?? 0),
            'probabilidad_catarata'  => (float)($_POST['probabilidad_catarata'] ?? 0),
            'diagnostico_medico'     => $diagnostico_medico ?: null,
            'alerta_anomalia'        => (int)($_POST['alerta_anomalia'] ?? 0),
            'es_referencial'         => (int)($_POST['es_referencial'] ?? 1),
            'tiempo_analisis'        => isset($_POST['tiempo_analisis']) ? (float)$_POST['tiempo_analisis'] : null
        ];

        $idAnalisis = $this->model->registrarAnalisis($data);
        if (!$idAnalisis) {
            echo json_encode(['success' => false, 'error' => 'Error al registrar análisis en base de datos']);
            return;
        }

        echo json_encode([
            'success' => true,
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
                'diagnostico_medico'     => $analisis['diagnostico_medico'] ?? null,
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
        case 'registrar_final': $controller->registrar_final(); break;
        case 'datos_pdf':  $controller->datosPdf();   break;
    }
}
