/**
 * RetinAI — SessionService
 * RF-02: Iniciar Sesión — Expiración por inactividad (60 min)
 *
 * Uso:
 *   <script src="/assets/js/session.service.js"></script>
 *   <script>
 *     SessionService.init({ timeout: 3600000, loginUrl: '/views/auth/login.php' });
 *   </script>
 *
 * Testeable con Jest fake timers:
 *   jest.useFakeTimers();
 *   SessionService.init({ timeout: 60000, loginUrl: '/login' });
 *   jest.advanceTimersByTime(60001);
 *   // → verifica que destroy() fue llamado
 */

const SessionService = (() => {
    // Estado privado
    let _timer       = null;
    let _timeout     = 3600000; // 60 minutos en ms (default)
    let _loginUrl    = '/views/auth/login.php';
    let _onExpire    = null;    // callback opcional (útil para tests)
    let _initialized = false;

    // Eventos que reinician el contador de inactividad
    const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

    /**
     * Cancela el timer actual y lo vuelve a lanzar.
     */
    function resetTimer() {
        if (_timer !== null) {
            clearTimeout(_timer);
        }
        _timer = setTimeout(_handleExpiration, _timeout);
    }

    /**
     * Se ejecuta cuando el tiempo de inactividad se agota.
     * Destruye la sesión en el cliente y redirige al login.
     */
    function _handleExpiration() {
        destroy();
        if (typeof _onExpire === 'function') {
            _onExpire();
        } else {
            // Redirigir con parámetro de sesión expirada
            if (typeof window !== 'undefined') {
                window.location.href = _loginUrl + '?expired=1';
            }
        }
    }

    /**
     * Elimina los datos de sesión del cliente (sessionStorage / localStorage).
     * El servidor invalida la sesión PHP en la próxima request.
     */
    function destroy() {
        if (_timer !== null) {
            clearTimeout(_timer);
            _timer = null;
        }
        // Limpiar storage del lado cliente
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.clear();
        }
        if (typeof localStorage !== 'undefined') {
            // Sólo limpiar claves propias de RetinAI para no afectar otros datos
            const retinaiKeys = Object.keys(localStorage).filter(k => k.startsWith('retinai_'));
            retinaiKeys.forEach(k => localStorage.removeItem(k));
        }
        _initialized = false;
    }

    /**
     * Inicializa el servicio de sesión.
     * @param {Object} options
     * @param {number}   [options.timeout=3600000]  - Timeout en ms (default 60 min)
     * @param {string}   [options.loginUrl]          - URL de login para redirección
     * @param {Function} [options.onExpire]          - Callback al expirar (útil en tests)
     */
    function init(options = {}) {
        _timeout    = options.timeout  ?? 3600000;
        _loginUrl   = options.loginUrl ?? '/views/auth/login.php';
        _onExpire   = options.onExpire ?? null;
        _initialized = true;

        // Registrar listeners de actividad (sólo en el browser)
        if (typeof window !== 'undefined') {
            ACTIVITY_EVENTS.forEach(event => {
                window.addEventListener(event, resetTimer, { passive: true });
            });
        }

        // Arrancar el timer
        resetTimer();
    }

    /**
     * Devuelve true si el servicio está activo.
     */
    function isActive() {
        return _initialized && _timer !== null;
    }

    // API pública
    return {
        init,
        resetTimer,
        destroy,
        isActive,
        // Exponer para tests de introspección
        _getTimer:   () => _timer,
        _getTimeout: () => _timeout,
    };
})();

// Exportar para Node.js / Jest
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SessionService;
}
