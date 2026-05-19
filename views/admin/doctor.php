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
    $stmt = $db->prepare("SELECT nombre FROM establecimientos WHERE id = :id");
    $stmt->execute([':id' => $est_id]);
    $est        = $stmt->fetch(PDO::FETCH_ASSOC);
    $est_nombre = $est['nombre'] ?? 'Mi Establecimiento';
    $header_sub = $est_nombre;

    // Médicos activos
    $q = $db->prepare(
        "SELECT id, nombre, correo, cmp, especialidad, ultimo_acceso, es_password_temporal
         FROM usuarios WHERE rol_codigo='MED' AND activo=1 AND establecimiento_id=:eid
         ORDER BY nombre"
    );
    $q->execute([':eid' => $est_id]);
    $activos = $q->fetchAll(PDO::FETCH_ASSOC);
    $cnt_activos = count($activos);

    // Badge del sidebar (pendientes)
    $cnt_pendientes = (int)$db->query(
        "SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=0 AND establecimiento_id=$est_id"
    )->fetchColumn();
} catch (Exception $ex) {
    $est_nombre = ''; $activos = [];
    $cnt_activos = 0; $cnt_pendientes = 0; $header_sub = '';
}

// Leer flash messages desde la sesión
$msg_ok = $_SESSION['flash_success'] ?? '';
$msg_err = $_SESSION['flash_errors'][0] ?? '';

// Limpiar mensajes después de leer
unset($_SESSION['flash_success'], $_SESSION['flash_errors']);
if (isset($_GET['ok'])) {
    $msg_ok = htmlspecialchars($_GET['ok']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Médicos</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.section-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.btn-add-doctor { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #1A56DB 0%, #1e40af 100%); color: #fff; font-size: 13px; font-weight: 600; border-radius: 10px; text-decoration: none; border: none; cursor: pointer; transition: transform .15s, box-shadow .15s; box-shadow: 0 4px 14px rgba(26,86,219,.35); white-space: nowrap; }
.btn-add-doctor:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,86,219,.45); }
.btn-add-doctor svg { flex-shrink: 0; }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">

        <?php if ($msg_ok): ?><div class="alert-flash alert-ok" id="flash-msg"><?= $msg_ok ?></div><?php endif; ?>
        <?php if ($msg_err): ?><div class="alert-flash alert-err"><?= $msg_err ?></div><?php endif; ?>

        <section class="content-section">
            <div class="section-top">
                <div>
                    <h1 class="page-title">Médicos activos</h1>
                    <p class="page-sub">Gestione el acceso de los médicos de <?= htmlspecialchars($est_nombre) ?>.</p>
                </div>
                <a href="<?= $base ?>views/admin/create_doctor.php" class="btn-add-doctor" id="btn-agregar-medico">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="8" r="4" stroke="white" stroke-width="1.8"/>
                        <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M19 3v6M16 6h6" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    Agregar Médico
                </a>
            </div>

            <div class="card">
                <?php if (empty($activos)): ?>
                <p class="empty-msg">No hay médicos activos en este establecimiento.</p>
                <?php else: ?>
                <table class="data-table" id="tabla-medicos">
                    <thead>
                        <tr>
                            <th>Médico</th>
                            <th>CMP</th>
                            <th>Especialidad</th>
                            <th>Último acceso</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activos as $m): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($m['nombre']) ?></strong><br>
                            <span class="text-muted small"><?= htmlspecialchars($m['correo']) ?></span>
                        </td>
                        <td class="mono"><?= htmlspecialchars($m['cmp'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['especialidad'] ?? '—') ?></td>
                        <td class="text-muted small">
                            <?= $m['ultimo_acceso'] ? date('d M Y H:i', strtotime($m['ultimo_acceso'])) : '—' ?>
                        </td>
                        <td>
                            <span class="badge badge-active">Activo</span>
                            <?php if ($m['es_password_temporal']): ?>
                                <span class="badge badge-warning" style="margin-left:4px">Clave temporal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="<?= $base ?>controllers/DoctorController.php?action=deactivate">
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
SessionService.init({ timeout: 300000, loginUrl: '<?= htmlspecialchars($base . "views/auth/login.php") ?>' });
let remaining = 300;
const cd = document.getElementById('session-countdown');
setInterval(() => {
    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
    const s = (remaining % 60).toString().padStart(2, '0');
    cd.textContent = m + ':' + s;
    if (remaining > 0) remaining--;
}, 1000);

const flash = document.getElementById('flash-msg');
if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 3000);
</script>
</body>
</html>
