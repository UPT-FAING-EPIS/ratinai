const { defineConfig } = require('cypress');

module.exports = defineConfig({
    allowCypressEnv: false,
    e2e: {
        baseUrl: 'http://ratinai.local',

        specPattern: 'tests/e2e/**/*.cy.js',
        supportFile: false,
        video: false,

        defaultCommandTimeout: 10000,
        pageLoadTimeout: 30000,

        chromeWebSecurity: false,
    },
});
