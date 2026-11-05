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

    /** @var list<array{int, int, string}> changed text, in input order: [offset, length, new text] */
    public array $edits = [];

    /** Why the call gave its input back unchanged, when a pattern failed; null otherwise. */
    public ?string $error = null;

    /** @param array<string, true> $named */
    public function __construct(
        public readonly Options $options,
        public readonly array $named,
        public LocaleInterface $locale,
    ) {
    }
}
