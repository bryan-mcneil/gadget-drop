// The cookie-consent banner is the best FIRST test: it depends on no database
// content — only the site's JavaScript and localStorage.
//
// Concepts introduced here:
//   • cy.clearLocalStorage() to reset state between tests
//   • cy.visit() and Cypress's automatic retry-until-visible assertions
//   • clicking a button by its visible text (cy.contains)
//   • reading localStorage back out to prove a side effect happened
//   • onBeforeLoad to set state before the page's JS runs

describe('Cookie consent banner', () => {
  beforeEach(() => {
    // Start each test with no stored choice, so the banner is eligible to show.
    cy.clearLocalStorage()
  })

  it('appears on first visit and is dismissed by "Accept all"', () => {
    cy.visit('/')

    // The banner is rendered hidden (Alpine x-cloak / x-show) and only becomes
    // visible ~800ms after load. We don't hard-wait: Cypress retries this
    // assertion until it passes or times out.
    cy.get('[aria-label="Cookie consent"]').should('be.visible')

    // Accept it — selecting the button by the text a user would read.
    cy.contains('button', 'Accept all').click()

    // The banner hides again...
    cy.get('[aria-label="Cookie consent"]').should('not.be.visible')

    // ...and the choice is persisted to localStorage.
    cy.window()
      .its('localStorage')
      .invoke('getItem', 'gadgetdrop_consent')
      .should('eq', 'accepted')
  })

  it('stays hidden when a choice was already stored', () => {
    // Seed the key BEFORE the page boots — the banner should never schedule.
    cy.visit('/', {
      onBeforeLoad(win) {
        win.localStorage.setItem('gadgetdrop_consent', 'rejected')
      },
    })

    // A fixed wait is normally a smell, but proving a negative (the 800ms
    // timer never shows the banner) is one of its few legitimate uses.
    cy.wait(1000)
    cy.get('[aria-label="Cookie consent"]').should('not.be.visible')
  })
})
