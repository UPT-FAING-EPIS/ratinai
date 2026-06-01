/* ═══════════════════════════════════════════════
   RetinAI — establecimientos.js
   Lógica para la vista de Establecimientos Activos
═══════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar sesión (la URL base debe ser provista globalmente o asumida relativa si no)
    // Aquí asumimos que SessionService ya fue incluido en la página.
    if (typeof SessionService !== 'undefined') {
        // En una app real, loginUrl se puede pasar por un data-attribute.
        // Aquí lo dejamos inicializado si SessionService.init ya se hizo o se hace con defaults
    }

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
});
