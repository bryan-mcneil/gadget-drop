// The header search is a plain GET form (no JavaScript required), which makes
// it the most deterministic content test. Submitting it navigates the browser
// to /search?q=<term>.
//
// Concepts introduced here:
//   • typing into an input and submitting with the {enter} key
//   • :visible to disambiguate duplicate elements
//   • asserting on the resulting URL (cy.url)
//   • writing resilient assertions that don't depend on specific dev data

describe('Search', () => {
  beforeEach(() => {
    cy.visitWithConsent('/')
  })

  it('submits the header search and lands on the results page', () => {
    // The header renders TWO search inputs (desktop + mobile); only one shows
    // at a given viewport. :visible scopes us to the one actually on screen.
    cy.get('header form[action*="search"] input[name="q"]:visible')
      .first()
      .type('wireless{enter}')

    // The GET form navigates to the results route carrying our query string.
    cy.url().should('include', '/search')
    cy.url().should('include', 'q=wireless')

    // Every public route renders an <h1>. We assert the results page loaded —
    // not a specific product — so the test survives changing dev content.
    cy.get('h1').should('be.visible')
  })
})
