<?php

/**
 * en-GB — English (United Kingdom).
 *
 * Generated from CLDR 49.0.0 (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/en-GB/delimiters.json.
 * Dash: New Hart’s Rules, 2nd ed., spaced en rule.
 */

return [
    'id' => 'en-GB',
    'aliases' => [],
    // Quote pairs, [opening, closing]. The first pair is used outermost, the
    // second inside it, then they alternate. They live only here; to depart from
    // CLDR on purpose, set PAIR_OVERRIDES in the generator and run it again.
    'quotes' => ['“', '”'],
    'nested' => ['‘', '’'],
    'apostrophe' => '’',
    // What `--` becomes: the mark, and whether it takes a space on each side.
    'dash' => ['mark' => '–', 'spaced' => true],
    'spacing' => [],
    // Named alternatives to the CLDR defaults above. A variant lists only the keys it changes.
    'variants' => [
        // New Hart’s Rules (Oxford University Press), 2nd ed.: single quotation marks outermost, double inside.
        'single-outer' => [
            'quotes' => ['‘', '’'],
            'nested' => ['“', '”'],
        ],
    ],
];
