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
    $establecimientos = $db->query(
        "SELECT e.id, e.nombre, e.direccion,
         COUNT(u.id) AS medicos
         FROM establecimientos e
         LEFT JOIN usuarios u ON u.establecimiento_id=e.id AND u.rol_codigo='MED' AND u.activo=1
         GROUP BY e.id ORDER BY e.nombre"
    )->fetchAll(PDO::FETCH_ASSOC);
    $todos_usuarios = $db->query(
        "SELECT u.nombre, u.correo, u.rol_codigo, u.activo, u.ultimo_acceso,
         e.nombre AS establecimiento
         FROM usuarios u
         LEFT JOIN establecimientos e ON e.id=u.establecimiento_id
         ORDER BY u.rol_codigo, u.nombre"
    )->fetchAll(PDO::FETCH_ASSOC);
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

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

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
