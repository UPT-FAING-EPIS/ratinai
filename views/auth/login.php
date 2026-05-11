<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$error = null;
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
$expired = isset($_GET['expired']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RetinAI — Iniciar Sesión</title>
    <meta name="description" content="Acceso al sistema RetinAI de análisis automatizado de imágenes retinianas para personal médico autorizado.">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth/login.css">
</head>
<body>

<div class="auth-wrap">
    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <div class="auth-logo-mark">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.5"/>
                    <circle cx="12" cy="12" r="3.5" fill="white" opacity=".6"/>
                    <circle cx="12" cy="12" r="1.2" fill="white"/>
                </svg>
            </div>
            <h1>Retin<span>AI</span></h1>
            <p>Sistema de Diagnóstico Oftalmológico</p>
        </div>

        <!-- Alerta de sesión expirada -->
        <?php if ($expired): ?>
        <div class="alert-box alert-warning" role="alert">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                <circle cx="10" cy="10" r="9" stroke="#D97706" stroke-width="1.5"/>
                <path d="M10 6v5M10 13h.01" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <p>Su sesión expiró por inactividad. Por favor ingrese nuevamente.</p>
        </div>
        <?php endif; ?>

        <!-- Error de credenciales -->
        <?php if ($error): ?>
        <div class="alert-box alert-danger" role="alert" id="login-error-box">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form action="../../controllers/AuthController.php?action=login" method="POST" id="login-form" novalidate>

            <div class="form-group">
                <label class="form-label" for="email">Correo electrónico</label>
                <input
                    class="form-input"
                    type="email"
                    id="email"
                    name="email"
                    placeholder="medico@hospital.pe"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Contraseña</label>
                <div class="input-wrapper">
                    <input
                        class="form-input"
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        style="padding-right:40px"
                        required
                    >
                    <button type="button" class="toggle-pass" id="toggle-pass" aria-label="Mostrar contraseña">
                        <!-- Ojo cerrado (por defecto) -->
                        <svg id="eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 3l18 18M10.73 10.73A3 3 0 0013.27 13.27M6.53 6.53C4.89 7.83 3.5 9.79 3 12c1.12 4.48 5.05 7.5 9 7.5 1.67 0 3.28-.49 4.7-1.35M9.9 4.58C10.59 4.37 11.29 4.25 12 4.25c3.95 0 7.88 3.02 9 7.5a13.1 13.1 0 01-2.08 4.04" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                        <!-- Ojo abierto -->
                        <svg id="eye-on" width="18" height="18" viewBox="0 0 24 24" fill="none" style="display:none">
                            <path d="M1 12C2.12 7.52 6.05 4.5 10 4.5c3.95 0 7.88 3.02 9 7.5-1.12 4.48-5.05 7.5-9 7.5-3.95 0-7.88-3.02-9-7.5z" stroke="currentColor" stroke-width="1.6"/>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="submit-btn">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                    <path d="M3 10a7 7 0 1014 0A7 7 0 003 10zm7-3v6m3-3H7" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Iniciar sesión
            </button>
        </form>

        <p class="info-text">La sesión expira tras 60 minutos de inactividad por seguridad.</p>

        <div class="divider"></div>

        <p class="auth-footer-text">
            ¿Médico nuevo? <a href="register.php">Solicitar acceso</a>
        </p>

    </div>
</div>

<script>
// Toggle mostrar/ocultar contraseña
document.getElementById('toggle-pass').addEventListener('click', function() {
    const input = document.getElementById('password');
    const eyeOff = document.getElementById('eye-off');
    const eyeOn  = document.getElementById('eye-on');
    if (input.type === 'password') {
        input.type = 'text';
        eyeOff.style.display = 'none';
        eyeOn.style.display  = 'block';
    } else {
        input.type = 'password';
        eyeOff.style.display = 'block';
        eyeOn.style.display  = 'none';
    }
});

// Loading state al enviar
document.getElementById('login-form').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value.trim();
    const pass  = document.getElementById('password').value;
    const btn   = document.getElementById('submit-btn');

    if (!email || !pass) {
        e.preventDefault();
        showClientError('Por favor ingrese correo y contraseña.');
        return;
    }

    btn.classList.add('loading');
    btn.innerHTML = ''; // Mostrar solo el spinner del CSS
});

function showClientError(msg) {
    let box = document.getElementById('login-error-box');
    if (!box) {
        box = document.createElement('div');
        box.id = 'login-error-box';
        box.className = 'alert-box alert-danger';
        box.innerHTML = `<svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px"><circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/><path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/></svg><p></p>`;
        document.getElementById('login-form').insertAdjacentElement('beforebegin', box);
    }
    box.querySelector('p').textContent = msg;
    box.style.display = 'flex';
}
</script>
</body>
</html>
