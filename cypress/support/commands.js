// Custom commands extend the cy.* API. They live here and are wired up
// globally by support/e2e.js. Docs: https://on.cypress.io/custom-commands

// cy.visitWithConsent(path) loads a page with the cookie-consent banner
// pre-dismissed, so the fixed banner can never cover the elements a test
// wants to click.
//
// Why a *visit wrapper* and not a plain cy.setLocalStorage? The banner's
// Alpine component (cookieConsent in resources/js/app.js) reads
// localStorage['gadgetdrop_consent'] inside init() — i.e. the moment the
// page's JS boots — and only then schedules the 800ms popup. So the key must
// exist BEFORE that code runs. onBeforeLoad fires before the app's scripts,
// which is exactly the right window; setting it after cy.visit() would be too
// late (init already saw an empty value and scheduled the banner).
Cypress.Commands.add('visitWithConsent', (path = '/') => {
  cy.visit(path, {
    onBeforeLoad(win) {
      win.localStorage.setItem('gadgetdrop_consent', 'rejected')
    },
  })
})
