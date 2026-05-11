<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/AuthModel.php';

class AuthController {
    private $model;

    public function __construct() {
        $this->model = new AuthModel();
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $this->redirectWithError("Por favor ingrese correo y contraseña.");
                return;
            }

            $user = $this->model->getUserByEmail($email);

            if ($user && password_verify($password, $user['password'])) {
                // --- Sesión exitosa ---
                $_SESSION['user_id']               = $user['id'];
                $_SESSION['rol_codigo']             = $user['rol_codigo'];
                $_SESSION['nombre']                 = $user['nombre'];
                $_SESSION['establecimiento_id']     = $user['establecimiento_id'];
                $_SESSION['es_password_temporal']   = $user['es_password_temporal'];
                $_SESSION['last_activity']          = time();

                $this->model->updateLastAccess($user['id']);

                // Contraseña temporal: forzar cambio
                if ($user['es_password_temporal']) {
                    header("Location: ../views/auth/change_password.php");
                    exit();
                }

                // Redirigir según rol
                $this->redirectByRole($user['rol_codigo']);
            } else {
                $this->redirectWithError("Credenciales incorrectas. Verifique su correo y contraseña.");
            }
        }
    }

    public function changePassword() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            header("Location: ../views/auth/login.php");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nueva    = $_POST['nueva_password'] ?? '';
            $confirma = $_POST['confirma_password'] ?? '';

            if (strlen($nueva) < 6) {
                $_SESSION['cp_error'] = "La contraseña debe tener al menos 6 caracteres.";
                header("Location: ../views/auth/change_password.php");
                exit();
            }
            if ($nueva !== $confirma) {
                $_SESSION['cp_error'] = "Las contraseñas no coinciden.";
                header("Location: ../views/auth/change_password.php");
                exit();
            }

            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            $this->model->updatePassword($_SESSION['user_id'], $hash);
            $_SESSION['es_password_temporal'] = false;

            $this->redirectByRole($_SESSION['rol_codigo']);
        }
    }

    public function logout() {
        session_unset();
        session_destroy();
        header("Location: ../../index.php");
        exit();
    }

    private function redirectWithError($error) {
        $_SESSION['login_error'] = $error;
        header("Location: ../views/auth/login.php");
        exit();
    }

    private function redirectByRole($rol) {
        switch ($rol) {
            case 'SAD':
                header("Location: ../views/dashboard/superadmin/index.php");
                break;
            case 'ADM':
                header("Location: ../views/dashboard/admin/index.php");
                break;
            case 'MED':
            default:
                header("Location: ../views/dashboard/medico/index.php");
                break;
        }
        exit();
    }
}

// --- Enrutamiento simple ---
if (isset($_GET['action'])) {
    $controller = new AuthController();
    switch ($_GET['action']) {
        case 'login':           $controller->login(); break;
        case 'logout':          $controller->logout(); break;
        case 'change_password': $controller->changePassword(); break;
    }
}
