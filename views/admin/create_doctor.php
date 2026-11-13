<?php
require_once __DIR__ . "/../../config/session_guard.php";
require_once __DIR__ . "/../../config/config.php";
require_role("ADM");

$user = current_user();
$initials = get_initials($user["nombre"]);
$base = get_base_path();
$est_id = (int) ($user["establecimiento_id"] ?? 0);
$logout_url = $base . "controllers/AuthController.php?action=logout";

require_once __DIR__ . "/../../controllers/DoctorController.php";

$role_label = "Administrador";
$role_class = "role-adm";
$avatar_class = "avatar-adm";

try {
    // Los catálogos y médicos recientes llegan desde el controlador del dominio.
    ["mis_establecimientos"=>$mis_establecimientos, "est_nombre"=>$est_nombre,
     "especialidades"=>$especialidades, "cnt_pendientes"=>$cnt_pendientes,
     "ultimos_medicos"=>$ultimos_medicos] = DoctorController::datosCreacion((int)$user["id"], $est_id);
    $header_sub = $est_nombre;
} catch (Exception $ex) {
    $mis_establecimientos = [];
    $est_nombre = "";
    $header_sub = "";
    $especialidades = [];
    $cnt_pendientes = 0;
    $ultimos_medicos = [];
}
$ultimos_medicos = $ultimos_medicos ?? [];

// Leer flash messages desde sesión
$errors = $_SESSION["flash_errors"] ?? [];
$msg_ok = $_SESSION["flash_success"] ?? "";
$temp_pass_display = $_SESSION["flash_temp_pass"] ?? "";
$old_post = $_SESSION["old_post"] ?? [];

