/**
 * RetinAI — Test Cypress: AuthController.login()
 * RF-02: Redirección obligatoria a change_password.php si es_password_temporal = 1
 *
 * Prerequisito: Médico de prueba en BD:
 *   correo: medico@hospital.com
 *   password: admin123
 *   es_password_temporal: 1
 *
 * Ejecutar: npx cypress run --spec "tests/e2e/login.cy.js"
 * O en modo interactivo: npx cypress open
 */

describe('RF-02 — Login: redirección por clave temporal', () => {

    const LOGIN_URL = '/views/auth/login.php';
    const MEDICO_TEMPORAL = {
        correo: 'test01@hospital.com',
        password: 'admin123',
    };

    beforeEach(() => {
        // Limpiar cookies y sesión antes de cada test
        cy.clearCookies();
        cy.clearLocalStorage();
    });

    // ── Test 1: Carga correcta del formulario ──────────────────────────────
    it('debe cargar la página de login sin errores', () => {
        cy.visit(LOGIN_URL);
        cy.get('#login-form').should('be.visible');
        cy.get('#email').should('be.visible');
        cy.get('#password').should('be.visible');
        cy.get('#submit-btn').should('be.visible');
        cy.title().should('contain', 'RetinAI');
    });

    // ── Test 2: Campos vacíos → error cliente ──────────────────────────────
    it('debe mostrar error al enviar campos vacíos', () => {
        cy.visit(LOGIN_URL);
        cy.get('#submit-btn').click();
        cy.get('#login-error-box').should('be.visible');
    });

    // ── Test 3: Credenciales incorrectas → error servidor ─────────────────
    it('debe mostrar "Credenciales incorrectas" con password incorrecto', () => {
        cy.visit(LOGIN_URL);
        cy.get('#email').type('test01@hospital.com');
        cy.get('#password').type('wrongpassword');
        cy.get('#login-form').submit();
        cy.get('.alert-danger').should('be.visible');
        cy.get('.alert-danger p').should('contain', 'Credenciales incorrectas');
    });

    // ── Test 4 (PRINCIPAL): Login con clave temporal → redirige a change_password.php ──
    it('debe redirigir a change_password.php cuando es_password_temporal es positivo', () => {
        cy.visit(LOGIN_URL);

        // Ingresar credenciales del médico con clave temporal
        cy.get('#email').type(MEDICO_TEMPORAL.correo);
        cy.get('#password').type(MEDICO_TEMPORAL.password);

        // Enviar formulario
        cy.get('#login-form').submit();

        // ASERCIÓN PRINCIPAL: la URL debe contener change_password.php
        cy.url().should('include', 'change_password.php');

        // El formulario de cambio de contraseña debe estar visible
        cy.get('#cp-form').should('be.visible');
        cy.get('#nueva_password').should('be.visible');
        cy.get('#confirma_password').should('be.visible');
    });

    // ── Test 5: Login normal (sin clave temporal) → redirige al dashboard ──
    it('debe redirigir al dashboard del médico tras login con clave permanente', () => {
        // Primero crear una sesión con clave permanente via setup fixture
        // (Este test asume que existe un médico con activo=1 y es_password_temporal=0)
        cy.visit(LOGIN_URL);
        cy.get('#email').type('admin@hospital.com');  // ADM con clave permanente
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();

        // Debe redirigir al dashboard admin (no a change_password)
        cy.url().should('not.include', 'change_password.php');
        cy.url().should('include', 'dashboard');
    });
});
