/**
 * RetinAI — Test Jest: SessionService
 * RF-02: Expiración de sesión por inactividad (1 minuto = 60 000 ms)
 *
 * Ejecutar: npm test
 */

const SessionService = require('../../assets/js/session.service');

describe('SessionService — Expiración por inactividad', () => {

    beforeEach(() => {
        jest.useFakeTimers();
        SessionService.destroy();
    });

    afterEach(() => {
        SessionService.destroy();
        jest.useRealTimers();
        jest.clearAllMocks();
    });

    test('debe inicializarse correctamente', () => {
        const onExpire = jest.fn();
        SessionService.init({ timeout: 60000, onExpire });

        expect(SessionService.isActive()).toBe(true);
    });

    test('debe disparar onExpire tras exactamente 60 segundos de inactividad', () => {
        const onExpire = jest.fn();
        SessionService.init({ timeout: 60000, onExpire });

        // Avanzar 59 999 ms — NO debe haber expirado
        jest.advanceTimersByTime(59999);
        expect(onExpire).not.toHaveBeenCalled();

        // Avanzar 1 ms más (total 60 000 ms) — DEBE expirar
        jest.advanceTimersByTime(1);
        expect(onExpire).toHaveBeenCalledTimes(1);
    });

    test('resetTimer debe reiniciar el contador de inactividad', () => {
        const onExpire = jest.fn();
        SessionService.init({ timeout: 60000, onExpire });

        // Avanzar 50 000 ms (50 s)
        jest.advanceTimersByTime(50000);
        expect(onExpire).not.toHaveBeenCalled();

        // Simular actividad del usuario — reset del timer
        SessionService.resetTimer();

        // Avanzar otros 59 999 ms desde el reset — NO debe expirar
        jest.advanceTimersByTime(59999);
        expect(onExpire).not.toHaveBeenCalled();

        // Avanzar 1 ms más — ahora SÍ debe expirar
        jest.advanceTimersByTime(1);
        expect(onExpire).toHaveBeenCalledTimes(1);
    });

    test('destroy debe detener el timer y destruir datos de sesión', () => {
        const onExpire = jest.fn();
        SessionService.init({ timeout: 60000, onExpire });
        expect(SessionService.isActive()).toBe(true);

        // Destruir la sesión explícitamente
        SessionService.destroy();

        // Avanzar el tiempo — NO debe llamar a onExpire
        jest.advanceTimersByTime(120000);
        expect(onExpire).not.toHaveBeenCalled();
        expect(SessionService.isActive()).toBe(false);
        expect(SessionService._getTimer()).toBeNull();
    });

    test('debe usar 300 000 ms (5 min) como timeout por defecto', () => {
        const onExpire = jest.fn();
        SessionService.init({ onExpire }); // sin especificar timeout

        expect(SessionService._getTimeout()).toBe(300000);

        // A los 4 min 59 s NO debe expirar
        jest.advanceTimersByTime(299999);
        expect(onExpire).not.toHaveBeenCalled();

        // Al minuto 5 exacto SÍ debe expirar
        jest.advanceTimersByTime(1);
        expect(onExpire).toHaveBeenCalledTimes(1);
    });

    test('multiples init() no deben acumular timers', () => {
        const onExpire = jest.fn();

        SessionService.init({ timeout: 60000, onExpire });
        SessionService.destroy();
        SessionService.init({ timeout: 60000, onExpire });

        jest.advanceTimersByTime(60000);

        expect(onExpire).toHaveBeenCalledTimes(1);
    });
});