// Limpiar sesión
unset(
    $_SESSION["flash_errors"],
    $_SESSION["flash_success"],
    $_SESSION["flash_temp_pass"],
    $_SESSION["old_post"],
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>RetinAI — Agregar Médico</title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">

    <link rel="stylesheet" href="<?= $base ?>assets/css/views/admin/create_doctor.css">
</head>

<body>

    <?php require_once __DIR__ . "/../shared/header.php"; ?>

    <div class="app-shell">
        <?php require_once __DIR__ . "/../shared/sidebar.php"; ?>

        <main class="main-content">
            <div class="form-page-wrap">

                <!-- Breadcrumb -->
                <div class="ajuste-visualizacion-flex-align-items-center-gap-8px-margen-inferior-20px">
                    <a href="<?= $base ?>views/admin/doctor.php" class="enlace-volver-medicos"
                        onmouseover="this.style.color='#f1f5f9'" onmouseout="this.style.color=''">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 14a6 6 0 10-12 0" stroke="currentColor"
                                stroke-width="1.4" stroke-linecap="round" />
                        </svg>
                        Médicos
                    </a>
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="none">
                        <path d="M7 5l5 5-5 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    <span>Agregar Médico</span>
                </div>

                <div class="registration-layout">
                    <div class="form-card">
                        <div class="form-card-header">
                            <div class="form-card-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="8" r="4" stroke="white" stroke-width="1.8" />
                                    <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke="white" stroke-width="1.8"
                                        stroke-linecap="round" />
                                    <path d="M19 3v6M16 6h6" stroke="white" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                            </div>
                            <div>
                                <p class="form-card-title">Registrar nuevo médico</p>
                                <p class="form-card-sub">El médico recibirá una contraseña temporal para su primer
                                    acceso.</p>
                            </div>
                        </div>

                        <?php if (!empty($errors)): ?>
                        <div class="alert-list" role="alert">
                            <?php foreach ($errors as $e): ?>
                            <p><?= htmlspecialchars($e) ?></p>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($msg_ok): ?>
                        <div class="alert-ok-box">
                            <p><?= $msg_ok ?></p>
                            <?php if ($temp_pass_display): ?>
                            <div class="temp-pass-box">
                                <p>El correo no pudo enviarse. Entregue esta contraseña temporal al médico de forma
                                    manual:</p>
                                <span class="temp-pass-val" id="temp-pass-val"><?= htmlspecialchars(
                            $temp_pass_display,
                        ) ?></span>
                                <button type="button" onclick="copyPass()" class="ajuste-margen-izquierdo-12px-padding-6px-14px-fondo-rgba-26-86-219-3-border-1px-solid-rgba-2">Copiar</button>
                                <p class="ajuste-margen-superior-10px-tamano-texto-12px">El médico deberá cambiarla en su primer inicio de sesión.
                                </p>
                            </div>
                            <div class="ajuste-margen-superior-14px-visualizacion-flex-gap-10px">
                                <a href="<?= $base ?>views/admin/doctor.php" class="btn-submit enlace-sin-subrayado">Ver lista de médicos</a>
                                <a href="<?= $base ?>views/admin/create_doctor.php" class="btn-cancel">Agregar otro</a>
                            </div>
                            <?php else: ?>
                            <div class="ajuste-margen-superior-10px">
                                <a href="<?= $base ?>views/admin/doctor.php" class="btn-submit enlace-sin-subrayado">Ver lista de médicos</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (empty($msg_ok)): ?>
                        <div class="info-box">
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" class="ajuste-reduccion-flex-0-margen-superior-2px">
                                <circle cx="10" cy="10" r="9" stroke="#1A56DB" stroke-width="1.5" />
                                <path d="M10 9v5M10 7h.01" stroke="#1A56DB" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                            <p>El médico se creará como <strong>Activo</strong> con contraseña temporal. Deberá
                                cambiarla en su primer ingreso.</p>
                        </div>

                        <form method="POST" action="<?= $base ?>controllers/DoctorController.php?action=create"
                            id="form-doctor" novalidate>
                            <!-- Selector de establecimiento -->
                            <?php if (count($mis_establecimientos) > 1): ?>
                            <div class="field ajuste-margen-inferior-22px">
                                <label for="establecimiento_id">Establecimiento destino <span
                                        class="ajuste-color-color">*</span>
                                </label>
                                <select id="establecimiento_id" name="establecimiento_id" required>
                                    <option value="">— Seleccione el establecimiento —</option>
                                    <?php foreach ($mis_establecimientos as $e): ?>
                                    <option value="<?= (int) $e[
                                "id"
                            ] ?>" <?= (int) ($old_post["establecimiento_id"] ??
    0) === (int) $e["id"]
    ? "selected"
    : "" ?>>
                                        <?= htmlspecialchars($e["nombre"]) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="hint">El médico quedará vinculado al establecimiento elegido.</p>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="establecimiento_id" value="<?= (int) ($mis_establecimientos[0][
                        "id"
                    ] ?? $est_id) ?>">
                            <?php endif; ?>
                            <!-- Nombre -->
                            <div class="field">
                                <label for="nombre">Nombre completo</label>
                                <input type="text" id="nombre" name="nombre" placeholder="Dr. Juan Pérez García" value="<?= htmlspecialchars(
                                   $old_post["nombre"] ?? "",
                               ) ?>" required>
                            </div>

                            <!-- Correo -->
                            <div class="field">
                                <label for="correo">Correo electrónico</label>
                                <input type="email" id="correo" name="correo" placeholder="medico@hospital.pe" value="<?= htmlspecialchars(
                                   $old_post["correo"] ?? "",
                               ) ?>" required>
                            </div>

                            <!-- CMP + Especialidad -->
                            <div class="form-row">
                                <div class="field">
                                    <label for="cmp">Número CMP</label>
                                    <input type="text" id="cmp" name="cmp" placeholder="123456" value="<?= htmlspecialchars(
                                       $old_post["cmp"] ?? "",
                                   ) ?>" required>
                                    <p class="hint">Colegio Médico del Perú</p>
                                </div>
                                <div class="field">
                                    <label>Especialidad</label>
                                    <!-- Input visible que muestra la selección -->
                                    <div class="combo-wrap" id="combo-wrap">
                                        <input type="text" class="combo-input" id="combo-display"
                                            placeholder="Buscar o seleccionar…" value="<?= htmlspecialchars(
                                           ($old_post["especialidad"] ?? "") ===
                                           "__nueva__"
                                               ? $old_post["esp_nueva"] ?? ""
                                               : $old_post["especialidad"] ??
                                                   "",
                                       ) ?>" autocomplete="off" readonly>
                                        <!-- Hidden real value -->
                                        <input type="hidden" name="especialidad" id="campo-especialidad" value="<?= htmlspecialchars(
                                           $old_post["especialidad"] ?? "",
                                       ) ?>">

                                        <div class="combo-dropdown" id="combo-dropdown">
                                            <input type="text" class="combo-search" id="combo-search"
                                                placeholder="Buscar especialidad…" autocomplete="off">
                                            <div class="combo-list" id="combo-list">
                                                <?php foreach (
                                            $especialidades
                                            as $esp
                                        ): ?>
                                                <div class="combo-opt" data-value="<?= htmlspecialchars(
                                            $esp["descripcion"],
                                        ) ?>">
                                                    <?= htmlspecialchars(
                                                $esp["descripcion"],
                                            ) ?>
                                                </div>
                                                <?php endforeach; ?>
                                                <div class="combo-opt nueva" data-value="__nueva__">
                                                    Agregar nueva especialidad…
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Campo que aparece si elige "nueva especialidad" -->
                                    <div class="nueva-esp-field" id="nueva-esp-field">
                                        <input type="text" id="esp_nueva" name="esp_nueva"
                                            placeholder="Escribe la nueva especialidad…" value="<?= htmlspecialchars(
                                           $old_post["esp_nueva"] ?? "",
                                       ) ?>">
                                        <p class="hint">Se guardará en la base de datos como nueva opción.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Campo oculto para override de contraseña (usado en tests automatizados) -->
                            <input type="hidden" name="password_override" id="password_override" value="">


                            <div class="form-actions">
                                <button type="submit" class="btn-submit" id="btn-guardar">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                        <path d="M5 13l4 4L19 7" stroke="white" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                    Guardar médico
                                </button>
                                <a href="<?= $base ?>views/admin/doctor.php" class="btn-cancel">Cancelar</a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                    <aside class="context-column" aria-label="Información de apoyo">
                        <section class="context-card">
                            <h2>Últimos médicos registrados</h2>
                            <p>Las cinco cuentas creadas más recientemente en sus establecimientos.</p>
                            <?php if (
                        empty($ultimos_medicos)
                    ): ?><p>Aún no hay médicos registrados.</p><?php else:foreach (
                            $ultimos_medicos
                            as $medico
                        ): ?>
                            <div class="recent-doctor">
                                <span class="recent-avatar"><?= htmlspecialchars(
                        get_initials($medico["nombre"]),
                    ) ?></span>
                                <div>
                                    <strong><?= htmlspecialchars(
    $medico["nombre"],
) ?></strong>
                                    <span><?= htmlspecialchars(
    $medico["especialidad"] ?: "Sin especialidad",
) ?> · <?= (int) $medico["activo"] === 1
     ? "Activo"
     : "Inactivo" ?></span>
                                </div>
                            </div>
                            <?php endforeach;endif; ?>
                        </section>
                        <section class="context-card">
                            <h2>Guía rápida de registro</h2>
                            <p>Revise estos datos antes de enviar la invitación.</p>
                            <div class="guide-step">
                                <span class="step-no">1</span>
                                <div>
                                    <b>Establecimiento destino</b>
                                    <p>Define el centro al que quedará vinculado el médico.</p>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="step-no">2</span>
                                <div>
                                    <b>Identidad y correo</b>
                                    <p>El correo será su usuario y recibirá las credenciales de primer acceso.</p>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="step-no">3</span>
                                <div>
                                    <b>CMP y especialidad</b>
                                    <p>Validan su perfil profesional y facilitan la gestión posterior.</p>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="step-no">4</span>
                                <div>
                                    <b>Acceso seguro</b>
                                    <p>La cuenta nace activa con una clave temporal que deberá cambiar en el primer
                                        ingreso.</p>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    <script src="<?= $base ?>assets/js/session.service.js"></script>
    <script>
        SessionService.init({
            timeout: 300000,
            loginUrl: '<?= htmlspecialchars(
    $base . "views/auth/login.php",
) ?>'
        });

        const display = document.getElementById('combo-display');
        const dropdown = document.getElementById('combo-dropdown');
        const searchInp = document.getElementById('combo-search');
        const list = document.getElementById('combo-list');
        const hidden = document.getElementById('campo-especialidad');
        const nuevaWrap = document.getElementById('nueva-esp-field');
        const nuevaInp = document.getElementById('esp_nueva');

        if (display) {
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
                    display.value = '';
                    hidden.value = '__nueva__';
                    nuevaWrap.style.display = 'block';
                    nuevaInp.focus();
                } else {
                    display.value = opt.textContent.trim();
                    hidden.value = val;
                    nuevaWrap.style.display = 'none';
                    nuevaInp.value = '';
                }
                document.querySelectorAll('.combo-opt').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                dropdown.classList.remove('open');
            });

            // Restaurar estado si hubo error en POST y se eligió nueva
            <?php if (($old_post["especialidad"] ?? "") === "__nueva__"): ?>
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