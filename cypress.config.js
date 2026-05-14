const { defineConfig } = require('cypress');

module.exports = defineConfig({
    e2e: {
        baseUrl: 'https://retinai-ehcadnergkbkd9dr.eastus2-01.azurewebsites.net',
        //baseUrl: 'http://localhost/ratinai',

        specPattern: 'tests/e2e/**/*.cy.js',
        supportFile: false,
        video: false,

        defaultCommandTimeout: 10000,
        pageLoadTimeout: 30000,

        chromeWebSecurity: false,
    },
});
