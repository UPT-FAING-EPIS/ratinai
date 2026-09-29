describe('RF-08: Registro de Centro Oftalmológico y Aprobación', () => {

  const superAdminEmail = 'superadmin@ratinai.com';
  const superAdminPass = 'admin123';

  it('1. Debe mostrar validaciones en el formulario de registro si hay campos vacíos', () => {
    cy.visit('/index.php');
    
    // Navegar al registro desde la página principal
    cy.contains('Registrar centro oftalmológico').click();
    cy.url().should('include', 'views/auth/solicitud_registro.php');

    // Intentar enviar sin llenar nada
    cy.get('#submit-btn').click();

    // Debe mostrar error global y errores específicos
    cy.get('#client-error-box').should('be.visible').and('contain', 'complete todos los campos requeridos');
    cy.get('#err-nombre').should('be.visible');
    cy.get('#err-ruc').should('be.visible');
  });

  it('2. Debe permitir simular el envío de código de verificación', () => {
    cy.visit('/views/auth/solicitud_registro.php');
    
    // Llenar datos básicos del titular para el código
    cy.get('#nombres_titular').type('Juan');
    cy.get('#correo_contacto').type('test_cypress@hospital.com');

    // Interceptar la llamada AJAX para que no envíe correo real
    cy.intercept('POST', '**/SolicitudController.php?action=send_code', {
      statusCode: 200,
      body: { success: true }
    }).as('sendCode');

    // Clic en enviar código
    cy.get('#btn-send-code').click();

    // Esperar la intercepción
    cy.wait('@sendCode');

    // El input de código debe aparecer y mostrar el mensaje de éxito
    cy.get('#code-sent-msg').should('have.class', 'show');
    cy.get('#code-input-wrap').should('have.class', 'show');
    cy.get('#codigo_verificacion').should('be.visible');
  });

  it('3. Super Administrador puede acceder a Solicitudes de Registro', () => {
    // Login como Super Admin
    cy.visit('/views/auth/login.php');
    cy.get('input[name="email"]').type(superAdminEmail);
    cy.get('input[name="password"]').type(superAdminPass);
    cy.get('button[type="submit"]').click();

    // Navegar a Solicitudes
    cy.contains('Solicitudes').click();
    cy.url().should('include', 'views/superadmin/SolicitudesRegistro.php');

    // Verificar que la tabla cargue (puede estar vacía o tener datos)
    cy.get('.card').should('be.visible');
    cy.get('h1').should('contain', 'Solicitudes de Registro');
  });
});
