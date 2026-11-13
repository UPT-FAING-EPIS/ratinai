const { defineConfig } = require('cypress');

module.exports = defineConfig({
    allowCypressEnv: false,
    e2e: {
        // El entorno objetivo de las pruebas funcionales es el sitio desplegado.
        // Se puede sobrescribir puntualmente con CYPRESS_baseUrl si se requiere una ejecución local.
        baseUrl: process.env.CYPRESS_baseUrl || 'https://retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net',

        specPattern: 'tests/e2e/**/*.cy.js',
        supportFile: false,
        video: false,

        defaultCommandTimeout: 10000,
        pageLoadTimeout: 30000,

        chromeWebSecurity: false,
    },
});
