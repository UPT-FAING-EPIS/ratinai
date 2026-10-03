describe('RF-11 — comparación de controles del mismo ojo', () => {
    it('compara dos controles y conserva la interpretación para el médico', () => {
        cy.visit('/views/auth/login.php');
        cy.get('#email').type('medico@hospital.com');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();
        cy.visit('/views/medico/pacientes.php');

        cy.get('#hist-dni').type('12345678');
        cy.contains('.paciente-card', '12345678').find('.paciente-header').click();
        cy.get('.paciente-detalle-wrapper').should('be.visible');
        cy.contains('.carpeta-header', 'Análisis sin carpeta').click();
        cy.get('.selector-comparacion[data-ojo="derecho"]').should('have.length.at.least', 2);
        cy.get('.selector-comparacion[data-ojo="derecho"]').eq(0).check();
        cy.get('.selector-comparacion[data-ojo="derecho"]').eq(1).check();
        cy.get('#comparar-controles').should('not.be.disabled').click();

        cy.get('#resultado-comparacion').should('be.visible');
        cy.contains('Comparación de controles del mismo ojo').should('be.visible');
        cy.contains('la interpretación del cambio corresponde al médico').should('be.visible');
    });
});
