/**
 * RetinAI — Test Cypress: Historial de Pacientes
 * RF-06: Gestionar Historial por Paciente
 */

describe('RF-06: Gestión de Historial de Pacientes', () => {

    const LOGIN_URL = '/views/auth/login.php';
    const PACIENTES_URL = '/views/medico/pacientes.php';

    beforeEach(() => {
        cy.clearCookies();
        cy.clearLocalStorage();
    });

    it('Verifica el historial completo del paciente (flujo principal)', () => {
        // 1. Login con el médico
        cy.visit(LOGIN_URL);
        cy.get('#email').type('victoraprendiendocon');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();

        // 2. Ir a la vista de pacientes
        cy.visit(PACIENTES_URL);

        // 3. Buscar al paciente por DNI
        cy.get('#hist-dni').type('76352371');
        
        // 4. Debe aparecer la tarjeta y darle clic al header para expandir
        cy.get('.paciente-card').contains('76352371').parents('.paciente-card').within(() => {
            cy.get('.paciente-header').click();
        });

        // 5. Expandir la carpeta específica "Testeos - Glaucoma"
        // Aseguramos que el contenedor asíncrono se hace visible
        cy.get('.paciente-detalle-wrapper', { timeout: 10000 }).should('be.visible');
        cy.contains('.carpeta-box', 'Testeos - Glaucoma').within(() => {
            cy.get('.carpeta-header').click();
            
            // 6. Verificar el análisis y el porcentaje ("Catarata · 93.1%")
            cy.contains('Catarata · 93.1%').should('be.visible');
            
            // 7. El botón PDF debe estar presente
            cy.contains('button', 'PDF').should('be.visible');
        });
    });
});
