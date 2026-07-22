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
 * The money moment: the tracked price dropped far enough inside the reader's
 * return window to be worth a return-and-rebuy. One per watch, ever
 * (notified_at). Prices here are OUR tracked data with the checked-at date
 * spelled out, and the only link is the review page — never /out, never
 * Amazon directly.
 */
class WatchDropMail extends Mailable
{
    use Queueable, SerializesModels;

    public PriceWatch $watch;

    public float $currentPrice;

    public float $paid;

    public float $savings;

    public int $daysLeft;

    public ?string $checkedAt;

    public string $reviewUrl;

    public string $unsubscribeUrl;

    public function __construct(PriceWatch $watch)
    {
        $this->watch = $watch->loadMissing('product');

        $this->currentPrice = (float) $watch->product->price;
        $this->paid = (float) $watch->purchase_price;
        $this->savings = $watch->savings($this->currentPrice);
        // An alert can never postdate the window (active() excludes expired),
        // so today is always inside it; the last day still counts as 1.
        $this->daysLeft = max(1, (int) today()->diffInDays($watch->expires_at->copy()->startOfDay(), false));
        $this->checkedAt = $watch->product->price_checked_at?->format('M j');
        $this->reviewUrl = $watch->reviewUrl();

        $this->unsubscribeUrl = URL::signedRoute(
            'watch.unsubscribe', ['token' => $watch->token],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'It dropped $%s — return & rebuy your %s',
                number_format($this->savings, 2),
                $this->watch->product->name,
            ),
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
            view: 'mail.watch-drop',
        );
    }
}
