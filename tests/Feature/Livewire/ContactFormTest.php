<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ContactForm;
use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /** Fill every field validly and move past the too-fast-submit bot gate. */
    private function validSubmission()
    {
        $component = Livewire::test(ContactForm::class);

        $this->travel(10)->seconds();

        return $component
            ->set('name', 'Jane Reader')
            ->set('email', 'jane@example.com')
            ->set('topic', 'correction')
            ->set('message', 'The battery spec in the Widget Pro review looks wrong — the box says 30 hours.');
    }

    public function test_a_valid_message_sends_mail_to_the_site_author(): void
    {
        Mail::fake();

        $this->validSubmission()
            ->call('send')
            ->assertSet('status', 'success');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->hasTo(config('site.author.email'))
                && $mail->senderEmail === 'jane@example.com'
                && $mail->topic === 'correction';
        });
    }

    public function test_validation_errors_block_sending(): void
    {
        Mail::fake();

        Livewire::test(ContactForm::class)
            ->set('email', 'not-an-email')
            ->set('message', 'too short')
            ->call('send')
            ->assertHasErrors(['name', 'email', 'message'])
            ->assertSet('status', null);

        Mail::assertNothingSent();
    }

    public function test_filled_honeypot_pretends_success_but_sends_nothing(): void
    {
        Mail::fake();

        $this->validSubmission()
            ->set('company', 'Totally Real Corp')
            ->call('send')
            ->assertSet('status', 'success');

        Mail::assertNothingSent();
    }

    public function test_instant_submit_is_treated_as_a_bot(): void
    {
        Mail::fake();

        // No time travel — submitted within 3s of render.
        Livewire::test(ContactForm::class)
            ->set('name', 'Speedy Bot')
            ->set('email', 'bot@example.com')
            ->set('topic', 'other')
            ->set('message', 'This message was typed suspiciously fast for a human being.')
            ->call('send')
            ->assertSet('status', 'success');

        Mail::assertNothingSent();
    }

    public function test_sends_are_rate_limited_per_ip(): void
    {
        Mail::fake();

        foreach (range(1, 3) as $i) {
            RateLimiter::hit('contact:127.0.0.1', 3600);
        }

        $this->validSubmission()
            ->call('send')
            ->assertSet('status', 'throttled');

        Mail::assertNothingSent();
    }

    public function test_contact_page_renders_the_form(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Send message', false)
            ->assertSeeLivewire(ContactForm::class);
    }

    public function test_the_notification_email_carries_no_em_dashes(): void
    {
        $html = (new ContactMessage('Jane Reader', 'jane@example.com', 'correction', 'Body text.'))->render();

        $this->assertStringNotContainsString("\u{2014}", $html);
        $this->assertStringNotContainsString('&mdash;', $html);
    }
}
