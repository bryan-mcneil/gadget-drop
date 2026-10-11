// The "Join the Drop" newsletter form is a Livewire component. Submitting it
// fires an XHR to Livewire's update endpoint; on success the form swaps itself
// for a "You're in!" confirmation.
//
// Concepts introduced here:
//   • cy.intercept() to observe (and alias) a network request
//   • cy.wait('@alias') to block until the real response arrives — no guessing
//     with fixed delays
//   • asserting on DOM that only appears AFTER the response

describe('Join the Drop newsletter', () => {
  it('confirms the subscription on success', () => {
    cy.visitWithConsent('/')

    // Alias the Livewire round-trip so we can wait for the real response.
    // Livewire posts to a hashed, asset-prefixed endpoint — the real URL here
    // is /livewire-<hash>/update (the hash busts caches and changes between
    // versions), NOT the /livewire/update you might assume. So we match it by
    // shape with a regex rather than hardcoding the path. (Tip: to find the
    // real endpoint, read the request URL in the Cypress command log, or run
    // `php artisan route:list | grep livewire`.)
    cy.intercept('POST', /livewire.*\/update/).as('livewire')

    // A unique address each run avoids the component's "already subscribed"
    // branch. (Date.now() runs in Node here, which is fine in Cypress specs.)
    const email = `learn-cypress+${Date.now()}@example.com`

    cy.get('#subscribe form input[type=email]').type(email)
    cy.get('#subscribe').contains('button', 'Get the Drop').click()

    // Block until Livewire responds, then assert the success swap rendered.
    cy.wait('@livewire')
    cy.get('#subscribe').contains("You're in!").should('be.visible')
  })
})

// Heads-up: against the live Herd site this test inserts a real row into the
// dev `subscribers` table — harmless in local dev, and a fresh unique email
// each run. You *can* stub the response with cy.intercept(url, { body }) to
// avoid the write, but a Livewire update payload is component-state-shaped and
// awkward to fake by hand, so a real submit is the pragmatic learning choice.
