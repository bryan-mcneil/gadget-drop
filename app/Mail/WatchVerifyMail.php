<?php

namespace App\Mail;

use App\Models\PriceWatch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Double-opt-in confirmation for a post-purchase price watch. The verify link
 * is a 48h temporary signed URL; the unsubscribe link is signed but permanent
 * (an escape hatch must never expire).
 */
class WatchVerifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public PriceWatch $watch;

    public string $verifyUrl;

    public string $unsubscribeUrl;

    public function __construct(PriceWatch $watch)
    {
        $this->watch = $watch->loadMissing('product');

        $this->verifyUrl = URL::temporarySignedRoute(
            'watch.verify', now()->addHours(48), ['token' => $watch->token],
        );

        $this->unsubscribeUrl = URL::signedRoute(
            'watch.unsubscribe', ['token' => $watch->token],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your price watch: '.$this->watch->product->name,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.watch-verify',
        );
    }
}
