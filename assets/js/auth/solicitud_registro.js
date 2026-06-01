/* ═══════════════════════════════════════════════
   RetinAI — solicitud_registro.js
   Formulario RF-08: Lógica de UI, validaciones y uploads
═══════════════════════════════════════════════ */

const MAX_FILES     = 2;
const MAX_KB        = 200;
const MAX_BYTES     = MAX_KB * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
const ALLOWED_EXT   = /\.(jpg|jpeg|png|pdf)$/i;

let uploadedFiles   = [];     // { file, b64, name, type }
let codeSent        = false;
let codeVerified    = false;  // no requerimos confirmar antes de submit (el server valida)
let resendTimer     = null;
let expireTimer     = null;
let expireSeconds   = 600;    // 10 minutos
let resendSeconds   = 60;

document.addEventListener('DOMContentLoaded', () => {

    /* ── Solo dígitos en RUC y DNI ── */
    document.getElementById('ruc')?.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g,'').slice(0,11);
    });
    document.getElementById('dni_titular')?.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g,'').slice(0,8);
    });
    document.getElementById('codigo_verificacion')?.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g,'').slice(0,6);
    });

    /* ── Eventos de envío y validación ── */
    const form = document.getElementById('form-solicitud');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }

    const btnSendCode = document.getElementById('btn-send-code');
    if (btnSendCode) {
        btnSendCode.addEventListener('click', enviarCodigo);
    }

    const resendLink = document.getElementById('resend-link');
    if (resendLink) {
        resendLink.addEventListener('click', reenviarCodigo);
    }

    // Archivos
    const fileInput = document.getElementById('file-input');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            agregarArchivos(Array.from(e.target.files));
            this.value = ''; // Reset para permitir re-selección
        });
    }

    const uploadArea = document.getElementById('upload-area');
    if (uploadArea) {
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);
    }

    // Limpiar errores al escribir
    ['nombre_centro','direccion','ruc','dni_titular','nombres_titular','apellidos_titular',
     'telefono','correo_contacto','codigo_verificacion'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', hideGlobalError);
    });
});

/* ═══════════════════════════════════════
   ENVÍO DE CÓDIGO DE VERIFICACIÓN
═══════════════════════════════════════ */
function enviarCodigo() {
    const correo  = document.getElementById('correo_contacto').value.trim();
    const nombres = document.getElementById('nombres_titular').value.trim();
    const errEl   = document.getElementById('err-correo');

    if (!correo || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        errEl.textContent = 'Ingrese un correo válido antes de enviar el código.';
        errEl.style.display = 'block';
        document.getElementById('correo_contacto').classList.add('error');
        return;
    }
    errEl.style.display = 'none';
    document.getElementById('correo_contacto').classList.remove('error');

    const btn = document.getElementById('btn-send-code');
    btn.disabled = true;
    btn.classList.add('loading');
    btn.textContent = '';

    fetch('../../controllers/SolicitudController.php?action=send_code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'correo=' + encodeURIComponent(correo) + '&nombres=' + encodeURIComponent(nombres || 'Solicitante')
    })
    .then(r => r.json())
    .then(data => {
        btn.classList.remove('loading');
        btn.textContent = 'Enviar código';

        if (data.success) {
            codeSent = true;
            mostrarCodigoEnviado(correo);
        } else {
            btn.disabled = false;
            errEl.textContent = data.message || 'Error al enviar el código. Intente de nuevo.';
            errEl.style.display = 'block';
        }
    })
    .catch(() => {
        btn.classList.remove('loading');
        btn.textContent = 'Enviar código';
        btn.disabled = false;
        errEl.textContent = 'Error de conexión al enviar el código.';
        errEl.style.display = 'block';
    });
}

function mostrarCodigoEnviado(correo) {
    const sentMsg  = document.getElementById('code-sent-msg');
    const sentText = document.getElementById('code-sent-text');
    const codeWrap = document.getElementById('code-input-wrap');
    const resend   = document.getElementById('resend-link');

    sentText.textContent = 'Código enviado a ' + correo + '.';
    sentMsg.classList.add('show');
    codeWrap.classList.add('show');

    // Countdown de reenvío (60s)
    resendSeconds = 60;
    resend.classList.add('disabled');
    clearInterval(resendTimer);
    resendTimer = setInterval(() => {
        resendSeconds--;
        const cdEl = document.getElementById('resend-cd');
        if (cdEl) cdEl.textContent = resendSeconds;
        if (resendSeconds <= 0) {
            clearInterval(resendTimer);
            resend.classList.remove('disabled');
            resend.innerHTML = 'Reenviar código';
        }
    }, 1000);

    // Countdown de expiración (10 min)
    expireSeconds = 600;
    clearInterval(expireTimer);
    expireTimer = setInterval(() => {
        expireSeconds--;
        const cdEl = document.getElementById('expire-cd');
        if (!cdEl) return;
        const m = Math.floor(expireSeconds / 60).toString().padStart(2,'0');
        const s = (expireSeconds % 60).toString().padStart(2,'0');
        cdEl.textContent = m + ':' + s;
        if (expireSeconds <= 0) {
            clearInterval(expireTimer);
            cdEl.parentElement.style.color = '#DC2626';
            cdEl.textContent = 'Expirado';
        }
    }, 1000);
}

