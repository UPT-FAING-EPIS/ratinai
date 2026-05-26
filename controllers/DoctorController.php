<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/DoctorModel.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../config/session_guard.php';
require_once __DIR__ . '/../utils/PasswordHelper.php';

class DoctorController {
    private $model;

    public function __construct() {
        $this->model = new DoctorModel();
    }

    private function baseUrl(): string {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base = rtrim(dirname($scriptDir), '/') . '/';
        $parts = explode('/', rtrim($scriptDir, '/'));
        array_pop($parts);
        $root = implode('/', $parts);
        return ($root === '' ? '/' : $root . '/');
    }

    private function redirect($path) {
        header("Location: " . $this->baseUrl() . ltrim($path, '/'));
        exit();
    }

    public function createDoctor() {
        require_role('ADM');
        $user = current_user();
        $est_id = (int)($user['establecimiento_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre       = trim($_POST['nombre']       ?? '');
            $correo       = trim($_POST['correo']       ?? '');
            $cmp          = trim($_POST['cmp']          ?? '');
            $esp_sel      = trim($_POST['especialidad'] ?? '');
            $esp_nueva    = trim($_POST['esp_nueva']    ?? '');
            $password_override = trim($_POST['password_override'] ?? '');
            $password_temp   = ($password_override !== '') ? $password_override : PasswordHelper::generateTemp(12);

            $especialidad_final = ($esp_sel === '__nueva__') ? $esp_nueva : $esp_sel;

            $errors = [];
            if (empty($nombre)) $errors[] = "El nombre es obligatorio.";
            if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errors[] = "Ingrese un correo electrónico válido.";
            if (empty($cmp)) $errors[] = "El número CMP es obligatorio.";
            if (empty($especialidad_final)) $errors[] = "Seleccione o ingrese una especialidad.";

            if (!empty($errors)) {
                $_SESSION['flash_errors'] = $errors;
                $_SESSION['old_post'] = $_POST;
                $this->redirect('views/admin/create_doctor.php');
            }

            if ($this->model->existsByEmail($correo)) {
                $_SESSION['flash_errors'] = ["Ya existe un usuario con ese correo electrónico."];
                $_SESSION['old_post'] = $_POST;
                $this->redirect('views/admin/create_doctor.php');
            }

            if ($this->model->existsByCMP($cmp)) {
                $_SESSION['flash_errors'] = ["El número CMP ya está registrado."];
                $_SESSION['old_post'] = $_POST;
                $this->redirect('views/admin/create_doctor.php');
            }

            if ($esp_sel === '__nueva__' && !empty($esp_nueva)) {
                $this->model->addSpecialty($esp_nueva);
            }

            $hash = password_hash($password_temp, PASSWORD_BCRYPT);

            $data = [
                'nombre' => $nombre,
                'email' => $correo,
                'cmp' => $cmp,
                'especialidad' => $especialidad_final,
                'password' => $hash,
                'establecimiento_id' => $est_id
            ];

            $this->model->create($data);

            // Flujo según diagrama de secuencia RF-01
            $mail_sent = MailService::sendTempPassword($correo, $nombre, $password_temp);

            if ($mail_sent) {
                $_SESSION['flash_success'] = "Cuenta creada. Contraseña temporal enviada al correo del médico.";
                $this->redirect('views/admin/doctor.php');
            } else {
                $_SESSION['flash_success'] = "Médico <strong>$nombre</strong> creado exitosamente.";
                $_SESSION['flash_temp_pass'] = $password_temp;
                $this->redirect('views/admin/create_doctor.php');
            }
        }
    }

    public function deactivate() {
        require_role('ADM');
        $user = current_user();
        $est_id = (int)($user['establecimiento_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $target_id = (int)($_POST['target_id'] ?? 0);
            $this->model->deactivate($target_id, $est_id);
            $_SESSION['flash_success'] = "Acceso del médico desactivado exitosamente.";
            $this->redirect('views/admin/doctor.php');
        }
    }

    public function editDoctor() {
        require_role('ADM');
        $user = current_user();
        $est_id = (int)($user['establecimiento_id'] ?? 0);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            
            $id = (int)($_POST['edit_id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $cmp = trim($_POST['cmp'] ?? '');
            $especialidad = trim($_POST['especialidad'] ?? '');
            
            if (empty($nombre) || empty($correo) || empty($cmp) || empty($especialidad)) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
                exit;
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Correo inválido.']);
                exit;
            }
            
            if ($this->model->existsByEmail($correo, $id)) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro médico con ese correo.']);
                exit;
            }
            if ($this->model->existsByCMP($cmp, $id)) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro médico con ese CMP.']);
                exit;
            }
            
            $this->model->updateDoctor($id, $est_id, [
                'nombre' => $nombre,
                'email' => $correo,
                'cmp' => $cmp,
                'especialidad' => $especialidad
            ]);
            
            echo json_encode(['success' => true]);
            exit;
        }
    }

    public function resetPassword() {
        require_role('ADM');
        $user = current_user();
        $est_id = (int)($user['establecimiento_id'] ?? 0);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            $id = (int)($_POST['reset_id'] ?? 0);
            
            $doctor = $this->model->findById($id, $est_id);
            if (!$doctor) {
                echo json_encode(['success' => false, 'message' => 'Médico no encontrado.']);
                exit;
            }
            
            $tempPass = PasswordHelper::generateTemp(12);
            $hash = password_hash($tempPass, PASSWORD_BCRYPT);
            
            $this->model->resetPassword($id, $est_id, $hash);
            
            $mailSent = MailService::sendResetPassword($doctor['correo'], $doctor['nombre'], $tempPass);
            
            if ($mailSent) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Contraseña reseteada, pero no se pudo enviar el correo.']);
            }
            exit;
        }
    }
}

// Router
if (isset($_GET['action'])) {
    $controller = new DoctorController();
    switch ($_GET['action']) {
        case 'create':     $controller->createDoctor(); break;
        case 'deactivate': $controller->deactivate(); break;
        case 'edit':       $controller->editDoctor(); break;
        case 'reset':      $controller->resetPassword(); break;
    }
}
