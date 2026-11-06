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

        // Style per language. CLDR is the default, except German, which is book style »…«
        // (Duden). Variants: en-GB 'single-outer' (New Hart's Rules), en-US 'spaced-en-dash'
        // (Bringhurst, 5.2.1: a spaced en dash for breaks), de 'low-high' (CLDR's „…“),
        // de-CH 'guillemets' (Schreibweisungen der Bundeskanzlei), es 'guillemets-outer'
        // (RAE), nl 'double-outer' (Taalunie).
        'variants' => [
            // 'en-GB' => 'single-outer',
        ],

        // Off: an outermost quotation typed with " gets the language's main quotation marks
        // and one typed with ' its inner marks, and a quotation inside another gets the other
        // pair. On: every quotation is set by its nesting depth, whichever mark was typed.
        'normalizeQuotes' => false,

        // Fine typography after Robert Bringhurst, *The Elements of Typographic Style*, 3rd ed.,
        // chapter 5: a spaced en dash for breaks (5.2.1), a dash and a thin space opening
        // dialogue (5.2.3), ranges including clock times (5.2.2), fractions joined to their whole
        // number (5.2.5), the multiplication sign (5.2.6), spaced ellipses in English (5.2.7)
        // and thin spaces inside guillemets (5.4.1). A rule switch you have moved off its default
        // still wins; one left at its default follows this setting.
        'fineTypography' => false,

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
