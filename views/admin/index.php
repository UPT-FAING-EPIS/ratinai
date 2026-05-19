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

    // Solicitudes pendientes (activo=0)
    $q = $db->prepare(
        "SELECT id, nombre, correo, cmp, especialidad, ultimo_acceso
         FROM usuarios WHERE rol_codigo='MED' AND activo=0 AND establecimiento_id=:eid
         ORDER BY nombre"
    );
    $q->execute([':eid' => $est_id]);
    $pendientes = $q->fetchAll(PDO::FETCH_ASSOC);

    // Contadores para KPIs y sidebar badge
    $cnt_pendientes = count($pendientes);
    $cnt_activos    = (int)$db->prepare(
        "SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=1 AND establecimiento_id=:eid"
    )->execute([':eid' => $est_id]) ? $db->query(
        "SELECT COUNT(*) FROM usuarios WHERE rol_codigo='MED' AND activo=1 AND establecimiento_id=$est_id"
    )->fetchColumn() : 0;
} catch (Exception $ex) {
    $est_nombre = ''; $pendientes = [];
    $cnt_pendientes = 0; $cnt_activos = 0; $header_sub = '';
}

// Acciones POST: aprobar / rechazar
$msg_ok = $msg_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    try {
        $target_id = (int)($_POST['target_id'] ?? 0);
        if ($_POST['action_type'] === 'aprobar') {
            $s = $db->prepare("UPDATE usuarios SET activo=1 WHERE id=:id AND establecimiento_id=:eid AND rol_codigo='MED'");
            $s->execute([':id' => $target_id, ':eid' => $est_id]);
            $msg_ok = "Médico aprobado exitosamente.";
        } elseif ($_POST['action_type'] === 'rechazar') {
            $s = $db->prepare("UPDATE usuarios SET activo=0 WHERE id=:id AND establecimiento_id=:eid AND rol_codigo='MED'");
            $s->execute([':id' => $target_id, ':eid' => $est_id]);
            $msg_ok = "Solicitud rechazada.";
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?ok=" . urlencode($msg_ok));
        exit();
    } catch (Exception $ex) {
        $msg_err = "Error al procesar la acción.";
    }
}
if (isset($_GET['ok'])) $msg_ok = htmlspecialchars($_GET['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Dashboard Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">

    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">

        <?php if ($msg_ok): ?><div class="alert-flash alert-ok" id="flash-msg"><?= $msg_ok ?></div><?php endif; ?>
        <?php if ($msg_err): ?><div class="alert-flash alert-err"><?= $msg_err ?></div><?php endif; ?>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon kpi-warning">⏳</div>
                <div class="kpi-body">
                    <span class="kpi-value"><?= $cnt_pendientes ?></span>
                    <span class="kpi-label">Solicitudes pendientes</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon kpi-green">✅</div>
                <div class="kpi-body">
                    <span class="kpi-value"><?= $cnt_activos ?></span>
                    <span class="kpi-label">Médicos activos</span>
                </div>
            </div>
        </div>

        <!-- Solicitudes pendientes -->
        <section class="content-section">
            <div class="section-header">
                <h1 class="page-title">Solicitudes de registro</h1>
                <p class="page-sub">Revise y apruebe o rechace solicitudes de médicos oftalmólogos.</p>
            </div>
            <div class="card">
                <?php if (empty($pendientes)): ?>
                <p class="empty-msg">✅ No hay solicitudes pendientes.</p>
                <?php else: ?>
                <table class="data-table" id="tabla-pendientes">
                    <thead>
                        <tr><th>Médico</th><th>CMP</th><th>Especialidad</th><th>Estado</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendientes as $m): ?>
                    <tr id="row-<?= $m['id'] ?>">
                        <td>
                            <strong><?= htmlspecialchars($m['nombre']) ?></strong><br>
                            <span class="text-muted small"><?= htmlspecialchars($m['correo']) ?></span>
                        </td>
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
