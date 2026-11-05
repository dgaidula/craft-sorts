<?php

/**
 * Sorts config override example.
 *
 * Copy to config/sorts.php in the host site to override Settings values
 * per environment. This file is a template only — it is NOT loaded by Craft
 * and ships purely as documentation; delete or keep it as you like.
 *
 * Anything set here overrides the plugin's saved Settings (project config) for
 * the current environment. A setting set here shows locked in the control panel.
 * Everything below `pluginName` and `ckeditorAsYouType` is a Pro setting; Lite
 * stores it and does not read it.
 *
 * @see \iceboxind\sorts\models\Settings
 */

return [
    // Global overrides, applied in every environment unless overridden below.
    '*' => [
        // Relabels the plugin in the CP (sidebar, Plugins screen, settings
        // breadcrumb). Blank keeps "Sorts".
        'pluginName' => '',

        // Lite. Convert quotes as people type in CKEditor fields.
        'ckeditorAsYouType' => true,

        // The automatic whole-page pass, and whether it also sets <title> and the
        // description, Open Graph and Twitter <meta> text.
        'pagePass' => true,
        'processHead' => false,

        // The largest page, in bytes, the page pass sets. A larger page is sent as
        // rendered, with a warning in the log; so is one the pass might not have the
        // memory for under PHP's memory_limit.
        'maxPageBytes' => 1000000,

        // Rule switches by handle. Omitted handles keep their defaults:
        // on: quotes, apostrophes, primes, dashes, ellipsis, symbols, widows, nbsp, locale-spacing
        // off: ranges, multiplication, fractions, ordinals, hook-amp, hook-caps, hook-quotes
        'rules' => [
            // 'fractions' => true,
            // 'ranges' => true,
        ],

        // The fewest words a heading needs before its last two words are joined.
        'widowsMinWords' => 3,

        // Quotation style per language. CLDR is the default; en-GB can use single
        // quotation marks outermost (New Hart's Rules).
        'variants' => [
            // 'en-GB' => 'single-outer',
        ],

        // Exclusions: tag names, class names, and request paths (`*` matches any run
        // of characters) the page pass leaves alone.
        'skipTags' => [],
        'skipClasses' => [],
        'skipUris' => [
            // '/api/*',
        ],

        // Fix stored content as elements are saved (off by default), and which kinds
        // of value it and the audit utility cover. `fixOnSaveBulk` also runs it for
        // resaves and while migrations run.
        'fixOnSave' => false,
        'fixTitles' => true,
        'fixPlainText' => true,
        'fixCkeditor' => true,
        'fixOnSaveBulk' => false,
    ],

    // Dev-only overrides — this branch is skipped in staging/production.
    'dev' => [
        // 'pagePass' => false,
    ],
];
