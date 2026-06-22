<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/CarpetaModel.php';

class CarpetaController {
    private $model;

    public function __construct() {
        $this->model = new CarpetaModel();
    }

    public function listar() {
        header('Content-Type: application/json');

        if (!$this->checkSession()) return;

        $id_paciente = (int)($_POST['id_paciente'] ?? 0);
        if ($id_paciente <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de paciente inválido']);
            return;
        }

        $id_medico = (int)$_SESSION['user_id'];
        $carpetas  = $this->model->listarPorPaciente($id_paciente, $id_medico);

        echo json_encode(['success' => true, 'carpetas' => $carpetas]);
    }

    /**
     * Crea una nueva carpeta para un paciente.
     * POST: id_paciente, nombre, descripcion (opcional)
     */
    public function crear() {
        header('Content-Type: application/json');

        if (!$this->checkSession()) return;

        $id_paciente = (int)($_POST['id_paciente'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '') ?: null;

        if ($id_paciente <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de paciente inválido']);
            return;
        }
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'error' => 'El nombre de la carpeta es obligatorio']);
            return;
        }
        if (strlen($nombre) > 100) {
            echo json_encode(['success' => false, 'error' => 'El nombre no puede superar los 100 caracteres']);
            return;
        }

        $id_medico  = (int)$_SESSION['user_id'];
        $id_carpeta = $this->model->crear($id_paciente, $id_medico, $nombre, $descripcion);

        if ($id_carpeta) {
            $carpeta = $this->model->obtenerPorId($id_carpeta);
            // Agregar total_analisis = 0 para consistencia con listar()
            $carpeta['total_analisis'] = 0;
            echo json_encode(['success' => true, 'carpeta' => $carpeta]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al crear la carpeta']);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function checkSession() {
        if (!isset($_SESSION['user_id']) || $_SESSION['rol_codigo'] !== 'MED') {
            echo json_encode(['success' => false, 'error' => 'Sesión inválida', 'expired' => true]);
            return false;
        }
        return true;
    }
}

if (isset($_GET['action'])) {
    $controller = new CarpetaController();
    switch ($_GET['action']) {
        case 'listar': $controller->listar(); break;
        case 'crear':  $controller->crear();  break;
    }
}