function reenviarCodigo() {
    const resend = document.getElementById('resend-link');
    if (resend.classList.contains('disabled')) return;
    enviarCodigo();
}

/* ═══════════════════════════════════════
   MANEJO DE ARCHIVOS
═══════════════════════════════════════ */
function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('upload-area').classList.add('drag-over');
}
function handleDragLeave(e) {
    document.getElementById('upload-area').classList.remove('drag-over');
}
function handleDrop(e) {
    e.preventDefault();
    document.getElementById('upload-area').classList.remove('drag-over');
    agregarArchivos(Array.from(e.dataTransfer.files));
}

function agregarArchivos(files) {
    const errEl = document.getElementById('err-upload');
    errEl.style.display = 'none';

    for (const file of files) {
        if (uploadedFiles.length >= MAX_FILES) {
            errEl.textContent = 'Máximo 2 archivos permitidos.';
            errEl.style.display = 'block';
            break;
        }
        if (!ALLOWED_TYPES.includes(file.type) && !ALLOWED_EXT.test(file.name)) {
            errEl.textContent = 'Solo se permiten archivos JPG, PNG o PDF.';
            errEl.style.display = 'block';
            continue;
        }
        if (file.size > MAX_BYTES) {
            errEl.textContent = `"${file.name}" supera los ${MAX_KB} KB permitidos.`;
            errEl.style.display = 'block';
            continue;
        }
        leerComoBase64(file);
    }
}

function leerComoBase64(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        uploadedFiles.push({ name: file.name, type: file.type, b64: e.target.result });
        renderPreviews();
        actualizarHiddenInputs();
    };
    reader.readAsDataURL(file);
}

window.removerArchivo = function(idx) {
    uploadedFiles.splice(idx, 1);
    renderPreviews();
    actualizarHiddenInputs();
};

function renderPreviews() {
    const container = document.getElementById('upload-previews');
    const area      = document.getElementById('upload-area');
    container.innerHTML = '';

    uploadedFiles.forEach((f, idx) => {
        const isImg = f.type.startsWith('image/');
        const sizeKB = (f.b64.length * 0.75 / 1024).toFixed(0);
        const ext  = f.name.split('.').pop().toUpperCase();

        const item = document.createElement('div');
        item.className = 'file-preview-item';

        if (isImg) {
            item.innerHTML = `
                <img src="${f.b64}" class="file-preview-img" alt="preview">
                <div class="file-info">
                    <span class="file-name">${f.name}</span>
                    <span class="file-size">~${sizeKB} KB</span>
                </div>
                <span class="file-remove" onclick="removerArchivo(${idx})" title="Eliminar">✕</span>`;
        } else {
            item.innerHTML = `
                <span class="file-icon">📄</span>
                <div class="file-info">
                    <span class="file-name">${f.name}</span>
                    <span class="file-size">${ext} · ~${sizeKB} KB</span>
                </div>
                <span class="file-remove" onclick="removerArchivo(${idx})" title="Eliminar">✕</span>`;
        }
        container.appendChild(item);
    });

    area.classList.toggle('has-files', uploadedFiles.length > 0);
}

function actualizarHiddenInputs() {
    document.getElementById('evidencia_1_b64').value    = uploadedFiles[0]?.b64    || '';
    document.getElementById('evidencia_1_nombre').value = uploadedFiles[0]?.name   || '';
    document.getElementById('evidencia_2_b64').value    = uploadedFiles[1]?.b64    || '';
    document.getElementById('evidencia_2_nombre').value = uploadedFiles[1]?.name   || '';
}

