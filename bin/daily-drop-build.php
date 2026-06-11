<?php

/**
 * Daily Drop build script.
 *
 * Parses daily-drop/product-*.md (plain structured markdown written by /drop-write)
 * and assembles the final JSON array for the /admin/daily-drop importer.
 *
 * Writes:
 *   - daily-drop-output.md   (human file: date header + fenced json block, same format as before)
 *   - daily-drop/output.json (raw JSON array, used to validate against DailyDropImporterService::parseJson)
 *
 * Plain PHP, no Laravel boot. Run with: php bin/daily-drop-build.php
 * Exit code 0 = built (warnings allowed), 1 = hard error (nothing written).
 */

$root      = dirname(__DIR__);
$workDir   = $root . DIRECTORY_SEPARATOR . 'daily-drop';
$outputMd  = $root . DIRECTORY_SEPARATOR . 'daily-drop-output.md';
$outputJson = $workDir . DIRECTORY_SEPARATOR . 'output.json';

$errors   = [];
$warnings = [];

// ---------------------------------------------------------------------------
// Date from research.md (falls back to today)
// ---------------------------------------------------------------------------
$date = date('Y-m-d');
$researchFile = $workDir . DIRECTORY_SEPARATOR . 'research.md';
if (is_file($researchFile)) {
    if (preg_match('/^DATE:\s*(\S+)/m', (string) file_get_contents($researchFile), $m)) {
        $date = $m[1];
        if ($date !== date('Y-m-d')) {
            $warnings[] = "research.md DATE is {$date}, not today (" . date('Y-m-d') . ')';
        }
    }
} else {
    $warnings[] = 'daily-drop/research.md not found, using today\'s date';
}

// ---------------------------------------------------------------------------
// Collect product files
// ---------------------------------------------------------------------------
$files = glob($workDir . DIRECTORY_SEPARATOR . 'product-*.md') ?: [];
natsort($files);
$files = array_values($files);

