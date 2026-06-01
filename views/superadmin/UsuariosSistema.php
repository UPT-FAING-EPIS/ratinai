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
    $todos_usuarios = $db->query(
        "SELECT u.id, u.nombre, u.correo, u.rol_codigo, u.activo, u.ultimo_acceso,
         e.nombre AS establecimiento
         FROM usuarios u
         LEFT JOIN establecimientos e ON e.id = u.establecimiento_id
         ORDER BY u.rol_codigo, u.nombre"
    )->fetchAll(PDO::FETCH_ASSOC);
    $cnt_solicitudes_pendientes = 0;
    try {
        $cnt_solicitudes_pendientes = $db->query("SELECT COUNT(*) FROM solicitudes_establecimiento WHERE estado='pendiente'")->fetchColumn();
    } catch(Exception $e) { $cnt_solicitudes_pendientes = 0; }
} catch(Exception $ex) {
    $todos_usuarios = [];
    $cnt_solicitudes_pendientes = 0;
}

$rolLabel = ['SAD' => 'Super Admin', 'ADM' => 'Admin', 'MED' => 'Médico'];
$rolClass = ['SAD' => 'badge-sad',   'ADM' => 'badge-adm', 'MED' => 'badge-med'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Usuarios del Sistema</title>
<meta name="description" content="Listado de todos los usuarios registrados en la plataforma RetinAI.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.filter-bar { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center; }
.filter-bar input[type=search] {
    padding:9px 14px; border:1.5px solid var(--border,#DDE3EC); border-radius:8px;
    font-family:inherit; font-size:14px; outline:none; width:260px;
    transition:border 0.15s;
}
.filter-bar input[type=search]:focus { border-color:var(--accent,#1A56DB); }
.filter-bar select {
    padding:9px 14px; border:1.5px solid var(--border,#DDE3EC); border-radius:8px;
    font-family:inherit; font-size:14px; outline:none; background:#fff; cursor:pointer;
}
.user-count { margin-left:auto; font-size:13px; color:var(--text2,#4A5568); }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">

        <div class="section-header">
            <h1 class="page-title">Usuarios del Sistema</h1>
            <p class="page-sub">Todos los usuarios registrados en RetinAI.</p>
        </div>

        <!-- ── Filtros ── -->
        <div class="filter-bar">
            <input type="search" id="search-usuario" placeholder="🔍 Buscar por nombre o correo…" oninput="filtrarTabla()">
            <select id="filter-rol" onchange="filtrarTabla()">
                <option value="">Todos los roles</option>
                <option value="SAD">Super Admin</option>
                <option value="ADM">Admin</option>
                <option value="MED">Médico</option>
            </select>
            <select id="filter-estado" onchange="filtrarTabla()">
                <option value="">Todos los estados</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
            <span class="user-count" id="user-count">
                <?= count($todos_usuarios) ?> usuario<?= count($todos_usuarios) !== 1 ? 's' : '' ?>
            </span>
        </div>

        <div class="card">
            <table class="data-table" id="tabla-usuarios">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Establecimiento</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($todos_usuarios as $u): ?>
                <tr
                    data-nombre="<?= strtolower(htmlspecialchars($u['nombre'])) ?>"
                    data-correo="<?= strtolower(htmlspecialchars($u['correo'])) ?>"
                    data-rol="<?= htmlspecialchars($u['rol_codigo']) ?>"
                    data-activo="<?= $u['activo'] ? '1' : '0' ?>"
                >
                    <td>
                        <strong><?= htmlspecialchars($u['nombre']) ?></strong><br>
                        <span class="text-muted small"><?= htmlspecialchars($u['correo']) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $rolClass[$u['rol_codigo']] ?? 'badge-info' ?>">
                            <?= $rolLabel[$u['rol_codigo']] ?? $u['rol_codigo'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($u['establecimiento'] ?? 'Global') ?></td>
                    <td class="text-muted small">
                        <?= $u['ultimo_acceso'] ? date('d M Y H:i', strtotime($u['ultimo_acceso'])) : '—' ?>
                    </td>
                    <td>
                        <span class="badge <?= $u['activo'] ? 'badge-active' : 'badge-disabled' ?>">
                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script>
SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base."views/auth/login.php") ?>' });
let remaining = 300;
const cd = document.getElementById('session-countdown');
setInterval(()=>{ const m=Math.floor(remaining/60).toString().padStart(2,'0'); const s=(remaining%60).toString().padStart(2,'0'); cd.textContent=m+':'+s; if(remaining>0)remaining--; },1000);

function filtrarTabla() {
    const q      = document.getElementById('search-usuario').value.toLowerCase();
    const rol    = document.getElementById('filter-rol').value;
    const estado = document.getElementById('filter-estado').value;
    const rows   = document.querySelectorAll('#tabla-usuarios tbody tr');
    let visible  = 0;
    rows.forEach(row => {
        const nombre  = row.dataset.nombre || '';
        const correo  = row.dataset.correo || '';
        const rowRol  = row.dataset.rol    || '';
        const rowAct  = row.dataset.activo || '';
        const matchQ  = !q      || nombre.includes(q) || correo.includes(q);
        const matchR  = !rol    || rowRol === rol;
        const matchE  = !estado || rowAct  === estado;
        const show    = matchQ && matchR && matchE;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('user-count').textContent =
        visible + ' usuario' + (visible !== 1 ? 's' : '');
}
</script>
</body>
</html>
