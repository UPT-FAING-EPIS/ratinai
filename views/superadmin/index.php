<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('SAD');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';

$role_label   = '⚡ Super Administrador';
$role_class   = 'role-sad';
$avatar_class = 'avatar-sad';
$header_sub   = 'Control Global';

try {
    $db = (new Database())->getConnection();
    $total_establecimientos = $db->query("SELECT COUNT(*) FROM establecimientos")->fetchColumn();
    $total_medicos = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=1")->fetchColumn();
    $total_admins  = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='ADM' AND activo=1")->fetchColumn();
    $cnt_solicitudes_pendientes = 0;
    try {
        $cnt_solicitudes_pendientes = $db->query("SELECT COUNT(*) FROM solicitudes_establecimiento WHERE estado='pendiente'")->fetchColumn();
    } catch(Exception $e) { $cnt_solicitudes_pendientes = 0; }
} catch(Exception $ex) {
    $total_establecimientos = $total_medicos = $total_admins = 0;
    $cnt_solicitudes_pendientes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Dashboard del Sistema</title>
<meta name="description" content="Panel de control global del Super Administrador de RetinAI.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">

        <!-- ── Page title ── -->
        <div class="section-header">
            <h1 class="page-title">Dashboard del Sistema</h1>
            <p class="page-sub">Resumen global de la plataforma RetinAI.</p>
        </div>

        <!-- ── KPIs ── -->
        <div class="kpi-grid">
            <a href="<?= $base ?>views/superadmin/Establecimientos.php" class="kpi-card kpi-link">
                <div class="kpi-icon kpi-blue">🏥</div>
                <div class="kpi-body">
                    <span class="kpi-value"><?= (int)$total_establecimientos ?></span>
                    <span class="kpi-label">Establecimientos</span>
                </div>
            </a>
            <a href="<?= $base ?>views/superadmin/UsuariosSistema.php" class="kpi-card kpi-link">
                <div class="kpi-icon kpi-green">👨‍⚕️</div>
                <div class="kpi-body">
                    <span class="kpi-value"><?= (int)$total_medicos ?></span>
                    <span class="kpi-label">Médicos Activos</span>
                </div>
            </a>
            <a href="<?= $base ?>views/superadmin/UsuariosSistema.php" class="kpi-card kpi-link">
                <div class="kpi-icon kpi-purple">🛡️</div>
                <div class="kpi-body">
                    <span class="kpi-value"><?= (int)$total_admins ?></span>
                    <span class="kpi-label">Administradores</span>
                </div>
            </a>
            <a href="<?= $base ?>views/superadmin/Establecimientos.php#solicitudes" class="kpi-card kpi-link">
                <div class="kpi-icon kpi-orange">📋</div>
                <div class="kpi-body">
                    <span class="kpi-value"><?= (int)$cnt_solicitudes_pendientes ?></span>
                    <span class="kpi-label">Solicitudes Pendientes</span>
                </div>
            </a>
        </div>

        <!-- ── Accesos rápidos ── -->
        <section class="content-section">
            <div class="section-header">
                <h2 class="page-title" style="font-size:16px;">Accesos Rápidos</h2>
            </div>
            <div class="quick-grid">
                <a href="<?= $base ?>views/superadmin/Establecimientos.php" class="quick-card">
                    <div class="quick-icon">🏥</div>
                    <div>
                        <div class="quick-title">Ver Establecimientos</div>
                        <div class="quick-desc">Lista y gestión de centros oftalmológicos registrados.</div>
                    </div>
                </a>
                <a href="<?= $base ?>views/superadmin/UsuariosSistema.php" class="quick-card">
                    <div class="quick-icon">👥</div>
                    <div>
                        <div class="quick-title">Usuarios del Sistema</div>
                        <div class="quick-desc">Todos los usuarios registrados en RetinAI por rol.</div>
                    </div>
                </a>
                <a href="<?= $base ?>views/superadmin/Establecimientos.php#solicitudes" class="quick-card <?= $cnt_solicitudes_pendientes > 0 ? 'quick-card-alert' : '' ?>">
                    <div class="quick-icon">📋</div>
                    <div>
                        <div class="quick-title">
                            Solicitudes de Registro
                            <?php if ($cnt_solicitudes_pendientes > 0): ?>
                                <span class="nav-badge" style="margin-left:6px;"><?= $cnt_solicitudes_pendientes ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="quick-desc">Revisar solicitudes de nuevos centros oftalmológicos.</div>
                    </div>
                </a>
            </div>
        </section>

    </main>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script>
SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base."views/auth/login.php") ?>' });
let remaining = 300;
const cd = document.getElementById('session-countdown');
setInterval(()=>{ const m=Math.floor(remaining/60).toString().padStart(2,'0'); const s=(remaining%60).toString().padStart(2,'0'); cd.textContent=m+':'+s; if(remaining>0)remaining--; },1000);
</script>
</body>
</html>
