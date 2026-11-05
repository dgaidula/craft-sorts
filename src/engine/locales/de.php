<?php

/**
 * de — German.
 *
 * Generated from CLDR 49.0.0 (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/de/delimiters.json.
 * Dash: Duden, Gedankenstrich as a spaced Halbgeviertstrich.
 */

return [
    'id' => 'de',
    'aliases' => [],
    // Quote pairs, [opening, closing]. The first pair is used outermost, the
    // second inside it, then they alternate. They live only here; to depart from
    // CLDR on purpose, set PAIR_OVERRIDES in the generator and run it again.
    'quotes' => ['„', '“'],
    'nested' => ['‚', '‘'],
    'apostrophe' => '’',
    // What `--` becomes: the mark, and whether it takes a space on each side.
    'dash' => ['mark' => '–', 'spaced' => true],
    'spacing' => [],
    // Named alternatives to the CLDR defaults above. A variant lists only the keys it changes.
    'variants' => [],
];
