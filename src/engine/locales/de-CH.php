<?php

/**
 * de-CH — German (Switzerland).
 *
 * Generated from CLDR 49.0.0 (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/de-CH/delimiters.json.
 * Dash: as de (Duden); no separate Swiss-German authority was consulted.
 */

return [
    'id' => 'de-CH',
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
    'variants' => [
        // Schweizerische Bundeskanzlei, Schreibweisungen, 2nd ed. 2013 (corrected 2015), section 2.1.1 (rules 202 and 203): guillemets « » for a quotation and half guillemets ‹ › for one inside it, a space outside each mark and none inside (read in the copy at https://kr-geschaefte.zug.ch/dokumente/11187/3468-2-17179_Beilage2-Schreibweisungen-Bund.pdf). The rule is for federal texts; CLDR 49.0.0 gives de-CH the German marks „…“ ‚…‘.
        'guillemets' => [
            'quotes' => ['«', '»'],
            'nested' => ['‹', '›'],
        ],
    ],
];
