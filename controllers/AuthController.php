<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/AuthModel.php';

class AuthController {
    private $model;

    public function __construct() {
        $this->model = new AuthModel();
    }
    private function baseUrl(): string {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base = rtrim(dirname($scriptDir), '/') . '/';
        $parts = explode('/', rtrim($scriptDir, '/'));
        array_pop($parts);
        $root = implode('/', $parts);
        return ($root === '' ? '/' : $root . '/');
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

            if (!$user) {
                $this->redirectWithError("Credenciales incorrectas. Verifique su correo y contraseña.");
                return;
            }

            if (!password_verify($password, $user['password'])) {
                $this->redirectWithError("Credenciales incorrectas. Verifique su correo y contraseña.");
                return;
            }

            // Verificar estado activo
            if (!$user['activo']) {
                $this->redirectWithError("Su cuenta aún no ha sido aprobada por el administrador.");
                return;
            }

            // --- Sesión exitosa ---
            $_SESSION['user_id']               = $user['id'];
            $_SESSION['rol_codigo']             = $user['rol_codigo'];
            $_SESSION['nombre']                 = $user['nombre'];
            $_SESSION['establecimiento_id']     = $user['establecimiento_id'];
            $_SESSION['es_password_temporal']   = (bool) $user['es_password_temporal'];
            $_SESSION['last_activity']          = time();

            $this->model->updateLastAccess($user['id']);

            // Contraseña temporal: forzar cambio
            if ($user['es_password_temporal']) {
                $this->redirect('views/auth/change_password.php');
            }

            // Redirigir según rol
            $this->redirectByRole($user['rol_codigo']);
        }
    }

    public function changePassword() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('views/auth/login.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nueva    = $_POST['nueva_password'] ?? '';
            $confirma = $_POST['confirma_password'] ?? '';

            if (strlen($nueva) < 6) {
                $_SESSION['cp_error'] = "La contraseña debe tener al menos 6 caracteres.";
                $this->redirect('views/auth/change_password.php');
            }
            if ($nueva !== $confirma) {
                $_SESSION['cp_error'] = "Las contraseñas no coinciden.";
                $this->redirect('views/auth/change_password.php');
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
        $this->redirect('index.php');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    private function redirect(string $path): void {
        $base = $this->baseUrl();
        header("Location: " . $base . ltrim($path, '/'));
        exit();
    }

    private function redirectWithError(string $error): void {
        $_SESSION['login_error'] = $error;
        $this->redirect('views/auth/login.php');
    }

    private function redirectByRole(string $rol): void {
        switch ($rol) {
            case 'SAD':
                $this->redirect('views/dashboard/superadmin/index.php');
                break;
            case 'ADM':
                $this->redirect('views/dashboard/admin/index.php');
                break;
            case 'MED':
            default:
                $this->redirect('views/dashboard/medico/index.php');
                break;
        }
    }
}

// --- Enrutamiento simple ---
if (isset($_GET['action'])) {
    $controller = new AuthController();
    switch ($_GET['action']) {
        case 'login':           $controller->login();          break;
        case 'logout':          $controller->logout();         break;
        case 'change_password': $controller->changePassword(); break;
    }
}
