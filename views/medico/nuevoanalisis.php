<?php
require_once __DIR__ . "/../../config/session_guard.php";
require_once __DIR__ . "/../../config/config.php";
require_role("MED");
$user = current_user();
$initials = get_initials($user["nombre"]);
$base = get_base_path();
$logout_url = $base . "controllers/AuthController.php?action=logout";
$role_label = "Médico";
$role_class = "role-med";
$avatar_class = "avatar-green";
$header_sub = "Médico Oftalmólogo";
$_page = "nuevoanalisis.php";
$servicioAnalisisDisponible = servicioAnalisisRemotoDisponible();
?>
<!-- El flujo conserva identificadores estables para la lógica de assets/js/medico_analisis.js. -->
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>RetinAI — Nuevo análisis</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
    <script>
        const BASE_URL = <?= json_encode($base) ?>;
        const SERVICIO_ANALISIS_DISPONIBLE = <?= $servicioAnalisisDisponible ? "true" : "false" ?>;
    </script>
    <link rel="stylesheet" href="<?= $base ?>assets/css/views/medico/nuevoanalisis.css">
</head>

<body>
    <?php require_once __DIR__ . "/../shared/header.php"; ?>
    <div class="app-shell">
        <?php require_once __DIR__ . "/../shared/sidebar.php"; ?>
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Nuevo análisis retinal</h1>
                <p class="page-sub">Identifique al paciente, valide la captura y complete el informe.</p>
            </div>
            <ol class="flujo-pasos" aria-label="Progreso del análisis">
                <li class="flujo-paso activo" id="paso-1">
                    <strong>1</strong>Validar contenido y calidad
                </li>
                <li class="flujo-paso" id="paso-2">
                    <strong>2</strong>Clasificar con CNN
                </li>
                <li class="flujo-paso" id="paso-3">
                    <strong>3</strong>Revisar resultado
                </li>
                <li class="flujo-paso" id="paso-4">
                    <strong>4</strong>Editar y aprobar
                </li>
            </ol>
            <!-- La identificación, el ojo y la carpeta delimitan los controles comparables. -->
            <div class="rejilla-carga" id="rejilla-carga">
                <div class="pila">
                    <section class="card">
                        <div class="card-title">Paciente y captura</div>
                        <div class="campos-dobles">
                            <div class="form-group-d">
                                <label class="form-label-d" for="dni-input">DNI del
                                    paciente</label>
                                <input class="form-input-d" id="dni-input" inputmode="numeric"
                                    maxlength="8" pattern="[0-9]{8}">
                            </div>
                            <div class="form-group-d">
                                <label class="form-label-d" for="ojo-input">Ojo
                                    analizado</label>
                                <select class="form-input-d" id="ojo-input">
                                    <option value="">Seleccione</option>
                                    <option value="derecho">Derecho (OD)</option>
                                    <option value="izquierdo">Izquierdo (OI)</option>
                                </select>
                            </div>
                        </div>
                        <div class="campos-dobles">
                            <div class="form-group-d">
                                <label class="form-label-d" for="fecha-captura-input">Fecha y hora
                                    de captura</label>
                                <input class="form-input-d" type="datetime-local"
                                    id="fecha-captura-input">
                            </div>
                            <div class="form-group-d">
                                <label class="form-label-d">Paciente</label>
                                <button
                                    class="btn btn-secondary btn-sm" id="btn-buscar-paciente" type="button">Buscar o
                                    registrar</button>
                                <p class="campo-ayuda" id="paciente-result">Identifique al paciente para continuar.</p>
                            </div>
                        </div>
                    </section>
                    <section class="card tarjeta-oculta" id="carpeta-section">
                        <div class="card-title">Carpeta del análisis</div>
                        <div class="carpetas" id="folder-grid"></div>
                        <div class="acciones">
                            <button class="btn btn-ghost btn-sm" id="btn-toggle-new-folder"
                                type="button">Nueva carpeta</button>
                        </div>
                        <div id="new-folder-form" class="tarjeta-oculta ajuste-margen-superior-12px">
                            <div class="campos-dobles">
                                <input class="form-input-d" id="folder-name-input"
                                    maxlength="100" placeholder="Nombre de carpeta">
                                <input class="form-input-d"
                                    id="folder-desc-input" maxlength="255" placeholder="Descripción opcional">
                            </div>
                            <div class="acciones">
                                <button class="btn btn-primary btn-sm" id="btn-create-folder"
                                    type="button">Crear y seleccionar</button>
                                <button class="btn btn-ghost btn-sm"
                                    id="btn-cancel-new-folder" type="button">Cancelar</button>
                            </div>
                        </div>
                        <p class="campo-ayuda">La carpeta seleccionada organizará el PDF en el almacenamiento del
                            establecimiento.</p>
                    </section>
                </div>
                <div class="pila">
                    <section class="card">
                        <div class="card-title">Retinografía</div><?php if (
    !$servicioAnalisisDisponible
): ?><div class="alert-box alert-warning">
                            <p>Configure y active el servicio CNN v2 para validar imágenes reales.</p>
                        </div><?php endif; ?><div class="zona-carga" id="drop-zone" role="button" tabindex="0">
                            <div class="indicacion-carga">
                                <strong>Seleccione o arrastre la retinografía</strong>
                                <p class="campo-ayuda">JPG o PNG, máximo 10 MB</p>
                            </div>
                            <img id="preview-image-element" alt="Vista previa">
                            <input type="file" id="file-input"
                                accept=".jpg,.jpeg,.png" hidden>
                        </div>
                        <p id="file-name" class="campo-ayuda"></p>
                        <div id="quality-warning" class="alert-box alert-warning oculto-inicialmente">
                            <p>La resolución es baja; el filtro CNN de calidad decidirá si es evaluable.</p>
                        </div>
                        <div class="validacion-cnn" id="resultado-validacion-cnn" aria-live="polite">
                            <strong
                                id="mensaje-validacion-cnn">Validando…</strong>
                            <div class="validacion-cnn-grid">
                                <div class="validacion-cnn-dato">
                                    <span>Contenido</span>
                                    <strong
                                        id="validacion-contenido-cnn">—</strong>
                                </div>
                                <div class="validacion-cnn-dato">
                                    <span>Calidad</span>
                                    <strong
                                        id="validacion-calidad-cnn">—</strong>
                                </div>
                            </div>
                        </div>
                        <div class="acciones">
                            <button class="btn btn-primary" id="validate-btn" type="button"
                                disabled>Validar contenido y calidad</button>
                            <button
                                class="btn btn-ghost oculto-inicialmente" id="change-image-btn" type="button">Cambiar
                                imagen</button>
                        </div>
                    </section>
                    <section class="card tarjeta-oculta" id="analysis-section">
                        <div class="card-title">2. Clasificar con CNN</div><?php if (
    !$servicioAnalisisDisponible
): ?><div class="alert-box alert-warning">
                            <p>El servicio CNN v2 no está disponible.</p>
                        </div><?php endif; ?><p class="campo-ayuda">Los filtros de contenido y calidad fueron aprobados.
                            El siguiente paso calcula los porcentajes de normal, retinopatía diabética, glaucoma y
                            catarata.</p>
                        <div class="acciones">
                            <button class="btn btn-primary" id="analyze-btn" type="button"
                                disabled>Analizar imagen con CNN</button>
                        </div>
                    </section>
                </div>
            </div>
            <div class="rejilla-resultado">
                <section class="card tarjeta-oculta" id="result-col">
                    <div class="card-title">3. Resultado referencial</div>
                    <div class="resultado-estado" id="result-main">
                        <h3 id="result-title"></h3>
                        <p id="result-sub"></p>
                    </div>
                    <div class="validacion-imagen">
                        <div class="dato-validacion">
                            <span>Contenido</span>
                            <strong id="estado-retinografia">—</strong>
                        </div>
                        <div class="dato-validacion">
                            <span>Calidad</span>
                            <strong id="estado-calidad">—</strong>
                        </div>
                        <div class="dato-validacion">
                            <span>Modelo</span>
                            <strong id="version-modelo">—</strong>
                        </div>
                        <div class="dato-validacion">
                            <span>Tiempo CNN</span>
                            <strong id="tiempo-modelo">—</strong>
                        </div>
                    </div>
                    <div id="probabilidades-clinicas"><?php foreach (
    [
        "diabetes" => "Retinopatía diabética",
        "glaucoma" => "Glaucoma",
        "catarata" => "Catarata",
        "normal" => "Normal",
    ]
    as $clave => $etiqueta
): ?><div class="barra-resultado">
                            <div>
                                <span><?= $etiqueta ?></span>
                                <span id="r_<?= $clave ?>">0%</span>
                            </div>
                            <div class="pista">
                                <div class="relleno" id="f_<?= $clave ?>"></div>
                            </div>
                        </div><?php endforeach; ?></div>
                    <div id="bloque-valoracion" class="ajuste-margen-superior-16px">
                        <label class="form-label-d">Valoración
                            opcional</label>
                        <div class="opciones-valoracion">
                            <button class="opcion-valoracion" type="button"
                                data-valoracion="coincido">Coincido</button>
                            <button class="opcion-valoracion"
                                type="button" data-valoracion="discrepo">Discrepo</button>
                            <button
                                class="opcion-valoracion" type="button" data-valoracion="evaluar_despues">Evaluar
                                después</button>
                        </div>
                        <div id="detalle-discrepancia" class="tarjeta-oculta ajuste-margen-superior-10px">
                            <input
                                class="form-input-d" id="motivo-discrepancia" maxlength="255"
                                placeholder="Motivo">
                            <textarea class="form-input-d" id="comentario-discrepancia"
                                maxlength="1000" rows="2" placeholder="Comentario opcional"></textarea>
                        </div>
                        <p class="estado-guardado" id="estado-valoracion"></p>
                    </div>
                </section>
                <!-- El borrador clínico se presenta para revisión y aprobación médica. -->
                <section class="card tarjeta-oculta" id="informe-section">
                    <div class="card-title">4. Editar y aprobar</div>
                    <div id="error-borrador" class="alert-box alert-warning oculto-inicialmente">
                        <p id="error-borrador-texto"></p>
                        <button class="btn btn-secondary btn-sm"
                            id="btn-generar-borrador" type="button">Reintentar generación</button>
                    </div>
                    <textarea class="form-input-d" id="texto-informe" rows="13" maxlength="12000"
                        disabled></textarea>
                    <p class="estado-guardado" id="estado-borrador"></p>
                    <div class="acciones">
                        <button class="btn btn-secondary" id="btn-guardar-borrador" type="button"
                            disabled>Guardar borrador</button>
                        <button class="btn btn-primary" id="btn-aprobar-informe"
                            type="button" disabled>Aprobar y generar PDF</button>
                    </div>
                    <div class="acciones">
                        <button class="btn btn-secondary oculto-inicialmente" id="btn-pdf"
                            type="button">Descargar informe aprobado</button>
                        <button class="btn btn-ghost"
                            id="btn-reset" type="button">Nuevo análisis</button>
                    </div>
                </section>
                <section class="card tarjeta-oculta imagen-resultado" id="imagen-resultado">
                    <div class="card-title">Retinografía analizada</div>
                    <img id="imagen-resultado-img"
                        alt="Retinografía del análisis">
                </section>
            </div>
        </main>
    </div>
    <div id="toast" class="toast oculto-inicialmente"></div>
    <script src="<?= $base ?>assets/js/medico_analisis.js?v=<?= (int) filemtime(
    __DIR__ . "/../../assets/js/medico_analisis.js",
) ?>"></script>
</body>

</html>