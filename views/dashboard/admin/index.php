<?php
require_once __DIR__ . '/../../../config/session_guard.php';
require_once __DIR__ . '/../../../config/config.php';
require_role('ADM');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$est_id = (int)($user['establecimiento_id'] ?? 0);
$logout_url = $base . 'controllers/AuthController.php?action=logout';

try {
    $db = (new Database())->getConnection();
    // Nombre del establecimiento
    $stmt = $db->prepare("SELECT nombre FROM establecimientos WHERE id = :id");
    $stmt->execute([':id' => $est_id]);
    $est = $stmt->fetch(PDO::FETCH_ASSOC);
    $est_nombre = $est['nombre'] ?? 'Mi Establecimiento';

    // Médicos pendientes (activo=0 → pendiente de aprobación)
    $pendientes = $db->prepare(
        "SELECT id, nombre, correo, cmp, especialidad, ultimo_acceso
         FROM usuarios WHERE rol_codigo='MED' AND activo=0 AND establecimiento_id=:eid
         ORDER BY nombre"
    );
    $pendientes->execute([':eid' => $est_id]);
    $pendientes = $pendientes->fetchAll(PDO::FETCH_ASSOC);

    // Médicos activos
    $activos = $db->prepare(
        "SELECT id, nombre, correo, cmp, especialidad, ultimo_acceso, es_password_temporal
         FROM usuarios WHERE rol_codigo='MED' AND activo=1 AND establecimiento_id=:eid
         ORDER BY nombre"
    );
    $activos->execute([':eid' => $est_id]);
    $activos = $activos->fetchAll(PDO::FETCH_ASSOC);

    $cnt_pendientes = count($pendientes);
    $cnt_activos    = count($activos);
} catch(Exception $ex) {
    $est_nombre=''; $pendientes=[]; $activos=[];
    $cnt_pendientes=0; $cnt_activos=0;
}

