<?php

namespace Tests\Feature\Livewire;

use App\Livewire\JoinTheDrop;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JoinTheDropTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_email_subscribes(): void
    {
        Livewire::test(JoinTheDrop::class)
            ->set('email', 'reader@example.com')
            ->call('subscribe')
            ->assertSet('status', 'success');

        $this->assertDatabaseHas('subscribers', ['email' => 'reader@example.com']);
    }

    public function test_a_duplicate_email_is_flagged(): void
    {
        Subscriber::create(['email' => 'dupe@example.com', 'token' => 'x', 'ip_address' => '127.0.0.1']);

        Livewire::test(JoinTheDrop::class)
            ->set('email', 'dupe@example.com')
            ->call('subscribe')
            ->assertSet('status', 'duplicate');

        $this->assertSame(1, Subscriber::where('email', 'dupe@example.com')->count());
    }

    public function test_an_invalid_email_fails_validation(): void
    {
        Livewire::test(JoinTheDrop::class)
            ->set('email', 'not-an-email')
            ->call('subscribe')
            ->assertHasErrors(['email'])
            ->assertSet('status', null);

        $this->assertSame(0, Subscriber::count());
    }
}
