<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('SAD');

require_once __DIR__ . '/../../models/SolicitudModel.php';

$user     = current_user();
$initials = get_initials($user['nombre']);
$base     = get_base_path();
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

    // Solicitudes pendientes de registro
    $solicitudes = [];
    $cnt_solicitudes_pendientes = 0;
    try {
        $solicitudModel = new SolicitudModel();
        $solicitudes = $solicitudModel->getAllSolicitudesConOrigen();
        $cnt_solicitudes_pendientes = $solicitudModel->countPendientes();
    } catch(Exception $e) {
        $solicitudes = [];
        $cnt_solicitudes_pendientes = 0;
    }

} catch(Exception $ex) {
    $solicitudes = [];
    $cnt_solicitudes_pendientes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Solicitudes de Registro</title>
<meta name="description" content="Gestión de solicitudes de registro de centros oftalmológicos en RetinAI.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
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
.info-block { display:flex; flex-direction:column; gap:2px; font-size:12px; }
.info-block .label { font-size:10px; color:var(--text3); text-transform:uppercase; font-weight:700; }
.evi-link { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:var(--accent); text-decoration:none; margin-right:6px; background:#EFF6FF; padding:4px 8px; border-radius:4px; cursor:pointer; border:none; }
.evi-link:hover { background:#DBEAFE; }

/* Modal Preview Evidencias */
.modal-overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,0.6); backdrop-filter:blur(3px);
    display:none; align-items:center; justify-content:center;
    z-index:9999;
}
.modal-overlay.active { display:flex; }
.modal-content {
    background:#fff; border-radius:12px; width:90%; max-width:800px;
    max-height:90vh; display:flex; flex-direction:column;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
}
.modal-header {
    padding:16px 20px; border-bottom:1px solid #e5e9f0;
    display:flex; justify-content:space-between; align-items:center;
}
.modal-title { font-size:16px; font-weight:700; color:#0F1923; margin:0; }
.btn-close {
    background:none; border:none; font-size:20px; color:#4A5568;
    cursor:pointer; padding:4px; line-height:1;
}
.btn-close:hover { color:#0F1923; }
.modal-body {
    padding:20px; overflow-y:auto; flex:1; text-align:center;
    background:#f8fafc; border-radius:0 0 12px 12px;
}
.modal-body img, .modal-body iframe, .modal-body object {
    max-width:100%; max-height:70vh; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
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
            <h1 class="page-title">Solicitudes de Registro</h1>
            <p class="page-sub">Gestión y aprobación de solicitudes de centros oftalmológicos.</p>
        </div>

        <div class="card" style="overflow-x:auto;">
            <?php if (empty($solicitudes)): ?>
            <p class="empty-msg">No hay solicitudes de registro enviadas aún.</p>
            <?php else: ?>
            <table class="data-table" style="min-width:1000px;">
                <thead>
                    <tr>
                        <th>Centro Oftalmológico</th>
                        <th>Titular</th>
                        <th>Contacto</th>
                        <th>Origen</th>
                        <th>Evidencias</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                <tr id="row-solicitud-<?= (int)$s['id'] ?>">
                    <td>
                        <div class="info-block">
                            <strong><?= htmlspecialchars($s['nombre_centro']) ?></strong>
                            <span>RUC: <span class="mono"><?= htmlspecialchars($s['ruc']) ?></span></span>
                            <span><span class="badge-tipo" style="padding:1px 6px;font-size:10px;"><?= ucfirst(htmlspecialchars($s['tipo'])) ?></span> <?= htmlspecialchars($s['direccion']) ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="info-block">
                            <strong><?= htmlspecialchars($s['nombres_titular'] . ' ' . $s['apellidos_titular']) ?></strong>
                            <span>DNI: <span class="mono"><?= htmlspecialchars($s['dni_titular']) ?></span></span>
                        </div>
                    </td>
                    <td>
                        <div class="info-block">
                            <span>📞 <?= htmlspecialchars($s['telefono']) ?></span>
                            <span>✉️ <?= htmlspecialchars($s['correo_contacto']) ?></span>
                        </div>
                    </td>
                    <!-- Columna Origen: distingue solicitud pública vs. ADM existente -->
                    <td>
                        <?php if (!empty($s['id_usuario_solicitante'])): ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#ECFDF5;color:#065F46;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;">
                            🛡️ ADM registrado
                        </span>
                        <div style="font-size:11px;color:var(--text3);margin-top:3px;"><?= htmlspecialchars($s['nombre_usuario_solicitante'] ?? '') ?></div>
                        <?php else: ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#EFF6FF;color:#1E40AF;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;">
                            🌐 Solicitud pública
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['evidencia_1']): ?>
                        <button type="button" class="evi-link btn-preview" data-name="<?= htmlspecialchars($s['evidencia_1_nombre'] ?: 'Evidencia 1') ?>" data-src="<?= htmlspecialchars($s['evidencia_1']) ?>">📎 Evidencia 1</button>
                        <?php endif; ?>
                        <?php if ($s['evidencia_2']): ?>
                        <button type="button" class="evi-link btn-preview" data-name="<?= htmlspecialchars($s['evidencia_2_nombre'] ?: 'Evidencia 2') ?>" data-src="<?= htmlspecialchars($s['evidencia_2']) ?>">📎 Evidencia 2</button>
                        <?php endif; ?>
                        <?php if (!$s['evidencia_1'] && !$s['evidencia_2']) echo '<span class="text-muted small">—</span>'; ?>
                    </td>
                    <td class="text-muted small"><?= $s['fecha_solicitud'] ? date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) : '—' ?></td>
                    <td>
                        <?php
                        $estadoClass = match($s['estado']) {
                            'pendiente' => 'badge-pending',
                            'aprobado'  => 'badge-approved',
                            'rechazado' => 'badge-rejected',
                            default     => 'badge-info'
                        };
                        $estadoLabel = match($s['estado']) {
                            'pendiente' => 'Pendiente',
                            'aprobado'  => 'Aprobado',
                            'rechazado' => 'Rechazado',
                            default     => ucfirst($s['estado'])
                        };
                        ?>
                        <span class="<?= $estadoClass ?>"><?= $estadoLabel ?></span>
                    </td>
                    <td>
                        <?php if ($s['estado'] === 'pendiente'): ?>
                        <?php
                            // Mensaje de confirmación diferente según origen
                            $esAdmExistente = !empty($s['id_usuario_solicitante']);
                            $confirmMsg = $esAdmExistente
                                ? '¿Aprobar esta solicitud? El establecimiento se vinculará al ADM existente (NO se creará nueva cuenta ni se enviará contraseña).'
                                : '¿Aprobar esta solicitud? Se creará el establecimiento, la cuenta ADM y se enviarán las credenciales al titular.';
                        ?>
                        <div class="action-btns">
                            <form method="POST" action="<?= $base ?>controllers/SolicitudController.php" style="margin:0">
                                <input type="hidden" name="action" value="aprobar">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn-approve"
                                    onclick="return confirm('<?= htmlspecialchars($confirmMsg, ENT_QUOTES) ?>')">
                                    ✓ Aprobar
                                </button>
                            </form>
                            <form method="POST" action="<?= $base ?>controllers/SolicitudController.php" style="margin:0">
                                <input type="hidden" name="action" value="rechazar">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn-reject" onclick="return confirm('¿Rechazar esta solicitud?')">✗ Rechazar</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
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

<!-- Modal -->
<div class="modal-overlay" id="preview-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title">Previsualización de Evidencia</h3>
            <button type="button" class="btn-close" id="btn-close-modal">✕</button>
        </div>
        <div class="modal-body" id="modal-body">
            <!-- El contenido dinámico va aquí (img o embed/iframe) -->
        </div>
    </div>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script>
    if (typeof SessionService !== 'undefined') {
        SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base."views/auth/login.php") ?>' });
    }
</script>
<script src="<?= $base ?>assets/js/dashboard/solicitudes.js"></script>
</body>
</html>
