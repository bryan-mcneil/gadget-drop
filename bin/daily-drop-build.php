<?php

/**
 * Daily Drop build script.
 *
 * Parses the pipeline's structured markdown files in daily-drop/:
 *   product-*.md  (reviews,   /drop-write,  TYPE article)
 *   tip-*.md      (tech tips, /drop-tip,    TYPE tech_tip)
 *   news-*.md     (tech news, /drop-news,   TYPE tech_news)
 * and assembles the final JSON array for `php artisan posts:import`
 * (or the /admin/daily-drop Import page as browser fallback).
 *
 * Writes: daily-drop/output.json (raw JSON array).
 *
 * Plain PHP, no Laravel boot. Run with: php bin/daily-drop-build.php
 * An alternate working directory can be passed as the first argument
 * (used by the test suite): php bin/daily-drop-build.php path/to/dir
 * Exit code 0 = built (warnings allowed), 1 = hard error (nothing written).
 */
$root = dirname(__DIR__);
$workDir = $argv[1] ?? $root.DIRECTORY_SEPARATOR.'daily-drop';
$outputJson = $workDir.DIRECTORY_SEPARATOR.'output.json';

$errors = [];
$warnings = [];

// ---------------------------------------------------------------------------
// Date from research.md (falls back to today)
// ---------------------------------------------------------------------------
$date = date('Y-m-d');
$researchFile = $workDir.DIRECTORY_SEPARATOR.'research.md';
if (is_file($researchFile)) {
    if (preg_match('/^DATE:\s*(\S+)/m', (string) file_get_contents($researchFile), $m)) {
        $date = $m[1];
        if ($date !== date('Y-m-d')) {
            $warnings[] = "research.md DATE is {$date}, not today (".date('Y-m-d').')';
        }
    }
} else {
    $warnings[] = 'daily-drop/research.md not found, using today\'s date';
}

// ---------------------------------------------------------------------------
// Collect content files
// ---------------------------------------------------------------------------
$files = array_merge(
    glob($workDir.DIRECTORY_SEPARATOR.'product-*.md') ?: [],
    glob($workDir.DIRECTORY_SEPARATOR.'tip-*.md') ?: [],
    glob($workDir.DIRECTORY_SEPARATOR.'news-*.md') ?: [],
);
natsort($files);
$files = array_values($files);