// Manejo de acciones POST (aprobar/desactivar)
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    try {
        $target_id = (int)($_POST['target_id'] ?? 0);
        if ($_POST['action_type'] === 'aprobar') {
            $s = $db->prepare("UPDATE usuarios SET activo=1 WHERE id=:id AND establecimiento_id=:eid AND rol_codigo='MED'");
            $s->execute([':id'=>$target_id,':eid'=>$est_id]);
            $msg_ok = "Médico aprobado exitosamente.";
        } elseif ($_POST['action_type'] === 'rechazar') {
            $s = $db->prepare("UPDATE usuarios SET activo=0 WHERE id=:id AND establecimiento_id=:eid AND rol_codigo='MED'");
            $s->execute([':id'=>$target_id,':eid'=>$est_id]);
            $msg_ok = "Solicitud procesada.";
        } elseif ($_POST['action_type'] === 'desactivar') {
            $s = $db->prepare("UPDATE usuarios SET activo=0 WHERE id=:id AND establecimiento_id=:eid AND rol_codigo='MED'");
            $s->execute([':id'=>$target_id,':eid'=>$est_id]);
            $msg_ok = "Acceso del médico desactivado exitosamente.";
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?ok=" . urlencode($msg_ok));
        exit();
    } catch(Exception $ex) {
        $msg_err = "Error al procesar la acción.";
    }
}
if (isset($_GET['ok'])) $msg_ok = htmlspecialchars($_GET['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Admin Establecimiento</title>
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
    <span class="role-pill role-adm">🛡️ Administrador</span>
    <div class="header-user">
        <div class="avatar avatar-adm"><?= htmlspecialchars($initials) ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($user['nombre']) ?></span>
            <span class="user-role"><?= htmlspecialchars($est_nombre) ?></span>
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
                <span class="nav-section-label">Gestión</span>
                <a href="#pendientes" class="nav-item active" data-section="pendientes">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><rect x="2" y="4" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.3"/><path d="M6 8h8M6 12h5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                    Solicitudes pendientes
                    <?php if($cnt_pendientes > 0): ?><span class="nav-badge"><?= $cnt_pendientes ?></span><?php endif; ?>
                </a>
                <a href="#activos" class="nav-item" data-section="activos">
                    <svg class="nav-icon" viewBox="0 0 20 20" fill="none"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 14a6 6 0 10-12 0" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                    Médicos activos
                    <span class="nav-badge"><?= $cnt_activos ?></span>
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

        <?php if($msg_ok): ?><div class="alert-flash alert-ok" id="flash-msg"><?= $msg_ok ?></div><?php endif; ?>
        <?php if($msg_err): ?><div class="alert-flash alert-err"><?= $msg_err ?></div><?php endif; ?>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon kpi-warning">⏳</div>
                <div class="kpi-body"><span class="kpi-value"><?= $cnt_pendientes ?></span><span class="kpi-label">Pendientes</span></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-green">✅</div>
                <div class="kpi-body"><span class="kpi-value"><?= $cnt_activos ?></span><span class="kpi-label">Activos</span></div>
            </div>
        </div>

        <!-- Solicitudes pendientes -->
        <section id="pendientes" class="content-section">
            <div class="section-header">
                <h1 class="page-title">Solicitudes de registro</h1>
                <p class="page-sub">Revise y apruebe o rechace solicitudes de médicos oftalmólogos.</p>
            </div>
            <div class="card">
                <?php if(empty($pendientes)): ?>
                <p class="empty-msg">✅ No hay solicitudes pendientes.</p>
                <?php else: ?>
                <table class="data-table" id="tabla-pendientes">
                    <thead><tr><th>Médico</th><th>CMP</th><th>Especialidad</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php foreach($pendientes as $m): ?>
                    <tr id="row-<?= $m['id'] ?>">
                        <td><strong><?= htmlspecialchars($m['nombre']) ?></strong><br><span class="text-muted small"><?= htmlspecialchars($m['correo']) ?></span></td>
                        <td class="mono"><?= htmlspecialchars($m['cmp'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['especialidad'] ?? '—') ?></td>
                        <td><span class="badge badge-pending">Pendiente</span></td>
                        <td>
                            <div class="action-group">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action_type" value="aprobar">
                                    <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action_type" value="rechazar">
                                    <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger-outline">Rechazar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- Médicos activos -->
        <section id="activos" class="content-section" style="display:none">
            <div class="section-header">
                <div class="flex-between">
                    <div>
                        <h1 class="page-title">Médicos activos</h1>
                        <p class="page-sub">Gestione el acceso de los médicos de <?= htmlspecialchars($est_nombre) ?>.</p>
                    </div>
                    <span class="badge badge-info" style="font-size:13px;padding:6px 14px"><?= $cnt_activos ?> activos</span>
                </div>
            </div>
            <div class="card">
                <?php if(empty($activos)): ?>
                <p class="empty-msg">No hay médicos activos en este establecimiento.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Médico</th><th>CMP</th><th>Especialidad</th><th>Último acceso</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php foreach($activos as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['nombre']) ?></strong><br><span class="text-muted small"><?= htmlspecialchars($m['correo']) ?></span></td>
                        <td class="mono"><?= htmlspecialchars($m['cmp'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['especialidad'] ?? '—') ?></td>
                        <td class="text-muted small"><?= $m['ultimo_acceso'] ? date('d M Y H:i', strtotime($m['ultimo_acceso'])) : '—' ?></td>
                        <td>
                            <span class="badge badge-active">Activo</span>
                            <?php if($m['es_password_temporal']): ?><span class="badge badge-warning" style="margin-left:4px">Clave temporal</span><?php endif; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action_type" value="desactivar">
                                <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger-outline">Desactivar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
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

// Auto-ocultar flash
const flash = document.getElementById('flash-msg');
if(flash) setTimeout(()=>{ flash.style.opacity='0'; setTimeout(()=>flash.remove(),400); }, 3000);
</script>
</body>
</html>
