<?php

/**
 * fr — French.
 *
 * Generated from CLDR 49.0.0 (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/fr/delimiters.json.
 * Dash: Imprimerie nationale, Lexique des règles typographiques, spaced demi-cadratin. Spacing: the same, a narrow no-break space before ; ! ? and inside guillemets, a no-break space before the colon (the OECD French style guide also asks for a non-breaking space there).
 */

return [
    'id' => 'fr',
    'aliases' => [],
    // Quote pairs, [opening, closing]. The first pair is used outermost, the
    // second inside it, then they alternate. They live only here; to depart from
    // CLDR on purpose, set PAIR_OVERRIDES in the generator and run it again.
    'quotes' => ['«', '»'],
    'nested' => ['«', '»'],
    'apostrophe' => '’',
    // What `--` becomes: the mark, and whether it takes a space on each side.
    'dash' => ['mark' => '–', 'spaced' => true],
    'spacing' => [
        'before' => [
            ';' => "\u{202F}",
            '!' => "\u{202F}",
            '?' => "\u{202F}",
            ':' => "\u{00A0}",
        ],
        'guillemets' => "\u{202F}",
    ],
    // Named alternatives to the CLDR defaults above. A variant lists only the keys it changes.
    'variants' => [],
];
