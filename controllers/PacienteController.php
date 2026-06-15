<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/PacienteModel.php';

class PacienteController {
    private $model;

    public function __construct() {
        $this->model = new PacienteModel();
    }

    public function buscar_registrar() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }

        $dni = trim($_POST['dni'] ?? '');
        if (strlen($dni) !== 8 || !ctype_digit($dni)) {
            echo json_encode(['success' => false, 'error' => 'DNI inválido']);
            return;
        }

        $paciente = $this->model->buscarPorDNI($dni);
        if ($paciente) {
            echo json_encode(['success' => true, 'paciente' => $paciente, 'nuevo' => false]);
            return;
        }

        // Registrar
        $id = $this->model->registrarPaciente($dni);
        if ($id) {
            $paciente = $this->model->buscarPorDNI($dni);
            echo json_encode(['success' => true, 'paciente' => $paciente, 'nuevo' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al registrar paciente']);
        }
    }
}

if (isset($_GET['action'])) {
    $controller = new PacienteController();
    switch ($_GET['action']) {
        case 'buscar_registrar': $controller->buscar_registrar(); break;
    }
}
