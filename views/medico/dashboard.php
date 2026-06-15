<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_auth();
require_role('MED');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';

$role_label   = '🩺 Médico';
$role_class   = 'role-med';
$avatar_class = 'avatar-green';
$header_sub   = 'Médico Oftalmólogo';

$_page = 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Dashboard Médico</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Bienvenido, <?= htmlspecialchars($user['nombre']) ?></h1>
            <p class="page-sub">Panel principal del médico oftalmólogo.</p>
        </div>
        
        <div class="grid-2" style="gap:20px; margin-top:20px;">
            <div class="card text-center" style="padding:40px 20px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="margin:0 auto 16px"><rect x="2" y="2" width="20" height="20" rx="3" stroke="#1A56DB" stroke-width="1.5"/><circle cx="12" cy="12" r="5" stroke="#1A56DB" stroke-width="1.5"/><circle cx="12" cy="12" r="2" fill="#1A56DB" opacity=".4"/></svg>
                <h3>Nuevo análisis retinal</h3>
                <p class="text-muted" style="margin-bottom:20px;">Cargue una nueva retinografía para evaluación de IA.</p>
                <a href="<?= $base ?>views/medico/nuevoanalisis.php" class="btn btn-primary">Comenzar análisis</a>
            </div>
            <div class="card text-center" style="padding:40px 20px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="margin:0 auto 16px"><path d="M12 4v16m-8-8h16" stroke="#059669" stroke-width="1.5" stroke-linecap="round"/></svg>
                <h3>Historial de pacientes</h3>
                <p class="text-muted" style="margin-bottom:20px;">Revise análisis previos y resultados de pacientes.</p>
                <a href="<?= $base ?>views/medico/pacientes.php" class="btn btn-secondary">Ver historial</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>
