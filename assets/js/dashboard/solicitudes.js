/* ═══════════════════════════════════════════════
   RetinAI — solicitudes.js
   Lógica para la vista de Solicitudes de Registro
═══════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

    // Countdown de sesión
    let remaining = 300;
    const cd = document.getElementById('session-countdown');
    if (cd) {
        setInterval(() => { 
            const m = Math.floor(remaining / 60).toString().padStart(2, '0'); 
            const s = (remaining % 60).toString().padStart(2, '0'); 
            cd.textContent = m + ':' + s; 
            if (remaining > 0) remaining--; 
        }, 1000);
    }

    // Modal de Previsualización
    const modal = document.getElementById('preview-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const btnClose = document.getElementById('btn-close-modal');

    // Cerrar modal
    const closeModal = () => {
        if (!modal) return;
        modal.classList.remove('active');
        modalBody.innerHTML = ''; // Limpiar contenido para detener videos/PDFs
    };

    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', (e) => {
            // Cerrar si se hace clic fuera del contenido
            if (e.target === modal) closeModal();
        });
    }

    // Abrir modal con preview
    const previewBtns = document.querySelectorAll('.btn-preview');
    previewBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const src = btn.getAttribute('data-src');
            const name = btn.getAttribute('data-name');
            
            if (!src) return;

            modalTitle.textContent = name || 'Previsualización de Evidencia';
            modalBody.innerHTML = ''; // Limpiar anterior

            // Detectar si es PDF o imagen basado en el inicio de la cadena base64
            if (src.startsWith('data:application/pdf')) {
                // Es un PDF
                const iframe = document.createElement('iframe');
                iframe.src = src;
                iframe.style.width = '100%';
                iframe.style.height = '70vh';
                iframe.style.border = 'none';
                modalBody.appendChild(iframe);
            } else if (src.startsWith('data:image/')) {
                // Es una imagen
                const img = document.createElement('img');
                img.src = src;
                img.alt = name;
                modalBody.appendChild(img);
            } else {
                // Otro tipo, usar un object general
                const obj = document.createElement('object');
                obj.data = src;
                obj.style.width = '100%';
                obj.style.height = '70vh';
                modalBody.appendChild(obj);
            }

            modal.classList.add('active');
        });
    });

});
