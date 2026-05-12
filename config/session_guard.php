<?php
/**
 * RetinAI — Session Guard Middleware
 * Protege las vistas que requieren autenticación y valida roles.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SESSION_TIMEOUT', 300); // 5 minutos

/**
 * Verifica que el usuario esté autenticado.
 * Si no, redirige al login.
 */
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . get_login_url());
        exit();
    }

    // Verificar expiración de sesión
    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: ' . get_login_url() . '?expired=1');
            exit();
        }
    }

    // Actualizar timestamp de actividad
    $_SESSION['last_activity'] = time();
}

/**
 * Verifica que el usuario tenga uno de los roles permitidos.
 * @param array|string $roles Rol(es) permitidos (SAD, ADM, MED)
 */
function require_role($roles) {
    require_auth();

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    if (!in_array($_SESSION['rol_codigo'] ?? '', $roles)) {
        // Redirigir al dashboard correspondiente si no tiene permiso
        header('Location: ' . get_dashboard_url($_SESSION['rol_codigo'] ?? ''));
        exit();
    }
}

/**
 * Retorna la URL del login relativa a la raíz del proyecto.
 */
function get_login_url() {
    // Detectar la profundidad del archivo actual
    $base = get_base_path();
    return $base . 'views/auth/login.php';
}

/**
 * Retorna la URL del dashboard según el rol.
 */
function get_dashboard_url($rol) {
    $base = get_base_path();
    switch ($rol) {
        case 'SAD': return $base . 'views/dashboard/superadmin/index.php';
        case 'ADM': return $base . 'views/dashboard/admin/index.php';
        case 'MED': return $base . 'views/dashboard/medico/index.php';
        default:    return $base . 'views/auth/login.php';
    }
}

/**
 * Calcula la ruta base desde el archivo actual al root del proyecto.
 */
function get_base_path() {
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $current = str_replace('\\', '/', dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
    
    // Calcular cuántos niveles subir
    $relative = '';
    $curr = $current;
    while (strlen($curr) > strlen($root) && $curr !== $root) {
        $relative .= '../';
        $curr = dirname($curr);
    }
    return $relative ?: './';
}

/**
 * Retorna los datos del usuario en sesión.
 */
function current_user() {
    return [
        'id'                  => $_SESSION['user_id'] ?? null,
        'nombre'              => $_SESSION['nombre'] ?? '',
        'rol_codigo'          => $_SESSION['rol_codigo'] ?? '',
        'es_password_temporal'=> $_SESSION['es_password_temporal'] ?? false,
    ];
}

/**
 * Genera las iniciales del nombre para el avatar.
 */
function get_initials($nombre) {
    $partes = explode(' ', trim($nombre));
    $iniciales = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $iniciales .= strtoupper(mb_substr($p, 0, 1));
    }
    return $iniciales ?: '?';
}
