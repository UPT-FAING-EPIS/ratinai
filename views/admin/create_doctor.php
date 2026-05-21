<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_role('ADM');

$user       = current_user();
$initials   = get_initials($user['nombre']);
$base       = get_base_path();
$est_id     = (int)($user['establecimiento_id'] ?? 0);
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
    
    // Cargar especialidades desde maestro
    $qEsp = $db->query(
        "SELECT codigo, descripcion FROM maestro WHERE tipo='TIPO_ESPECIALIDAD' AND descripcion != 'Otro' ORDER BY orden ASC"
    );
    $especialidades = $qEsp->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    $est_nombre = ''; $header_sub = ''; $especialidades = [];
}

// Leer flash messages desde sesión
$errors            = $_SESSION['flash_errors'] ?? [];
$msg_ok            = $_SESSION['flash_success'] ?? '';
$temp_pass_display = $_SESSION['flash_temp_pass'] ?? '';
$old_post          = $_SESSION['old_post'] ?? [];

// Limpiar sesión
unset($_SESSION['flash_errors'], $_SESSION['flash_success'], $_SESSION['flash_temp_pass'], $_SESSION['old_post']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>RetinAI — Agregar Médico</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.form-page-wrap{max-width:680px;margin:0 auto;padding:8px 0 40px}
.form-card{background:var(--surface,#1e2235);border:1px solid var(--border,rgba(255,255,255,.07));border-radius:16px;padding:36px 40px}
.form-card-header{display:flex;align-items:center;gap:14px;margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--border,rgba(255,255,255,.07))}
.form-card-icon{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#1A56DB,#1e40af);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(26,86,219,.35)}
.form-card-title{font-size:17px;font-weight:700;color:var(--text,#f1f5f9);margin:0}
.form-card-sub{font-size:12px;color:var(--text-muted,#94a3b8);margin:2px 0 0}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:560px){.form-row{grid-template-columns:1fr}}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;font-weight:600;color:var(--text-muted,#94a3b8);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
.field input,.field select,.field textarea{width:100%;padding:10px 14px;background:var(--bg,#13172b);border:1.5px solid var(--border,rgba(255,255,255,.1));border-radius:10px;color:var(--text,#f1f5f9);font-size:14px;font-family:inherit;transition:border-color .2s,box-shadow .2s;outline:none;box-sizing:border-box}
.field input:focus,.field select:focus{border-color:#1A56DB;box-shadow:0 0 0 3px rgba(26,86,219,.18)}
.field input::placeholder{color:var(--text-muted,#64748b)}
.field .hint{font-size:11px;color:var(--text-muted,#64748b);margin-top:5px}
.alert-list{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.3);border-radius:10px;padding:14px 16px;margin-bottom:20px}
.alert-list p{font-size:13px;color:#fca5a5;margin:3px 0}
.alert-ok-box{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);border-radius:10px;padding:14px 16px;margin-bottom:20px}
.alert-ok-box p{font-size:13px;color:#6ee7b7;margin:3px 0}
.temp-pass-box{background:rgba(26,86,219,.12);border:1.5px dashed rgba(26,86,219,.4);border-radius:10px;padding:16px;margin-top:12px}
.temp-pass-box p{font-size:12px;color:#93c5fd;margin:0 0 8px}
.temp-pass-val{font-family:'DM Mono',monospace;font-size:20px;font-weight:700;color:#fff;letter-spacing:3px;background:rgba(0,0,0,.25);border-radius:8px;padding:10px 16px;display:inline-block}
.info-box{display:flex;gap:10px;align-items:flex-start;background:rgba(26,86,219,.12);border:1px solid rgba(26,86,219,.25);border-radius:10px;padding:12px 16px;margin-bottom:20px}
.info-box p{font-size:13px;color:#93c5fd;margin:0;line-height:1.5}
.form-actions{display:flex;gap:12px;align-items:center;margin-top:28px;padding-top:20px;border-top:1px solid var(--border,rgba(255,255,255,.07));flex-wrap:wrap}
.btn-submit{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;background:linear-gradient(135deg,#1A56DB,#1e40af);color:#fff;font-size:14px;font-weight:600;border:none;border-radius:10px;cursor:pointer;box-shadow:0 4px 14px rgba(26,86,219,.35);transition:transform .15s,box-shadow .15s}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,86,219,.45)}
.btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:11px 20px;background:transparent;border:1.5px solid var(--border,rgba(255,255,255,.12));border-radius:10px;color:var(--text-muted,#94a3b8);font-size:14px;font-weight:500;text-decoration:none;cursor:pointer;transition:border-color .2s,color .2s}
.btn-cancel:hover{border-color:#94a3b8;color:var(--text,#f1f5f9)}
.pwd-wrap{position:relative}
.pwd-wrap input{padding-right:42px}
.eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted,#64748b);padding:0;display:flex;align-items:center}
.eye-btn:hover{color:var(--text,#f1f5f9)}

/* ── Combo buscable ── */
.combo-wrap{position:relative}
.combo-input{cursor:pointer}
.combo-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--surface,#1e2235);border:1.5px solid #1A56DB;border-radius:10px;z-index:200;max-height:240px;overflow:hidden;display:none;flex-direction:column;box-shadow:0 8px 24px rgba(0,0,0,.4)}
.combo-dropdown.open{display:flex}
.combo-search{padding:8px 12px;background:var(--bg,#13172b);border:none;border-bottom:1px solid var(--border,rgba(255,255,255,.08));color:var(--text,#f1f5f9);font-size:13px;font-family:inherit;outline:none}
.combo-search::placeholder{color:var(--text-muted,#64748b)}
.combo-list{overflow-y:auto;max-height:180px}
.combo-opt{padding:9px 14px;font-size:13px;color:var(--text,#f1f5f9);cursor:pointer;transition:background .15s}
.combo-opt:hover,.combo-opt.selected{background:rgba(26,86,219,.25)}
.combo-opt.nueva{color:#60a5fa;font-style:italic}
.combo-opt.nueva:hover{background:rgba(26,86,219,.35)}
.nueva-esp-field{display:none;margin-top:10px}
</style>
</head>
<body>

<?php require_once __DIR__ . '/../shared/header.php'; ?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../shared/sidebar.php'; ?>

    <main class="main-content">
        <div class="form-page-wrap">

            <!-- Breadcrumb -->
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:13px;color:var(--text-muted,#94a3b8)">
                <a href="<?= $base ?>views/admin/doctor.php" style="color:var(--text-muted,#94a3b8);text-decoration:none;display:flex;align-items:center;gap:4px" onmouseover="this.style.color='#f1f5f9'" onmouseout="this.style.color=''">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 14a6 6 0 10-12 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Médicos
                </a>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M7 5l5 5-5 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <span>Agregar Médico</span>
            </div>

            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="8" r="4" stroke="white" stroke-width="1.8"/>
                            <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M19 3v6M16 6h6" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div>
                        <p class="form-card-title">Registrar nuevo médico</p>
                        <p class="form-card-sub">El médico recibirá una contraseña temporal para su primer acceso.</p>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert-list" role="alert">
                    <?php foreach ($errors as $e): ?>
                    <p>⚠ <?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($msg_ok): ?>
                <div class="alert-ok-box">
                    <p>✅ <?= $msg_ok ?></p>
                    <?php if ($temp_pass_display): ?>
                    <div class="temp-pass-box">
                        <p>⚠ El correo no pudo enviarse. Entregue esta contraseña temporal al médico de forma manual:</p>
                        <span class="temp-pass-val" id="temp-pass-val"><?= htmlspecialchars($temp_pass_display) ?></span>
                        <button type="button" onclick="copyPass()" style="margin-left:12px;padding:6px 14px;background:rgba(26,86,219,.3);border:1px solid rgba(26,86,219,.5);border-radius:8px;color:#93c5fd;font-size:12px;cursor:pointer">Copiar</button>
                        <p style="margin-top:10px;font-size:12px">El médico deberá cambiarla en su primer inicio de sesión.</p>
                    </div>
                    <div style="margin-top:14px;display:flex;gap:10px">
                        <a href="<?= $base ?>views/admin/doctor.php" class="btn-submit" style="text-decoration:none">Ver lista de médicos</a>
                        <a href="<?= $base ?>views/admin/create_doctor.php" class="btn-cancel">Agregar otro</a>
                    </div>
                    <?php else: ?>
                    <div style="margin-top:10px">
                        <a href="<?= $base ?>views/admin/doctor.php" class="btn-submit" style="text-decoration:none">Ver lista de médicos</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (empty($msg_ok)): ?>
                <div class="info-box">
                    <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:2px">
                        <circle cx="10" cy="10" r="9" stroke="#1A56DB" stroke-width="1.5"/>
                        <path d="M10 9v5M10 7h.01" stroke="#1A56DB" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <p>El médico se creará como <strong>Activo</strong> con contraseña temporal. Deberá cambiarla en su primer ingreso.</p>
                </div>

                <form method="POST" action="<?= $base ?>controllers/DoctorController.php?action=create" id="form-doctor" novalidate>
                    <!-- Nombre -->
                    <div class="field">
                        <label for="nombre">Nombre completo</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Dr. Juan Pérez García"
                               value="<?= htmlspecialchars($old_post['nombre'] ?? '') ?>" required>
                    </div>

                    <!-- Correo -->
                    <div class="field">
                        <label for="correo">Correo electrónico</label>
                        <input type="email" id="correo" name="correo" placeholder="medico@hospital.pe"
                               value="<?= htmlspecialchars($old_post['correo'] ?? '') ?>" required>
                    </div>

                    <!-- CMP + Especialidad -->
                    <div class="form-row">
                        <div class="field">
                            <label for="cmp">Número CMP</label>
                            <input type="text" id="cmp" name="cmp" placeholder="123456"
                                   value="<?= htmlspecialchars($old_post['cmp'] ?? '') ?>" required>
                            <p class="hint">Colegio Médico del Perú</p>
                        </div>
                        <div class="field">
                            <label>Especialidad</label>
                            <!-- Input visible que muestra la selección -->
                            <div class="combo-wrap" id="combo-wrap">
                                <input type="text" class="combo-input" id="combo-display"
                                       placeholder="Buscar o seleccionar…"
                                       value="<?= htmlspecialchars(($old_post['especialidad'] ?? '') === '__nueva__' ? ($old_post['esp_nueva'] ?? '') : ($old_post['especialidad'] ?? '')) ?>"
                                       autocomplete="off" readonly>
                                <!-- Hidden real value -->
                                <input type="hidden" name="especialidad" id="campo-especialidad"
                                       value="<?= htmlspecialchars($old_post['especialidad'] ?? '') ?>">

                                <div class="combo-dropdown" id="combo-dropdown">
                                    <input type="text" class="combo-search" id="combo-search"
                                           placeholder="Buscar especialidad…" autocomplete="off">
                                    <div class="combo-list" id="combo-list">
                                        <?php foreach ($especialidades as $esp): ?>
                                        <div class="combo-opt" data-value="<?= htmlspecialchars($esp['descripcion']) ?>">
                                            <?= htmlspecialchars($esp['descripcion']) ?>
                                        </div>
                                        <?php endforeach; ?>
                                        <div class="combo-opt nueva" data-value="__nueva__">
                                            ➕ Agregar nueva especialidad…
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campo que aparece si elige "nueva especialidad" -->
                            <div class="nueva-esp-field" id="nueva-esp-field">
                                <input type="text" id="esp_nueva" name="esp_nueva"
                                       placeholder="Escribe la nueva especialidad…"
                                       value="<?= htmlspecialchars($old_post['esp_nueva'] ?? '') ?>">
                                <p class="hint">Se guardará en la base de datos como nueva opción.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Campo oculto para override de contraseña (usado en tests automatizados) -->
                    <input type="hidden" name="password_override" id="password_override" value="">


                    <div class="form-actions">
                        <button type="submit" class="btn-submit" id="btn-guardar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M5 13l4 4L19 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Guardar médico
                        </button>
                        <a href="<?= $base ?>views/admin/doctor.php" class="btn-cancel">Cancelar</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
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

const display   = document.getElementById('combo-display');
const dropdown  = document.getElementById('combo-dropdown');
const searchInp = document.getElementById('combo-search');
const list      = document.getElementById('combo-list');
const hidden    = document.getElementById('campo-especialidad');
const nuevaWrap = document.getElementById('nueva-esp-field');
const nuevaInp  = document.getElementById('esp_nueva');

if(display) {
    display.addEventListener('click', () => {
        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) {
            searchInp.value = '';
            filterOpts('');
            searchInp.focus();
        }
    });

    document.addEventListener('click', e => {
        if (!document.getElementById('combo-wrap').contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // Búsqueda en tiempo real
    searchInp.addEventListener('input', () => filterOpts(searchInp.value));

    function filterOpts(query) {
        const q = query.toLowerCase();
        document.querySelectorAll('.combo-opt').forEach(opt => {
            const txt = opt.textContent.toLowerCase();
            opt.style.display = txt.includes(q) ? '' : 'none';
        });
    }

    // Seleccionar opción
    list.addEventListener('click', e => {
        const opt = e.target.closest('.combo-opt');
        if (!opt) return;
        const val = opt.dataset.value;
        if (val === '__nueva__') {
            display.value  = '';
            hidden.value   = '__nueva__';
            nuevaWrap.style.display = 'block';
            nuevaInp.focus();
        } else {
            display.value  = opt.textContent.trim();
            hidden.value   = val;
            nuevaWrap.style.display = 'none';
            nuevaInp.value = '';
        }
        document.querySelectorAll('.combo-opt').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        dropdown.classList.remove('open');
    });

    // Restaurar estado si hubo error en POST y se eligió nueva
    <?php if (($old_post['especialidad'] ?? '') === '__nueva__'): ?>
    nuevaWrap.style.display = 'block';
    hidden.value = '__nueva__';
    <?php endif; ?>
}

// Copiar contraseña temporal
function copyPass() {
    const val = document.getElementById('temp-pass-val')?.textContent ?? '';
    navigator.clipboard.writeText(val).then(() => {
        const btn = event.target;
        btn.textContent = '¡Copiado!';
        setTimeout(() => btn.textContent = 'Copiar', 2000);
    });
}
</script>
</body>
</html>
