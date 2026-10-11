import { defineConfig } from 'cypress'

// Cypress config. The repo is ESM ("type": "module" in package.json), so this
// file uses `import` / `export default`. Run the tests with:
//   npm run cypress:open   → interactive Test Runner (best for learning)
//   npm run cypress:run    → headless
export default defineConfig({
  e2e: {
    // The app is served by Laravel Herd at this custom local domain.
    // Every cy.visit('/path') is resolved relative to this baseUrl.
    baseUrl: 'http://127.0.0.1:8888',

    // Herd serves real, changing dev content, so a spec can occasionally race
    // the page. Retry a failed test in headless runs; never retry in the
    // interactive runner, where you want to watch the failure happen.
    retries: { runMode: 2, openMode: 0 },

    // Where Cypress looks for spec files.
    specPattern: 'cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',

    setupNodeEvents(on, config) {
      // Hook point for Node-side plugins and cy.task(). Nothing needed yet.
      return config
    },
  },
})
