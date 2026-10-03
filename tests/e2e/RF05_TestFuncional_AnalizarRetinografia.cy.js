describe('RF-05 y RF-10: análisis y validación retinal', () => {
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
        cy.get('#paciente-result').should('contain.text', 'Paciente');
        cy.get('#ojo-input').select('derecho');

        cy.get('input[type="file"]').selectFile(testImagePath, { force: true });

        cy.get('#preview-image-element').should('be.visible');
        cy.get('#file-name').should('contain.text', 'retinopatia_normal.jpg');

        cy.get('#analyze-btn').click();

        cy.get('#result-col', { timeout: 15000 }).should('be.visible');

        cy.get('#result-title').should('contain.text', 'Normal');
        cy.get('#result-sub').should('contain.text', 'Salida original');
        cy.get('#estado-retinografia').should('contain.text', 'confirmada');
        cy.get('#estado-calidad').should('contain.text', 'Evaluable');

        cy.get('#r_normal').invoke('text').then(text => {
            const percentage = parseFloat(text.replace('%', ''));
            expect(percentage).to.be.gt(50);
        });
    });

    it('rechaza una imagen que no cumple las condiciones sin mostrar salida clínica', () => {
        cy.get('#dni-input').type('12345678');
        cy.get('#btn-buscar-paciente').click();
        cy.get('#ojo-input').select('izquierdo');
        cy.get('input[type="file"]').selectFile('assets/images/logo_retinai_fondo_blanco.png', { force: true });
        cy.get('#analyze-btn').click();

        cy.get('#result-col', { timeout: 15000 }).should('be.visible');
        cy.get('#result-title').should('contain.text', 'Imagen rechazada');
        cy.get('#probabilidades-clinicas').should('not.be.visible');
        cy.get('#informe-section').should('not.be.visible');
    });
});
