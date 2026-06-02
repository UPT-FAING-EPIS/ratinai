// Logica de validación y evidencias
const form = document.getElementById('form-solicitud');
const rucInput = document.getElementById('ruc');
const dniInput = document.getElementById('dni_titular');
const fileInput = document.getElementById('file-input');
const uploadArea = document.getElementById('upload-area');
const uploadPreviews = document.getElementById('upload-previews');
const errUpload = document.getElementById('err-upload');

let selectedFiles = [];
const MAX_FILES = 2;
const MAX_SIZE = 200 * 1024; // 200KB

form.addEventListener('submit', function(e) {
    e.preventDefault();
    let isValid = true;
    const rucVal = rucInput.value.trim();
    const dniVal = dniInput.value.trim();

    if(!/^\d{11}$/.test(rucVal)) { document.getElementById('err-ruc').textContent='RUC inválido (11 dígitos).'; document.getElementById('err-ruc').style.display='block'; isValid=false; } else { document.getElementById('err-ruc').style.display='none'; }
    if(!/^\d{8}$/.test(dniVal)) { document.getElementById('err-dni').textContent='DNI inválido (8 dígitos).'; document.getElementById('err-dni').style.display='block'; isValid=false; } else { document.getElementById('err-dni').style.display='none'; }
    
    if (selectedFiles.length === 0) {
        errUpload.textContent = 'Debe adjuntar al menos 1 archivo.';
        errUpload.style.display = 'block';
        isValid = false;
    } else {
        errUpload.style.display = 'none';
    }

    if (!document.querySelector('input[name="tipo"]:checked')) {
        document.getElementById('err-tipo').style.display = 'block';
        isValid = false;
    } else {
        document.getElementById('err-tipo').style.display = 'none';
    }

    if(!isValid) {
        return;
    }

    if (selectedFiles[0]) {
        document.getElementById('evidencia_1_b64').value = selectedFiles[0].base64;
        document.getElementById('evidencia_1_nombre').value = selectedFiles[0].file.name;
    }
    if (selectedFiles[1]) {
        document.getElementById('evidencia_2_b64').value = selectedFiles[1].base64;
        document.getElementById('evidencia_2_nombre').value = selectedFiles[1].file.name;
    }

    const submitBtn = document.getElementById('submit-btn');
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '';
    
    document.getElementById('form-solicitud').submit();
});

uploadArea.addEventListener('click', () => fileInput.click());

function handleDragOver(e) { e.preventDefault(); uploadArea.classList.add('dragover'); }
function handleDragLeave(e) { e.preventDefault(); uploadArea.classList.remove('dragover'); }
function handleDrop(e) {
    e.preventDefault(); uploadArea.classList.remove('dragover');
    processFiles(e.dataTransfer.files);
}
fileInput.addEventListener('change', (e) => processFiles(e.target.files));

function processFiles(files) {
    errUpload.style.display = 'none';
    for (let i = 0; i < files.length; i++) {
        if (selectedFiles.length >= MAX_FILES) {
            errUpload.textContent = `Máximo ${MAX_FILES} archivos permitidos.`;
            errUpload.style.display = 'block';
            break;
        }
        let file = files[i];
        if (file.size > MAX_SIZE) {
            errUpload.textContent = `El archivo ${file.name} supera los 200 KB.`;
            errUpload.style.display = 'block';
            continue;
        }
        let reader = new FileReader();
        reader.onload = function(e) {
            selectedFiles.push({ file: file, base64: e.target.result });
            renderPreviews();
        };
        reader.readAsDataURL(file);
    }
    fileInput.value = '';
}

function renderPreviews() {
    uploadPreviews.innerHTML = '';
    selectedFiles.forEach((f, idx) => {
        let div = document.createElement('div');
        div.className = 'upload-item';
        let ext = f.file.name.split('.').pop().toUpperCase();
        let icon = ext === 'PDF' ? '📄' : '🖼️';
        div.innerHTML = `
            <div class="upload-item-icon">${icon}</div>
            <div class="upload-item-info">
                <span class="upload-item-name">${f.file.name}</span>
                <span class="upload-item-size">${(f.file.size / 1024).toFixed(1)} KB</span>
            </div>
            <button type="button" class="upload-item-remove" onclick="removeFile(${idx})">✖</button>
        `;
        uploadPreviews.appendChild(div);
    });
}

function removeFile(idx) {
    selectedFiles.splice(idx, 1);
    renderPreviews();
}
