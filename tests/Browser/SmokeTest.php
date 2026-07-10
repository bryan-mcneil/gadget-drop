<?php

namespace Tests\Browser;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Local flow (two terminals, from the project root):
 *
 *   1. php artisan serve      — serves 127.0.0.1:8000; the built-in server
 *      re-reads .env per request, so it picks up the Dusk env swap.
 *   2. php artisan dusk       — swaps .env ↔ .env.dusk.local, runs this suite,
 *      restores .env afterwards.
 *
 * The database is the sqlite FILE database/dusk.sqlite (gitignored) — shared
 * by the serve process and the test process. DatabaseTruncation (never
 * RefreshDatabase — its transactions would hide data from the serve process;
 * not DatabaseMigrations — its teardown rollback trips the sqlite-broken
 * down() of 2026_06_01_000001_add_share_code_to_posts_table) migrates the
 * file once and truncates between tests; tests/DuskTestCase.php hard-aborts
 * if the boot lands anywhere else.
 */
class SmokeTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_home_page_renders_hero_nav_and_subscribe_form(): void
    {
        // Direct Model::create + User::factory() is this suite's seeding
        // convention (see PublicPagesTest) — there are no Post factories.
        Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Dusk Smoke Test Drop',
            'slug' => 'dusk-smoke-test-drop',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                // Hero H1 comes from the published post (SSR).
                ->assertSeeIn('h1', 'Dusk Smoke Test Drop')
                // Desktop nav is visible at the default 1920px window.
                ->assertVisible('header nav')
                ->assertSeeIn('header nav', 'Trending')
                // Join the Drop — the Livewire subscribe form.
                ->assertPresent('#subscribe form input[type=email]');
        });
    }
}
