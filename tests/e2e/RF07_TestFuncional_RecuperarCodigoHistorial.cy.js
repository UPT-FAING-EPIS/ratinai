/**
 * RF-07: Recuperación del código de historial por DNI.
 * Requiere un médico y un paciente de prueba con análisis registrados.
 */
describe('RF-07: Recuperar código de historial', () => {
    const dniExistente = '76352371';

    beforeEach(() => {
        cy.visit('/views/auth/login.php');
        cy.get('#email').type('victoraprendiendocon@gmail.com');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();
        cy.visit('/views/medico/pacientes.php');
    });

    it('muestra el código de historial para un DNI registrado', () => {
        cy.get('#hist-dni').type(dniExistente);
        cy.contains('button', 'Buscar').click();

        cy.get('#history-search-note')
            .should('be.visible')
            .and('contain', 'Paciente encontrado. Código de historial:');
        cy.get('#history-search-note .history-code').should('not.be.empty');
    });

    it('muestra un mensaje claro cuando el DNI no existe', () => {
        cy.get('#hist-dni').type('00000000');
        cy.contains('button', 'Buscar').click();

        cy.get('#history-search-note')
            .should('be.visible')
            .and('contain', 'No se encontraron pacientes que coincidan con la búsqueda.');
    });
});
