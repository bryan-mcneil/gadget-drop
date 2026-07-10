<?php

namespace App\Support;

use App\Models\Post;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Generates branded 1200×630 social-share (Open Graph / Twitter) cards with GD.
 *
 * Pure GD — no Imagick, no headless browser — so it runs on Hostinger shared
 * hosting. Cards are cached to storage/app/public/og/ keyed by a content hash,
 * so they only regenerate when the post's title/image/etc. actually change.
 *
 * Layout (1200×630):
 *   ┌─────────────────────────────────────────────┐
 *   │ ▣ GD  gadgetdrop.tech            [ accent     │
 *   │                                    glow ]     │
 *   │  [CATEGORY]                  ╔═════════════╗  │
 *   │  Big bold post title         ║  featured/  ║  │
 *   │  wrapped to a few lines      ║   product   ║  │
 *   │                              ║    image    ║  │
 *   │  ★★★★★ 4.5 · Review          ╚═════════════╝  │
 *   └─────────────────────────────────────────────┘
 */
class OgImageGenerator
{
    private const W = 1200;

    private const H = 630;

    /** Card themes keyed by post type. RGB triplets. */
    private const THEMES = [
        // indigo-500 accent for product posts / reviews / tips
        'default' => ['accent' => [99, 102, 241],  'glow' => [79, 70, 229]],
        // rose-500 accent for tech news (matches the news section)
        'tech_news' => ['accent' => [244, 63, 94],   'glow' => [225, 29, 72]],
    ];

    /**
     * Return the absolute filesystem path to the post's cached card,
     * generating it on a cache miss. Used by the OG image controller.
     */
    public function forPost(Post $post): string
    {
        $post->loadMissing(['categories', 'seoMeta']);

        $category = $post->categories->first()?->name;
        $rating = $post->rating ? (float) $post->rating : null;

        $signature = sha1(implode('|', [
            $post->id,
            $post->type,
            $post->title,
            $post->featured_image,
            $category,
            $rating,
            $post->updated_at?->timestamp,
        ]));

        $relative = "og/post-{$post->id}-".substr($signature, 0, 12).'.jpg';
        $disk = Storage::disk('public');

        if (! $disk->exists($relative)) {
            $this->pruneOldVariants($post->id, $relative);
            $disk->put($relative, $this->renderPostCard($post, $category, $rating));
        }

        return $disk->path($relative);
    }

