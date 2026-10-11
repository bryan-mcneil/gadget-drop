// A smoke test for the homepage — the Cypress port of the existing Laravel
// Dusk test (tests/Browser/SmokeTest.php). It asserts the page's key
// landmarks render. Runs against real dev content served by Herd.
//
// Concepts introduced here:
//   • using our custom command (cy.visitWithConsent)
//   • cy.get() with CSS selectors + chained .should()/.and() assertions
//   • asserting text within a container ('contain')

describe('Homepage', () => {
  beforeEach(() => {
    // Loads '/' with the cookie banner pre-dismissed (see support/commands.js)
    // so nothing is covered by the fixed banner.
    cy.visitWithConsent('/')
  })

  it('renders a hero heading', () => {
    // The hero H1 is server-rendered from the latest published post.
    cy.get('h1').should('be.visible').and('not.be.empty')
  })

  it('shows the primary navigation with a "Trending" link', () => {
    cy.get('header nav').should('be.visible').and('contain', 'Trending')
  })

  it('shows the "Join the Drop" newsletter form', () => {
    cy.get('#subscribe form input[type=email]').should('exist')
  })
})
