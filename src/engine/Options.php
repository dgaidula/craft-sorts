<?php

namespace iceboxind\sorts\engine;

/**
 * What a call to the engine is told. Immutable; {@see with()} returns a changed copy.
 *
 * `rules` maps a rule handle to true or false to override that rule’s
 * `defaultEnabled()`. Every rule has three states, and these are them (D864): true is on, false is off, and a
 * handle left out follows the preset, and with no preset its `defaultEnabled()`. False is a site’s veto of a rule
 * the preset turns on; true turns on a rule the preset leaves off. `ruleOptions` maps a handle to that rule’s own settings
 * (for example `widows` => ['tags' => ['h1', 'h2'], 'minWords' => 4]). `variants`
 * maps a locale id to the name of a variant to use for it (`['en-GB' => 'single-outer']`);
 * a name the locale does not have means the CLDR default.
 *
 * `quoteNesting` says which glyph a recognised quotation gets. `typed` (the default) keeps the
 * type the author typed for the outermost quotation: a straight `"` pair becomes the locale’s
 * primary pair and a straight `'` pair its secondary pair, as CKEditor does while typing. A
 * quotation inside another takes the other level from its parent whatever was typed, so the levels
 * alternate (`"She said "no""` is “She said ‘no’”). `depth` normalises every quotation by how
 * deeply it is nested: the primary pair outermost, the secondary inside it, then alternating,
 * whichever mark was typed, even for an outermost `'`. Any other value means `typed`.
 *
 * `preset` names a {@see Presets} set of choices that sits above the rule switches ('' for none, the
 * default). A rule in `rules` and a variant in `variants` win over it; see {@see Presets} for how they combine.
 *
 * `allowed`, when set, lists the only rules that may run at all: a rule outside it
 * stays off whatever turns it on (`rules`, a `data-sorts-rules` scope or a rule
 * named in the call). Null allows every registered rule.
 */
final class Options
{
    public const HTML = 'html';
    public const TEXT = 'text';

    public const NESTING_TYPED = 'typed';
    public const NESTING_DEPTH = 'depth';

    public const PRESET_NONE = '';
    public const PRESET_BRINGHURST = Presets::BRINGHURST;

    /**
     * @param array<string, bool> $rules
     * @param array<string, array<string, mixed>> $ruleOptions
     * @param list<string> $skipTags extra tag names whose contents are left alone
     * @param list<string> $skipClasses extra class names whose elements are left alone
     * @param array<string, string> $variants locale id => variant name
     * @param list<string>|null $allowed handles of the only rules that may run; null for every rule
     */
    public function __construct(
        public readonly string $locale = LocaleRegistry::FALLBACK,
        public readonly string $mode = self::HTML,
        public readonly array $rules = [],
        public readonly array $ruleOptions = [],
        public readonly array $skipTags = [],
        public readonly array $skipClasses = [],
        public readonly array $variants = [],
        public readonly ?array $allowed = null,
        public readonly string $quoteNesting = self::NESTING_TYPED,
        public readonly string $preset = self::PRESET_NONE,
    ) {
    }

    /** A copy with the named constructor arguments replaced. */
    public function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
