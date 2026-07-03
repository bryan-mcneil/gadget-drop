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
        // Near-miss slugs the importer's firstOrCreate resurrected after the
        // original merge (the map only knew `tvs`/`audio-home-theater`).
        'tv'           => 'audio',
        'home-theater' => 'audio',
        // Slug alias: the display name "Audio & Home Theater" slugifies to
        // audio-home-theater, but the canonical slug stays `audio`.
        'audio-home-theater' => 'audio',
        // Slug alias for the "Computers & Accessories" display name.
        'computers-accessories' => 'computers',
    ],

    /*
    | Display renames applied to surviving categories during consolidation
    | (slug stays stable so indexed URLs keep working).
    */
    'category_renames' => [
        'audio'     => 'Audio & Home Theater',
        'computers' => 'Computers & Accessories',
    ],

    /*
    |--------------------------------------------------------------------------
    | Item-level re-homes (post-consolidation cleanup)
    |--------------------------------------------------------------------------
    | The bulk merge left individual items in the wrong hub (wearables dumped
    | into Computers, a record player stand likewise). Consumed by
    | categories:rehome (App\Console\Commands\RehomeCategoryItems), which moves
    | the product AND swaps the category on its linked posts. Keyed by exact
    | product name — stable across dev/prod, unlike IDs.
    */
    'category_rehome' => [
        'Google Fitbit Air'      => 'wearables',
        'Garmin Forerunner 165'  => 'wearables',
        'Garmin Forerunner® 170' => 'wearables',
        'RingConn Gen 2'         => 'wearables',
        'Record Player Stand'    => 'audio',
    ],

    /*
    | Category attachments for posts with no product to follow (tech tips and
    | news that were never categorized). Post slug => category slug; attach
    | only, nothing is detached. Also consumed by categories:rehome.
    */
    'category_rehome_posts' => [
        'learn-new-tools-or-master-the-fundamental' => 'computers',
        'computer-tricks-most-people-dont-know'     => 'computers',
        'personal-cybersecurity-best-practices'     => 'computers',
        'sysadmin-tips-and-tricks-that-save-time'   => 'computers',
        'hidden-iphone-hacks'                       => 'computers',
        'underrated-open-source-tools'              => 'computers',
        'new-pokemon-pitch-black-set'               => 'gaming',
    ],
];
