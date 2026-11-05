<?php

namespace iceboxind\sorts\engine;

/**
 * The text of one paragraph-like stretch: everything between two boundaries
 * (a block element’s start or end, a line break), with inline tags looked
 * through. Quote direction and widows are decided on a flow, not a single run.
 *
 * @internal
 */
final class Flow
{
    /**
     * Segments in order: [token index, text, editable, frame, prefix, suffix].
     * Prefix and suffix are the markup the engine writes back around the text.
     *
     * @var list<array{0: int, 1: string, 2: bool, 3: Frame, 4: string, 5: string}>
     */
    public array $segments = [];

    /** @var array<int, string> each segment’s text once its rules have run */
    public array $done = [];

    /** @var array<string, mixed> scratch space shared by rules, keyed by the rule */
    public array $state = [];

    /** @var array<string, true> */
    public array $claims = [];

    public function __construct(public readonly ?string $block)
    {
    }
}
