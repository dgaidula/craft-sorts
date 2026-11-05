<?php

namespace iceboxind\sorts\engine;

/**
 * The text of one paragraph-like stretch: everything between two boundaries
 * (a block element’s start or end, a line break), with inline tags looked
 * through. Quote direction and widows are decided on a flow, not a single run.
 *
 * The segments are kept in four parallel lists rather than an array each, so a
 * page of many short runs costs a few slots per run, not a whole array.
 *
 * @internal
 */
final class Flow
{
    /** @var list<string> each segment’s text, as the page had it */
    public array $texts = [];

    /** @var list<int> where each segment starts in the input, in bytes (-1 for an object’s stand-in) */
    public array $offsets = [];

    /** @var list<bool> whether a segment may be edited */
    public array $editable = [];

    /** @var list<Frame> the element each segment sits in */
    public array $frames = [];

    /** @var array<int, string> each segment’s text once its rules have run */
    public array $done = [];

    /** @var array<string, mixed> scratch space shared by rules, keyed by the rule */
    public array $state = [];

    /** @var array<string, true> */
    public array $claims = [];

    public function __construct(public readonly ?string $block)
    {
    }

    public function add(int $offset, string $text, bool $editable, Frame $frame): void
    {
        $this->texts[] = $text;
        $this->offsets[] = $offset;
        $this->editable[] = $editable;
        $this->frames[] = $frame;
    }
}
