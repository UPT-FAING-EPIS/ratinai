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

$msg_success = '';
$msg_error   = '';
if (isset($_SESSION['solicitud_msg'])) {
    $msg_success = $_SESSION['solicitud_msg'];
    unset($_SESSION['solicitud_msg']);
}

try {
    $db = (new Database())->getConnection();

    // Establecimientos activos
    $establecimientos = $db->query(
        "SELECT e.id, e.nombre, e.direccion,
         COUNT(u.id) AS medicos
         FROM establecimientos e
         LEFT JOIN usuarios u ON u.establecimiento_id=e.id AND u.rol_codigo='MED' AND u.activo=1
         GROUP BY e.id ORDER BY e.nombre"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Solicitudes pendientes de registro
    $solicitudes = [];
    $cnt_solicitudes_pendientes = 0;
    try {
        $solicitudes = $db->query(
            "SELECT id, nombre_centro, direccion, tipo, ruc,
                    dni_titular, nombres_titular, apellidos_titular, telefono, correo_contacto,
                    evidencia_1, evidencia_1_nombre, evidencia_2, evidencia_2_nombre,
                    estado, fecha_solicitud
             FROM solicitudes_establecimiento
             ORDER BY FIELD(estado,'pendiente','aprobado','rechazado'), fecha_solicitud DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $cnt_solicitudes_pendientes = $db->query(
            "SELECT COUNT(*) FROM solicitudes_establecimiento WHERE estado='pendiente'"
        )->fetchColumn();
    } catch(Exception $e) {
        $solicitudes = [];
        $cnt_solicitudes_pendientes = 0;
    }

} catch(Exception $ex) {
    $establecimientos = [];
    $solicitudes = [];
    $cnt_solicitudes_pendientes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Establecimientos</title>
<meta name="description" content="Gestión de centros oftalmológicos registrados en RetinAI.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.tabs-bar { display:flex; gap:4px; margin-bottom:24px; border-bottom:2px solid var(--border,#e5e9f0); padding-bottom:0; }
.tab-btn {
    padding:10px 20px; background:none; border:none; border-bottom:2px solid transparent;
    font-family:inherit; font-size:14px; font-weight:600; color:var(--text2,#4A5568);
    cursor:pointer; margin-bottom:-2px; transition:all 0.18s; display:flex; align-items:center; gap:6px;
}
.tab-btn.active { color:var(--accent,#1A56DB); border-bottom-color:var(--accent,#1A56DB); }
.tab-btn:hover:not(.active) { color:var(--text,#0F1923); background:var(--surface2,#F0F3F7); border-radius:6px 6px 0 0; }
.tab-panel { display:none; }
.tab-panel.active { display:block; }
.badge-pending  { background:#FEF3C7; color:#92400E; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-approved { background:#D1FAE5; color:#065F46; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-rejected { background:#FEE2E2; color:#991B1B; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-tipo     { background:#EFF6FF; color:#1E40AF; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.action-btns    { display:flex; gap:6px; }
.btn-approve, .btn-reject {
    padding:5px 12px; border:none; border-radius:6px; font-size:12px;
    font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.18s;
}
.btn-approve { background:#059669; color:#fff; }
.btn-approve:hover { background:#047857; }
.btn-reject  { background:#DC2626; color:#fff; }
.btn-reject:hover  { background:#B91C1C; }
.alert-flash { padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:14px; font-weight:500;
    background:#D1FAE5; border:1px solid #6EE7B7; color:#065F46; animation:fadeUp .3s ease; }
@keyframes fadeUp { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.counter-badge { background:#EF4444; color:#fff; font-size:11px; font-weight:700;
    padding:2px 7px; border-radius:10px; }
.info-block { display:flex; flex-direction:column; gap:2px; font-size:12px; }
.info-block .label { font-size:10px; color:var(--text3); text-transform:uppercase; font-weight:700; }
.evi-link { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:var(--accent); text-decoration:none; margin-right:6px; background:#EFF6FF; padding:4px 8px; border-radius:4px; }
.evi-link:hover { background:#DBEAFE; }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">

        <?php if ($msg_success): ?>
        <div class="alert-flash"><?= htmlspecialchars($msg_success) ?></div>
        <?php endif; ?>

        <div class="section-header">
            <h1 class="page-title">Establecimientos</h1>
            <p class="page-sub">Gestión de centros oftalmológicos en la plataforma.</p>
        </div>

        <!-- ── Centros activos ── -->
        <div class="card">
            <?php if (empty($establecimientos)): ?>
            <p class="empty-msg">No hay establecimientos registrados.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>#</th><th>Nombre</th><th>Dirección</th><th>Médicos</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($establecimientos as $e): ?>
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

    </main>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script>
    if (typeof SessionService !== 'undefined') {
        SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base."views/auth/login.php") ?>' });
    }
</script>
<script src="<?= $base ?>assets/js/dashboard/establecimientos.js"></script>
</body>
</html>
