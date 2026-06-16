document.addEventListener('DOMContentLoaded', () => {
    const fileInput         = document.getElementById('file-input');
    const dropZone          = document.getElementById('drop-zone');
    const analyzeBtn        = document.getElementById('analyze-btn');
    const btnBuscarPaciente = document.getElementById('btn-buscar-paciente');
    const btnReset          = document.getElementById('btn-reset');
    const btnPdf            = document.getElementById('btn-pdf');

    let currentFile    = null;
    let isProcessing   = false;
    let currentAnalisisId = null;   // ID del análisis guardado en BD

    // ── File handling ────────────────────────────────────────────────────────
    fileInput.addEventListener('change', function() {
        handleFile(this.files[0]);
    });

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--primary)';
        dropZone.style.background  = '#eff6ff';
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#1A56DB';
        dropZone.style.background  = 'none';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#1A56DB';
        dropZone.style.background  = 'none';
        handleFile(e.dataTransfer.files[0]);
    });

    function handleFile(file) {
        if (!file) return;

        if (file.size > 10 * 1024 * 1024) {
            showToast('El archivo supera el tamaño máximo permitido de 10 MB. Por favor comprime o recorta la imagen.', 'danger');
            return;
        }

        if (!file.type.match('image/jpeg') && !file.type.match('image/png')) {
            showToast('Formato no permitido. Por favor sube una imagen en formato JPG o PNG.', 'danger');
            return;
        }

        currentFile = file;
        document.getElementById('file-name').textContent = file.name;

        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('preview-image-element').src = e.target.result;

            const img = new Image();
            img.onload = function() {
                if (this.width < 500 || this.height < 500) {
                    document.getElementById('quality-warning').style.display = 'flex';
                } else {
                    document.getElementById('quality-warning').style.display = 'none';
                }
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);

        document.getElementById('preview-area').style.display = 'block';
        analyzeBtn.disabled = false;
    }

    // ── Análisis ─────────────────────────────────────────────────────────────
    analyzeBtn.addEventListener('click', async () => {
        if (!currentFile || isProcessing) return;

        isProcessing = true;
        analyzeBtn.disabled  = true;
        analyzeBtn.textContent = 'Procesando...';

        document.getElementById('step2').className = 'step-circle active';
        document.getElementById('line1').className  = 'step-line done';
        document.getElementById('lbl-step2').classList.add('active');

        const startTime = Date.now();

        const formData = new FormData();
        formData.append('imagen', currentFile);
        const dni = document.getElementById('dni-input').value.trim();
        if (dni) formData.append('dni_paciente', dni);

        try {
            const response = await fetch(BASE_URL + 'controllers/AnalisisController.php?action=analizar', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            const elapsed = Date.now() - startTime;
            if (elapsed < 2500) {
                await new Promise(r => setTimeout(r, 2500 - elapsed));
            }

            if (result.success) {
                // Guardar el ID del análisis registrado en BD
                currentAnalisisId = result.id_analisis;
                mostrarResultados(result.data);
            } else {
                if (result.expired) {
                    window.location.href = BASE_URL + 'views/auth/login.php';
                } else {
                    showToast(result.error || 'Error desconocido', 'danger');
                    resetStepper();
                }
            }

        } catch (error) {
            console.error(error);
            showToast('El análisis está tardando más de lo esperado. Verifique su conexión o intente nuevamente.', 'danger');
            resetStepper();
        } finally {
            isProcessing = false;
            analyzeBtn.disabled   = false;
            analyzeBtn.textContent = 'Analizar imagen';
        }
    });

    // ── Mostrar resultados ────────────────────────────────────────────────────
    function mostrarResultados(data) {
        document.getElementById('step2').className   = 'step-circle done';
        document.getElementById('step2').textContent = '✓';
        document.getElementById('step3').className   = 'step-circle active';
        document.getElementById('line2').className   = 'step-line done';
        document.getElementById('lbl-step3').classList.add('active');

        document.getElementById('result-col').style.display = 'block';

        const mainAlert = document.getElementById('result-main');
        const icon  = document.getElementById('result-icon');
        const title = document.getElementById('result-title');
        const sub   = document.getElementById('result-sub');

        title.textContent = data.resultado_principal.charAt(0).toUpperCase() + data.resultado_principal.slice(1);

        if (data.alerta_anomalia) {
            mainAlert.className = 'big-alert big-alert-danger';
            icon.textContent    = '⚠️';
            sub.textContent     = 'Alta probabilidad de anomalía detectada (' + data.probabilidad_principal + '%)';
        } else {
            mainAlert.className        = 'big-alert big-alert-success';
            icon.textContent           = '✓';
            sub.textContent            = 'No se detectaron anomalías significativas (' + data.probabilidad_principal + '%)';
            mainAlert.style.background = '#f0fdf4';
            mainAlert.style.borderColor= '#bbf7d0';
        }

        setTimeout(() => {
            const p = data.probabilidades;
            document.getElementById('r_diabetes').textContent     = (p.diabetes  || 0) + '%';
            document.getElementById('f_diabetes').style.width     = (p.diabetes  || 0) + '%';
            document.getElementById('r_glaucoma').textContent     = (p.glaucoma  || 0) + '%';
            document.getElementById('f_glaucoma').style.width     = (p.glaucoma  || 0) + '%';
            document.getElementById('r_catarata').textContent     = (p.catarata  || 0) + '%';
            document.getElementById('f_catarata').style.width     = (p.catarata  || 0) + '%';
            document.getElementById('r_normal').textContent       = (p.normal    || 0) + '%';
            document.getElementById('f_normal').style.width       = (p.normal    || 0) + '%';
        }, 100);
    }

    // ── Botón Reset ───────────────────────────────────────────────────────────
    btnReset.addEventListener('click', () => {
        document.getElementById('result-col').style.display    = 'none';
        document.getElementById('preview-area').style.display  = 'none';
        document.getElementById('quality-warning').style.display = 'none';
        document.getElementById('file-input').value            = '';
        currentFile       = null;
        currentAnalisisId = null;
        resetStepper();
    });

    function resetStepper() {
        ['step2','step3'].forEach(s => {
            document.getElementById(s).className = 'step-circle pending';
        });
        document.getElementById('step2').textContent = '2';
        document.getElementById('step3').textContent = '3';
        document.getElementById('step1').className   = 'step-circle active';
        ['line1','line2'].forEach(l => document.getElementById(l).className = 'step-line');
        document.getElementById('lbl-step2').classList.remove('active');
        document.getElementById('lbl-step3').classList.remove('active');
    }

    // ── Buscar Paciente ───────────────────────────────────────────────────────
    btnBuscarPaciente.addEventListener('click', async () => {
        const dni = document.getElementById('dni-input').value.trim();
        if (dni.length !== 8 || !/^\d+$/.test(dni)) {
            showToast('Ingrese un DNI válido de 8 dígitos', 'danger');
            return;
        }

        btnBuscarPaciente.disabled     = true;
        btnBuscarPaciente.textContent  = 'Buscando...';

        const fd = new FormData();
        fd.append('dni', dni);

        try {
            const res  = await fetch(BASE_URL + 'controllers/PacienteController.php?action=buscar_registrar', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                const p   = data.paciente;
                const msg = data.nuevo ? 'Paciente registrado' : 'Paciente identificado';
                const resDiv = document.getElementById('paciente-result');
                resDiv.style.display = 'block';
                resDiv.innerHTML = `<div class="alert-box alert-success"><p>${msg} · Código: <strong>${p.codigo_paciente}</strong></p></div>`;
            } else {
                showToast(data.error || 'Error', 'danger');
            }
        } catch (e) {
            showToast('Error de conexión', 'danger');
        } finally {
            btnBuscarPaciente.disabled    = false;
            btnBuscarPaciente.textContent = 'Buscar / registrar paciente';
        }
    });

    // ── Botón PDF ─────────────────────────────────────────────────────────────
    btnPdf.addEventListener('click', async () => {
        if (!currentAnalisisId) {
            showToast('No hay análisis disponible para descargar.', 'danger');
            return;
        }

        btnPdf.disabled    = true;
        btnPdf.textContent = 'Generando PDF...';

        try {
            const res  = await fetch(BASE_URL + 'controllers/AnalisisController.php?action=datos_pdf&id_analisis=' + currentAnalisisId);
            const json = await res.json();

            if (!json.success) {
                if (json.expired) window.location.href = BASE_URL + 'views/auth/login.php';
                showToast(json.error || 'Error al obtener datos del análisis.', 'danger');
                return;
            }

            await generarPDF(json.analisis);
            showToast('Reporte PDF descargado correctamente.', 'success');

        } catch (e) {
            console.error(e);
            showToast('Error al generar el PDF. Intente nuevamente.', 'danger');
        } finally {
            btnPdf.disabled    = false;
            btnPdf.textContent = 'Descargar reporte PDF';
        }
    });

    // ── Generación PDF con jsPDF ──────────────────────────────────────────────
    async function generarPDF(a) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });

        const W  = doc.internal.pageSize.getWidth();   // 210
        const pageH = doc.internal.pageSize.getHeight(); // 297
        let y = 0;

        // ── ENCABEZADO ────────────────────────────────────────────────────────
        // Banda superior azul
        doc.setFillColor(26, 86, 219);
        doc.rect(0, 0, W, 30, 'F');

        // Logo / Título
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(20);
        doc.text('RetinAI', 14, 13);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text('Sistema de análisis retinal asistido por inteligencia artificial', 14, 20);

        // Número de reporte derecha
        doc.setFontSize(8);
        doc.text('Reporte #' + a.id, W - 14, 13, { align: 'right' });
        doc.text('Análisis retinal — Reporte médico', W - 14, 20, { align: 'right' });

        y = 38;

        // ── DATOS DEL MÉDICO ─────────────────────────────────────────────────
        doc.setDrawColor(230, 235, 245);
        doc.setFillColor(248, 250, 255);
        doc.roundedRect(10, y, W - 20, 28, 3, 3, 'FD');

        doc.setTextColor(26, 86, 219);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.text('MÉDICO TRATANTE', 16, y + 7);

        doc.setTextColor(30, 30, 40);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);
        doc.text('Dr. ' + (a.nombre_medico || 'N/D'), 16, y + 15);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(90, 95, 110);
        const espLabel = a.especialidad_medico ? a.especialidad_medico : 'Médico Oftalmólogo';
        doc.text(espLabel + (a.cmp_medico ? '   ·   CMP: ' + a.cmp_medico : ''), 16, y + 22);

        // Fecha y hora a la derecha
        const fechaStr = formatearFecha(a.fecha_analisis);
        doc.setTextColor(26, 86, 219);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.text('FECHA Y HORA DEL ANÁLISIS', W - 14, y + 7, { align: 'right' });
        doc.setTextColor(30, 30, 40);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        doc.text(fechaStr, W - 14, y + 15, { align: 'right' });

        if (a.codigo_paciente || a.dni_paciente) {
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(90, 95, 110);
            const pacTxt = (a.codigo_paciente ? 'Cód. Paciente: ' + a.codigo_paciente : '')
                         + (a.dni_paciente    ? '   DNI: ' + a.dni_paciente : '');
            doc.text(pacTxt, W - 14, y + 22, { align: 'right' });
        }

        y += 36;

        // ── MINIATURA RETINOGRAFÍA ────────────────────────────────────────────
        const imgW  = 60;
        const imgH  = 55;
        const imgX  = 14;
        const imgY  = y;

        if (a.imagen_b64) {
            try {
                const ext = a.imagen_b64.startsWith('data:image/png') ? 'PNG' : 'JPEG';
                doc.addImage(a.imagen_b64, ext, imgX, imgY, imgW, imgH, '', 'MEDIUM');
                // Borde suave
                doc.setDrawColor(200, 210, 230);
                doc.setLineWidth(0.4);
                doc.rect(imgX, imgY, imgW, imgH);
            } catch(e) {
                console.warn('No se pudo incrustar la imagen:', e);
            }
        }

        // Etiqueta bajo la imagen
        doc.setFont('helvetica', 'italic');
        doc.setFontSize(7);
        doc.setTextColor(120, 125, 140);
        doc.text('Retinografía analizada', imgX + imgW / 2, imgY + imgH + 5, { align: 'center' });

        // ── RESULTADO PRINCIPAL (a la derecha de la imagen) ───────────────────
        const rX = imgX + imgW + 8;
        const rW = W - rX - 10;

        // Badge resultado principal
        const esAnomalia = a.alerta_anomalia;
        const badgeColor = esAnomalia ? [220, 38, 38] : [5, 150, 105];

        doc.setFillColor(...badgeColor);
        doc.roundedRect(rX, imgY, rW, 22, 3, 3, 'F');

        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.text('RESULTADO PRINCIPAL', rX + rW / 2, imgY + 7, { align: 'center' });

        doc.setFontSize(14);
        const resultadoLabel = capitalizar(a.resultado_principal);
        doc.text(resultadoLabel, rX + rW / 2, imgY + 17, { align: 'center' });

        // Probabilidad principal
        doc.setFillColor(240, 245, 255);
        doc.setDrawColor(210, 220, 240);
        doc.roundedRect(rX, imgY + 25, rW, 12, 2, 2, 'FD');
        doc.setTextColor(26, 86, 219);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.text('Confianza: ' + Number(a.probabilidad_principal).toFixed(1) + '%', rX + rW / 2, imgY + 33, { align: 'center' });

        // ── BARRAS DE PROBABILIDADES ─────────────────────────────────────────
        const barY0 = imgY + 40;
        const clases = [
            { label: 'Retinopatía Diabética', val: a.probabilidad_diabetes, color: [220, 38, 38]  },
            { label: 'Glaucoma',              val: a.probabilidad_glaucoma, color: [217, 119, 6]  },
            { label: 'Catarata',              val: a.probabilidad_catarata, color: [37, 99, 235]  },
            { label: 'Normal',                val: a.probabilidad_normal,   color: [5, 150, 105]  },
        ];

        const barMaxW = rW - 4;
        clases.forEach((c, i) => {
            const by = barY0 + i * 11;
            const pct = Math.min(100, Math.max(0, Number(c.val)));
            const filledW = (pct / 100) * barMaxW;

            // Etiqueta
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(7.5);
            doc.setTextColor(50, 55, 70);
            doc.text(c.label, rX, by + 3.5);
            doc.setTextColor(80, 85, 100);
            doc.text(pct.toFixed(1) + '%', rX + rW, by + 3.5, { align: 'right' });

            // Track
            doc.setFillColor(230, 234, 242);
            doc.roundedRect(rX, by + 5, barMaxW, 3.5, 1, 1, 'F');

            // Fill
            if (filledW > 0) {
                doc.setFillColor(...c.color);
                doc.roundedRect(rX, by + 5, filledW, 3.5, 1, 1, 'F');
            }
        });

        y = Math.max(imgY + imgH + 8, barY0 + clases.length * 11 + 6);

        // ── AVISO REFERENCIAL ─────────────────────────────────────────────────
        y += 4;
        doc.setFillColor(255, 251, 235);
        doc.setDrawColor(253, 230, 138);
        doc.roundedRect(10, y, W - 20, 14, 2, 2, 'FD');

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(7.5);
        doc.setTextColor(146, 64, 14);
        doc.text('⚠  RESULTADO REFERENCIAL', 16, y + 6);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7);
        doc.setTextColor(120, 80, 20);
        doc.text('Este análisis fue generado por un modelo de inteligencia artificial. No constituye diagnóstico definitivo.', 16, y + 11);

        y += 20;

        // ── LEYENDA LEGAL ─────────────────────────────────────────────────────
        const leyenda = 'Resultado generado por modelo de inteligencia artificial con fines de apoyo diagnóstico. La decisión clínica final corresponde al médico tratante.';
        doc.setFillColor(241, 245, 249);
        doc.setDrawColor(203, 213, 225);

        const leyendaLines = doc.splitTextToSize(leyenda, W - 32);
        const leyendaH = leyendaLines.length * 4.5 + 8;
        doc.roundedRect(10, y, W - 20, leyendaH, 2, 2, 'FD');

        doc.setFont('helvetica', 'italic');
        doc.setFontSize(8);
        doc.setTextColor(71, 85, 105);
        doc.text(leyendaLines, 16, y + 7);

        y += leyendaH + 6;

        // ── PIE DE PÁGINA ─────────────────────────────────────────────────────
        doc.setFillColor(26, 86, 219);
        doc.rect(0, pageH - 12, W, 12, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7);
        doc.text('RetinAI — Sistema de análisis retinal asistido por IA', 14, pageH - 5);
        doc.text('Generado: ' + new Date().toLocaleString('es-PE'), W - 14, pageH - 5, { align: 'right' });

        // ── DESCARGAR ─────────────────────────────────────────────────────────
        const fechaFile = new Date().toISOString().slice(0, 10);
        doc.save('RetinAI_Reporte_' + a.id + '_' + fechaFile + '.pdf');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function formatearFecha(fechaStr) {
        if (!fechaStr) return 'N/D';
        const d = new Date(fechaStr.replace(' ', 'T'));
        return d.toLocaleDateString('es-PE', { day:'2-digit', month:'long', year:'numeric' })
             + '  —  '
             + d.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
    }

    function capitalizar(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    function showToast(msg, type = '') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className   = 'toast ' + (type || '');
        t.style.display = 'flex';
        setTimeout(() => t.style.display = 'none', 4000);
    }
});
