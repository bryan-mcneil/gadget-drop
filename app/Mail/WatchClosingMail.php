<?php

namespace App\Mail;

use App\Models\PriceWatch;
use App\Support\PriceIntel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The honesty flourish: the window is closing and the price never dropped far
 * enough — say so, with verdict context on what they paid when our 90-day
 * stats can honestly provide it. Invite (not push) to the newsletter.
 */
class WatchClosingMail extends Mailable
{
    use Queueable, SerializesModels;

    public PriceWatch $watch;

    public float $paid;

    /** null until PriceIntel's honesty gates open — the view has a no-stats path. */
    public ?string $verdict;

    public string $verdictLine;

    public string $reviewUrl;

    public string $unsubscribeUrl;

    public function __construct(PriceWatch $watch)
    {
        $this->watch = $watch->loadMissing('product');

        $this->paid = (float) $watch->purchase_price;
        $this->verdict = PriceIntel::verdictFor($this->paid, PriceIntel::stats($watch->product_id));

        $this->verdictLine = match ($this->verdict) {
            'lowest' => 'you paid the lowest price we have tracked in the last 90 days. It never got better — that is the best outcome a watch can report.',
            'good' => 'you paid a better-than-typical price for it. Per our 90-day data, that was a genuinely good buy.',
            'elevated' => 'you paid above its typical tracked price — but it never dropped far enough during your window for a return and rebuy to be worth the hassle.',
            'typical' => 'you paid a typical price for it. No regrets warranted — it simply never went on a real sale during your window.',
            default => 'we do not have enough tracked history on this one to judge what you paid — but the price never dropped below it while we watched.',
        };

        $this->reviewUrl = $watch->reviewUrl();

        $this->unsubscribeUrl = URL::signedRoute(
            'watch.unsubscribe', ['token' => $watch->token],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your return window on '.$this->watch->product->name.' closes '.$this->watch->expires_at->format('M j'),
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
            view: 'mail.watch-closing',
        );
    }
}
