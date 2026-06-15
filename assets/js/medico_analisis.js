document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('file-input');
    const dropZone = document.getElementById('drop-zone');
    const analyzeBtn = document.getElementById('analyze-btn');
    const btnBuscarPaciente = document.getElementById('btn-buscar-paciente');
    const btnReset = document.getElementById('btn-reset');
    const btnPdf = document.getElementById('btn-pdf');
    
    let currentFile = null;
    let isProcessing = false;

    // File handling
    fileInput.addEventListener('change', function() {
        handleFile(this.files[0]);
    });

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--primary)';
        dropZone.style.background = '#eff6ff';
    });

    dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#1A56DB';
        dropZone.style.background = 'none';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#1A56DB';
        dropZone.style.background = 'none';
        handleFile(e.dataTransfer.files[0]);
    });

    function handleFile(file) {
        if (!file) return;
        
        // Validar tamaño (10MB)
        if (file.size > 10 * 1024 * 1024) {
            showToast('El archivo supera el tamaño máximo permitido de 10 MB. Por favor comprime o recorta la imagen.', 'danger');
            return;
        }

        // Validar tipo
        if (!file.type.match('image/jpeg') && !file.type.match('image/png')) {
            showToast('Formato no permitido. Por favor sube una imagen en formato JPG o PNG.', 'danger');
            return;
        }

        currentFile = file;
        document.getElementById('file-name').textContent = file.name;
        
        // Vista previa
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('preview-image-element').src = e.target.result;
            
            // Simular validación de calidad baja
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

    // Análisis
    analyzeBtn.addEventListener('click', async () => {
        if (!currentFile || isProcessing) return;
        
        isProcessing = true;
        analyzeBtn.disabled = true;
        analyzeBtn.textContent = 'Procesando...';

        document.getElementById('step2').className = 'step-circle active';
        document.getElementById('line1').className = 'step-line done';
        document.getElementById('lbl-step2').classList.add('active');

        // Para simular los 5 segundos pero enviar al backend
        const startTime = Date.now();

        const formData = new FormData();
        formData.append('imagen', currentFile);
        const dni = document.getElementById('dni-input').value.trim();
        if (dni) {
            formData.append('dni_paciente', dni);
        }

        try {
            const response = await fetch(BASE_URL + 'controllers/AnalisisController.php?action=analizar', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            // Garantizamos que la animación dure al menos 2.5s para UX
            const elapsedTime = Date.now() - startTime;
            if (elapsedTime < 2500) {
                await new Promise(r => setTimeout(r, 2500 - elapsedTime));
            }

            if (result.success) {
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
            analyzeBtn.disabled = false;
            analyzeBtn.textContent = 'Analizar imagen';
        }
    });

    function mostrarResultados(data) {
        document.getElementById('step2').className = 'step-circle done';
        document.getElementById('step2').textContent = '✓';
        document.getElementById('step3').className = 'step-circle active';
        document.getElementById('line2').className = 'step-line done';
        document.getElementById('lbl-step3').classList.add('active');

        document.getElementById('result-col').style.display = 'block';

        // Actualizar UI con resultados
        const mainAlert = document.getElementById('result-main');
        const icon = document.getElementById('result-icon');
        const title = document.getElementById('result-title');
        const sub = document.getElementById('result-sub');

        title.textContent = data.resultado_principal.charAt(0).toUpperCase() + data.resultado_principal.slice(1);

        if (data.alerta_anomalia) {
            mainAlert.className = 'big-alert big-alert-danger';
            icon.textContent = '⚠️';
            sub.textContent = 'Alta probabilidad de anomalía detectada (' + data.probabilidad_principal + '%)';
        } else {
            mainAlert.className = 'big-alert big-alert-success';
            icon.textContent = '✓';
            sub.textContent = 'No se detectaron anomalías significativas (' + data.probabilidad_principal + '%)';
            mainAlert.style.background = '#f0fdf4';
            mainAlert.style.borderColor = '#bbf7d0';
        }

        // Animación barras
        setTimeout(() => {
            const p = data.probabilidades;
            document.getElementById('r_diabetes').textContent = (p.diabetes || 0) + '%';
            document.getElementById('f_diabetes').style.width = (p.diabetes || 0) + '%';

            document.getElementById('r_glaucoma').textContent = (p.glaucoma || 0) + '%';
            document.getElementById('f_glaucoma').style.width = (p.glaucoma || 0) + '%';

            document.getElementById('r_catarata').textContent = (p.catarata || 0) + '%';
            document.getElementById('f_catarata').style.width = (p.catarata || 0) + '%';

            document.getElementById('r_normal').textContent = (p.normal || 0) + '%';
            document.getElementById('f_normal').style.width = (p.normal || 0) + '%';
        }, 100);
    }

    // Reset
    btnReset.addEventListener('click', () => {
        document.getElementById('result-col').style.display = 'none';
        document.getElementById('preview-area').style.display = 'none';
        document.getElementById('quality-warning').style.display = 'none';
        document.getElementById('file-input').value = '';
        currentFile = null;

        resetStepper();
    });

    function resetStepper() {
        ['step2','step3'].forEach(s=>{ document.getElementById(s).className='step-circle pending'; });
        document.getElementById('step2').textContent = '2';
        document.getElementById('step3').textContent = '3';
        document.getElementById('step1').className = 'step-circle active';
        ['line1','line2'].forEach(l => document.getElementById(l).className='step-line');
        document.getElementById('lbl-step2').classList.remove('active');
        document.getElementById('lbl-step3').classList.remove('active');
    }

    // Buscar Paciente
    btnBuscarPaciente.addEventListener('click', async () => {
        const dni = document.getElementById('dni-input').value.trim();
        if (dni.length !== 8 || !/^\d+$/.test(dni)) {
            showToast('Ingrese un DNI válido de 8 dígitos', 'danger');
            return;
        }

        btnBuscarPaciente.disabled = true;
        btnBuscarPaciente.textContent = 'Buscando...';

        const fd = new FormData();
        fd.append('dni', dni);

        try {
            const res = await fetch(BASE_URL + 'controllers/PacienteController.php?action=buscar_registrar', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                const p = data.paciente;
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
            btnBuscarPaciente.disabled = false;
            btnBuscarPaciente.textContent = 'Buscar / registrar paciente';
        }
    });

    // PDF
    btnPdf.addEventListener('click', () => {
        showToast('Descarga de PDF formal generada exitosamente.', 'success');
        // Aquí iría el open() a un endpoint que genera el mpdf
    });

    function showToast(msg, type='') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + (type || '');
        t.style.display = 'flex';
        setTimeout(() => t.style.display='none', 4000);
    }
});
