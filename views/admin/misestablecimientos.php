<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('ADM');

$user     = current_user();
$initials = get_initials($user['nombre']);
$base     = get_base_path();
$est_id   = (int)($user['establecimiento_id'] ?? 0);
$logout_url = $base . 'controllers/AuthController.php?action=logout';

$role_label   = '🛡️ Administrador';
$role_class   = 'role-adm';
$avatar_class = 'avatar-adm';

try {
    $db   = (new Database())->getConnection();
    // Establecimiento actual
    $stmt = $db->prepare("SELECT nombre, direccion, tipo, ruc FROM establecimientos WHERE id = :id");
    $stmt->execute([':id' => $est_id]);
    $est_actual = $stmt->fetch(PDO::FETCH_ASSOC);

    // Solicitudes del admin actual
    $q = $db->prepare(
        "SELECT id, nombre_centro, direccion, tipo, ruc, estado, fecha_solicitud
         FROM solicitudes_establecimiento WHERE correo_contacto = :correo
         ORDER BY fecha_solicitud DESC"
    );
    $q->execute([':correo' => $user['correo']]);
    $solicitudes = $q->fetchAll(PDO::FETCH_ASSOC);

    // Badge del sidebar (pendientes medicos)
    $cnt_pendientes = (int)$db->query(
        "SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=0 AND establecimiento_id=$est_id"
    )->fetchColumn();
} catch (Exception $ex) {
    $est_actual = null; $solicitudes = [];
    $cnt_pendientes = 0;
}

$msg_ok = '';
if (isset($_SESSION['solicitud_success'])) {
    $msg_ok = $_SESSION['solicitud_success'];
    unset($_SESSION['solicitud_success']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Mis Establecimientos</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.section-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.btn-add { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #1A56DB 0%, #1e40af 100%); color: #fff; font-size: 13px; font-weight: 600; border-radius: 10px; text-decoration: none; border: none; cursor: pointer; transition: transform .15s, box-shadow .15s; box-shadow: 0 4px 14px rgba(26,86,219,.35); white-space: nowrap; }
.btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,86,219,.45); }
.badge-pendiente { background-color: #fef08a; color: #854d0e; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.badge-aprobado { background-color: #bbf7d0; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.badge-rechazado { background-color: #fecaca; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($msg_ok): ?><div class="alert-flash alert-ok" id="flash-msg"><?= $msg_ok ?></div><?php endif; ?>

        <section class="content-section">
            <div class="section-top">
                <div>
                    <h1 class="page-title">Mis Establecimientos</h1>
                    <p class="page-sub">Revise el estado de sus establecimientos y registre nuevos.</p>
                </div>
                <a href="<?= $base ?>views/admin/nuevoestablecimiento.php" class="btn-add">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="8" r="4" stroke="white" stroke-width="1.8"/>
                        <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M19 3v6M16 6h6" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    Añadir establecimiento
                </a>
            </div>

            <?php if ($est_actual): ?>
            <div class="card full-width-card" style="margin-bottom: 24px; border-left: 4px solid #10b981;">
                <h3 style="margin-top:0; font-size: 16px; margin-bottom: 12px;">Establecimiento Activo</h3>
                <p style="margin: 4px 0; font-size: 14px;"><strong>Nombre:</strong> <?= htmlspecialchars($est_actual['nombre']) ?></p>
                <p style="margin: 4px 0; font-size: 14px;"><strong>RUC:</strong> <?= htmlspecialchars($est_actual['ruc'] ?? '—') ?></p>
                <p style="margin: 4px 0; font-size: 14px;"><strong>Dirección:</strong> <?= htmlspecialchars($est_actual['direccion'] ?? '—') ?></p>
                <p style="margin: 4px 0; font-size: 14px;"><strong>Tipo:</strong> <?= ucfirst(htmlspecialchars($est_actual['tipo'] ?? '—')) ?></p>
            </div>
            <?php endif; ?>

            <div class="card full-width-card">
                <h3 style="margin-top:0; font-size: 16px; margin-bottom: 16px;">Historial de Solicitudes</h3>
                <?php if (empty($solicitudes)): ?>
                <p class="empty-msg">No ha registrado solicitudes adicionales.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Centro</th>
                            <th>Dirección</th>
                            <th>RUC</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($s['nombre_centro']) ?></strong><br>
                            <span class="text-muted small">Tipo: <?= ucfirst(htmlspecialchars($s['tipo'])) ?></span>
                        </td>
                        <td><?= htmlspecialchars($s['direccion']) ?></td>
                        <td class="mono"><?= htmlspecialchars($s['ruc']) ?></td>
                        <td class="text-muted small"><?= date('d/m/Y', strtotime($s['fecha_solicitud'])) ?></td>
                        <td>
                            <?php if ($s['estado'] === 'pendiente'): ?>
                                <span class="badge-pendiente">Pendiente</span>
                            <?php elseif ($s['estado'] === 'aprobado'): ?>
                                <span class="badge-aprobado">Aprobado</span>
                            <?php else: ?>
                                <span class="badge-rechazado">Rechazado</span>
                            <?php endif; ?>
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
SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base . "views/auth/login.php") ?>' });
let remaining = 300;
const cd = document.getElementById('session-countdown');
if(cd) {
    setInterval(() => {
        const m = Math.floor(remaining / 60).toString().padStart(2, '0');
        const s = (remaining % 60).toString().padStart(2, '0');
        cd.textContent = m + ':' + s;
        if (remaining > 0) remaining--;
    }, 1000);
}
const flash = document.getElementById('flash-msg');
if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 3000);
</script>
</body>
</html>
