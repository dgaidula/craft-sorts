<?php

namespace iceboxind\sorts\engine;

/**
 * An open element during the walk, as its text runs see it: the locale and rule
 * scope inherited from its ancestors, and whether its text is left alone.
 *
 * @internal
 */
final class Frame
{
    /** @var array|null the rules for this element’s text, set by the engine the first time it asks */
    public ?array $ruleSet = null;

    /** How many elements are open around this one, itself included. */
    public readonly int $depth;

    public function __construct(
        public readonly string $name,
        public readonly string $raw,
        public readonly ?Frame $parent,
        public readonly LocaleInterface $locale,
        public readonly bool $skip,
        /** Handles named by `data-sorts-rules` on this element and its ancestors, sorted, space-separated. */
        public readonly string $scope,
        /** The nearest enclosing element that is not inline. */
        public readonly ?string $block,
    ) {
        $this->depth = ($parent->depth ?? 0) + 1;
    }
}
