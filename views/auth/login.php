<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RetinAI - Iniciar Sesión</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
                <h1>RetinAI</h1>
                <p>Acceso al sistema para médicos</p>
            </div>

            <!-- Formulario de inicio de sesión -->
            <form action="../../controllers/AuthController.php?action=login" method="POST" id="login-form">
                <div class="form-group">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input class="form-input" type="email" placeholder="medico@hospital.pe" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-input" type="password" placeholder="••••••••" id="password" name="password" required>
                </div>

                <?php if (isset($_SESSION['login_error'])): ?>
                <div class="alert-box alert-danger mb-16" id="login-error">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px">
                        <circle cx="10" cy="10" r="9" stroke="#DC2626" stroke-width="1.5"/>
                        <path d="M10 6v5M10 13h.01" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <p id="login-error-msg"><?= htmlspecialchars($_SESSION['login_error']) ?></p>
                </div>
                <?php unset($_SESSION['login_error']); endif; ?>

                <button type="submit" class="btn btn-primary btn-full">Iniciar sesión</button>

                <div class="info-text">
                    <p>La sesión expira tras 60 minutos de inactividad por seguridad.</p>
                </div>

                <div class="divider"></div>
                
                <p class="register-text">
                    Contacte a la administración del establecimiento para solicitar acceso al sistema.
                </p>
            </form>
        </div>
    </div>
</body>
</html>
