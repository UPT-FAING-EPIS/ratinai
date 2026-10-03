<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../config/config.php';
require_auth();
require_role('MED');
$user = current_user();
$initials = get_initials($user['nombre']);
$base = get_base_path();
$logout_url = $base . 'controllers/AuthController.php?action=logout';
$role_label = '🩺 Médico';
$role_class = 'role-med';
$avatar_class = 'avatar-green';
$header_sub = 'Médico Oftalmólogo';
$_page = 'nuevoanalisis.php';
$servicioAnalisisDisponible = servicioAnalisisRemotoDisponible();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Nuevo análisis</title>
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<script>
const BASE_URL = '<?= $base ?>';
const SERVICIO_ANALISIS_DISPONIBLE = <?= $servicioAnalisisDisponible ? 'true' : 'false' ?>;
</script>
<style>
.flujo-pasos{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:0 0 20px;padding:0;list-style:none}.flujo-paso{display:flex;align-items:center;gap:9px;padding:11px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text3);font-size:12px;transition:border-color .2s,background .2s,box-shadow .2s}.flujo-paso strong{display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:var(--surface2);color:var(--text2)}.flujo-paso.activo{border:2px solid var(--accent);color:var(--accent);background:var(--accent2);box-shadow:0 0 0 3px rgba(37,99,235,.16)}.flujo-paso.activo strong,.flujo-paso.completo strong{background:var(--accent);color:#fff}.flujo-paso.completo{border-color:var(--accent);color:var(--text2)}.flujo-paso.completo strong::before{content:'✓';font-weight:700}.flujo-paso.completo strong{font-size:0}
.rejilla-flujo{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.8fr);gap:20px;align-items:start}.pila{display:grid;gap:16px}.seccion-flujo{scroll-margin-top:90px}.campos-dobles{display:grid;grid-template-columns:1fr 1fr;gap:12px}.campo-ayuda{font-size:11px;color:var(--text3);margin-top:5px}.zona-carga{display:grid;place-items:center;min-height:240px;border:2px dashed var(--border2);border-radius:12px;background:var(--surface2);cursor:pointer;overflow:hidden;text-align:center}.zona-carga:focus-visible{outline:3px solid rgba(26,86,219,.25);outline-offset:2px}.zona-carga.con-archivo{background:#0f172a;border-style:solid}.zona-carga img{display:none;width:100%;height:310px;object-fit:contain}.zona-carga.con-archivo img{display:block}.zona-carga.con-archivo .indicacion-carga{display:none}.acciones{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.resultado-estado{padding:14px;border-radius:10px;background:#eef4ff;border-left:4px solid var(--accent)}.resultado-estado.rechazado{background:#fff3f3;border-color:var(--danger)}.resultado-estado h3{margin:0 0 4px}.validacion-imagen{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:14px 0}.dato-validacion{padding:10px;border:1px solid var(--border);border-radius:8px}.dato-validacion span{display:block;font-size:10px;color:var(--text3);text-transform:uppercase}.dato-validacion strong{font-size:13px}.barra-resultado{margin:11px 0}.barra-resultado>div:first-child{display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px}.pista{height:7px;background:var(--surface2);border-radius:99px;overflow:hidden}.relleno{height:100%;width:0;background:var(--accent);transition:width .25s}.tarjeta-oculta{display:none}.opciones-valoracion{display:flex;flex-wrap:wrap;gap:8px}.opcion-valoracion{border:1px solid var(--border);background:var(--surface);padding:9px 12px;border-radius:8px;cursor:pointer}.opcion-valoracion.seleccionada{border-color:var(--accent);background:var(--accent2);color:var(--accent)}.estado-guardado{font-size:11px;color:var(--text3);margin-top:7px}.carpetas{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}.carpeta{padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);cursor:pointer;text-align:left}.carpeta.seleccionada{border-color:var(--accent);background:var(--accent2)}
@media(max-width:980px){.rejilla-flujo{grid-template-columns:1fr}.flujo-pasos{grid-template-columns:1fr 1fr}}@media(max-width:620px){.campos-dobles,.validacion-imagen{grid-template-columns:1fr}.flujo-pasos{grid-template-columns:1fr}.zona-carga img{height:240px}}
@media(prefers-reduced-motion:reduce){.relleno{transition:none}}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../shared/header.php'; ?>
<div class="app-shell">
<?php require_once __DIR__ . '/../shared/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header"><div><h1 class="page-title">Nuevo análisis retinal</h1><p class="page-sub">Identifique la captura, valide la imagen y apruebe la conclusión médica.</p></div></div>

    <ol class="flujo-pasos" aria-label="Progreso del análisis">
        <li class="flujo-paso activo" id="paso-1" aria-current="step"><strong>1</strong> Cargar e identificar</li>
        <li class="flujo-paso" id="paso-2"><strong>2</strong> Analizar con CNN</li>
        <li class="flujo-paso" id="paso-3"><strong>3</strong> Revisar resultado</li>
        <li class="flujo-paso" id="paso-4"><strong>4</strong> Editar y aprobar</li>
    </ol>

    <div class="rejilla-flujo">
        <div class="pila">
            <section class="card seccion-flujo" aria-labelledby="titulo-identificacion">
                <div class="card-title" id="titulo-identificacion">1. Paciente y captura</div>
                <div class="campos-dobles">
                    <div class="form-group-d"><label class="form-label-d" for="dni-input">DNI del paciente</label><input class="form-input-d" id="dni-input" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" placeholder="Ingrese los 8 dígitos"></div>
                    <div class="form-group-d"><label class="form-label-d" for="ojo-input">Ojo analizado</label><select class="form-input-d" id="ojo-input"><option value="">Seleccione…</option><option value="derecho">Derecho (OD)</option><option value="izquierdo">Izquierdo (OI)</option></select></div>
                </div>
                <div class="campos-dobles">
                    <div class="form-group-d"><label class="form-label-d" for="fecha-captura-input">Fecha y hora de captura</label><input class="form-input-d" type="datetime-local" id="fecha-captura-input"></div>
                    <div class="form-group-d"><label class="form-label-d">Paciente</label><button class="btn btn-secondary btn-sm" id="btn-buscar-paciente" type="button">Buscar o registrar</button><p class="campo-ayuda" id="paciente-result" aria-live="polite">Identifique al paciente antes de analizar.</p></div>
                </div>
            </section>

            <section class="card seccion-flujo" aria-labelledby="titulo-carga">
                <div class="card-title" id="titulo-carga">2. Retinografía</div>
                <?php if (!$servicioAnalisisDisponible): ?>
                <div class="alert-box alert-warning" style="margin-bottom:12px">
                    <p><strong>Análisis CNN no disponible.</strong> Falta configurar y validar el servicio remoto de EC2. La aplicación no generará resultados de demostración.</p>
                </div>
                <?php endif; ?>
                <div class="zona-carga" id="drop-zone" role="button" tabindex="0" aria-label="Seleccionar retinografía JPG o PNG">
                    <div class="indicacion-carga"><strong>Seleccione o arrastre la retinografía</strong><p class="campo-ayuda">JPG o PNG · máximo 10 MB</p></div>
                    <img id="preview-image-element" alt="Vista previa de la retinografía seleccionada">
                    <input type="file" id="file-input" accept=".jpg,.jpeg,.png" hidden>
                </div>
                <p id="file-name" class="campo-ayuda" aria-live="polite"></p>
                <div id="quality-warning" class="alert-box alert-warning" style="display:none"><p>La resolución visible es baja. El servicio CNN decidirá si la imagen es evaluable.</p></div>
                <div class="acciones"><button class="btn btn-primary" id="analyze-btn" type="button" disabled><?= $servicioAnalisisDisponible ? 'Analizar imagen con CNN' : 'CNN remota sin configurar' ?></button><button class="btn btn-ghost" id="change-image-btn" type="button" style="display:none">Cambiar imagen</button></div>
            </section>

            <section class="card tarjeta-oculta" id="carpeta-section" aria-labelledby="titulo-carpeta">
                <div class="card-title" id="titulo-carpeta">Organización del control <span class="text-muted">(opcional)</span></div>
                <div class="carpetas" id="folder-grid"></div>
                <div class="acciones"><button class="btn btn-ghost btn-sm" id="btn-toggle-new-folder" type="button">Nueva carpeta</button><button class="btn btn-ghost btn-sm" id="btn-quitar-carpeta" type="button">Sin carpeta</button></div>
                <div id="new-folder-form" class="tarjeta-oculta" style="margin-top:12px">
                    <div class="campos-dobles"><input class="form-input-d" id="folder-name-input" maxlength="100" placeholder="Nombre de carpeta"><input class="form-input-d" id="folder-desc-input" maxlength="255" placeholder="Descripción opcional"></div>
                    <div class="acciones"><button class="btn btn-primary btn-sm" id="btn-create-folder" type="button">Crear y seleccionar</button><button class="btn btn-ghost btn-sm" id="btn-cancel-new-folder" type="button">Cancelar</button></div>
                </div>
            </section>
        </div>

        <div class="pila">
            <section class="card tarjeta-oculta" id="result-col" aria-labelledby="titulo-resultado">
                <div class="card-title" id="titulo-resultado">3. Resultado referencial</div>
                <div class="resultado-estado" id="result-main"><h3 id="result-title"></h3><p id="result-sub"></p></div>
                <div class="validacion-imagen">
                    <div class="dato-validacion"><span>Contenido</span><strong id="estado-retinografia">—</strong></div>
                    <div class="dato-validacion"><span>Calidad</span><strong id="estado-calidad">—</strong></div>
                    <div class="dato-validacion"><span>Modelo</span><strong id="version-modelo">—</strong></div>
                    <div class="dato-validacion"><span>Tiempo CNN</span><strong id="tiempo-modelo">—</strong></div>
                </div>
                <div id="probabilidades-clinicas">
                    <?php foreach (['diabetes' => 'Retinopatía diabética', 'glaucoma' => 'Glaucoma', 'catarata' => 'Catarata', 'normal' => 'Normal'] as $clave => $etiqueta): ?>
                    <div class="barra-resultado"><div><span><?= $etiqueta ?></span><span id="r_<?= $clave ?>">0%</span></div><div class="pista"><div class="relleno" id="f_<?= $clave ?>"></div></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="warning-banner"><p>La salida de la CNN es referencial y permanece separada de la conclusión médica.</p></div>

                <div id="bloque-valoracion" style="margin-top:16px">
                    <label class="form-label-d">Valoración opcional del resultado de IA</label>
                    <div class="opciones-valoracion" role="group" aria-label="Valoración opcional">
                        <button class="opcion-valoracion" type="button" data-valoracion="coincido">Coincido</button>
                        <button class="opcion-valoracion" type="button" data-valoracion="discrepo">Discrepo</button>
                        <button class="opcion-valoracion" type="button" data-valoracion="evaluar_despues">Evaluar después</button>
                    </div>
                    <div id="detalle-discrepancia" class="tarjeta-oculta" style="margin-top:10px"><input class="form-input-d" id="motivo-discrepancia" maxlength="255" placeholder="Motivo de discrepancia"><textarea class="form-input-d" id="comentario-discrepancia" maxlength="1000" rows="2" placeholder="Comentario opcional" style="margin-top:8px"></textarea></div>
                    <p class="estado-guardado" id="estado-valoracion">Puede aprobar el informe sin responder.</p>
                </div>
            </section>

            <section class="card tarjeta-oculta" id="informe-section" aria-labelledby="titulo-informe">
                <div class="card-title" id="titulo-informe">4. Borrador de informe clínico</div>
                <p class="campo-ayuda">Borrador redactado con OpenAI a partir de la salida real de la CNN. Revíselo y edítelo antes de aprobar.</p>
                <textarea class="form-input-d" id="texto-informe" rows="13" maxlength="12000"></textarea>
                <p class="estado-guardado" id="estado-borrador">Borrador sin guardar.</p>
                <div class="acciones"><button class="btn btn-secondary" id="btn-guardar-borrador" type="button">Guardar borrador</button><button class="btn btn-primary" id="btn-aprobar-informe" type="button">Aprobar y generar PDF</button></div>
                <div class="acciones"><button class="btn btn-secondary" id="btn-pdf" type="button" style="display:none">Descargar informe aprobado</button><button class="btn btn-ghost" id="btn-reset" type="button">Nuevo análisis</button></div>
            </section>
        </div>
    </div>
</main>
</div>
<div id="toast" class="toast" style="display:none" role="status" aria-live="polite"></div>
<script src="<?= $base ?>assets/js/medico_analisis.js"></script>
</body>
</html>
