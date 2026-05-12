const { defineConfig } = require('cypress');

module.exports = defineConfig({
    e2e: {
        // URL base del servidor. Cambiar según el entorno:
        // Local: 'http://localhost/ratinai' o 'http://localhost:8000'
        // Azure: 'https://retinai.azurewebsites.net'
        baseUrl: 'https://retinai.azurewebsites.net',

        specPattern: 'tests/e2e/**/*.cy.js',
        supportFile: false,
        video: false,

        // Timeout generoso para Azure (puede tener cold starts)
        defaultCommandTimeout: 10000,
        pageLoadTimeout: 30000,

        // Ignorar errores de certificado en staging
        chromeWebSecurity: false,
    },
});
