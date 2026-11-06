<?php

/**
 * es — Spanish.
 *
 * Generated from CLDR 49.0.0 (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/es/delimiters.json.
 * Dash: Real Academia Española, Ortografía de la lengua española (2010), raya closed up to the words it encloses.
 */

return [
    'id' => 'es',
    'aliases' => [],
    // Quote pairs, [opening, closing]. The first pair is used outermost, the
    // second inside it, then they alternate. They live only here; to depart from
    // CLDR on purpose, set PAIR_OVERRIDES in the generator and run it again.
    'quotes' => ['“', '”'],
    'nested' => ['‘', '’'],
    'apostrophe' => '’',
    // What `--` becomes: the mark, and whether it takes a space on each side.
    'dash' => ['mark' => '—', 'spaced' => false],
    'spacing' => [],
    // Named alternatives to the CLDR defaults above. A variant lists only the keys it changes.
    'variants' => [
        // Real Academia Española, Ortografía de la lengua española (2010), chapter III, “Las comillas” (https://www.rae.es/ortografía/las-comillas): in print, the angular marks « » first, then the English marks “ ”, then the single marks ‘ ’ for a quotation inside a quotation. Not read at rae.es (it refused automated fetches): the order is as the Universidad de Piura’s Castellano actual quotes it from the RAE Libro de estilo de la lengua española (2018), p. 120.
        'guillemets-outer' => [
            'quotes' => ['«', '»'],
            'nested' => ['“', '”'],
            'third' => ['‘', '’'],
        ],
    ],
];
