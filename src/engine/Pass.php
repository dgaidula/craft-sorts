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

    /** @param array<string, true> $named */
    public function __construct(
        public readonly Options $options,
        public readonly array $named,
        public LocaleInterface $locale,
    ) {
    }
}
