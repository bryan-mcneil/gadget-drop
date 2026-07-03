<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Site author / editor
    |--------------------------------------------------------------------------
    | The single, real, accountable person behind GadgetDrop. The byline, the
    | author page, the About copy and the author-consolidation command all read
    | from here so there is exactly one source of truth. Override in .env if your
    | name / slug / email differ from the defaults below.
    */
    'author' => [
        'name'  => env('SITE_AUTHOR_NAME', 'Bryan McNeil'),
        'slug'  => env('SITE_AUTHOR_SLUG', 'bryan-mcneil'),
        'email' => env('SITE_AUTHOR_EMAIL', 'hello@gadgetdrop.tech'),
        // Public profile URLs (X/Twitter, LinkedIn, GitHub, YouTube, …) emitted
        // as Person sameAs in JSON-LD so raters/search can verify the author is
        // a real person. Comma-separated in SITE_AUTHOR_SAMEAS.
        'same_as' => array_values(array_filter(array_map('trim', explode(',', env('SITE_AUTHOR_SAMEAS', ''))))),
    ],

    /*
    | Old fictional-persona author slugs. These were retired when authorship was
    | consolidated to one real person; they 301-redirect to the real author so
    | previously-indexed URLs don't 404. See App\Console\Commands\ConsolidateAuthor.
    */
    'legacy_author_slugs' => ['maya-reeves', 'ken-fujimoto', 'elizabeth-avery', 'sam-johnson'],

    /*
    |--------------------------------------------------------------------------
    | Category consolidation map (source slug => target slug)
    |--------------------------------------------------------------------------
    | Twelve ~3-post categories looked like thin doorway pages to the AdSense
    | review, so they were merged into six real hubs. Consumed by:
    |  - routes/web.php            → 301s for the retired category URLs
    |  - categories:consolidate    → one-time re-pivot (App\Console\Commands)
    |  - DailyDropImporterService  → keeps imports from resurrecting dead slugs
    | After running the command, write an 80-150 word description for each
    | surviving category in /admin/categories.
    */
    'category_map' => [
        'appliances'   => 'smart-home',
        'security'     => 'smart-home',
        'monitors'     => 'computers',
        'tablets'      => 'computers',
        'productivity' => 'computers',
        'accessories'  => 'computers',
        'tvs'          => 'audio',
        'photography'  => 'cameras',
        // Slug alias: the display name "Audio & Home Theater" slugifies to
        // audio-home-theater, but the canonical slug stays `audio`.
        'audio-home-theater' => 'audio',
    ],

    /*
    | Display renames applied to surviving categories during consolidation
    | (slug stays stable so indexed URLs keep working).
    */
    'category_renames' => [
        'audio' => 'Audio & Home Theater',
    ],
];
