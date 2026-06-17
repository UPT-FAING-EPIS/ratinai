/**
 * RetinAI — Test Cypress: AuthController.login()
 * RF-02: Redirección obligatoria a change_password.php si es_password_temporal = 1
 *
 * Prerequisito: Médico de prueba en BD:
 *   correo: test01@hospital.com
 *   password: admin123
 *   es_password_temporal: 1
 */

describe('RF-02 — Login: redirección por clave temporal', () => {

    const LOGIN_URL = '/views/auth/login.php';
    const MEDICO_TEMPORAL = {
        correo: 'cypress_doc_1780367862081@hospital.com',
        password: 'cypress123',
    };

    beforeEach(() => {
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
        cy.get('#password').type('aaaaaa');
        cy.get('#login-form').submit();
        cy.get('.alert-danger').should('be.visible');
        cy.get('.alert-danger p').should('contain', 'Credenciales incorrectas');
    });

    // ── Test 4 (PRINCIPAL): Login con clave temporal → redirige a change_password.php ──
    it('debe redirigir a change_password.php cuando es_password_temporal es positivo', () => {
        // --- 1. Crear un médico temporal primero (usando la sesión de admin) ---
        const tempEmail = `temp_login_${Date.now()}@hospital.com`;
        const tempPass = 'cypress123';

        cy.visit(LOGIN_URL);
        cy.get('#email').type('admin@hospital.com');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();

        cy.visit('/views/admin/create_doctor.php');
        cy.get('#nombre').type('Doctor Temporal Login');
        cy.get('#correo').type(tempEmail);
        cy.get('#cmp').type(`CMP${Math.floor(Math.random() * 10000)}`);
        
        cy.get('#combo-display').click({force: true});
        cy.get('.combo-opt').not('.nueva').first().click({force: true});
        
        cy.get('body').then($body => {
            if ($body.find('#establecimiento_id').length > 0) {
                cy.get('#establecimiento_id').select(1, {force: true});
            }
        });

        cy.get('#password_override').invoke('val', tempPass);
        cy.get('#btn-guardar').click({force: true});

        // Hacer logout
        cy.visit('/controllers/AuthController.php?action=logout');
        cy.clearCookies();
        cy.clearLocalStorage();

        // --- 2. Hacer login con el nuevo médico temporal ---
        cy.visit(LOGIN_URL);
        cy.get('#email').type(tempEmail);
        cy.get('#password').type(tempPass);
        cy.get('#login-form').submit();

        cy.url().should('include', 'change_password.php');

        cy.get('#cp-form').should('be.visible');
        cy.get('#nueva_password').should('be.visible');
        cy.get('#confirma_password').should('be.visible');
    });

    // ── Test 5: Login normal (sin clave temporal) → redirige al dashboard ──
    it('debe redirigir al panel de administración tras login con clave permanente', () => {
        cy.visit(LOGIN_URL);
        cy.get('#email').type('admin@hospital.com');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();

        cy.url().should('not.include', 'change_password.php');
        cy.url().should('include', 'views/admin');
    });
});
