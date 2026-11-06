<?php

/**
 * nl — Dutch.
 *
 * Generated from CLDR 49.0.0 (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/nl/delimiters.json.
 * Dash: Taalunie, Woordenlijst and Schrijfwijzer, spaced en dash.
 */

return [
    'id' => 'nl',
    'aliases' => [],
    // Quote pairs, [opening, closing]. The first pair is used outermost, the
    // second inside it, then they alternate. They live only here; to depart from
    // CLDR on purpose, set PAIR_OVERRIDES in the generator and run it again.
    'quotes' => ['‘', '’'],
    'nested' => ['‘', '’'],
    'apostrophe' => '’',
    // What `--` becomes: the mark, and whether it takes a space on each side.
    'dash' => ['mark' => '–', 'spaced' => true],
    'spacing' => [],
    // Named alternatives to the CLDR defaults above. A variant lists only the keys it changes.
    'variants' => [
        // Taalunie, taaladvies.net, “Dubbele of enkele aanhalingstekens bij een citaat” (12 May 2021, https://taaladvies.net/dubbele-of-enkele-aanhalingstekens-bij-een-citaat/): there is no fixed rule, but a traditional preference for double marks around a literal quotation, single marks inside it. CLDR 49.0.0 gives Dutch the single marks ‘…’ at both levels, which stay the default.
        'double-outer' => [
            'quotes' => ['“', '”'],
            'nested' => ['‘', '’'],
        ],
    ],
];
