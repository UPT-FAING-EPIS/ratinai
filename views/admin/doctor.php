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

            <div class="card full-width-card">
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
                            <div class="flex-actions">
                                <button type="button" class="btn-action-icon" title="Editar" onclick="openEditModal(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nombre'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($m['correo'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($m['cmp'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($m['especialidad'] ?? '', ENT_QUOTES) ?>')">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                                <button type="button" class="btn-action-icon" title="Resetear contraseña" onclick="openResetModal(<?= $m['id'] ?>)">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                </button>
                                <form method="POST" action="<?= $base ?>controllers/DoctorController.php?action=deactivate" style="margin:0;">
                                    <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger-outline" style="padding:6px 12px; height:32px;">Desactivar</button>
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

<div id="floating-msg" class="floating-msg">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    <span id="floating-msg-text"></span>
</div>

<!-- Modal Editar -->
<div id="modal-edit" class="modal-overlay">
    <div class="modal-content">
        <h2 class="modal-title">Editar datos del médico</h2>
        <form id="form-edit" method="POST">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group">
                <label>Nombre completo</label>
                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="correo" id="edit_correo" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Número CMP</label>
                <input type="text" name="cmp" id="edit_cmp" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Especialidad</label>
                <input type="text" name="especialidad" id="edit_especialidad" class="form-control" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn-save">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Resetear -->
<div id="modal-reset" class="modal-overlay">
    <div class="modal-content" style="text-align: center; max-width: 350px;">
        <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="#eab308" style="margin: 0 auto 16px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <h2 class="modal-title" style="margin-bottom:8px; font-size:16px;">¿Está seguro de que desea resetear la contraseña de este médico?</h2>
        <p style="font-size:13px; color:#64748b; margin-bottom:24px;">Se generará una nueva clave temporal y se enviará automáticamente al correo del médico.</p>
        <form id="form-reset" method="POST">
            <input type="hidden" name="reset_id" id="reset_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeResetModal()">Cancelar</button>
                <button type="submit" class="btn-save" style="background: #1A56DB;">Sí, resetear</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= $base ?>assets/js/session.service.js"></script>
<script>
function showFloatingMsg(msg) {
    const f = document.getElementById('floating-msg');
    document.getElementById('floating-msg-text').textContent = msg;
    f.classList.add('show');
    setTimeout(() => { f.classList.remove('show'); }, 4000);
}

// Modal Editar logic
function openEditModal(id, nombre, correo, cmp, especialidad) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_correo').value = correo;
    document.getElementById('edit_cmp').value = cmp;
    document.getElementById('edit_especialidad').value = especialidad;
    document.getElementById('modal-edit').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('modal-edit').style.display = 'none';
}

document.getElementById('form-edit').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = new FormData(this);
    fetch('<?= $base ?>controllers/DoctorController.php?action=edit', {
        method: 'POST', body: data
    }).then(r => r.json()).then(res => {
        if(res.success) {
            closeEditModal();
            showFloatingMsg('Los datos del médico han sido actualizados correctamente.');
            setTimeout(() => location.reload(), 2000);
        } else {
            alert(res.message);
        }
    }).catch(e => { alert('Ocurrió un error.'); });
});

// Modal Reset logic
function openResetModal(id) {
    document.getElementById('reset_id').value = id;
    document.getElementById('modal-reset').style.display = 'flex';
}
function closeResetModal() {
    document.getElementById('modal-reset').style.display = 'none';
}

document.getElementById('form-reset').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    
    const data = new FormData(this);
    fetch('<?= $base ?>controllers/DoctorController.php?action=reset', {
        method: 'POST', body: data
    }).then(r => r.json()).then(res => {
        if(res.success) {
            closeResetModal();
            showFloatingMsg('La contraseña ha sido reseteada y enviada al correo del médico.');
            setTimeout(() => location.reload(), 2000);
        } else {
            alert(res.message);
            btn.disabled = false;
            btn.textContent = 'Sí, resetear';
        }
    }).catch(e => { 
        alert('Ocurrió un error.'); 
        btn.disabled = false;
        btn.textContent = 'Sí, resetear';
    });
});

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
