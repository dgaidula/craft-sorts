<?php

/**
 * de — German.
 *
 * Generated from CLDR 49.0.0 (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/de/delimiters.json.
 * Departs from CLDR: Dan Gaidula’s taste (decision D844, 2026-11-06): German book style »…« ›…‹ is the default; CLDR 49.0.0 gives German „…“ ‚…‘, which stays available as the variant low-high. The only place Sorts departs from CLDR by default.
 * Dash: Duden, Gedankenstrich as a spaced Halbgeviertstrich.
 */

return [
    'id' => 'de',
    'aliases' => [],
    // Quote pairs, [opening, closing]. The first pair is used outermost, the
    // second inside it, then they alternate. They live only here; to depart from
    // CLDR on purpose, set PAIR_OVERRIDES in the generator and run it again.
    'quotes' => ['»', '«'],
    'nested' => ['›', '‹'],
    'apostrophe' => '’',
    // What `--` becomes: the mark, and whether it takes a space on each side.
    'dash' => ['mark' => '–', 'spaced' => true],
    'spacing' => [],
    // Named alternatives to the default above (which departs from CLDR, see the header). A variant lists only the keys it changes.
    'variants' => [
        // Unicode CLDR 49.0.0, main/de/delimiters.json: the low-high marks „…“ for a quotation and ‚…‘ for one inside it (Gänsefüßchen), as CLDR gives German. Not the default since D844: the default is the book style »…« ›…‹ (Dan Gaidula, typographer; Duden Band 9, Richtiges und gutes Deutsch, 5th ed. 2001, via a secondary source), which sets no space inside either mark.
        'low-high' => [
            'quotes' => ['„', '“'],
            'nested' => ['‚', '‘'],
        ],
    ],
];
