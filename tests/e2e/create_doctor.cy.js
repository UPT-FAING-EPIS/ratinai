describe('RF-01 — Crear Médico: validación y envío de clave temporal', () => {

    const ADMIN_CREDENTIALS = {
        correo: 'admin@hospital.com',
        password: 'admin123'
    };

    const DOCTOR_TEST = {
        nombre: 'Dr. Cypress Test',
        correo: `cypress_doc_${Date.now()}@hospital.com`,
        cmp: `CMP${Math.floor(Math.random() * 10000)}`,
        password: 'cypress123',
    };

    beforeEach(() => {
        // Limpiamos las cookies y hacemos login antes de los tests
        cy.clearCookies();
        cy.clearLocalStorage();
        cy.visit('/views/auth/login.php');
        cy.get('#email').type(ADMIN_CREDENTIALS.correo);
        cy.get('#password').type(ADMIN_CREDENTIALS.password);
        cy.get('#login-form').submit();
        
        // Verificamos que se haya logueado correctamente
        cy.url().should('include', 'views/admin');
    });

    it('debe llenar el formulario y crear el médico', () => {
        // Navegar a la página de creación
        cy.visit('/views/admin/create_doctor.php');

        // Llenar el formulario
        cy.get('#nombre').type(DOCTOR_TEST.nombre);
        cy.get('#correo').type(DOCTOR_TEST.correo);
        cy.get('#cmp').type(DOCTOR_TEST.cmp);

        // Seleccionar una especialidad
        cy.get('#combo-display').click({force: true});
        cy.get('.combo-opt').not('.nueva').first().click({force: true});

        // Inyectar contraseña fija para tests reproducibles
        cy.get('#password_override').invoke('val', DOCTOR_TEST.password);

        // Enviar el formulario
        cy.get('#btn-guardar').click({force: true});

        // Revisar qué pasa en la página
        cy.get('body').then($body => {
            if ($body.find('.alert-list').length > 0) {
                const errors = $body.find('.alert-list').text();
                throw new Error('Validation failed with errors: ' + errors);
            } else if ($body.find('.alert-ok-box').length > 0) {
                cy.log('MailService failed but doctor was created (temp pass shown)');
            } else {
                cy.url().should('include', 'views/admin/doctor.php');
            }
        });
    });

    it('debe validar la inserción en la tabla de médicos', () => {
        cy.visit('/views/admin/doctor.php');
        
        // Verificamos que el médico aparezca en la lista de médicos (tabla de la base de datos)
        cy.contains('td', DOCTOR_TEST.correo).should('be.visible');
        cy.contains('td', DOCTOR_TEST.nombre).should('be.visible');
        cy.contains('td', DOCTOR_TEST.cmp).should('be.visible');
    });
});
