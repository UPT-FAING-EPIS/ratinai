<?php
require_once __DIR__ . '/../../config/session_guard.php';
require_once __DIR__ . '/../../models/PacienteModel.php';
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
$_page = 'pacientes.php';
$pacientes = (new PacienteModel())->listarPacientesConAnalisisMedico((int)$user['id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RetinAI — Historial de pacientes</title>
<link rel="stylesheet" href="<?= $base ?>assets/css/dashboard/dashboard.css">
<style>
.paciente{border:1px solid var(--border);border-radius:10px;margin:10px 0;overflow:hidden}.paciente-cabecera{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px;cursor:pointer;background:var(--surface)}.paciente-cabecera:hover{background:var(--surface2)}.paciente-cabecera h3{font-size:14px;margin:0 0 4px}.paciente-resumen{display:flex;flex-wrap:wrap;gap:7px}.paciente-detalle{display:none;padding:0 15px 15px}.barra-comparacion{position:sticky;bottom:14px;z-index:8;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 15px;border:1px solid var(--accent);border-radius:10px;background:var(--surface);box-shadow:0 8px 26px rgba(15,23,42,.16)}.comparacion{display:none;margin-top:16px}.comparacion-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.control{border:1px solid var(--border);border-radius:9px;padding:13px}.probabilidad-comparada{display:grid;grid-template-columns:1fr auto;gap:5px;padding:5px 0;border-bottom:1px dashed var(--border)}.advertencia-version{padding:10px;border-radius:8px;background:#fff8e6;color:#784c00;margin-bottom:12px}@media(max-width:720px){.paciente-cabecera,.barra-comparacion{align-items:flex-start;flex-direction:column}.comparacion-grid{grid-template-columns:1fr}}
</style>
<script>const BASE_URL = '<?= $base ?>';</script>
</head>
<body>
<?php require_once __DIR__ . '/../shared/header.php'; ?>
<div class="app-shell">
<?php require_once __DIR__ . '/../shared/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header"><h1 class="page-title">Historial de pacientes</h1><p class="page-sub">Consulte controles del mismo ojo, retome borradores y descargue informes aprobados.</p></div>
    <section class="card mb-16">
        <div class="card-title">Buscar paciente</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><input class="form-input-d" id="hist-dni" placeholder="DNI o código de paciente" autocomplete="off" style="flex:1;min-width:220px"><button class="btn btn-primary" type="button" id="buscar-historial">Buscar</button></div>
        <p class="text-muted" id="history-search-note" style="font-size:12px;margin-top:7px" aria-live="polite"></p>
    </section>

    <section class="card">
        <div class="card-title">Pacientes recientes</div>
        <?php if ($pacientes === []): ?>
        <p class="text-muted">No hay pacientes con análisis registrados.</p>
        <?php endif; ?>
        <?php foreach ($pacientes as $paciente): ?>
        <article class="paciente paciente-card" data-busqueda="<?= htmlspecialchars(strtolower($paciente['dni'] . ' ' . $paciente['codigo_paciente'])) ?>">
            <div class="paciente-cabecera paciente-header" role="button" tabindex="0" data-id-paciente="<?= (int)$paciente['id'] ?>" aria-expanded="false">
                <div><h3>Paciente #<?= htmlspecialchars($paciente['codigo_paciente']) ?></h3><p class="text-muted" style="font-size:12px">DNI <?= htmlspecialchars($paciente['dni']) ?> · Último control <?= date('d/m/Y', strtotime($paciente['ultimo_analisis'])) ?></p></div>
                <div class="paciente-resumen"><span class="badge badge-info"><?= (int)$paciente['total_analisis'] ?> análisis</span><span class="badge badge-info"><?= (int)$paciente['total_carpetas'] ?> carpetas</span><?php if ((int)$paciente['total_alertas'] > 0): ?><span class="badge badge-pending"><?= (int)$paciente['total_alertas'] ?> alertas</span><?php endif; ?></div>
            </div>
            <div class="paciente-detalle paciente-detalle-wrapper" id="detalle-<?= (int)$paciente['id'] ?>"><p class="text-muted">Cargando historial…</p></div>
        </article>
        <?php endforeach; ?>
    </section>

    <section class="card comparacion" id="resultado-comparacion" aria-live="polite"></section>
    <div class="barra-comparacion" id="barra-comparacion" style="display:none"><span id="conteo-comparacion">Seleccione dos controles del mismo ojo.</span><div><button class="btn btn-ghost btn-sm" id="limpiar-comparacion">Limpiar</button><button class="btn btn-primary btn-sm" id="comparar-controles" disabled>Comparar controles</button></div></div>
</main>
</div>
<div id="toast" class="toast" style="display:none" role="status" aria-live="polite"></div>
<script>
(() => {
    'use strict';
    const DURACION_MENSAJE = 4000;
    const detallesCargados = new Set();
    const busqueda = document.getElementById('hist-dni');
    const notaBusqueda = document.getElementById('history-search-note');
    const botonBusqueda = document.getElementById('buscar-historial');
    const barraComparacion = document.getElementById('barra-comparacion');
    const conteoComparacion = document.getElementById('conteo-comparacion');
    const botonComparar = document.getElementById('comparar-controles');
    const resultadoComparacion = document.getElementById('resultado-comparacion');

    busqueda.addEventListener('input', () => {
        const termino = busqueda.value.trim().toLowerCase();
        let coincidencias = 0;
        document.querySelectorAll('.paciente').forEach(paciente => {
            const visible = !termino || paciente.dataset.busqueda.includes(termino);
            paciente.style.display = visible ? 'block' : 'none';
            if (visible) coincidencias += 1;
        });
        notaBusqueda.textContent = termino
            ? (coincidencias ? `${coincidencias} paciente(s) encontrado(s).` : 'No se encontraron pacientes que coincidan con la búsqueda.')
            : '';
    });

    botonBusqueda.addEventListener('click', async () => {
        const dni = busqueda.value.trim();
        if (!/^\d{8}$/.test(dni)) {
            notaBusqueda.textContent = 'Ingrese un DNI de 8 dígitos para recuperar el código de historial.';
            return;
        }
        const formulario = new URLSearchParams({ dni });
        try {
            const respuesta = await fetch(`${BASE_URL}controllers/PacienteController.php?action=recuperar_codigo`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formulario.toString()
            }).then(resultado => resultado.json());
            if (!respuesta.success) {
                notaBusqueda.textContent = 'No se encontraron pacientes que coincidan con la búsqueda.';
                return;
            }
            notaBusqueda.replaceChildren();
            notaBusqueda.append('Paciente encontrado. Código de historial: ');
            const codigo = document.createElement('strong');
            codigo.className = 'history-code';
            codigo.textContent = respuesta.paciente.codigo_paciente;
            notaBusqueda.appendChild(codigo);
        } catch (_) {
            notaBusqueda.textContent = 'No se pudo consultar el historial en este momento.';
        }
    });

    document.querySelectorAll('.paciente-cabecera').forEach(cabecera => {
        const alternar = () => cargarDetalle(cabecera);
        cabecera.addEventListener('click', alternar);
        cabecera.addEventListener('keydown', evento => {
            if (evento.key === 'Enter' || evento.key === ' ') {
                evento.preventDefault();
                alternar();
            }
        });
    });

    async function cargarDetalle(cabecera) {
        const idPaciente = Number(cabecera.dataset.idPaciente);
        const contenedor = document.getElementById(`detalle-${idPaciente}`);
        const abierto = cabecera.getAttribute('aria-expanded') === 'true';
        cabecera.setAttribute('aria-expanded', String(!abierto));
        contenedor.style.display = abierto ? 'none' : 'block';
        if (abierto || detallesCargados.has(idPaciente)) return;
        try {
            const respuesta = await fetch(`${BASE_URL}controllers/PacienteController.php?action=detalle_html&id_paciente=${idPaciente}`);
            contenedor.innerHTML = await respuesta.text();
            detallesCargados.add(idPaciente);
            conectarSelectoresComparacion(contenedor);
        } catch (_) {
            contenedor.textContent = 'No se pudo cargar el historial.';
        }
    }

    window.toggleAnalisis = identificador => {
        const contenedor = document.getElementById(identificador);
        if (contenedor) contenedor.style.display = contenedor.style.display === 'none' ? 'block' : 'none';
    };
    window.filterFolders = entrada => {
        const termino = entrada.value.toLowerCase();
        entrada.closest('.paciente-detalle-container').querySelectorAll('.carpeta-box').forEach(carpeta => {
            carpeta.style.display = carpeta.textContent.toLowerCase().includes(termino) ? 'block' : 'none';
        });
    };
    window.descargarPDF = idAnalisis => {
        window.location.assign(`${BASE_URL}controllers/AnalisisController.php?action=descargar_informe&id_analisis=${idAnalisis}`);
    };

    function conectarSelectoresComparacion(contenedor) {
        contenedor.querySelectorAll('.selector-comparacion').forEach(selector => {
            selector.addEventListener('change', () => {
                const seleccionados = obtenerSeleccionados();
                if (seleccionados.length > 2) {
                    selector.checked = false;
                    mostrarMensaje('Solo puede comparar dos controles.', 'warning');
                }
                actualizarBarraComparacion();
            });
        });
    }

    function obtenerSeleccionados() {
        return [...document.querySelectorAll('.selector-comparacion:checked')];
    }

    function actualizarBarraComparacion() {
        const seleccionados = obtenerSeleccionados();
        barraComparacion.style.display = seleccionados.length ? 'flex' : 'none';
        conteoComparacion.textContent = `${seleccionados.length} de 2 controles seleccionados.`;
        const mismoPaciente = seleccionados.length === 2 && seleccionados[0].dataset.paciente === seleccionados[1].dataset.paciente;
        const mismoOjo = seleccionados.length === 2 && seleccionados[0].dataset.ojo === seleccionados[1].dataset.ojo;
        botonComparar.disabled = !(mismoPaciente && mismoOjo);
        if (seleccionados.length === 2 && (!mismoPaciente || !mismoOjo)) {
            conteoComparacion.textContent = 'Seleccione controles del mismo paciente y ojo.';
        }
    }

    document.getElementById('limpiar-comparacion').addEventListener('click', () => {
        obtenerSeleccionados().forEach(selector => { selector.checked = false; });
        resultadoComparacion.style.display = 'none';
        actualizarBarraComparacion();
    });

    botonComparar.addEventListener('click', async () => {
        const seleccionados = obtenerSeleccionados();
        try {
            const ruta = `${BASE_URL}controllers/AnalisisController.php?action=comparar&id_primero=${seleccionados[0].value}&id_segundo=${seleccionados[1].value}`;
            const respuesta = await fetch(ruta).then(resultado => resultado.json());
            if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo comparar.');
            renderizarComparacion(respuesta);
        } catch (error) {
            mostrarMensaje(error.message, 'danger');
        }
    });

    function renderizarComparacion(respuesta) {
        resultadoComparacion.replaceChildren();
        const titulo = document.createElement('div');
        titulo.className = 'card-title';
        titulo.textContent = 'Comparación de controles del mismo ojo';
        resultadoComparacion.appendChild(titulo);
        if (respuesta.versiones_diferentes) {
            const advertencia = document.createElement('p');
            advertencia.className = 'advertencia-version';
            advertencia.textContent = 'Las versiones de la CNN son diferentes; las probabilidades no son directamente equivalentes.';
            resultadoComparacion.appendChild(advertencia);
        }
        const rejilla = document.createElement('div');
        rejilla.className = 'comparacion-grid';
        respuesta.controles.forEach(control => {
            const tarjeta = document.createElement('article');
            tarjeta.className = 'control';
            const encabezado = document.createElement('h3');
            encabezado.textContent = `${control.fecha_captura || control.fecha_analisis} · ${control.ojo}`;
            tarjeta.appendChild(encabezado);
            const modelo = document.createElement('p');
            modelo.className = 'text-muted';
            modelo.textContent = `Modelo ${control.version_modelo || 'no registrado'}`;
            tarjeta.appendChild(modelo);
            [['Normal', control.probabilidad_normal], ['Diabetes', control.probabilidad_diabetes], ['Glaucoma', control.probabilidad_glaucoma], ['Catarata', control.probabilidad_catarata]].forEach(([nombre, valor]) => {
                const fila = document.createElement('div');
                fila.className = 'probabilidad-comparada';
                const etiqueta = document.createElement('span');
                etiqueta.textContent = nombre;
                const probabilidad = document.createElement('strong');
                probabilidad.textContent = `${Number(valor).toFixed(1)}%`;
                fila.append(etiqueta, probabilidad);
                tarjeta.appendChild(fila);
            });
            rejilla.appendChild(tarjeta);
        });
        resultadoComparacion.appendChild(rejilla);
        const aviso = document.createElement('p');
        aviso.className = 'text-muted';
        aviso.style.marginTop = '12px';
        aviso.textContent = 'RetinAI muestra datos de ambos controles; la interpretación del cambio corresponde al médico.';
        resultadoComparacion.appendChild(aviso);
        resultadoComparacion.style.display = 'block';
        resultadoComparacion.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function mostrarMensaje(mensaje, tipo) {
        const elemento = document.getElementById('toast');
        elemento.textContent = mensaje;
        elemento.className = `toast ${tipo}`;
        elemento.style.display = 'flex';
        window.setTimeout(() => { elemento.style.display = 'none'; }, DURACION_MENSAJE);
    }
})();
</script>
</body>
</html>