    /** Delete previously-cached cards for this post (stale content hashes). */
    private function pruneOldVariants(int $postId, string $keep): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->files('og') as $file) {
            if (str_starts_with(basename($file), "post-{$postId}-") && $file !== $keep) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Return the absolute filesystem path to the generic site card — the fallback
     * share image for pages without their own (home, category, news index, search,
     * about, …). Bump $version to force a regenerate after a design tweak.
     */
    public function forDefault(): string
    {
        $version = 'v1';
        $relative = "og/default-{$version}.jpg";
        $disk = Storage::disk('public');

        if (! $disk->exists($relative)) {
            $disk->put($relative, $this->renderDefaultCard());
        }

        return $disk->path($relative);
    }

    private function renderDefaultCard(): string
    {
        $theme = self::THEMES['default'];

        $im = imagecreatetruecolor(self::W, self::H);
        imagealphablending($im, true);
        imagesavealpha($im, false);

        // Centred composition → centre the glow too.
        $this->paintBackground($im, $theme['glow'], 600, 300);

        $accent = $this->color($im, $theme['accent']);
        $white = $this->color($im, [249, 250, 251]);
        $muted = $this->color($im, [148, 163, 184]);

        // Brand mark, centred.
        $logo = 116;
        $this->drawLogo($im, (int) ((self::W - $logo) / 2), 132, $theme['accent'], $logo);

        // Wordmark + tagline + domain.
        $this->centerText($im, 76, 372, 'GadgetDrop', $white, $this->font('ExtraBold'));
        $this->centerText($im, 27, 438, 'Daily Tech Picks · Gadget Reviews · Buying Guides', $muted, $this->font('Medium'));

        // Accent divider above the domain.
        imagefilledrectangle($im, (self::W / 2) - 40, 480, (self::W / 2) + 40, 484, $accent);

        $this->centerText($im, 24, 540, 'gadgetdrop.tech', $white, $this->font('SemiBold'));

        return $this->toJpeg($im);
    }

    private function renderPostCard(Post $post, ?string $category, ?float $rating): string
    {
        $theme = self::THEMES[$post->type] ?? self::THEMES['default'];
        $isNews = $post->type === 'tech_news';

        $im = imagecreatetruecolor(self::W, self::H);
        imagealphablending($im, true);
        imagesavealpha($im, false);

        $this->paintBackground($im, $theme['glow']);

        $accent = $this->color($im, $theme['accent']);
        $white = $this->color($im, [249, 250, 251]);
        $muted = $this->color($im, [148, 163, 184]);

        // --- Featured / product image panel (right) ---
        $panel = ['x1' => 700, 'y1' => 150, 'x2' => 1136, 'y2' => 510];
        $this->drawImagePanel($im, $post->featured_image, $panel, $isNews);

        // --- Brand row (top-left): GD mark + wordmark ---
        $this->drawLogo($im, 64, 60, $theme['accent']);
        imagettftext($im, 26, 0, 150, 105, $white, $this->font('SemiBold'), 'gadgetdrop.tech');

        // --- Category pill ---
        $titleTop = 250;
        if ($category) {
            $titleTop = $this->drawPill($im, 64, 190, strtoupper($category), $theme['accent']) + 40;
        }

        // --- Title (auto-sized, wrapped) ---
        $bottom = $this->drawTitle($im, $post->title, 64, $titleTop, 580, $white);

        // --- Footer: rating stars or tagline ---
        $footerY = 548;
        if ($rating) {
            $endX = $this->drawStars($im, 64, $footerY - 18, $rating, $theme['accent']);
            imagettftext($im, 19, 0, $endX + 18, $footerY - 4,
                $white, $this->font('SemiBold'), number_format($rating, 1).' / 5');
            imagettftext($im, 17, 0, $endX + 110, $footerY - 4,
                $muted, $this->font('Medium'), '·  Editor\'s Review');
        } else {
            $label = $isNews ? 'Latest in Tech · Breaking & Analysis' : 'Daily Tech Picks & Reviews';
            imagettftext($im, 19, 0, 64, $footerY - 4, $muted, $this->font('Medium'), $label);
        }

        // --- Accent rule under the brand row ---
        imagefilledrectangle($im, 64, 140, 124, 144, $accent);

        return $this->toJpeg($im);
    }

    /** Vertical dark gradient + a soft accent glow. */
    private function paintBackground(GdImage $im, array $glow, int $cx = 940, int $cy = 150): void
    {
        [$tr, $tg, $tb] = [26, 32, 54];   // top  #1a2036
        [$br, $bg, $bb] = [9, 12, 22];    // bottom #090c16

        for ($y = 0; $y < self::H; $y++) {
            $t = $y / self::H;
            $c = imagecolorallocate(
                $im,
                (int) ($tr + ($br - $tr) * $t),
                (int) ($tg + ($bg - $tg) * $t),
                (int) ($tb + ($bb - $tb) * $t),
            );
            imageline($im, 0, $y, self::W, $y, $c);
        }

        // Soft radial glow (stacked translucent ellipses build toward the centre).
        for ($r = 520; $r > 0; $r -= 8) {
            $alpha = 116; // faint; additive blending intensifies the core
            $c = imagecolorallocatealpha($im, $glow[0], $glow[1], $glow[2], $alpha);
            imagefilledellipse($im, $cx, $cy, $r, $r, $c);
        }
    }

    /** Draw the featured image inside a rounded card; contain for products, cover for news. */
    private function drawImagePanel(GdImage $im, ?string $src, array $p, bool $cover): void
    {
        $w = $p['x2'] - $p['x1'];
        $h = $p['y2'] - $p['y1'];

        // Card background (white for product contain, dark slate for news cover).
        $card = $cover ? $this->color($im, [15, 23, 42]) : $this->color($im, [255, 255, 255]);
        $this->roundedRect($im, $p['x1'], $p['y1'], $p['x2'], $p['y2'], 28, $card);

        $photo = $this->loadImage($src);
        if (! $photo) {
            return; // graceful: card stays as a clean coloured panel
        }

        $sw = imagesx($photo);
        $sh = imagesy($photo);

        if ($cover) {
            // Fill the panel, cropping overflow (inset 10px so card corners read).
            $inset = 10;
            $iw = $w - $inset * 2;
            $ih = $h - $inset * 2;
            $scale = max($iw / $sw, $ih / $sh);
            $cropW = (int) ($iw / $scale);
            $cropH = (int) ($ih / $scale);
            imagecopyresampled(
                $im, $photo,
                $p['x1'] + $inset, $p['y1'] + $inset, (int) (($sw - $cropW) / 2), (int) (($sh - $cropH) / 2),
                $iw, $ih, $cropW, $cropH,
            );
        } else {
            // Contain with padding so the whole product shows on the white card.
            $pad = 34;
            $iw = $w - $pad * 2;
            $ih = $h - $pad * 2;
            $scale = min($iw / $sw, $ih / $sh);
            $nw = (int) ($sw * $scale);
            $nh = (int) ($sh * $scale);
            $dx = $p['x1'] + (int) (($w - $nw) / 2);
            $dy = $p['y1'] + (int) (($h - $nh) / 2);
            imagecopyresampled($im, $photo, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
        }

        imagedestroy($photo);
    }

    /** Auto-size + wrap the title so it fits the left column; return the bottom Y. */
    private function drawTitle(GdImage $im, string $title, int $x, int $top, int $maxWidth, int $color): int
    {
        $font = $this->font('ExtraBold');
        $budget = 470 - $top; // vertical space available for the title block

        foreach ([58, 52, 47, 42, 38] as $size) {
            $lineHeight = (int) round($size * 1.22);
            $lines = $this->wrap($font, $size, $title, $maxWidth);

            if (count($lines) * $lineHeight <= $budget || $size === 38) {
                $lines = array_slice($lines, 0, (int) floor($budget / $lineHeight) ?: 1);
                $y = $top + $size;
                foreach ($lines as $line) {
                    imagettftext($im, $size, 0, $x, $y, $color, $font, $line);
                    $y += $lineHeight;
                }

                return $y;
            }
        }

        return $top;
    }

    /** Greedy word-wrap against the rendered pixel width. */
    private function wrap(string $font, int $size, string $text, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text));
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $try = $current === '' ? $word : "$current $word";
            $box = imagettfbbox($size, 0, $font, $try);
            if (($box[2] - $box[0]) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $try;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /** Filled accent pill with uppercase label; returns the pill's bottom Y. */
    private function drawPill(GdImage $im, int $x, int $y, string $label, array $accent): int
    {
        $font = $this->font('Bold');
        $size = 17;
        $box = imagettfbbox($size, 0, $font, $label);
        $tw = $box[2] - $box[0];
        $padX = 22;
        $padY = 13;
        $h = $size + $padY * 2;

        $this->roundedRect($im, $x, $y, $x + $tw + $padX * 2, $y + $h, (int) ($h / 2), $this->color($im, $accent));
        imagettftext($im, $size, 0, $x + $padX, $y + $size + $padY, $this->color($im, [255, 255, 255]), $font, $label);

        return $y + $h;
    }

    /** Draw the "GD" rounded-square brand mark (mirrors favicon.svg); scales with $s. */
    private function drawLogo(GdImage $im, int $x, int $y, array $accent, int $s = 60): void
    {
        $this->roundedRect($im, $x, $y, $x + $s, $y + $s, (int) round($s * 0.23), $this->color($im, [243, 244, 246]));
        $font = $this->font('ExtraBold');
        $fontSize = (int) round($s * 0.5);
        $baseline = $y + (int) round($s * 0.73);
        imagettftext($im, $fontSize, 0, $x + (int) round($s * 0.13), $baseline, $this->color($im, [17, 24, 39]), $font, 'G');
        imagettftext($im, $fontSize, 0, $x + (int) round($s * 0.5), $baseline, $this->color($im, $accent), $font, 'D');
    }

    /** Draw horizontally-centred text at baseline $y. */
    private function centerText(GdImage $im, int $size, int $y, string $text, int $color, string $font): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $x = (int) ((self::W - ($box[2] - $box[0])) / 2) - $box[0];
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }

    /** Draw a 5-star rating row; returns the X just past the stars. */
    private function drawStars(GdImage $im, int $x, int $y, float $rating, array $accent): int
    {
        $size = 22;
        $gap = 8;
        $on = $this->color($im, $accent);
        $off = $this->color($im, [71, 85, 105]);

        for ($i = 0; $i < 5; $i++) {
            $cx = $x + $i * ($size + $gap) + $size / 2;
            $this->drawStar($im, (int) $cx, $y + $size / 2, $size / 2, $i < round($rating) ? $on : $off);
        }

        return $x + 5 * ($size + $gap);
    }

    private function drawStar(GdImage $im, int $cx, int $cy, float $r, int $color): void
    {
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $radius = $i % 2 === 0 ? $r : $r * 0.42;
            $angle = M_PI / 2 + $i * M_PI / 5; // start at top
            $points[] = $cx + $radius * cos($angle);
            $points[] = $cy - $radius * sin($angle);
        }
        imagefilledpolygon($im, $points, $color);
    }

    /** Rounded filled rectangle. */
    private function roundedRect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    /** Load an image from a remote URL or a local public/storage path. */
    private function loadImage(?string $src): ?GdImage
    {
        if (! $src) {
            return null;
        }

        try {
            if (str_starts_with($src, 'http')) {
                $data = Http::timeout(8)->retry(1, 200)->get($src)->body();
            } else {
                $path = public_path(ltrim($src, '/'));
                $data = is_file($path) ? file_get_contents($path) : null;
            }

            if (! $data) {
                return null;
            }

            $img = @imagecreatefromstring($data);

            return $img ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function toJpeg(GdImage $im): string
    {
        ob_start();
        imagejpeg($im, null, 90);
        $data = ob_get_clean();
        imagedestroy($im);

        return $data;
    }

    private function color(GdImage $im, array $rgb): int
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function font(string $weight): string
    {
        return storage_path("fonts/Figtree-{$weight}.ttf");
    }
}