if (! $files) {
    fwrite(STDERR, "ERROR: no daily-drop/product-*.md files found. Run /drop-write first.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Parsing
// ---------------------------------------------------------------------------
const SCALAR_KEYS = [
    'AUTHOR', 'TITLE', 'EXCERPT', 'TYPE', 'CATEGORY', 'TAGS', 'ASIN', 'RATING',
    'SEO_SCORE', 'META_TITLE', 'META_DESCRIPTION', 'FOCUS_KEYWORD', 'SLUG',
];
const LIST_KEYS = ['PROS', 'CONS'];

function parseBlock(string $block): array
{
    $post  = ['PROS' => [], 'CONS' => []];
    $lines = preg_split('/\r\n|\r|\n/', $block);
    $list  = null;

    foreach ($lines as $i => $line) {
        if (preg_match('/^([A-Z_]+):(.*)$/', $line, $m)) {
            $key = $m[1];
            if ($key === 'BODY') {
                $body = trim($m[2]) === '' ? '' : trim($m[2]) . "\n";
                $body .= implode("\n", array_slice($lines, $i + 1));
                $post['BODY'] = trim($body);
                break;
            }
            if (in_array($key, LIST_KEYS, true)) {
                $list = $key;
                continue;
            }
            if (in_array($key, SCALAR_KEYS, true)) {
                $post[$key] = trim($m[2]);
                $list = null;
                continue;
            }
        }
        if ($list !== null && preg_match('/^\s*-\s+(.+)$/', $line, $m)) {
            $post[$list][] = trim($m[1]);
        } elseif (trim($line) !== '') {
            $list = null;
        }
    }

    return $post;
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------
const BANNED_PHRASES = [
    'dive into', 'deep dive', "let's dive",
    'game-changer', 'game changer',
    "it's worth noting", 'worth noting',
    'seamlessly', 'seamless integration',
    'unleash', 'unlock your', 'elevate your',
    'cutting-edge',
    'at the end of the day', "in today's world", "in today's fast-paced",
    'look no further',
    'is designed to',
];

function len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function validatePost(array $p, string $label, array &$errors, array &$warnings): void
{
    if (($p['TITLE'] ?? '') === '' || ($p['BODY'] ?? '') === '') {
        $errors[] = "{$label}: missing required TITLE or BODY";
        return;
    }

    $titleLen = len($p['TITLE']);
    if ($titleLen < 50 || $titleLen > 65) {
        $warnings[] = "{$label}: TITLE is {$titleLen} chars (want 50-65)";
    }

    $excerptLen = len($p['EXCERPT'] ?? '');
    if ($excerptLen < 120 || $excerptLen > 155) {
        $warnings[] = "{$label}: EXCERPT is {$excerptLen} chars (want 120-155)";
    }

    if (len($p['META_TITLE'] ?? '') > 70) {
        $warnings[] = "{$label}: META_TITLE is " . len($p['META_TITLE']) . ' chars (max 70)';
    }

    $metaDescLen = len($p['META_DESCRIPTION'] ?? '');
    if ($metaDescLen < 120 || $metaDescLen > 155) {
        $warnings[] = "{$label}: META_DESCRIPTION is {$metaDescLen} chars (want 120-155)";
    }

    if (! preg_match('/^B0[A-Z0-9]{8}$/i', $p['ASIN'] ?? '')) {
        $warnings[] = "{$label}: ASIN '" . ($p['ASIN'] ?? '') . "' does not look like an Amazon ASIN";
    }

    $rating = (float) ($p['RATING'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        $warnings[] = "{$label}: RATING '" . ($p['RATING'] ?? '') . "' is not between 1 and 5";
    }

    if (empty($p['PROS'])) {
        $warnings[] = "{$label}: no PROS bullets";
    }
    if (empty($p['CONS'])) {
        $warnings[] = "{$label}: no CONS bullets";
    }

    $wordCount = str_word_count(strip_tags($p['BODY']));
    if ($wordCount < 500 || $wordCount > 1300) {
        $warnings[] = "{$label}: body is ~{$wordCount} words (want 600-1200)";
    }

    // Content scans: em dashes + banned phrases
    $scan = ($p['TITLE'] ?? '') . "\n" . ($p['EXCERPT'] ?? '') . "\n"
        . ($p['META_TITLE'] ?? '') . "\n" . ($p['META_DESCRIPTION'] ?? '') . "\n"
        . ($p['BODY'] ?? '');

    $emDashes = substr_count($scan, "\u{2014}");
    if ($emDashes > 0) {
        $warnings[] = "{$label}: contains {$emDashes} em dash(es), banned";
    }

    $scanLower = function_exists('mb_strtolower') ? mb_strtolower($scan) : strtolower($scan);
    foreach (BANNED_PHRASES as $phrase) {
        if (str_contains($scanLower, $phrase)) {
            $warnings[] = "{$label}: banned phrase \"{$phrase}\"";
        }
    }

    if (preg_match('/(^|[.!?]\s+)(Additionally|Furthermore),/m', $scan)) {
        $warnings[] = "{$label}: sentence opens with \"Additionally,\" or \"Furthermore,\" (banned)";
    }
}

// ---------------------------------------------------------------------------
// Build
// ---------------------------------------------------------------------------
$posts = [];

foreach ($files as $file) {
    $content = (string) file_get_contents($file);
    $blocks  = preg_split('/^===POST===\s*$/m', $content);
    $name    = basename($file);
    $n       = 0;

    foreach ($blocks as $block) {
        if (trim($block) === '') {
            continue;
        }
        $n++;
        $p     = parseBlock($block);
        $label = "{$name} post #{$n} (" . ($p['AUTHOR'] ?? 'unknown author') . ')';

        validatePost($p, $label, $errors, $warnings);

        $tags = array_values(array_filter(array_map('trim', explode('|', $p['TAGS'] ?? ''))));

        $posts[] = [
            'title'         => $p['TITLE'] ?? '',
            'excerpt'       => $p['EXCERPT'] ?? '',
            'body'          => $p['BODY'] ?? '',
            'type'          => ($p['TYPE'] ?? '') !== '' ? $p['TYPE'] : 'article',
            'author_name'   => $p['AUTHOR'] ?? '',
            'category_name' => $p['CATEGORY'] ?? '',
            'tag_names'     => $tags,
            'product_asin'  => strtoupper($p['ASIN'] ?? ''),
            'rating'        => (float) ($p['RATING'] ?? 0),
            'pros'          => $p['PROS'],
            'cons'          => $p['CONS'],
            'seo'           => [
                'score'            => (int) ($p['SEO_SCORE'] ?? 0),
                'meta_title'       => $p['META_TITLE'] ?? '',
                'meta_description' => $p['META_DESCRIPTION'] ?? '',
                'focus_keyword'    => $p['FOCUS_KEYWORD'] ?? '',
                'slug'             => $p['SLUG'] ?? '',
            ],
        ];
    }

    if ($n === 0) {
        $warnings[] = "{$name}: no ===POST=== blocks found";
    }
}

if ($errors) {
    fwrite(STDERR, "BUILD FAILED — fix these and re-run:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  ERROR: {$e}\n");
    }
    exit(1);
}

$json = json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

file_put_contents($outputJson, $json . "\n");
file_put_contents($outputMd, "# GadgetDrop Daily Drop — {$date}\n\n```json\n{$json}\n```\n");

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo 'Built ' . count($posts) . ' post(s) from ' . count($files) . " product file(s) — {$date}\n";
foreach ($posts as $post) {
    printf(
        "  %-18s %-9s seo:%-3d %s\n",
        $post['author_name'],
        '[' . $post['product_asin'] . ']',
        $post['seo']['score'],
        $post['title']
    );
}

if ($warnings) {
    echo "\n" . count($warnings) . " warning(s):\n";
    foreach ($warnings as $w) {
        echo "  WARN: {$w}\n";
    }
} else {
    echo "\nNo warnings.\n";
}

echo "\nOutput: daily-drop-output.md (paste the json block into /admin/daily-drop)\n";
echo "Raw array: daily-drop/output.json\n";
