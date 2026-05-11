<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar que hay sesión activa (viene del login con password temporal)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Si ya no es temporal, redirigir al dashboard
if (empty($_SESSION['es_password_temporal'])) {
    $rol = $_SESSION['rol_codigo'] ?? 'MED';
    $dest = [
        'SAD' => '../dashboard/superadmin/index.php',
        'ADM' => '../dashboard/admin/index.php',
        'MED' => '../dashboard/medico/index.php',
    ];
    header('Location: ' . ($dest[$rol] ?? '../dashboard/medico/index.php'));
    exit();
}

$error = null;
if (isset($_SESSION['cp_error'])) {
    $error = $_SESSION['cp_error'];
    unset($_SESSION['cp_error']);
}
$nombre = $_SESSION['nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RetinAI — Cambiar Contraseña</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth/login.css">
</head>
<body>

<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-logo">
            <div class="auth-logo-mark">
                <svg viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.5"/>
                    <circle cx="12" cy="12" r="3.5" fill="white" opacity=".6"/>
                    <circle cx="12" cy="12" r="1.2" fill="white"/>
                </svg>
            </div>
            <h1>Retin<span>AI</span></h1>
        </div>

        <!-- Aviso de contraseña temporal -->
        <div class="alert-box alert-warning" style="margin-bottom:20px;">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:2px">
                <circle cx="10" cy="10" r="9" stroke="#D97706" stroke-width="1.5"/>
                <path d="M10 6v5M10 13h.01" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <p>Hola, <strong><?= htmlspecialchars($nombre) ?></strong>. Debe establecer una contraseña permanente para continuar.</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-box alert-danger">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <form action="../../controllers/AuthController.php?action=change_password" method="POST" id="cp-form" novalidate>

            <div class="form-group">
                <label class="form-label" for="nueva_password">Nueva contraseña</label>
                <div class="input-wrapper">
                    <input
                        class="form-input"
                        type="password"
                        id="nueva_password"
                        name="nueva_password"
                        placeholder="Mínimo 6 caracteres"
                        autocomplete="new-password"
                        style="padding-right:40px"
                        oninput="checkStrength(this.value)"
                        required
                    >
                    <button type="button" class="toggle-pass" onclick="togglePass('nueva_password', 'eye1-off','eye1-on')" aria-label="Mostrar">
                        <svg id="eye1-off" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18M10.73 10.73A3 3 0 0013.27 13.27M6.53 6.53C4.89 7.83 3.5 9.79 3 12c1.12 4.48 5.05 7.5 9 7.5 1.67 0 3.28-.49 4.7-1.35M9.9 4.58C10.59 4.37 11.29 4.25 12 4.25c3.95 0 7.88 3.02 9 7.5a13.1 13.1 0 01-2.08 4.04" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        <svg id="eye1-on"  width="18" height="18" viewBox="0 0 24 24" fill="none" style="display:none"><path d="M1 12C2.12 7.52 6.05 4.5 10 4.5c3.95 0 7.88 3.02 9 7.5-1.12 4.48-5.05 7.5-9 7.5-3.95 0-7.88-3.02-9-7.5z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0%"></div></div>
                <p class="strength-label" id="strength-label"></p>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirma_password">Confirmar contraseña</label>
                <div class="input-wrapper">
                    <input
                        class="form-input"
                        type="password"
                        id="confirma_password"
                        name="confirma_password"
                        placeholder="Repita la contraseña"
                        autocomplete="new-password"
                        style="padding-right:40px"
                        required
                    >
                    <button type="button" class="toggle-pass" onclick="togglePass('confirma_password', 'eye2-off','eye2-on')" aria-label="Mostrar">
                        <svg id="eye2-off" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18M10.73 10.73A3 3 0 0013.27 13.27M6.53 6.53C4.89 7.83 3.5 9.79 3 12c1.12 4.48 5.05 7.5 9 7.5 1.67 0 3.28-.49 4.7-1.35M9.9 4.58C10.59 4.37 11.29 4.25 12 4.25c3.95 0 7.88 3.02 9 7.5a13.1 13.1 0 01-2.08 4.04" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        <svg id="eye2-on"  width="18" height="18" viewBox="0 0 24 24" fill="none" style="display:none"><path d="M1 12C2.12 7.52 6.05 4.5 10 4.5c3.95 0 7.88 3.02 9 7.5-1.12 4.48-5.05 7.5-9 7.5-3.95 0-7.88-3.02-9-7.5z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="cp-btn">
                Establecer contraseña
            </button>
        </form>

        <div class="divider"></div>
        <p class="auth-footer-text">
            <a href="../../controllers/AuthController.php?action=logout">Cerrar sesión</a>
        </p>

    </div>
</div>

<script>
function togglePass(inputId, offId, onId) {
    const input = document.getElementById(inputId);
    const off   = document.getElementById(offId);
    const on    = document.getElementById(onId);
    if (input.type === 'password') {
        input.type = 'text'; off.style.display = 'none'; on.style.display = 'block';
    } else {
        input.type = 'password'; off.style.display = 'block'; on.style.display = 'none';
    }
}

function checkStrength(val) {
    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct:'20%', color:'#DC2626', text:'Muy débil' },
        { pct:'40%', color:'#F97316', text:'Débil' },
        { pct:'60%', color:'#EAB308', text:'Moderada' },
        { pct:'80%', color:'#3B82F6', text:'Fuerte' },
        { pct:'100%',color:'#059669', text:'Muy fuerte' },
    ];
    const l = levels[Math.max(0, score - 1)] || levels[0];
    fill.style.width      = val.length > 0 ? l.pct : '0%';
    fill.style.background = l.color;
    label.textContent     = val.length > 0 ? l.text : '';
    label.style.color     = l.color;
}

document.getElementById('cp-form').addEventListener('submit', function(e) {
    const nueva    = document.getElementById('nueva_password').value;
    const confirma = document.getElementById('confirma_password').value;
    if (nueva.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres.');
        return;
    }
    if (nueva !== confirma) {
        e.preventDefault();
        alert('Las contraseñas no coinciden.');
        return;
    }
    document.getElementById('cp-btn').classList.add('loading');
    document.getElementById('cp-btn').textContent = '';
});
</script>
</body>
</html>
