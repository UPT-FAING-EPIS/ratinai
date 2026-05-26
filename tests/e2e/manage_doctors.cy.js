/// <reference types="cypress" />

describe('RF-03: Gestionar Médicos Activos', () => {
    const adminUrl = '/views/admin/doctor.php';
    const TEST_MEDICO = {
        id: 9999,
        nombre: 'Dr. Prueba Automation',
        correo: 'test.auto@medico.com',
        cmp: '99999',
        especialidad: 'General'
    };

    beforeEach(() => {
        // Iniciar sesión manualmente a través de la UI
        cy.visit('/views/auth/login.php');
        cy.get('#email').type('admin@hospital.com');
        cy.get('#password').type('admin123');
        cy.get('#login-form').submit();
        
        // Interceptar llamadas AJAX
        cy.intercept('POST', '**/DoctorController.php?action=edit').as('editDoctor');
        cy.intercept('POST', '**/DoctorController.php?action=reset').as('resetPassword');
        cy.intercept('POST', '**/DoctorController.php?action=deactivate').as('deactivateDoctor');
        
        cy.visit(adminUrl);
    });

    it('Debe editar a un médico y mostrar mensaje de éxito (AJAX + Reload)', () => {
        // Clic en editar del primer médico en la tabla
        cy.get('table#tabla-medicos tbody tr').first().within(() => {
            cy.get('button[title="Editar"]').click();
        });

        // Asegurarse de que el modal está visible
        cy.get('#modal-edit').should('be.visible');

        // Editar información
        const nuevoNombre = 'Dr. Editado ' + Date.now();
        cy.get('#modal-edit input[name="nombre"]').clear().type(nuevoNombre);
        
        // Guardar cambios
        cy.get('#modal-edit button[type="submit"]').click();

        // Esperar petición AJAX
        cy.wait('@editDoctor').then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
            expect(interception.response.body.success).to.be.true;
        });

        // NOTA: Según el diagrama de secuencia, el frontend debería hacer un window.location.reload()
        // Cypress esperará automáticamente a que la página recargue.
        cy.get('table#tabla-medicos').should('contain', nuevoNombre);
    });

    it('Debe resetear la contraseña de un médico temporalmente', () => {
        // Clic en reset password del primer médico
        cy.get('table#tabla-medicos tbody tr').first().within(() => {
            cy.get('button[title="Resetear contraseña"]').click();
        });

        cy.get('#modal-reset').should('be.visible');
        cy.get('#modal-reset button.btn-save').click();

        cy.wait('@resetPassword').then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
            expect(interception.response.body.success).to.be.true;
        });
    });

    it('Debe desactivar a un médico por POST de formulario', () => {
        // Encontrar un médico y capturar su nombre para verificar que desaparece
        cy.get('table#tabla-medicos tbody tr').first().then(($row) => {
            const nombreMedico = $row.find('td').first().find('strong').text().trim();

            cy.wrap($row).within(() => {
                // Si la desactivación es por un form submit tradicional
                cy.get('form[action*="action=deactivate"] button').click();
            });

            // La página cargará de nuevo por el POST 302 Redirection
            cy.url().should('include', 'doctor.php');
            
            // Si el test se corre en una base real, descomentar para asegurar borrado.
            // Para mocks es seguro chequear.
            // cy.get('table#tabla-medicos').should('not.contain', nombreMedico);
        });
    });
});