<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/EstablecimientoModel.php';
require_role('SAD');

$user         = current_user();
$initials     = get_initials($user['nombre']);
$base         = get_base_path();
$logout_url   = $base . 'controllers/AuthController.php?action=logout';
$role_label   = '⚡ Super Administrador';
$role_class   = 'role-sad';
$avatar_class = 'avatar-sad';
$header_sub   = 'Detalles del Establecimiento';

// Validar ID
$id_establecimiento = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_establecimiento === 0) {
    header('Location: Establecimientos.php');
    exit;
}

// Flash messages desde el controlador
$msg_success = '';
$msg_error   = '';
if (isset($_SESSION['est_success'])) {
    $msg_success = $_SESSION['est_success'];
    unset($_SESSION['est_success']);
}
if (isset($_SESSION['est_error'])) {
    $msg_error = $_SESSION['est_error'];
    unset($_SESSION['est_error']);
}

// Cargar datos a través del modelo
$model = new EstablecimientoModel();
try {
    $establecimiento = $model->getById($id_establecimiento);
    if (!$establecimiento) {
        header('Location: Establecimientos.php');
        exit;
    }
    $admins  = $model->getAdminByEstablecimiento($id_establecimiento);
    $medicos = $model->getMedicosByEstablecimiento($id_establecimiento);
} catch (Exception $ex) {
    $msg_error       = 'Error cargando detalles del establecimiento.';
    $establecimiento = [];
    $admins          = [];
    $medicos         = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Detalles Establecimiento</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/detalles_establecimientos.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <?php if ($msg_success): ?>
        <div class="alert-flash alert-ok" id="flash-msg"><?= htmlspecialchars($msg_success) ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
        <div class="alert-flash alert-err" id="flash-msg-err"><?= htmlspecialchars($msg_error) ?></div>
        <?php endif; ?>

        <div class="section-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="page-title">Detalles del Establecimiento</h1>
                    <p class="page-sub">Edita la información y gestiona los usuarios del establecimiento.</p>
                </div>
                <a href="Establecimientos.php" class="btn btn-outline" style="text-decoration:none; display:inline-flex; align-items:center; gap: 8px; padding:8px 16px;">
                    <svg viewBox="0 0 20 20" fill="none" width="16" height="16"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Volver
                </a>
            </div>
        </div>

        <div class="detalles-grid">
            <!-- Formulario de Edición — POST al controlador -->
            <div class="card form-card">
                <h3 class="card-title">Información del Establecimiento</h3>
                <form method="POST"
                      action="<?= $base ?>controllers/EstablecimientoController.php?action=update"
                      id="form-est">
                    <input type="hidden" name="id_establecimiento" value="<?= $id_establecimiento ?>">
                    <div class="form-group">
                        <label>Nombre del Centro</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($establecimiento['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="direccion" value="<?= htmlspecialchars($establecimiento['direccion'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">Seleccione...</option>
                            <option value="publico"  <?= (($establecimiento['tipo'] ?? '') === 'publico')  ? 'selected' : '' ?>>Público</option>
                            <option value="privado"  <?= (($establecimiento['tipo'] ?? '') === 'privado')  ? 'selected' : '' ?>>Privado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>RUC</label>
                        <input type="text" name="ruc" value="<?= htmlspecialchars($establecimiento['ruc'] ?? '') ?>" maxlength="11" pattern="\d{11}">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>

            <!-- Información de Usuarios -->
            <div class="users-section">
                <!-- Titular/Admin -->
                <div class="card list-card">
                    <h3 class="card-title">Titular / Administrador</h3>
                    <?php if (empty($admins)): ?>
                        <p class="empty-msg">No hay administrador asignado a este establecimiento.</p>
                    <?php else: ?>
                        <div class="user-list">
                            <?php foreach ($admins as $adm): ?>
                                <div class="user-item">
                                    <div class="user-info">
                                        <strong><?= htmlspecialchars($adm['nombre']) ?></strong>
                                        <span class="text-muted small"><?= htmlspecialchars($adm['correo']) ?></span>
                                    </div>
                                    <span class="badge badge-active">Admin</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Médicos -->
                <div class="card list-card">
                    <h3 class="card-title">Médicos Oftalmólogos</h3>
                    <?php if (empty($medicos)): ?>
                        <p class="empty-msg">No hay médicos registrados en este establecimiento.</p>
                    <?php else: ?>
                        <div class="user-list">
                            <?php foreach ($medicos as $med): ?>
                                <div class="user-item">
                                    <div class="user-info">
                                        <strong><?= htmlspecialchars($med['nombre']) ?></strong>
                                        <span class="text-muted small"><?= htmlspecialchars($med['correo']) ?> | CMP: <?= htmlspecialchars($med['cmp'] ?? '—') ?></span>
                                    </div>
                                    <?php if ($med['activo']): ?>
                                        <span class="badge badge-active">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Inactivo</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script>
    if (typeof SessionService !== 'undefined') {
        SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base."views/auth/login.php") ?>' });
    }
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
    if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 3500);
</script>
<script src="<?= $base ?>assets/js/dashboard/detalles_establecimientos.js"></script>
</body>
</html>
