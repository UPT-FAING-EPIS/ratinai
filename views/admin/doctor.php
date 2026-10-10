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
require_once __DIR__ . '/../../models/DoctorModel.php';

$role_label   = 'Administrador';
$role_class   = 'role-adm';
$avatar_class = 'avatar-adm';

try {
    $estModel = new EstablecimientoModel();
    $docModel = new DoctorModel();

    $user_id = (int)($user['id'] ?? 0);

    // Todos los establecimientos del admin
    $mis_establecimientos = $estModel->getByOwnerId($user_id);

    // Fallback: si no tiene ninguno vinculado por id_usuario, usar el de sesión
    if (empty($mis_establecimientos) && $est_id > 0) {
        $est = $estModel->getById($est_id);
        if ($est) $mis_establecimientos = [$est];
    }

    $est_nombre = $mis_establecimientos[0]['nombre'] ?? 'Mi Establecimiento';
    $header_sub = $est_nombre;
    $tiene_multi = count($mis_establecimientos) > 1;

    if (!empty($mis_establecimientos)) {
        $ids_est = array_map('intval', array_column($mis_establecimientos, 'id'));

        // La gestión incluye cuentas activas e inactivas para poder reactivarlas.
        $activos = $docModel->getDoctorsByEstablishments($ids_est);

        // Badge del sidebar
        $cnt_pendientes = $docModel->countPendingByEstablishments($ids_est);
    } else {
        $activos = [];
        $cnt_pendientes = 0;
    }
    $cnt_activos = count(array_filter($activos, static fn($doctor) => (int) $doctor['activo'] === 1));

} catch (Exception $ex) {
    $mis_establecimientos = []; $est_nombre = ''; $activos = [];
    $cnt_activos = 0; $cnt_pendientes = 0; $header_sub = ''; $tiene_multi = false;
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
.full-width-card { overflow-x: auto; }
#tabla-medicos { min-width: 840px; }
#tabla-medicos th:last-child, #tabla-medicos td:last-child { position: sticky; right: 0; background: var(--surface); box-shadow: -6px 0 10px rgba(15,23,42,.04); }
#tabla-medicos tr:hover td:last-child { background: #F0F3F7; }
.filters-bar { display:grid; grid-template-columns:minmax(220px,2fr) repeat(3,minmax(150px,1fr)); gap:12px; padding:16px; margin-bottom:16px; background:var(--surface,#fff); border:1px solid var(--border,#e2e8f0); border-radius:12px; }
.filters-bar input,.filters-bar select { width:100%; box-sizing:border-box; min-height:40px; padding:0 12px; border:1px solid var(--border,#dbe2ea); border-radius:8px; background:var(--bg,#fff); color:var(--text,#1e293b); font:inherit; }
.table-summary { font-size:13px; color:var(--text-muted,#64748b); margin:0 0 12px; }
.btn-activate { padding:6px 12px; height:32px; border:1px solid #16a34a; border-radius:7px; background:#f0fdf4; color:#15803d; font-weight:600; cursor:pointer; }
.empty-filter { display:none; padding:28px; text-align:center; color:var(--text-muted,#64748b); }
@media(max-width:900px){.filters-bar{grid-template-columns:1fr 1fr}.filters-bar input{grid-column:1/-1}}
@media(max-width:560px){.filters-bar{grid-template-columns:1fr}}
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
                    <h1 class="page-title">Médicos del establecimiento</h1>
                    <p class="page-sub">Consulte, filtre y gestione el acceso de todos los médicos de <?= htmlspecialchars($est_nombre) ?>.</p>
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
                <div class="filters-bar" aria-label="Filtros de médicos">
                    <input type="search" id="filter-search" placeholder="Buscar por nombre, correo o CMP" aria-label="Buscar médico">
                    <select id="filter-specialty" aria-label="Filtrar por especialidad"><option value="">Todas las especialidades</option>
                        <?php foreach (array_unique(array_filter(array_column($activos, 'especialidad'))) as $especialidad): ?><option value="<?= htmlspecialchars(mb_strtolower($especialidad)) ?>"><?= htmlspecialchars($especialidad) ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($tiene_multi): ?><select id="filter-establishment" aria-label="Filtrar por establecimiento"><option value="">Todos los establecimientos</option><?php foreach ($mis_establecimientos as $establecimiento): ?><option value="<?= (int) $establecimiento['id'] ?>"><?= htmlspecialchars($establecimiento['nombre']) ?></option><?php endforeach; ?></select><?php endif; ?>
                    <select id="filter-status" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active">Activo</option><option value="inactive">Inactivo</option><option value="temporary">Clave temporal</option></select>
                </div>
                <p class="table-summary" id="table-summary">Mostrando <?= count($activos) ?> médico(s), <?= $cnt_activos ?> activo(s).</p>
                <?php if (empty($activos)): ?>
                <p class="empty-msg">Aún no hay médicos registrados en este establecimiento.</p>
                <?php else: ?>
                <table class="data-table" id="tabla-medicos">
                    <thead>
                        <tr>
                            <th>Médico</th>
                            <?php if ($tiene_multi): ?><th>Establecimiento</th><?php endif; ?>
                            <th>CMP</th>
                            <th>Especialidad</th>
                            <th>Último acceso</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activos as $m): ?>
                    <tr data-name="<?= htmlspecialchars(mb_strtolower(($m['nombre'] ?? '') . ' ' . ($m['correo'] ?? '') . ' ' . ($m['cmp'] ?? '')), ENT_QUOTES) ?>" data-specialty="<?= htmlspecialchars(mb_strtolower($m['especialidad'] ?? ''), ENT_QUOTES) ?>" data-establishment="<?= (int) $m['establecimiento_id'] ?>" data-status="<?= (int) $m['activo'] === 1 ? ($m['es_password_temporal'] ? 'active temporary' : 'active') : 'inactive' ?>">
                        <td>
                            <strong><?= htmlspecialchars($m['nombre']) ?></strong><br>
                            <span class="text-muted small"><?= htmlspecialchars($m['correo']) ?></span>
                        </td>
                        <?php if ($tiene_multi): ?>
                        <td class="small"><?= htmlspecialchars($m['est_nombre'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td class="mono"><?= htmlspecialchars($m['cmp'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['especialidad'] ?? '—') ?></td>
                        <td class="text-muted small">
                            <?= $m['ultimo_acceso'] ? date('d M Y H:i', strtotime($m['ultimo_acceso'])) : '—' ?>
                        </td>
                        <td>
                            <span class="badge <?= (int)$m['activo'] === 1 ? 'badge-active' : 'badge-warning' ?>"><?= (int)$m['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                            <?php if ((int)$m['activo'] === 1 && $m['es_password_temporal']): ?>
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
                                <form method="POST" action="<?= $base ?>controllers/DoctorController.php?action=<?= (int)$m['activo'] === 1 ? 'deactivate' : 'activate' ?>" style="margin:0;" onsubmit="return confirm('<?= (int)$m['activo'] === 1 ? '¿Desactivar el acceso de este médico?' : '¿Activar el acceso de este médico?' ?>');">
                                    <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                                    <?php if ((int)$m['activo'] === 1): ?><button type="submit" class="btn btn-sm btn-danger-outline" style="padding:6px 12px; height:32px;">Desactivar</button><?php else: ?><button type="submit" class="btn-activate">Activar</button><?php endif; ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="empty-filter" id="empty-filter">No hay médicos que coincidan con los filtros seleccionados.</p>
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
            const mensaje = res.correo_enviado
                ? 'La contraseña ha sido reseteada y enviada al correo del médico.'
                : `${res.message} Clave: ${res.contrasena_temporal}`;
            showFloatingMsg(mensaje);
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

const doctorRows = Array.from(document.querySelectorAll('#tabla-medicos tbody tr'));
const searchFilter = document.getElementById('filter-search');
const specialtyFilter = document.getElementById('filter-specialty');
const establishmentFilter = document.getElementById('filter-establishment');
const statusFilter = document.getElementById('filter-status');
function applyDoctorFilters() {
    const query = (searchFilter?.value || '').trim().toLowerCase();
    const specialty = specialtyFilter?.value || '';
    const establishment = establishmentFilter?.value || '';
    const status = statusFilter?.value || '';
    let visible = 0;
    doctorRows.forEach(row => {
        const matches = (!query || row.dataset.name.includes(query))
            && (!specialty || row.dataset.specialty === specialty)
            && (!establishment || row.dataset.establishment === establishment)
            && (!status || row.dataset.status.split(' ').includes(status));
        row.hidden = !matches;
        if (matches) visible++;
    });
    const empty = document.getElementById('empty-filter');
    if (empty) empty.style.display = doctorRows.length && !visible ? 'block' : 'none';
    const summary = document.getElementById('table-summary');
    if (summary) summary.textContent = `Mostrando ${visible} de ${doctorRows.length} médico(s).`;
}
[searchFilter, specialtyFilter, establishmentFilter, statusFilter].filter(Boolean).forEach(input => input.addEventListener(input.tagName === 'INPUT' ? 'input' : 'change', applyDoctorFilters));

const flash = document.getElementById('flash-msg');
if (flash) setTimeout(() => { flash.style.opacity = '0'; setTimeout(() => flash.remove(), 400); }, 3000);
</script>
</body>
</html>
