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
    ],

    /*
    | Old fictional-persona author slugs. These were retired when authorship was
    | consolidated to one real person; they 301-redirect to the real author so
    | previously-indexed URLs don't 404. See App\Console\Commands\ConsolidateAuthor.
    */
    'legacy_author_slugs' => ['maya-reeves', 'ken-fujimoto', 'elizabeth-avery', 'sam-johnson'],
];
