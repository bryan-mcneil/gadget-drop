<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Post;
use App\Models\Product;

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
                'title'          => $p->title,
                'slug'           => $p->slug,
                'excerpt'        => $p->excerpt,
                'featured_image' => $p->featured_image,
                'published_at'   => $p->published_at?->format('M j'),
                'url'            => url('/posts/' . $p->slug),
            ])
            ->toArray();

        $tip = Post::published()
            ->where('type', 'tech_tip')
            ->latest('published_at')
            ->first(['title', 'slug', 'excerpt']);

        $this->techTip = $tip ? [
            'title'   => $tip->title,
            'excerpt' => $tip->excerpt,
            'url'     => url('/posts/' . $tip->slug),
        ] : null;

        $spotlightPost = Post::published()
            ->where('type', '!=', 'tech_tip')
            ->has('products')
            ->with(['products' => fn ($q) => $q->orderBy('display_order')->limit(1)])
            ->latest('published_at')
            ->first(['id', 'title', 'slug']);

        $this->spotlight = null;
        if ($spotlightPost && $spotlightPost->products->isNotEmpty()) {
            $p = $spotlightPost->products->first();
            $this->spotlight = [
                'name'          => $p->name,
                'price'         => $p->price,
                'image_url'     => $p->image_url,
                'affiliate_url' => $p->affiliate_url,
                'post_title'    => $spotlightPost->title,
                'post_url'      => url('/posts/' . $spotlightPost->slug),
            ];
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'This Week\'s Drop 🔥 — ' . now()->format('M j, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.weekly-digest',
        );
    }
}
