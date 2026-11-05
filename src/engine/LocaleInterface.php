<?php

namespace iceboxind\sorts\engine;

/**
 * One locale’s typography data: quote pairs, dash style and spacing. Rules read
 * a locale; they never branch on its id. Adding a locale means adding a file to
 * `locales/` (see {@see LocaleRegistry}).
 */
interface LocaleInterface
{
    /** The BCP 47 id, for example `de-CH`. */
    public function id(): string;

    /** Other language tags that resolve to this locale, for example `no` for `nb`. */
    public function aliases(): array;

    /** Primary quote pair, [opening, closing]. */
    public function quotes(): array;

    /** Pair used inside the primary pair, [opening, closing]. */
    public function nestedQuotes(): array;

    public function apostrophe(): string;

    /** What `--` becomes: ['mark' => string, 'spaced' => bool]. */
    public function dash(): array;

    /**
     * Names of the variants this locale offers. The data in the locale is CLDR’s;
     * a variant is another convention (a style authority’s) that a caller can choose.
     *
     * @return list<string>
     */
    public function variants(): array;

    /** This locale with the named variant applied. An unknown name returns the locale itself. */
    public function withVariant(string $name): LocaleInterface;

    /** Spacing rules. Empty for most locales; French sets `before` and `guillemets`. */
    public function spacing(): array;
}
