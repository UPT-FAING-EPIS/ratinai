<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('ADM');

$user     = current_user();
$initials = get_initials($user['nombre']);
$base     = get_base_path();
$est_id   = (int)($user['establecimiento_id'] ?? 0);
$logout_url = $base . 'controllers/AuthController.php?action=logout';

require_once __DIR__ . '/../../models/EstablecimientoModel.php';
require_once __DIR__ . '/../../models/SolicitudModel.php';
require_once __DIR__ . '/../../models/DoctorModel.php';

$role_label   = '🛡️ Administrador';
$role_class   = 'role-adm';
$avatar_class = 'avatar-adm';

try {
    $estModel = new EstablecimientoModel();
    $solModel = new SolicitudModel();
    $docModel = new DoctorModel();

    $user_id = (int)($user['id'] ?? 0);

    // Todos los establecimientos que pertenecen al usuario
    $mis_establecimientos = $estModel->getByOwnerId($user_id);

    // Solicitudes del admin actual
    $solicitudes = $solModel->getSolicitudesByContactEmailOrOwner($user['correo'], $user_id);

    // Badge del sidebar (pendientes médicos de CUALQUIER establecimiento del admin)
    $cnt_pendientes = 0;
    if (!empty($mis_establecimientos)) {
        $ids = array_map('intval', array_column($mis_establecimientos, 'id'));
        $cnt_pendientes = $docModel->countPendingByEstablishments($ids);
    }
    // también incluir el establecimiento original asignado (por compatibilidad)
    if ($est_id > 0) {
        // Agregamos al arreglo si no estaba o lo contamos individualmente si no hay previos
        if (empty($mis_establecimientos)) {
            $cnt_pendientes = $docModel->countPendingByEstablishments([$est_id]);
        }
    }

} catch (Exception $ex) {
    $mis_establecimientos = []; $solicitudes = [];
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
.badge-aprobado  { background-color: #bbf7d0; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.badge-rechazado { background-color: #fecaca; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }

/* Grid de establecimientos */
.est-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 28px; }
.est-card  { background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; border-radius: 12px; padding: 20px; transition: box-shadow .2s; }
.est-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.08); }
.est-card-title { font-size: 15px; font-weight: 700; margin: 0 0 10px; color: #0f172a; }
.est-card p { margin: 4px 0; font-size: 13px; color: #475569; }
.est-card strong { color: #0f172a; }
.badge-tipo { display: inline-block; margin-top: 8px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #e0f2fe; color: #0369a1; }
.badge-tipo.privado { background: #fce7f3; color: #9d174d; }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($msg_ok): ?><div class="alert-flash alert-ok" id="flash-msg"><?= $msg_ok ?></div><?php endif; ?>

        <div class="section-header" style="display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
            <div>
                <h1 class="page-title">Mis Establecimientos</h1>
                <p class="page-sub">Gestione sus centros oftalmológicos y solicite el registro de nuevos.</p>
            </div>
                <a href="<?= $base ?>views/admin/nuevoestablecimiento.php" class="btn-add">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M12 5v14M5 12h14" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Añadir establecimiento
                </a>
            </div>

            <!-- ── Establecimientos activos ─────────────────────────────── -->
            <h3 style="font-size:15px; margin-bottom:14px; color:#0f172a;">Establecimientos a su cargo (<?= count($mis_establecimientos) ?>)</h3>

            <?php if (empty($mis_establecimientos)): ?>
            <div class="card full-width-card" style="margin-bottom:24px;">
                <p class="empty-msg">Aún no tiene establecimientos registrados. Solicite uno usando el botón de arriba.</p>
            </div>
            <?php else: ?>
            <div class="est-grid">
                <?php foreach ($mis_establecimientos as $e): ?>
                <div class="est-card">
                    <p class="est-card-title">🏥 <?= htmlspecialchars($e['nombre']) ?></p>
                    <p><strong>RUC:</strong> <?= htmlspecialchars($e['ruc'] ?? '—') ?></p>
                    <p><strong>Dirección:</strong> <?= htmlspecialchars($e['direccion'] ?? '—') ?></p>
                    <span class="badge-tipo <?= $e['tipo'] === 'privado' ? 'privado' : '' ?>">
                        <?= ucfirst(htmlspecialchars($e['tipo'] ?? '—')) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ── Historial de solicitudes ────────────────────────────── -->
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
