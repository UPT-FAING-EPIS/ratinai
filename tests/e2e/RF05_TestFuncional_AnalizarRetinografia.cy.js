describe('Feature: Retinal Analysis', () => {
    const testImagePath = 'assets/images/retinopatia_normal.jpg';

    beforeEach(() => {
        cy.visit('views/auth/login.php');
        cy.get('#email').type('medico@hospital.com');
        cy.get('#password').type('admin123');
        cy.get('button[type="submit"]').click();
        cy.url().should('include', '/views/medico/dashboard.php');
        cy.visit('views/medico/nuevoanalisis.php');
    });

    it('should allow a doctor to perform a retinal analysis and get a "normal" result', () => {
        cy.get('#dni-input').type('12345678');
        cy.get('#btn-buscar-paciente').click();
        cy.get('#paciente-result').should('be.visible');

        cy.get('input[type="file"]').selectFile(testImagePath, { force: true });

        cy.get('#preview-area').should('be.visible');
        cy.get('#file-name').should('contain.text', 'retinopatia_normal.jpg');

        cy.get('#analyze-btn').click();

        cy.get('#result-col', { timeout: 10000 }).should('be.visible');

        cy.get('#result-title').should('contain.text', 'Normal');
        cy.get('#result-sub').should('contain.text', 'No se detectaron anomalías significativas');

        cy.get('#r_normal').should('not.contain.text', '0%');
        cy.get('#r_normal').invoke('text').then(text => {
            const percentage = parseFloat(text.replace('%', ''));
            expect(percentage).to.be.gt(50);
        });
    });
});
