<?php

return [
    /*
    |--------------------------------------------------------------------------
    | First-hand-testing claim phrases
    |--------------------------------------------------------------------------
    | GadgetDrop reviews are research-based (see /how-we-review). These phrases
    | imply physical testing that didn't happen, so published posts must not
    | contain them. `content:flag-claims` scans for the list; the daily-drop
    | pipeline validator (bin/daily-drop-build.php) carries a mirrored copy —
    | that script is plain PHP with no Laravel boot, so keep the two in sync
    | by hand when editing either.
    */
    'testing_claim_phrases' => [
        'we tested',
        'i tested',
        'we test ',
        'our testing',
        'our tests',
        'after testing',
        'weeks of testing',
        'days of testing',
        'we measured',
        'i measured',
        'our measurements',
        'reviewer measurements',
        'we benchmarked',
        'our benchmarks',
        'in our lab',
        'our lab',
        'hands-on test',
        'we put it through',
        'we ran it',
        'we ran the',
        'independent testing',
        "we've been using",
        "i've been using",
        'in my testing',
        'in our testing',
        'during testing, we',
        'during our review, we found',
    ],
];
