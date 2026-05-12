<?php
require_once __DIR__ . '/../../../config/session_guard.php';
require_once __DIR__ . '/../../../config/config.php';
require_role('SAD');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';

try {
    $db = (new Database())->getConnection();
    $total_establecimientos = $db->query("SELECT COUNT(*) FROM establecimientos")->fetchColumn();
    $total_medicos = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=1")->fetchColumn();
    $total_admins  = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol_codigo='ADM' AND activo=1")->fetchColumn();
    $establecimientos = $db->query("SELECT e.id, e.nombre, e.direccion,
        COUNT(u.id) AS medicos
        FROM establecimientos e
        LEFT JOIN usuarios u ON u.establecimiento_id=e.id AND u.rol_codigo='MED' AND u.activo=1
        GROUP BY e.id ORDER BY e.nombre")->fetchAll(PDO::FETCH_ASSOC);
    $todos_usuarios = $db->query("SELECT u.nombre, u.correo, u.rol_codigo, u.activo, u.ultimo_acceso,
        e.nombre AS establecimiento
        FROM usuarios u
        LEFT JOIN establecimientos e ON e.id=u.establecimiento_id
        ORDER BY u.rol_codigo, u.nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $ex) {
    $total_establecimientos=$total_medicos=$total_admins=0;
    $establecimientos=$todos_usuarios=[];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Super Administrador</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
</head>
<body>

<header class="top-header">
    <div class="header-brand">
        <div class="logo-mark">
            <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.5"/><circle cx="12" cy="12" r="3.5" fill="white" opacity=".6"/><circle cx="12" cy="12" r="1.2" fill="white"/></svg>
        </div>
        <span class="logo-text">Retin<em>AI</em></span>
    </div>
    <span class="role-pill role-sad">⚡ Super Administrador</span>
    <div class="header-user">
        <div class="avatar avatar-sad"><?= htmlspecialchars($initials) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($user['nombre']) ?></span>
            <span class="user-role">Control Global</span>
        </div>
        <a href="<?= $logout_url ?>" class="btn-logout" id="btn-logout">
            <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Salir
        </a>
    </div>
</header>

<div class="app-shell">
    <aside class="sidebar">
        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-section-label">Sistema Global</span>
                <a href="#establecimientos" class="nav-item active" data-section="establecimientos">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke="currentColor" stroke-width="1.3"/></svg>
                    Establecimientos
                </a>
                <a href="#usuarios" class="nav-item" data-section="usuarios">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.3"/></svg>
                    Usuarios del Sistema
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="session-info">
                <svg viewBox="0 0 20 20" fill="none" width="13" height="13"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.3"/><path d="M10 6v4l2.5 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                Sesión: <span id="session-countdown">05:00</span>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon kpi-blue">🏥</div>
                <div class="kpi-body"><span class="kpi-value"><?= (int)$total_establecimientos ?></span><span class="kpi-label">Establecimientos</span></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-green">👨‍⚕️</div>
                <div class="kpi-body"><span class="kpi-value"><?= (int)$total_medicos ?></span><span class="kpi-label">Médicos Activos</span></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-purple">🛡️</div>
                <div class="kpi-body"><span class="kpi-value"><?= (int)$total_admins ?></span><span class="kpi-label">Administradores</span></div>
            </div>
        </div>

        <section id="establecimientos" class="content-section">
            <div class="section-header">
                <h1 class="page-title">Establecimientos</h1>
                <p class="page-sub">Vista global de todos los centros oftalmológicos.</p>
            </div>
            <div class="card">
                <?php if(empty($establecimientos)): ?>
                <p class="empty-msg">No hay establecimientos registrados.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Nombre</th><th>Dirección</th><th>Médicos</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach($establecimientos as $e): ?>
                    <tr>
                        <td class="mono"><?= (int)$e['id'] ?></td>
                        <td><strong><?= htmlspecialchars($e['nombre']) ?></strong></td>
                        <td><?= htmlspecialchars($e['direccion'] ?? '—') ?></td>
                        <td><span class="badge badge-info"><?= (int)$e['medicos'] ?></span></td>
                        <td><span class="badge badge-active">Activo</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

        <section id="usuarios" class="content-section" style="display:none">
            <div class="section-header">
                <h1 class="page-title">Usuarios del sistema</h1>
                <p class="page-sub">Todos los usuarios registrados en RetinAI.</p>
            </div>
            <div class="card">
                <table class="data-table">
                    <thead><tr><th>Usuario</th><th>Rol</th><th>Establecimiento</th><th>Último acceso</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php
                    $rolL=['SAD'=>'Super Admin','ADM'=>'Admin','MED'=>'Médico'];
                    $rolC=['SAD'=>'badge-sad','ADM'=>'badge-adm','MED'=>'badge-med'];
                    foreach($todos_usuarios as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['nombre']) ?></strong><br><span class="text-muted small"><?= htmlspecialchars($u['correo']) ?></span></td>
                        <td><span class="badge <?= $rolC[$u['rol_codigo']]??'badge-info' ?>"><?= $rolL[$u['rol_codigo']]??$u['rol_codigo'] ?></span></td>
                        <td><?= htmlspecialchars($u['establecimiento']??'Global') ?></td>
                        <td class="text-muted small"><?= $u['ultimo_acceso'] ? date('d M Y H:i', strtotime($u['ultimo_acceso'])) : '—' ?></td>
                        <td><span class="badge <?= $u['activo']?'badge-active':'badge-disabled' ?>"><?= $u['activo']?'Activo':'Inactivo' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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
document.querySelectorAll('.nav-item[data-section]').forEach(link=>{
    link.addEventListener('click',function(e){
        e.preventDefault();
        const t=this.dataset.section;
        document.querySelectorAll('.nav-item').forEach(el=>el.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.content-section').forEach(s=>{ s.style.display=s.id===t?'block':'none'; });
    });
});
</script>
</body>
</html>
