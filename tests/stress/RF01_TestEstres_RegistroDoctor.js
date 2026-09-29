import http from 'k6/http';
import { check, fail } from 'k6';

// Úselo exclusivamente con MariaDB y cuentas de PRUEBA: cada iteración crea un médico.
// Ejemplo de ejecución en PowerShell:
// $env:BASE_URL='http://ratinai.local'; $env:ADMIN_EMAIL='...';
// $env:ADMIN_PASSWORD='...'; $env:ESTABLISHMENT_ID='1'; k6 run tests/stress/RF01_TestEstres_RegistroDoctor.js
const baseUrl = __ENV.BASE_URL;
const adminEmail = __ENV.ADMIN_EMAIL;
const adminPassword = __ENV.ADMIN_PASSWORD;
const establishmentId = __ENV.ESTABLISHMENT_ID;

export const options = {
    stages: [
        { duration: '15s', target: 2 },
        { duration: '30s', target: 5 },
        { duration: '15s', target: 0 }
    ],
    thresholds: {
        http_req_failed: ['rate<0.02'],
        http_req_duration: ['p(95)<5000'],
        checks: ['rate>0.98']
    }
};

export default function () {
    if (!baseUrl || !adminEmail || !adminPassword || !establishmentId) {
        fail('Defina BASE_URL, ADMIN_EMAIL, ADMIN_PASSWORD y ESTABLISHMENT_ID antes de ejecutar esta prueba.');
    }

    const login = http.post(
        `${baseUrl}/controllers/AuthController.php?action=login`,
        { email: adminEmail, password: adminPassword },
        { redirects: 0 }
    );

    check(login, {
        'RF01: administrador autenticado': (response) => response.status === 302
    });

    const uniqueId = `${__VU}-${__ITER}-${Date.now()}`;
    const registro = http.post(
        `${baseUrl}/controllers/DoctorController.php?action=create`,
        {
            nombre: `Médico estrés ${uniqueId}`,
            correo: `rf01.stress.${uniqueId}@example.test`,
            cmp: `CMP${Date.now()}${__VU}`,
            especialidad: 'Oftalmología',
            establecimiento_id: establishmentId,
            password_override: 'PruebaRF01!2026'
        },
        { redirects: 0 }
    );

    check(registro, {
        'RF01: registro responde con redirección': (response) => response.status === 302,
        'RF01: registro no devuelve error de validación': (response) => !response.body.includes('obligatorio')
    });
}
