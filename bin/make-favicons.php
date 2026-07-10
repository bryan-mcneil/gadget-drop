<?php

/**
 * Regenerate the GadgetDrop favicons from the "GD" logo design.
 *
 * Matches public/favicon.svg: a rounded gray-100 square with "G" (gray-900)
 * and "D" (indigo-600) set in Arial Black. Produces the raster icons Google
 * and browsers actually request:
 *
 *   public/favicon.ico         multi-res ICO (16 / 32 / 48, PNG-compressed)
 *   public/favicon-96x96.png   96x96 PNG (Google Search's preferred favicon)
 *   public/apple-touch-icon.png 180x180 PNG, opaque (iOS home screen)
 *
 * Run: php bin/make-favicons.php
 */
if (! function_exists('imagettftext')) {
    fwrite(STDERR, "GD FreeType support is required (imagettftext missing).\n");
    exit(1);
}

$font = 'C:/Windows/Fonts/ariblk.ttf'; // Arial Black — same family the SVG declares
if (! is_file($font)) {
    fwrite(STDERR, "Arial Black not found at {$font}\n");
    exit(1);
}

$publicDir = __DIR__.'/../public';

function gd_color($img, string $hex): int
{
    return imagecolorallocate(
        $img,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    );
}

/**
 * Render the GD logo at $size x $size px.
 * Supersampled 4x then downscaled for clean antialiased edges.
 *
 * @param  bool  $opaque  Fill the whole square (apple-touch-icon: iOS turns
 *                        transparency black). Otherwise rounded transparent corners.
 */
function render_logo(int $size, string $font, bool $opaque = false)
{
    $ss = 4;
    $w = $size * $ss;

    $img = imagecreatetruecolor($w, $w);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagealphablending($img, true);

    $scale = $w / 32.0;            // SVG viewBox is 0 0 32 32
    $bg = gd_color($img, 'f3f4f6');
    $r = (int) round(7 * $scale); // SVG corner radius rx=7

    if ($opaque) {
        imagefilledrectangle($img, 0, 0, $w - 1, $w - 1, $bg);
    } else {
        // Rounded rect: vertical band + horizontal band + 4 corner ellipses.
        imagefilledrectangle($img, $r, 0, $w - 1 - $r, $w - 1, $bg);
        imagefilledrectangle($img, 0, $r, $w - 1, $w - 1 - $r, $bg);
        $d = $r * 2;
        imagefilledellipse($img, $r, $r, $d, $d, $bg);
        imagefilledellipse($img, $w - 1 - $r, $r, $d, $d, $bg);
        imagefilledellipse($img, $r, $w - 1 - $r, $d, $d, $bg);
        imagefilledellipse($img, $w - 1 - $r, $w - 1 - $r, $d, $d, $bg);
    }

    // Letters — fixed x positions and baseline straight from the SVG
    // (G at x=2, D at x=16, baseline y=24, font-size 20). px -> pt = x * 3/4.
    $pt = 20 * $scale * 0.75;
    imagettftext($img, $pt, 0, (int) round(2 * $scale), (int) round(24 * $scale), gd_color($img, '111827'), $font, 'G');
    imagettftext($img, $pt, 0, (int) round(16 * $scale), (int) round(24 * $scale), gd_color($img, '4f46e5'), $font, 'D');

    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $w, $w);
    imagedestroy($img);

    return $out;
}

function png_bytes($img): string
{
    ob_start();
    imagepng($img, null, 9);

    return ob_get_clean();
}

/** Assemble a multi-resolution ICO from [size => pngBytes]. */
function build_ico(array $pngs): string
{
    $count = count($pngs);
    $out = pack('vvv', 0, 1, $count);  // ICONDIR: reserved, type=1, count
    $offset = 6 + $count * 16;
    $blob = '';
    foreach ($pngs as $size => $png) {
        $len = strlen($png);
        $b = $size >= 256 ? 0 : $size;   // 0 means 256 in the ICO spec
        // ICONDIRENTRY: w, h, colors, reserved, planes, bpp, bytes, offset
        $out .= pack('CCCCvvVV', $b, $b, 0, 0, 1, 32, $len, $offset);
        $blob .= $png;
        $offset += $len;
    }

    return $out.$blob;
}

// --- favicon.ico (16 / 32 / 48) ---
$pngs = [];
foreach ([16, 32, 48] as $s) {
    $img = render_logo($s, $font, false);
    $pngs[$s] = png_bytes($img);
    imagedestroy($img);
}
file_put_contents("{$publicDir}/favicon.ico", build_ico($pngs));

// --- favicon-96x96.png (Google's preferred) ---
$img = render_logo(96, $font, false);
imagepng($img, "{$publicDir}/favicon-96x96.png", 9);
imagedestroy($img);

// --- apple-touch-icon.png (opaque 180) ---
$img = render_logo(180, $font, true);
imagepng($img, "{$publicDir}/apple-touch-icon.png", 9);
imagedestroy($img);

echo "Wrote: favicon.ico (16/32/48), favicon-96x96.png, apple-touch-icon.png\n";
