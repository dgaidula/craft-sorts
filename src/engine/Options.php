<?php

namespace iceboxind\sorts\engine;

/**
 * What a call to the engine is told. Immutable; {@see with()} returns a changed copy.
 *
 * `rules` maps a rule handle to true or false to override that rule’s
 * `defaultEnabled()`. `ruleOptions` maps a handle to that rule’s own settings
 * (for example `widows` => ['tags' => ['h1', 'h2'], 'minWords' => 4]). `variants`
 * maps a locale id to the name of a variant to use for it (`['en-GB' => 'single-outer']`);
 * a name the locale does not have means the CLDR default.
 */
final class Options
{
    public const HTML = 'html';
    public const TEXT = 'text';

    /**
     * @param array<string, bool> $rules
     * @param array<string, array<string, mixed>> $ruleOptions
     * @param list<string> $skipTags extra tag names whose contents are left alone
     * @param list<string> $skipClasses extra class names whose elements are left alone
     * @param array<string, string> $variants locale id => variant name
     */
    public function __construct(
        public readonly string $locale = LocaleRegistry::FALLBACK,
        public readonly string $mode = self::HTML,
        public readonly array $rules = [],
        public readonly array $ruleOptions = [],
        public readonly array $skipTags = [],
        public readonly array $skipClasses = [],
        public readonly array $variants = [],
    ) {
    }

    /** A copy with the named constructor arguments replaced. */
    public function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
