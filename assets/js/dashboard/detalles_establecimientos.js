document.addEventListener('DOMContentLoaded', () => {
    const flashOk = document.getElementById('flash-msg');
    const flashErr = document.getElementById('flash-msg-err');
    
    [flashOk, flashErr].forEach(flash => {
        if (flash) {
            setTimeout(() => {
                flash.style.opacity = '0';
                flash.style.transition = 'opacity 0.4s ease';
                setTimeout(() => flash.remove(), 400);
            }, 3000);
        }
    });

    const form = document.getElementById('form-est');
    if (form) {
        form.addEventListener('submit', (e) => {
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Guardando...';
        });
    }
});
