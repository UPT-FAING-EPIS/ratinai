describe('RF-09 y RF-13: informe clínico y valoración opcional', () => {
    beforeEach(() => {
        cy.visit('views/auth/login.php');
        cy.get('#email').type('medico@hospital.com');
        cy.get('#password').type('admin123');
        cy.get('button[type="submit"]').click();
        cy.url().should('include', '/views/medico/dashboard.php');
        cy.visit('views/medico/nuevoanalisis.php');
    });

    it('permite aprobar el informe sin responder la valoración opcional', () => {
        cy.get('#dni-input').type('12345678');
        cy.get('#btn-buscar-paciente').click();
        cy.get('#paciente-result').should('contain.text', 'Paciente');
        cy.get('#ojo-input').select('derecho');
        cy.get('#file-input').selectFile('assets/images/retinopatia_normal.jpg', { force: true });
        cy.get('#analyze-btn').click();
        cy.get('#texto-informe', { timeout: 15000 }).should('be.visible').and('not.have.value', '');
        cy.get('#estado-valoracion').should('contain.text', 'Puede aprobar');
        cy.get('#texto-informe').type('\nControl funcional local verificado por Cypress.');
        cy.get('#btn-aprobar-informe').click();
        cy.get('#btn-pdf', { timeout: 15000 }).should('be.visible');
        cy.get('#estado-borrador').should('contain.text', 'Informe aprobado');
    });
});