if (! $files) {
    fwrite(STDERR, "ERROR: no daily-drop/product-*.md, tip-*.md, or news-*.md files found. Run /drop-write, /drop-tip, or /drop-news first.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Parsing
// ---------------------------------------------------------------------------
const SCALAR_KEYS = [
    'AUTHOR', 'TITLE', 'EXCERPT', 'TYPE', 'CATEGORY', 'TAGS', 'ASIN', 'RATING',
    'SOURCE_URL', 'SEO_SCORE', 'META_TITLE', 'META_DESCRIPTION', 'FOCUS_KEYWORD', 'SLUG',
    'TARGET_QUERY',
];
const LIST_KEYS = ['PROS', 'CONS'];

const VALID_TYPES = ['article', 'tech_tip', 'tech_news'];

// Per-type body word-count targets (warn outside the range). Floors sit well
// above thin-content territory on purpose — see CONTENT-GUIDELINES.md.
const WORD_RANGES = [
    'article' => [800, 1600, '900-1500'],
    'tech_tip' => [600, 1000, '600-1000'],
    'tech_news' => [600, 900,  '600-900'],
];

function parseBlock(string $block): array
{
    $post = ['PROS' => [], 'CONS' => []];
    $lines = preg_split('/\r\n|\r|\n/', $block);
    $list = null;

    foreach ($lines as $i => $line) {
        if (preg_match('/^([A-Z_]+):(.*)$/', $line, $m)) {
            $key = $m[1];
            if ($key === 'BODY') {
                $body = trim($m[2]) === '' ? '' : trim($m[2])."\n";
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

// Phrases that claim first-hand testing the site doesn't do. Reviews are
// research-based (see /how-we-review) — mirror of config/content.php
// 'testing_claim_phrases' (this script runs without Laravel, so keep the two
// lists in sync by hand when editing either).
const TESTING_CLAIM_PHRASES = [
    'we tested', 'i tested', 'we test ', 'our testing', 'our tests',
    'after testing', 'weeks of testing', 'days of testing',
    'we measured', 'i measured', 'our measurements', 'reviewer measurements',
    'we benchmarked', 'our benchmarks', 'in our lab', 'our lab',
    'hands-on test', 'we put it through', 'we ran it', 'we ran the',
    'independent testing', "we've been using", "i've been using",
    'in my testing', 'in our testing', 'during testing, we',
    'during our review, we found',
];

// The one real author (mirror of config/site.php 'author.name').
const SITE_AUTHOR = 'Bryan McNeil';

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

    $type = ($p['TYPE'] ?? '') !== '' ? $p['TYPE'] : 'article';
    if (! in_array($type, VALID_TYPES, true)) {
        $errors[] = "{$label}: unknown TYPE '{$type}' (expected article, tech_tip, or tech_news)";

        return;
    }
    $isReview = $type === 'article';

    // -- Shared checks (all types) --------------------------------------
    $titleLen = len($p['TITLE']);
    if ($titleLen < 50 || $titleLen > 65) {
        $warnings[] = "{$label}: TITLE is {$titleLen} chars (want 50-65)";
    }

    $excerptLen = len($p['EXCERPT'] ?? '');
    if ($excerptLen < 120 || $excerptLen > 155) {
        $warnings[] = "{$label}: EXCERPT is {$excerptLen} chars (want 120-155)";
    }

    if (len($p['META_TITLE'] ?? '') > 70) {
        $warnings[] = "{$label}: META_TITLE is ".len($p['META_TITLE']).' chars (max 70)';
    }

    $metaDescLen = len($p['META_DESCRIPTION'] ?? '');
    if ($metaDescLen < 120 || $metaDescLen > 155) {
        $warnings[] = "{$label}: META_DESCRIPTION is {$metaDescLen} chars (want 120-155)";
    }

    if (($p['AUTHOR'] ?? '') !== SITE_AUTHOR) {
        $warnings[] = "{$label}: AUTHOR '".($p['AUTHOR'] ?? '')."' is not '".SITE_AUTHOR."' (single real byline — personas are retired)";
    }

    [$min, $max, $want] = WORD_RANGES[$type];
    $wordCount = str_word_count(strip_tags($p['BODY']));
    if ($wordCount < $min || $wordCount > $max) {
        $warnings[] = "{$label}: body is ~{$wordCount} words (want {$want} for {$type})";
    }

    if (preg_match('~\]\(https?://(www\.)?amazon\.~i', $p['BODY'])) {
        $warnings[] = "{$label}: raw Amazon link in body — the product card is the single affiliate CTA; drop in-body Amazon links";
    }

    // -- Per-type checks --------------------------------------------------
    if ($isReview) {
        if (! preg_match('/^B0[A-Z0-9]{8}$/i', $p['ASIN'] ?? '')) {
            $warnings[] = "{$label}: ASIN '".($p['ASIN'] ?? '')."' does not look like an Amazon ASIN";
        }

        $rating = (float) ($p['RATING'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $warnings[] = "{$label}: RATING '".($p['RATING'] ?? '')."' is not between 1 and 5";
        }

        if (empty($p['PROS'])) {
            $warnings[] = "{$label}: no PROS bullets";
        }
        if (empty($p['CONS'])) {
            $warnings[] = "{$label}: no CONS bullets";
        }

        if (! str_contains($p['BODY'], '](/posts/')) {
            $warnings[] = "{$label}: no internal review link in body (the How-it-compares section should link 1-2 alternatives)";
        }
    } else {
        // Tips and news attribute a real source; the importer stores it in
        // posts.source_url and the public page renders the attribution footer.
        if (! preg_match('~^https?://~i', $p['SOURCE_URL'] ?? '')) {
            $errors[] = "{$label}: {$type} requires SOURCE_URL (http/https link to the source)";
        }

        foreach (['ASIN', 'RATING'] as $reviewKey) {
            if (($p[$reviewKey] ?? '') !== '') {
                $warnings[] = "{$label}: {$reviewKey} set on a {$type} (review-only field, will be ignored)";
            }
        }
        if (! empty($p['PROS']) || ! empty($p['CONS'])) {
            $warnings[] = "{$label}: PROS/CONS set on a {$type} (review-only fields, will be ignored)";
        }

        if ($type === 'tech_news' && ! preg_match('/^##\s+Buy or Wait\?/mi', $p['BODY'])) {
            $errors[] = "{$label}: tech_news body must contain a \"## Buy or Wait?\" section (the format's signature)";
        }
    }

    // -- Content scans: em dashes + banned phrases ------------------------
    $scan = ($p['TITLE'] ?? '')."\n".($p['EXCERPT'] ?? '')."\n"
        .($p['META_TITLE'] ?? '')."\n".($p['META_DESCRIPTION'] ?? '')."\n"
        .($p['BODY'] ?? '');

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

    foreach (TESTING_CLAIM_PHRASES as $phrase) {
        if (str_contains($scanLower, $phrase)) {
            $warnings[] = "{$label}: testing claim \"{$phrase}\" — reviews are research-based; rewrite to owner/spec framing";
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
    $blocks = preg_split('/^===POST===\s*$/m', $content);
    $name = basename($file);
    $n = 0;

    foreach ($blocks as $block) {
        if (trim($block) === '') {
            continue;
        }
        $n++;
        $p = parseBlock($block);
        $label = "{$name} post #{$n} (".($p['AUTHOR'] ?? 'unknown author').')';

        validatePost($p, $label, $errors, $warnings);

        $type = ($p['TYPE'] ?? '') !== '' ? $p['TYPE'] : 'article';
        $tags = array_values(array_filter(array_map('trim', explode('|', $p['TAGS'] ?? ''))));

        $post = [
            'title' => $p['TITLE'] ?? '',
            'excerpt' => $p['EXCERPT'] ?? '',
            'body' => $p['BODY'] ?? '',
            'type' => $type,
            'author_name' => $p['AUTHOR'] ?? '',
            'category_name' => $p['CATEGORY'] ?? '',
            'tag_names' => $tags,
            'seo' => [
                'score' => (int) ($p['SEO_SCORE'] ?? 0),
                'meta_title' => $p['META_TITLE'] ?? '',
                'meta_description' => $p['META_DESCRIPTION'] ?? '',
                'focus_keyword' => $p['FOCUS_KEYWORD'] ?? '',
                'target_query' => $p['TARGET_QUERY'] ?? '',
                'slug' => $p['SLUG'] ?? '',
            ],
        ];

        if ($type === 'article') {
            $post['product_asin'] = strtoupper($p['ASIN'] ?? '');
            $post['rating'] = (float) ($p['RATING'] ?? 0);
            $post['pros'] = $p['PROS'];
            $post['cons'] = $p['CONS'];
        } else {
            $post['source_url'] = $p['SOURCE_URL'] ?? '';
        }

        $posts[] = $post;
    }

    if ($n === 0) {
        $warnings[] = "{$name}: no ===POST=== blocks found";
    } elseif ($n > 1) {
        $warnings[] = "{$name}: {$n} ===POST=== blocks — the pipeline is one post per file";
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

file_put_contents($outputJson, $json."\n");

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo 'Built '.count($posts).' post(s) from '.count($files)." file(s) — {$date}\n";
foreach ($posts as $post) {
    printf(
        "  %-9s %-14s seo:%-3d %s\n",
        $post['type'],
        '['.($post['product_asin'] ?? 'no product').']',
        $post['seo']['score'],
        $post['title']
    );
}

if ($warnings) {
    echo "\n".count($warnings)." warning(s):\n";
    foreach ($warnings as $w) {
        echo "  WARN: {$w}\n";
    }
} else {
    echo "\nNo warnings.\n";
}

echo "\nOutput: daily-drop/output.json\n";
echo "Import: php artisan posts:import   (or paste into /admin/daily-drop when away from the CLI)\n";
