<?php
session_start();
require_once __DIR__ . '/../models/AuthModel.php';

class AuthController {
    private $model;

    public function __construct() {
        $this->model = new AuthModel();
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $this->redirectWithError("Por favor ingrese correo y contraseña.");
                return;
            }

            $user = $this->model->getUserByEmail($email);

            if ($user && password_verify($password, $user['password'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['rol_codigo'] = $user['rol_codigo']; // Ahora usamos rol_codigo (SAD, ADM, MED)
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['es_password_temporal'] = $user['es_password_temporal'];
                $_SESSION['last_activity'] = time(); // Para expiración de sesión
                
                $this->model->updateLastAccess($user['id']);

                // Redirigir según si la contraseña es temporal
                if ($user['es_password_temporal']) {
                    header("Location: ../views/auth/change_password.php");
                    exit();
                }

                header("Location: ../views/dashboard/index.php");
                exit();
            } else {
                $this->redirectWithError("Credenciales incorrectas.");
            }
        }
    }

    private function redirectWithError($error) {
        $_SESSION['login_error'] = $error;
        header("Location: ../views/auth/login.php");
        exit();
    }
    
    public function logout() {
        session_destroy();
        header("Location: ../views/auth/login.php");
        exit();
    }
}

// Enrutamiento simple
if (isset($_GET['action'])) {
    $controller = new AuthController();
    if ($_GET['action'] == 'login') {
        $controller->login();
    } elseif ($_GET['action'] == 'logout') {
        $controller->logout();
    }
}