/* ═══════════════════════════════════════
   VALIDACIÓN Y SUBMIT
═══════════════════════════════════════ */
function showGlobalError(msg) {
    const errBox = document.getElementById('client-error-box');
    const errMsg = document.getElementById('client-error-msg');
    errMsg.textContent = msg;
    errBox.style.display = 'flex';
    errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function hideGlobalError() { 
    const errBox = document.getElementById('client-error-box');
    if(errBox) errBox.style.display = 'none'; 
}

function fieldErr(id, errId, show, msg) {
    const f = document.getElementById(id);
    const e = document.getElementById(errId);
    if (!f || !e) return;
    if (show) { f.classList.add('error'); e.textContent = msg || ''; e.style.display = 'block'; }
    else       { f.classList.remove('error'); e.style.display = 'none'; }
}

function handleFormSubmit(e) {
    e.preventDefault();
    hideGlobalError();
    let valid = true;

    const nombre    = document.getElementById('nombre_centro').value.trim();
    const direccion = document.getElementById('direccion').value.trim();
    const ruc       = document.getElementById('ruc').value.trim();
    const tipo      = document.querySelector('input[name="tipo"]:checked');
    const dni       = document.getElementById('dni_titular').value.trim();
    const nombres   = document.getElementById('nombres_titular').value.trim();
    const apellidos = document.getElementById('apellidos_titular').value.trim();
    const telefono  = document.getElementById('telefono').value.trim();
    const correo    = document.getElementById('correo_contacto').value.trim();
    const codigo    = document.getElementById('codigo_verificacion').value.trim();

    // Nombre centro
    if (!nombre) { fieldErr('nombre_centro','err-nombre',true,'Campo obligatorio.'); valid=false; }
    else fieldErr('nombre_centro','err-nombre',false);

    // Dirección
    if (!direccion) { fieldErr('direccion','err-direccion',true,'Campo obligatorio.'); valid=false; }
    else fieldErr('direccion','err-direccion',false);

    // Tipo
    if (!tipo) { document.getElementById('err-tipo').style.display='block'; valid=false; }
    else document.getElementById('err-tipo').style.display='none';

    // RUC
    if (!ruc) { fieldErr('ruc','err-ruc',true,'El RUC es obligatorio.'); valid=false; }
    else if (!/^\d{11}$/.test(ruc)) { fieldErr('ruc','err-ruc',true,'El RUC debe tener exactamente 11 dígitos.'); valid=false; }
    else fieldErr('ruc','err-ruc',false);

    // DNI
    if (!dni) { fieldErr('dni_titular','err-dni',true,'El DNI es obligatorio.'); valid=false; }
    else if (!/^\d{8}$/.test(dni)) { fieldErr('dni_titular','err-dni',true,'El DNI debe tener 8 dígitos.'); valid=false; }
    else fieldErr('dni_titular','err-dni',false);

    // Nombres
    if (!nombres) { fieldErr('nombres_titular','err-nombres',true,'Campo obligatorio.'); valid=false; }
    else fieldErr('nombres_titular','err-nombres',false);

    // Apellidos
    if (!apellidos) { fieldErr('apellidos_titular','err-apellidos',true,'Campo obligatorio.'); valid=false; }
    else fieldErr('apellidos_titular','err-apellidos',false);

    // Teléfono
    if (!telefono) { fieldErr('telefono','err-telefono',true,'El teléfono es obligatorio.'); valid=false; }
    else if (!/^[\d\s\+\-\(\)]{7,15}$/.test(telefono)) { fieldErr('telefono','err-telefono',true,'Ingrese un número válido.'); valid=false; }
    else fieldErr('telefono','err-telefono',false);

    // Correo
    if (!correo) { fieldErr('correo_contacto','err-correo',true,'El correo es obligatorio.'); valid=false; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) { fieldErr('correo_contacto','err-correo',true,'Ingrese un correo válido.'); valid=false; }
    else fieldErr('correo_contacto','err-correo',false);

    // Código de verificación
    if (!codeSent) {
        document.getElementById('err-correo').textContent = 'Debe enviar y verificar el código antes de continuar.';
        document.getElementById('err-correo').style.display = 'block';
        valid = false;
    } else if (!codigo || !/^\d{6}$/.test(codigo)) {
        fieldErr('codigo_verificacion','err-codigo',true,'Ingrese el código de 6 dígitos recibido en su correo.'); valid=false;
    } else {
        fieldErr('codigo_verificacion','err-codigo',false);
    }

    // Evidencias
    const errUpload = document.getElementById('err-upload');
    if (uploadedFiles.length < 1) {
        errUpload.textContent = 'Debe adjuntar al menos 1 evidencia del establecimiento.';
        errUpload.style.display = 'block';
        valid = false;
    } else {
        errUpload.style.display = 'none';
    }

    if (!valid) {
        showGlobalError('Por favor, complete todos los campos requeridos antes de enviar.');
        return;
    }

    const submitBtn = document.getElementById('submit-btn');
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '';
    
    // Todo OK, enviar formulario real
    document.getElementById('form-solicitud').submit();
}
