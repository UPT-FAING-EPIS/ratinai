describe('RF-12 — administración del almacenamiento institucional', () => {
    it('permite al administrador probar la conexión y revisar sincronizaciones', () => {
        cy.visit('/views/auth/login.php');
        cy.get('#email').type('admin@hospital.com');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();

        cy.visit('/views/admin/integraciones.php');
        cy.contains('h1', 'Almacenamiento institucional').should('be.visible');
        cy.get('#probar-conexion').click();
        cy.get('#toast').should('be.visible').and('contain', 'disponible');
        cy.contains('Sincronizaciones de informes').should('be.visible');
        cy.get('body').then(($cuerpo) => {
            if ($cuerpo.find('.reintentar').length > 0) {
                cy.intercept('POST', '**/IntegracionController.php?action=reintentar').as('reintento');
                cy.get('.reintentar').first().click();
                cy.wait('@reintento').its('response.body.success').should('equal', true);
                cy.reload();
                cy.contains('td', 'completada').should('be.visible');
            }
        });
    });

    it('presenta al superadministrador datos agregados sin datos clínicos identificables', () => {
        cy.clearCookies();
        cy.visit('/views/auth/login.php');
        cy.get('#email').type('superadmin@ratinai.com');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();

        cy.visit('/views/superadmin/calidad_modelo.php');
        cy.contains('h1', 'Calidad del modelo').should('be.visible');
        cy.contains('Evaluados').should('be.visible');
        cy.contains('Sin evaluar').should('exist');
        cy.contains('Estado agregado de almacenamiento').should('be.visible');
        cy.get('body').should('not.contain', 'DNI');
    });
});
