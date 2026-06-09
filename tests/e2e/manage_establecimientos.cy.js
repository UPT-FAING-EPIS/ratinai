/// <reference types="cypress" />

/**
 * RF-04: Pruebas End-to-End — Gestión de Centro Oftalmológico (Edición)
 *
 * Valida que el Super Administrador pueda:
 *  - Acceder a los detalles de un establecimiento
 *  - Editar correctamente los datos del establecimiento
 *  - Recibir feedback visual tras guardar cambios
 *  - Visualizar los titulares y médicos del centro
 */
describe('RF-04: Gestión de Centro Oftalmológico (Edición)', () => {
    const SAD_CREDENTIALS = {
        email   : 'superadmin@retinai.com',
        password: 'admin123'
    };

    const BASE_URL = '/views/superadmin';

    // ─────────────────────────────────────────────────────────────────────────
    // Preparación: login como Super Administrador antes de cada prueba
    // ─────────────────────────────────────────────────────────────────────────
    beforeEach(() => {
        cy.visit('/views/auth/login.php');
        cy.get('#email').type(SAD_CREDENTIALS.email);
        cy.get('#password').type(SAD_CREDENTIALS.password);
        cy.get('#login-form').submit();

        // Intercept al controlador de establecimientos
        cy.intercept('POST', '**/EstablecimientoController.php?action=update').as('updateEst');

        // Navegar a la lista de establecimientos
        cy.visit(`${BASE_URL}/Establecimientos.php`);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1: Navegar desde la lista al detalle de un establecimiento
    // ─────────────────────────────────────────────────────────────────────────
    it('Debe navegar al detalle del primer establecimiento al hacer clic en Ver/Editar', () => {
        // La tabla de establecimientos debe existir
        cy.get('table.data-table tbody tr').should('have.length.at.least', 1);

        // Clic en el primer botón "Ver/Editar"
        cy.get('table.data-table tbody tr').first().within(() => {
            cy.get('a.btn-ver-editar, a[href*="detalles_establecimientos"]').click();
        });

        // La URL debe incluir el parámetro id y llegar a la vista de detalles
        cy.url().should('include', 'detalles_establecimientos.php');
        cy.url().should('include', 'id=');

        // La página debe mostrar el formulario de edición
        cy.get('form#form-est').should('exist');
        cy.get('input[name="nombre"]').should('exist');
        cy.get('h1.page-title').should('contain', 'Detalles del Establecimiento');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2: Editar los datos del establecimiento y verificar mensaje de éxito
    // ─────────────────────────────────────────────────────────────────────────
    it('Debe guardar cambios del establecimiento y mostrar alerta de éxito', () => {
        // Ir directo al primer establecimiento (id=1)
        cy.visit(`${BASE_URL}/detalles_establecimientos.php?id=1`);

        cy.get('form#form-est').should('be.visible');

        // Modificar el nombre con un timestamp para que sea único
        const nuevoNombre = 'Centro Editado ' + Date.now();
        cy.get('input[name="nombre"]').clear().type(nuevoNombre);

        // Modificar la dirección
        cy.get('input[name="direccion"]').clear().type('Jr. Las Rosas 999, Tacna');

        // Seleccionar tipo
        cy.get('select[name="tipo"]').select('privado');

        // Guardar
        cy.get('form#form-est button[type="submit"]').click();

        // Esperar a que el controlador procese y redirija
        cy.wait('@updateEst').then((interception) => {
            // El controlador redirige con HTTP 302, Cypress seguirá la redirección
            expect(interception.response.statusCode).to.be.oneOf([200, 302]);
        });

        // Verificar alerta verde de éxito en la vista recargada
        cy.get('.alert-flash.alert-ok', { timeout: 6000 })
            .should('be.visible')
            .and('contain', 'actualizado correctamente');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3: Validación cliente — el nombre no puede estar vacío
    // ─────────────────────────────────────────────────────────────────────────
    it('Debe mostrar validación HTML5 si el nombre está vacío', () => {
        cy.visit(`${BASE_URL}/detalles_establecimientos.php?id=1`);

        cy.get('form#form-est').should('be.visible');

        // Borrar el nombre (campo required)
        cy.get('input[name="nombre"]').clear();

        // Intentar guardar
        cy.get('form#form-est button[type="submit"]').click();

        // La validación nativa HTML5 (required) debe bloquear el submit
        // El campo debe tener el estado inválido
        cy.get('input[name="nombre"]').then(($input) => {
            expect($input[0].validity.valid).to.be.false;
        });

        // El controlador NO debe haber recibido ningún POST (el form fue bloqueado)
        // Verificar que seguimos en la misma página
        cy.url().should('include', 'detalles_establecimientos.php');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4: La vista muestra el titular/admin del establecimiento
    // ─────────────────────────────────────────────────────────────────────────
    it('Debe mostrar la sección de Titular / Administrador del establecimiento', () => {
        cy.visit(`${BASE_URL}/detalles_establecimientos.php?id=1`);

        // La sección de Titular/Admin debe existir
        cy.get('.card.list-card').first().within(() => {
            cy.get('h3.card-title').should('contain', 'Titular');

            // Debe mostrar datos del admin O el mensaje vacío
            cy.get('.user-list .user-item, .empty-msg').should('exist');
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Test 5: El botón "Volver" regresa a la lista de establecimientos
    // ─────────────────────────────────────────────────────────────────────────
    it('El botón Volver debe redirigir a la lista de establecimientos', () => {
        cy.visit(`${BASE_URL}/detalles_establecimientos.php?id=1`);

        cy.get('a[href*="Establecimientos.php"]').click();

        cy.url().should('include', 'Establecimientos.php');
    });
});
