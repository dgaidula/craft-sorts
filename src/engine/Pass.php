<?php

namespace iceboxind\sorts\engine;

/**
 * The state of one call to {@see Engine::apply()}. Kept apart from the engine
 * so a rule that calls the engine again does not disturb the call it is in.
 *
 * @internal
 */
final class Pass
{
    public bool $changed = false;

    /** @var array<string, array> rule sets by locale and scope, see Engine::ruleSet() */
    public array $ruleSets = [];

    /** @var array<string, LocaleInterface> locales with their variant applied, by id and variant */
    public array $variantLocales = [];

    /** @var array<int, LocaleInterface> locales with the preset’s spacing, by the object they came from */
    public array $presetLocales = [];

    /** @var array<string, bool> what the preset says about each rule it names; empty with no preset */
    public array $presetRules = [];

    /** @var array<string, string> the preset’s variant for each locale it names; empty with no preset */
    public array $presetVariants = [];

    /** @var list<array{int, int, string}> changed text, in input order: [offset, length, new text] */
    public array $edits = [];

    /** @var list<Finding>|null set by {@see Engine::findings()}: the pass reads and collects instead of editing */
    public ?array $findings = null;

    /** Why the call gave its input back unchanged, when a pattern failed; null otherwise. */
    public ?string $error = null;

    /** @param array<string, true> $named */
    public function __construct(
        public readonly Options $options,
        public readonly array $named,
        public LocaleInterface $locale,
    ) {
        $this->presetRules = Presets::rules($options->preset);
        $this->presetVariants = Presets::variants($options->preset);
    }
}
