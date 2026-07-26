<?php

namespace App\Mail;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class WeeklyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public array $posts;

    public ?array $techTip;

    public ?array $spotlight;

    public string $unsubscribeUrl;

    public function __construct(string $unsubscribeUrl = '#')
    {
        $this->unsubscribeUrl = $unsubscribeUrl;

        $cols = ['id', 'title', 'slug', 'excerpt', 'featured_image', 'published_at', 'type'];

        $this->posts = Post::published()
            ->where('type', '!=', 'tech_tip')
            ->latest('published_at')
            ->take(5)
            ->get($cols)
            ->map(fn ($p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'featured_image' => $p->featured_image,
                'published_at' => $p->published_at?->format('M j'),
                'url' => url('/posts/'.$p->slug),
            ])
            ->toArray();

        $tip = Post::published()
            ->where('type', 'tech_tip')
            ->latest('published_at')
            ->first(['title', 'slug', 'excerpt']);

        $this->techTip = $tip ? [
            'title' => $tip->title,
            'excerpt' => $tip->excerpt,
            'url' => url('/posts/'.$tip->slug),
        ] : null;

        $spotlightPost = Post::published()
            ->where('type', '!=', 'tech_tip')
            ->has('products')
            ->with(['products' => fn ($q) => $q->orderBy('display_order')->limit(1)])
            ->latest('published_at')
            ->first(['id', 'title', 'slug']);

        // No stored price in the email: it freezes at send time, and Amazon §2(b)
        // only allows prices served by Amazon or its API. The dated "price checked"
        // line + a click-through is the compliant framing.
        $this->spotlight = null;
        if ($spotlightPost && $spotlightPost->products->isNotEmpty()) {
            $p = $spotlightPost->products->first();
            $this->spotlight = [
                'name' => $p->name,
                'brand' => $p->brand,
                'image_url' => $p->image_url,
                'out_url' => route('affiliate.redirect', $p).'?post='.$spotlightPost->id,
                'price_checked_at' => $p->price_checked_at?->format('M j'),
                'post_title' => $spotlightPost->title,
                'post_url' => url('/posts/'.$spotlightPost->slug),
            ];
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ! empty($this->posts)
                ? 'This week\'s drop: '.$this->posts[0]['title']
                : 'The Weekly Drop: '.now()->format('M j, Y'),
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
            view: 'mail.weekly-digest',
        );
    }
}
