<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Shapes a published post into platform-ready social copy: title, excerpt,
 * canonical review URL, and light hashtags, fitted to each platform's length
 * limit (Bluesky counts the URL as plain text, so it eats into the 300).
 * Links always point at the gadgetdrop.tech review page — never Amazon — so
 * the affiliate chain stays on-site through /out/{product}.
 */
class SocialComposer
{
    private const LIMITS = [
        'bluesky'  => 300,
        'x'        => 280,
        'facebook' => 5000,
    ];

    public function compose(Post $post, string $platform): string
    {
        $limit = self::LIMITS[$platform] ?? 500;
        $url = route('posts.show', $post->slug);
        $tags = $this->hashtags($post);
        $title = trim($post->title);
        $excerpt = trim((string) $post->excerpt);

        // Try the richest assembly first, dropping pieces until one fits.
        $candidates = [
            [$title, $excerpt, $tags, $url],
            [$title, $excerpt, $url],
            [$title, $tags, $url],
            [$title, $url],
        ];

        foreach ($candidates as $pieces) {
            $text = implode("\n\n", array_filter($pieces, fn ($p) => $p !== ''));
            if (Str::length($text) <= $limit) {
                return $text;
            }
        }

        // Even title+URL overflows only on a pathological title; otherwise trim
        // the excerpt into whatever room the title and URL leave.
        $overhead = Str::length($title) + Str::length($url) + 4; // two "\n\n" separators
        $room = $limit - $overhead;

        if ($room > 20 && $excerpt !== '') {
            return $title . "\n\n" . Str::limit($excerpt, $room - 1, '…') . "\n\n" . $url;
        }

        return Str::limit($title, $limit - Str::length($url) - 3, '…') . "\n\n" . $url;
    }

    private function hashtags(Post $post): string
    {
        return match ($post->type) {
            'tech_tip'  => '#TechTips',
            'tech_news' => '#TechNews',
            default     => '#tech #gadgets',
        };
    }
}
