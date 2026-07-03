<?php

namespace App\Livewire;

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ContactForm extends Component
{
    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|in:correction,partnership,tip,other')]
    public string $topic = 'other';

    #[Validate('required|string|min:20|max:5000')]
    public string $message = '';

    /** Honeypot — hidden from humans; anything here means a bot filled the form. */
    public string $company = '';

    /** Unix time the form was first rendered; sub-3-second submits are bots. */
    #[Locked]
    public int $renderedAt = 0;

    /** null | 'success' | 'throttled' | 'error' */
    public ?string $status = null;

    public function mount(): void
    {
        // now()->timestamp (not time()) so tests can time-travel past the gate.
        $this->renderedAt = now()->timestamp;
    }

    public function send(): void
    {
        $this->status = null;
        $this->validate();

        // Bots get a silent "success" — no mail, no error to learn from.
        if ($this->company !== '' || (now()->timestamp - $this->renderedAt) < 3) {
            $this->reset('name', 'email', 'message', 'company');
            $this->status = 'success';

            return;
        }

        $key = 'contact:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->status = 'throttled';

            return;
        }

        try {
            Mail::to(config('site.author.email'))->send(new ContactMessage(
                senderName: $this->name,
                senderEmail: $this->email,
                topic: $this->topic,
                messageBody: $this->message,
            ));

            RateLimiter::hit($key, 3600);
            $this->reset('name', 'email', 'message');
            $this->status = 'success';
        } catch (\Throwable $e) {
            report($e);
            $this->status = 'error';
        }
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
