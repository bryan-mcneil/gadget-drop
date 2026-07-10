<?php

namespace Tests\Feature;

use App\Support\ImageVariants;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageVariantsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! ImageVariants::supported()) {
            $this->markTestSkipped('GD WebP support unavailable.');
        }

        Storage::fake('public');
    }

    /** Encode an in-memory image of the given width/height as $format bytes. */
    private function makeImage(int $w, int $h, string $format): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 120, 90, 200));

        ob_start();
        match ($format) {
            'webp' => imagewebp($im, null, 80),
            'png' => imagepng($im),
            default => imagejpeg($im, null, 85),
        };
        imagedestroy($im);

        return ob_get_clean();
    }

    public function test_webp_originals_get_responsive_variants(): void
    {
        Storage::disk('public')->put('uploads/photo.webp', $this->makeImage(800, 500, 'webp'));

        $created = ImageVariants::generate('uploads/photo.webp');

        // 480 and 960 (clamped to the 800px original) exist; once the original
        // width is reached no larger size is produced.
        $this->assertEqualsCanonicalizing(
            ['uploads/photo-480.webp', 'uploads/photo-960.webp'],
            $created,
        );
        Storage::disk('public')->assertExists('uploads/photo-480.webp');
        Storage::disk('public')->assertExists('uploads/photo-960.webp');
        Storage::disk('public')->assertMissing('uploads/photo-1600.webp');
    }

    public function test_jpg_originals_still_get_variants(): void
    {
        // 450px wide: the 480 variant is clamped to 450 and no larger sizes follow.
        Storage::disk('public')->put('uploads/photo.jpg', $this->makeImage(450, 300, 'jpeg'));

        $created = ImageVariants::generate('uploads/photo.jpg');

        $this->assertSame(['uploads/photo-480.webp'], $created);
        Storage::disk('public')->assertExists('uploads/photo-480.webp');
        Storage::disk('public')->assertMissing('uploads/photo-960.webp');
    }

    public function test_generated_variants_are_never_treated_as_sources(): void
    {
        $this->assertTrue(ImageVariants::isVariant('uploads/photo-480.webp'));
        $this->assertTrue(ImageVariants::isVariant('uploads/photo-1600.webp'));
        $this->assertFalse(ImageVariants::isVariant('uploads/photo.webp'));
        $this->assertFalse(ImageVariants::isVariant('uploads/photo-123.webp'));

        // A variant on disk must not spawn variant-of-variant files.
        Storage::disk('public')->put('uploads/photo-480.webp', $this->makeImage(480, 300, 'webp'));

        $this->assertSame([], ImageVariants::generate('uploads/photo-480.webp'));
        Storage::disk('public')->assertMissing('uploads/photo-480-480.webp');
    }
}
