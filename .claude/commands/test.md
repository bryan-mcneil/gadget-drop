# /test — Unit & Feature Test Generator

You are a Laravel testing expert for GadgetDrop, a Laravel 12 affiliate site using PHPUnit and Pest.

## How to use

Name a controller, model, or feature after `/test`. Examples:
- `/test PostController`
- `/test affiliate click tracking`
- `/test admin post creation`

## What to generate

### PHP Feature Tests (Laravel Pest or PHPUnit)

For each controller action, generate tests covering:

1. **Happy path** — authenticated user, valid data, expected redirect/response
2. **Unauthenticated** — guest gets 302 redirect to `/login`
3. **Validation errors** — missing required fields return validation errors
4. **Authorization** — users cannot modify other users' posts (if applicable)
5. **Edge cases** — empty collections, nullable fields, scheduled vs published status

### Test patterns to follow

```php
// Use RefreshDatabase to reset state between tests
uses(RefreshDatabase::class);

// Use actingAs() for authenticated requests
$user = User::factory()->create();
actingAs($user);

// Use assertInertia() for Inertia page assertions
$response->assertInertia(fn ($page) => $page
    ->component('Admin/Posts/Index')
    ->has('posts')
);

// Test affiliate redirect logs a click
test('affiliate redirect logs click and redirects', function () {
    $product = Product::factory()->create();
    $response = get(route('affiliate.redirect', $product));
    $response->assertRedirect($product->affiliate_url);
    assertDatabaseHas('affiliate_clicks', ['product_id' => $product->id]);
});
```

### Database factories

If a Model factory doesn't exist for the entity being tested, generate it too:
- `Post::factory()` with realistic fake data using Faker
- `Product::factory()` with a valid ASIN format and affiliate URL
- `Category::factory()` and `Tag::factory()`

## Output format

Return complete, runnable test files. Place them in `tests/Feature/` for HTTP tests and `tests/Unit/` for model/service tests. Include the file path as a header comment.
