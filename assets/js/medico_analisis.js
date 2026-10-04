document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const TAMANO_MAXIMO_IMAGEN = 10 * 1024 * 1024;
    const RESOLUCION_MINIMA_PROVISIONAL = 500;
    const DURACION_MENSAJE = 4500;
    const CANTIDAD_PASOS = 4;
    const TIPOS_IMAGEN_PERMITIDOS = ['image/jpeg', 'image/png'];

    const elementos = {
        archivo: document.getElementById('file-input'),
        zonaCarga: document.getElementById('drop-zone'),
        vistaPrevia: document.getElementById('preview-image-element'),
        nombreArchivo: document.getElementById('file-name'),
        cambiarImagen: document.getElementById('change-image-btn'),
        validar: document.getElementById('validate-btn'),
        analizar: document.getElementById('analyze-btn'),
        seccionAnalisis: document.getElementById('analysis-section'),
        estadoValidacionProvisional: document.getElementById('estado-validacion-provisional'),
        dni: document.getElementById('dni-input'),
        ojo: document.getElementById('ojo-input'),
        fechaCaptura: document.getElementById('fecha-captura-input'),
        buscarPaciente: document.getElementById('btn-buscar-paciente'),
        resultadoPaciente: document.getElementById('paciente-result'),
        advertenciaCalidad: document.getElementById('quality-warning'),
        resultado: document.getElementById('result-col'),
        estadoResultado: document.getElementById('result-main'),
        tituloResultado: document.getElementById('result-title'),
        detalleResultado: document.getElementById('result-sub'),
        probabilidades: document.getElementById('probabilidades-clinicas'),
        estadoRetinografia: document.getElementById('estado-retinografia'),
        estadoCalidad: document.getElementById('estado-calidad'),
        versionModelo: document.getElementById('version-modelo'),
        tiempoModelo: document.getElementById('tiempo-modelo'),
        bloqueValoracion: document.getElementById('bloque-valoracion'),
        detalleDiscrepancia: document.getElementById('detalle-discrepancia'),
        motivoDiscrepancia: document.getElementById('motivo-discrepancia'),
        comentarioDiscrepancia: document.getElementById('comentario-discrepancia'),
        estadoValoracion: document.getElementById('estado-valoracion'),
        informe: document.getElementById('informe-section'),
        textoInforme: document.getElementById('texto-informe'),
        estadoBorrador: document.getElementById('estado-borrador'),
        errorBorrador: document.getElementById('error-borrador'),
        errorBorradorTexto: document.getElementById('error-borrador-texto'),
        generarBorrador: document.getElementById('btn-generar-borrador'),
        guardarBorrador: document.getElementById('btn-guardar-borrador'),
        aprobarInforme: document.getElementById('btn-aprobar-informe'),
        descargarPdf: document.getElementById('btn-pdf'),
        reiniciar: document.getElementById('btn-reset'),
        seccionCarpeta: document.getElementById('carpeta-section'),
        rejillaCarpetas: document.getElementById('folder-grid'),
        nuevaCarpeta: document.getElementById('btn-toggle-new-folder'),
        formularioCarpeta: document.getElementById('new-folder-form'),
        nombreCarpeta: document.getElementById('folder-name-input'),
        descripcionCarpeta: document.getElementById('folder-desc-input'),
        crearCarpeta: document.getElementById('btn-create-folder'),
        cancelarCarpeta: document.getElementById('btn-cancel-new-folder'),
        mensaje: document.getElementById('toast'),
    };

    let archivoSeleccionado = null;
    let idPacienteActual = null;
    let idCarpetaActual = null;
    let idAnalisisActual = null;
    let procesando = false;
    let imagenValidada = false;
    let dimensionesImagen = { ancho: 0, alto: 0 };

    elementos.fechaCaptura.value = obtenerFechaLocalActual();
    establecerPasoActual(1);
    cargarAnalisisPendiente();

    elementos.zonaCarga.addEventListener('click', () => elementos.archivo.click());
    elementos.zonaCarga.addEventListener('keydown', evento => {
        if (evento.key === 'Enter' || evento.key === ' ') {
            evento.preventDefault();
            elementos.archivo.click();
        }
    });
    elementos.archivo.addEventListener('change', () => seleccionarArchivo(elementos.archivo.files[0]));
    elementos.cambiarImagen.addEventListener('click', () => elementos.archivo.click());
    elementos.zonaCarga.addEventListener('dragover', evento => evento.preventDefault());
    elementos.zonaCarga.addEventListener('drop', evento => {
        evento.preventDefault();
        seleccionarArchivo(evento.dataTransfer.files[0]);
    });

    function seleccionarArchivo(archivo) {
        if (!archivo) return;
        if (!TIPOS_IMAGEN_PERMITIDOS.includes(archivo.type)) {
            mostrarMensaje('Seleccione una imagen JPG o PNG.', 'danger');
            return;
        }
        if (archivo.size > TAMANO_MAXIMO_IMAGEN) {
            mostrarMensaje('La imagen supera el máximo de 10 MB.', 'danger');
            return;
        }

        archivoSeleccionado = archivo;
        imagenValidada = false;
        dimensionesImagen = { ancho: 0, alto: 0 };
        elementos.nombreArchivo.textContent = archivo.name;
        elementos.zonaCarga.classList.add('con-archivo');
        elementos.cambiarImagen.style.display = 'inline-flex';
        elementos.estadoValidacionProvisional.style.display = 'none';
        elementos.seccionAnalisis.style.display = 'none';
        const lector = new FileReader();
        lector.addEventListener('load', evento => {
            elementos.vistaPrevia.src = evento.target.result;
            const imagen = new Image();
            imagen.addEventListener('load', () => {
                dimensionesImagen = { ancho: imagen.width, alto: imagen.height };
                const resolucionBaja = imagen.width < RESOLUCION_MINIMA_PROVISIONAL
                    || imagen.height < RESOLUCION_MINIMA_PROVISIONAL;
                elementos.advertenciaCalidad.style.display = resolucionBaja ? 'flex' : 'none';
                actualizarDisponibilidadAnalisis();
            });
            imagen.src = evento.target.result;
        });
        lector.readAsDataURL(archivo);
        actualizarDisponibilidadAnalisis();
    }

    elementos.buscarPaciente.addEventListener('click', async () => {
        const dni = elementos.dni.value.trim();
        if (!/^\d{8}$/.test(dni)) {
            mostrarMensaje('El DNI debe contener 8 dígitos.', 'danger');
            return;
        }
        elementos.buscarPaciente.disabled = true;
        try {
            const respuesta = await enviarFormulario('controllers/PacienteController.php?action=buscar_registrar', { dni });
            if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo identificar al paciente.');
            idPacienteActual = Number(respuesta.paciente.id);
            elementos.resultadoPaciente.textContent = `${respuesta.nuevo ? 'Paciente registrado' : 'Paciente encontrado'} · Código ${respuesta.paciente.codigo_paciente}`;
            elementos.seccionCarpeta.style.display = 'block';
            await cargarCarpetas();
            actualizarDisponibilidadAnalisis();
        } catch (error) {
            mostrarMensaje(error.message, 'danger');
        } finally {
            elementos.buscarPaciente.disabled = false;
        }
    });

    elementos.dni.addEventListener('input', () => {
        idPacienteActual = null;
        elementos.resultadoPaciente.textContent = 'Confirme nuevamente el paciente.';
        actualizarDisponibilidadAnalisis();
    });
    elementos.ojo.addEventListener('change', actualizarDisponibilidadAnalisis);

    function actualizarDisponibilidadAnalisis() {
        const identificacionCompleta = Boolean(archivoSeleccionado && idPacienteActual && elementos.ojo.value && idCarpetaActual);
        elementos.validar.disabled = procesando || !identificacionCompleta || dimensionesImagen.ancho === 0;
        elementos.analizar.disabled = !SERVICIO_ANALISIS_DISPONIBLE || procesando || !imagenValidada;
    }

    elementos.validar.addEventListener('click', () => {
        const cumpleResolucion = dimensionesImagen.ancho >= RESOLUCION_MINIMA_PROVISIONAL
            && dimensionesImagen.alto >= RESOLUCION_MINIMA_PROVISIONAL;
        if (!cumpleResolucion) {
            imagenValidada = false;
            elementos.advertenciaCalidad.style.display = 'flex';
            mostrarMensaje('La imagen no supera la validación provisional de resolución.', 'danger');
            actualizarDisponibilidadAnalisis();
            return;
        }
        imagenValidada = true;
        elementos.advertenciaCalidad.style.display = 'none';
        elementos.estadoValidacionProvisional.style.display = 'block';
        elementos.cambiarImagen.style.display = 'none';
        elementos.seccionAnalisis.style.display = 'block';
        establecerPasoActual(2);
        actualizarDisponibilidadAnalisis();
    });

    elementos.analizar.addEventListener('click', async () => {
        if (elementos.analizar.disabled) return;
        procesando = true;
        actualizarDisponibilidadAnalisis();
        elementos.analizar.textContent = 'Analizando con CNN…';
        establecerPasoActual(2);

        const formulario = new FormData();
        formulario.append('imagen', archivoSeleccionado);
        formulario.append('dni_paciente', elementos.dni.value.trim());
        formulario.append('ojo', elementos.ojo.value);
        formulario.append('fecha_captura', elementos.fechaCaptura.value);
        if (idCarpetaActual) formulario.append('id_carpeta', String(idCarpetaActual));

        try {
            const respuestaHttp = await fetch(BASE_URL + 'controllers/AnalisisController.php?action=analizar', {
                method: 'POST',
                body: formulario,
            });
            const respuesta = await leerJson(respuestaHttp);
            if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo completar el análisis.');
            idAnalisisActual = Number(respuesta.data.id_analisis);
            mostrarResultado(respuesta.data);
        } catch (error) {
            mostrarMensaje(error.message, 'danger');
            establecerPasoActual(2);
        } finally {
            procesando = false;
            elementos.analizar.textContent = 'Analizar imagen con CNN';
            actualizarDisponibilidadAnalisis();
        }
    });

    async function mostrarResultado(salida) {
        const validacion = salida.validacion || {};
        const validacionDisponible = validacion.es_retinografia !== null
            && validacion.es_retinografia !== undefined
            && validacion.es_evaluable !== null
            && validacion.es_evaluable !== undefined;
        const esRetinografia = Boolean(validacion.es_retinografia);
        const esEvaluable = Boolean(validacion.es_evaluable);
        const aceptada = validacionDisponible
            ? esRetinografia && esEvaluable
            : Boolean(salida.resultado_principal && salida.probabilidades);

        elementos.resultado.style.display = 'block';
        elementos.estadoRetinografia.textContent = validacionDisponible
            ? (esRetinografia ? 'Retinografía confirmada' : 'Imagen ajena a retina')
            : 'No evaluado por CNN v1';
        elementos.estadoCalidad.textContent = validacionDisponible
            ? (esEvaluable ? 'Evaluable' : 'No evaluable')
            : 'No evaluado por CNN v1';
        elementos.versionModelo.textContent = salida.modelo_version || 'No informada';
        elementos.tiempoModelo.textContent = Number.isFinite(Number(salida.tiempo_analisis))
            ? `${Number(salida.tiempo_analisis).toFixed(3)} s`
            : 'No informado';
        establecerPasoActual(3);

        if (!aceptada) {
            elementos.estadoResultado.classList.add('rechazado');
            elementos.tituloResultado.textContent = 'Imagen rechazada';
            elementos.detalleResultado.textContent = validacion.motivo_rechazo || 'La imagen no superó las condiciones de entrada.';
            elementos.probabilidades.style.display = 'none';
            elementos.bloqueValoracion.style.display = 'none';
            elementos.informe.style.display = 'none';
            mostrarMensaje('No se generó una interpretación clínica. Seleccione otra imagen.', 'warning');
            return;
        }

        elementos.estadoResultado.classList.remove('rechazado');
        elementos.tituloResultado.textContent = formatearResultado(salida.resultado_principal);
        elementos.detalleResultado.textContent = 'Salida original de la CNN. Requiere revisión médica.';
        elementos.probabilidades.style.display = 'block';
        elementos.bloqueValoracion.style.display = 'block';
        mostrarProbabilidades(salida.probabilidades || {});
        await generarBorrador();
    }

    function mostrarProbabilidades(probabilidades) {
        ['diabetes', 'glaucoma', 'catarata', 'normal'].forEach(clave => {
            const probabilidad = Number(probabilidades[clave]);
            const probabilidadValida = Number.isFinite(probabilidad);
            const valorVisual = probabilidadValida ? Math.max(0, Math.min(100, probabilidad)) : 0;
            document.getElementById(`r_${clave}`).textContent = probabilidadValida
                ? `${probabilidad.toFixed(1)}%`
                : 'No informada';
            document.getElementById(`f_${clave}`).style.width = `${valorVisual}%`;
        });
    }

    async function generarBorrador() {
        elementos.informe.style.display = 'block';
        elementos.errorBorrador.style.display = 'none';
        try {
            const respuesta = await enviarFormulario('controllers/AnalisisController.php?action=generar_borrador', {
                id_analisis: idAnalisisActual,
            });
            if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo generar el borrador.');
            elementos.textoInforme.value = respuesta.informe.texto_editado;
            elementos.informe.style.display = 'block';
            elementos.estadoBorrador.textContent = 'Borrador guardado en el servidor.';
            establecerPasoActual(4);
        } catch (error) {
            elementos.errorBorradorTexto.textContent = error.message;
            elementos.errorBorrador.style.display = 'block';
            elementos.textoInforme.value = '';
            elementos.estadoBorrador.textContent = 'No se generó ningún texto.';
            establecerPasoActual(4);
            mostrarMensaje(error.message, 'danger');
        }
    }

    elementos.generarBorrador.addEventListener('click', generarBorrador);

    document.querySelectorAll('[data-valoracion]').forEach(boton => {
        boton.addEventListener('click', async () => {
            const valoracion = boton.dataset.valoracion;
            document.querySelectorAll('[data-valoracion]').forEach(opcion => opcion.classList.toggle('seleccionada', opcion === boton));
            elementos.detalleDiscrepancia.style.display = valoracion === 'discrepo' ? 'block' : 'none';
            if (valoracion === 'discrepo' && !elementos.motivoDiscrepancia.value.trim()) {
                elementos.estadoValoracion.textContent = 'Escriba un motivo y vuelva a seleccionar “Discrepo”.';
                elementos.motivoDiscrepancia.focus();
                return;
            }
            try {
                const respuesta = await enviarFormulario('controllers/AnalisisController.php?action=valorar_resultado', {
                    id_analisis: idAnalisisActual,
                    valoracion,
                    motivo: elementos.motivoDiscrepancia.value.trim(),
                    comentario: elementos.comentarioDiscrepancia.value.trim(),
                });
                if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo guardar la valoración.');
                elementos.estadoValoracion.textContent = 'Valoración guardada. No altera la salida de la CNN.';
            } catch (error) {
                mostrarMensaje(error.message, 'danger');
            }
        });
    });

    elementos.textoInforme.addEventListener('input', () => {
        elementos.estadoBorrador.textContent = 'Cambios pendientes de guardar.';
    });
    elementos.guardarBorrador.addEventListener('click', () => guardarBorrador(false));
    elementos.aprobarInforme.addEventListener('click', () => guardarBorrador(true));

    async function guardarBorrador(aprobar) {
        const texto = elementos.textoInforme.value.trim();
        if (!texto) {
            mostrarMensaje('El informe no puede quedar vacío.', 'danger');
            return;
        }
        const boton = aprobar ? elementos.aprobarInforme : elementos.guardarBorrador;
        boton.disabled = true;
        const textoAnterior = boton.textContent;
        boton.textContent = aprobar ? 'Generando PDF…' : 'Guardando…';
        try {
            const accion = aprobar ? 'aprobar_informe' : 'guardar_borrador';
            const respuesta = await enviarFormulario(`controllers/AnalisisController.php?action=${accion}`, {
                id_analisis: idAnalisisActual,
                texto_informe: texto,
            });
            if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo guardar el informe.');
            if (aprobar) {
                elementos.textoInforme.disabled = true;
                elementos.guardarBorrador.style.display = 'none';
                elementos.aprobarInforme.style.display = 'none';
                elementos.descargarPdf.style.display = 'inline-flex';
                const estados = {
                    completada: 'Informe aprobado y PDF sincronizado con la cuenta documental.',
                    fallida: 'Informe aprobado y PDF local guardado. Falló la sincronización; el administrador puede reintentarla.',
                    local: 'Informe aprobado y PDF local guardado. El establecimiento aún no conectó una cuenta documental.',
                };
                elementos.estadoBorrador.textContent = estados[respuesta.estado_sincronizacion] || 'Informe aprobado.';
                completarFlujo();
            } else {
                elementos.estadoBorrador.textContent = 'Borrador guardado en el servidor.';
            }
        } catch (error) {
            mostrarMensaje(error.message, 'danger');
        } finally {
            boton.disabled = false;
            boton.textContent = textoAnterior;
        }
    }

    elementos.descargarPdf.addEventListener('click', () => {
        window.location.assign(`${BASE_URL}controllers/AnalisisController.php?action=descargar_informe&id_analisis=${idAnalisisActual}`);
    });
    elementos.reiniciar.addEventListener('click', () => window.location.reload());

    async function cargarCarpetas() {
        const respuesta = await enviarFormulario('controllers/CarpetaController.php?action=listar', {
            id_paciente: idPacienteActual,
        });
        if (!respuesta.success) throw new Error(respuesta.error || 'No se pudieron cargar las carpetas.');
        elementos.rejillaCarpetas.replaceChildren();
        if (respuesta.carpetas.length === 0) {
            const vacio = document.createElement('p');
            vacio.className = 'campo-ayuda';
            vacio.textContent = 'No hay carpetas para este paciente.';
            elementos.rejillaCarpetas.appendChild(vacio);
            return;
        }
        respuesta.carpetas.forEach(carpeta => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'carpeta';
            boton.innerHTML = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2.5 5.5h5l1.5 2h8.5v8h-15z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg><span></span>';
            boton.querySelector('span').textContent = `${carpeta.nombre} · ${carpeta.total_analisis} análisis`;
            boton.addEventListener('click', () => {
                idCarpetaActual = Number(carpeta.id);
                elementos.rejillaCarpetas.querySelectorAll('.carpeta').forEach(elemento => elemento.classList.remove('seleccionada'));
                boton.classList.add('seleccionada');
                imagenValidada = false;
                elementos.estadoValidacionProvisional.style.display = 'none';
                elementos.seccionAnalisis.style.display = 'none';
                elementos.cambiarImagen.style.display = archivoSeleccionado ? 'inline-flex' : 'none';
                establecerPasoActual(1);
                actualizarDisponibilidadAnalisis();
            });
            elementos.rejillaCarpetas.appendChild(boton);
        });
    }

    elementos.nuevaCarpeta.addEventListener('click', () => {
        elementos.formularioCarpeta.style.display = 'block';
        elementos.nombreCarpeta.focus();
    });
    elementos.cancelarCarpeta.addEventListener('click', () => {
        elementos.formularioCarpeta.style.display = 'none';
    });
    elementos.crearCarpeta.addEventListener('click', async () => {
        const nombre = elementos.nombreCarpeta.value.trim();
        if (!nombre) {
            mostrarMensaje('Escriba el nombre de la carpeta.', 'danger');
            return;
        }
        try {
            const respuesta = await enviarFormulario('controllers/CarpetaController.php?action=crear', {
                id_paciente: idPacienteActual,
                nombre,
                descripcion: elementos.descripcionCarpeta.value.trim(),
            });
            if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo crear la carpeta.');
            idCarpetaActual = Number(respuesta.carpeta.id);
            elementos.formularioCarpeta.style.display = 'none';
            elementos.nombreCarpeta.value = '';
            elementos.descripcionCarpeta.value = '';
            await cargarCarpetas();
            const nueva = [...elementos.rejillaCarpetas.querySelectorAll('.carpeta')]
                .find(boton => boton.textContent.includes(respuesta.carpeta.nombre));
            nueva?.classList.add('seleccionada');
            actualizarDisponibilidadAnalisis();
        } catch (error) {
            mostrarMensaje(error.message, 'danger');
        }
    });

    async function enviarFormulario(ruta, valores) {
        const formulario = new FormData();
        Object.entries(valores).forEach(([clave, valor]) => formulario.append(clave, String(valor ?? '')));
        const url = new URL(BASE_URL + ruta, window.location.href);
        const respuestaHttp = await fetch(url, { method: 'POST', body: formulario });
        const respuesta = await leerJson(respuestaHttp);
        if (respuesta.expired) window.location.assign(BASE_URL + 'views/auth/login.php');
        return respuesta;
    }

    async function leerJson(respuestaHttp) {
        try {
            const respuesta = await respuestaHttp.json();
            if (!respuestaHttp.ok && !respuesta.error) respuesta.error = `El servidor respondió HTTP ${respuestaHttp.status}.`;
            return respuesta;
        } catch (_) {
            throw new Error(`El servidor devolvió una respuesta inesperada (HTTP ${respuestaHttp.status}).`);
        }
    }

    function establecerPasoActual(numeroActual) {
        for (let numeroPaso = 1; numeroPaso <= CANTIDAD_PASOS; numeroPaso += 1) {
            const paso = document.getElementById(`paso-${numeroPaso}`);
            paso.classList.toggle('completo', numeroPaso < numeroActual);
            paso.classList.toggle('activo', numeroPaso === numeroActual);
            if (numeroPaso === numeroActual) {
                paso.setAttribute('aria-current', 'step');
            } else {
                paso.removeAttribute('aria-current');
            }
        }
    }

    function completarFlujo() {
        for (let numeroPaso = 1; numeroPaso <= CANTIDAD_PASOS; numeroPaso += 1) {
            const paso = document.getElementById(`paso-${numeroPaso}`);
            paso.classList.remove('activo');
            paso.classList.add('completo');
            paso.removeAttribute('aria-current');
        }
    }

    function mostrarMensaje(mensaje, tipo) {
        elementos.mensaje.textContent = mensaje;
        elementos.mensaje.className = `toast ${tipo || ''}`;
        elementos.mensaje.style.display = 'flex';
        window.setTimeout(() => { elementos.mensaje.style.display = 'none'; }, DURACION_MENSAJE);
    }

    function formatearResultado(resultado) {
        const etiquetas = {
            diabetes: 'Retinopatía diabética',
            glaucoma: 'Glaucoma',
            catarata: 'Catarata',
            normal: 'Normal',
        };
        return etiquetas[resultado] || resultado;
    }

    async function cargarAnalisisPendiente() {
        const idPendiente = Number(new URLSearchParams(window.location.search).get('reanudar') || 0);
        if (idPendiente <= 0) return;
        try {
            const respuesta = await fetch(`${BASE_URL}controllers/AnalisisController.php?action=datos_pdf&id_analisis=${idPendiente}`)
                .then(resultado => resultado.json());
            if (!respuesta.success) throw new Error(respuesta.error || 'No se pudo abrir el análisis.');
            const analisis = respuesta.analisis;
            idAnalisisActual = Number(analisis.id);
            const salida = {
                id_analisis: idAnalisisActual,
                modelo_version: analisis.version_modelo,
                tiempo_analisis: analisis.tiempo_analisis,
                validacion: {
                    es_retinografia: analisis.es_retinografia === null ? null : Number(analisis.es_retinografia) === 1,
                    es_evaluable: analisis.es_evaluable === null ? null : Number(analisis.es_evaluable) === 1,
                    motivo_rechazo: analisis.motivo_rechazo,
                },
                resultado_principal: analisis.resultado_principal,
                probabilidades: {
                    normal: analisis.probabilidad_normal,
                    diabetes: analisis.probabilidad_diabetes,
                    glaucoma: analisis.probabilidad_glaucoma,
                    catarata: analisis.probabilidad_catarata,
                },
            };
            await mostrarResultado(salida);
            if (analisis.estado_informe === 'aprobado') {
                elementos.textoInforme.value = analisis.texto_editado || '';
                elementos.textoInforme.disabled = true;
                elementos.guardarBorrador.style.display = 'none';
                elementos.aprobarInforme.style.display = 'none';
                elementos.descargarPdf.style.display = 'inline-flex';
                elementos.estadoBorrador.textContent = 'Informe aprobado.';
                completarFlujo();
            }
        } catch (error) {
            mostrarMensaje(error.message, 'danger');
        }
    }

    function obtenerFechaLocalActual() {
        const ahora = new Date();
        const desplazamiento = ahora.getTimezoneOffset() * 60000;
        return new Date(ahora.getTime() - desplazamiento).toISOString().slice(0, 16);
    }
});
