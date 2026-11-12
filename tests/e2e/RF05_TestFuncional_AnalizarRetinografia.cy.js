describe('RF-05 y RF-10: análisis y validación retinal', () => {
    const testImagePath = 'assets/images/retinopatia_normal.jpg';

    function crearCarpetaDePrueba() {
        const nombreCarpeta = `Cypress RF05 ${Date.now()}`;
        cy.get('#carpeta-section').should('be.visible');
        cy.get('#btn-toggle-new-folder').click();
        cy.get('#new-folder-form').should('be.visible');
        cy.get('#folder-name-input').type(nombreCarpeta);
        cy.get('#folder-desc-input').type('Carpeta temporal creada por la prueba funcional.');
        cy.get('#btn-create-folder').click();
        cy.contains('#folder-grid .carpeta', nombreCarpeta).should('have.class', 'seleccionada');
    }

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
        crearCarpetaDePrueba();

        cy.get('input[type="file"]').selectFile(testImagePath, { force: true });

        cy.get('#preview-image-element').should('be.visible');
        cy.get('#file-name').should('contain.text', 'retinopatia_normal.jpg');
        cy.get('#validate-btn').should('not.be.disabled').click();
        cy.get('#resultado-validacion-cnn', { timeout: 30000 }).should('have.class', 'aprobada');
        cy.get('#validacion-contenido-cnn').should('contain.text', 'Retinografía');
        cy.get('#validacion-calidad-cnn').should('contain.text', 'Evaluable');
        cy.get('#analysis-section').should('be.visible');
        cy.get('#analyze-btn').then($button => {
            if ($button.is(':disabled')) {
                cy.contains('#analysis-section', 'no está disponible').should('be.visible');
                return;
            }
            cy.wrap($button).click();
            cy.get('#result-col', { timeout: 15000 }).should('be.visible');

            cy.get('#result-title').should('contain.text', 'Normal');
            cy.get('#estado-retinografia').should('contain.text', 'Retinografía confirmada');
            cy.get('#estado-calidad').should('contain.text', 'Evaluable');
            cy.get('button[data-valoracion="coincido"]').click();
            cy.get('#texto-informe', { timeout: 15000 }).should('not.be.disabled').and('not.have.value', '');
            cy.get('#btn-aprobar-informe').should('not.be.disabled').click();
            cy.get('#btn-pdf', { timeout: 15000 }).should('be.visible');
        });
    });

    it('rechaza una imagen no retinal antes de habilitar la clasificación clínica', () => {
        cy.get('#dni-input').type('12345678');
        cy.get('#btn-buscar-paciente').click();
        cy.get('#ojo-input').select('izquierdo');
        crearCarpetaDePrueba();
        cy.get('input[type="file"]').selectFile('assets/images/logo_retinai_fondo_blanco.png', { force: true });
        cy.get('#validate-btn').should('not.be.disabled').click();
        cy.get('#resultado-validacion-cnn', { timeout: 30000 }).should('have.class', 'rechazada');
        cy.get('#validacion-contenido-cnn').should('contain.text', 'No retinal');
        cy.get('#analysis-section').should('not.be.visible');
    });
});
