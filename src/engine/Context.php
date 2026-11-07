<?php

namespace iceboxind\sorts\engine;

/**
 * What a rule knows about the run it is given: the locale, the mode, the
 * element it sits in, and the text around it. Text before the run is what the
 * earlier runs became; text after it is still as the page had it.
 */
final class Context
{
    public readonly string $mode;

    /** {@see Options::NESTING_TYPED} or {@see Options::NESTING_DEPTH}: how a quotation’s marks are chosen. */
    public readonly string $quoteNesting;

    /**
     * @param array<string, true> $active handles of the rules running on this run
     * @internal built by the engine
     */
    public function __construct(
        public readonly LocaleInterface $locale,
        private readonly Options $options,
        private readonly Flow $flow,
        private readonly int $index,
        private readonly Frame $frame,
        private readonly array $active,
    ) {
        $this->mode = $options->mode;
        $this->quoteNesting = $options->quoteNesting === Options::NESTING_DEPTH ? Options::NESTING_DEPTH : Options::NESTING_TYPED;
    }

    /** Tag names of the enclosing elements, outermost first. */
    public function ancestors(): array
    {
        $names = [];
        for ($f = $this->frame; $f !== null && $f->name !== ''; $f = $f->parent) {
            $names[] = $f->name;
        }
        return array_reverse($names);
    }

    public function hasAncestor(string $tag): bool
    {
        $tag = strtolower($tag);
        for ($f = $this->frame; $f !== null; $f = $f->parent) {
            if ($f->name === $tag) {
                return true;
            }
        }
        return false;
    }

    public function hasAncestorClass(string $class): bool
    {
        for ($f = $this->frame; $f !== null; $f = $f->parent) {
            if ($f->raw !== '' && preg_match('/\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $f->raw, $m)) {
                $value = $m[1] . ($m[2] ?? '') . ($m[3] ?? '');
                if (in_array($class, preg_split('/\s+/', trim($value)) ?: [], true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** The nearest enclosing element that is not inline, or null in plain text. */
    public function block(): ?string
    {
        return $this->flow->block;
    }

    /** A rule’s own setting from the call’s options, for example option('widows', 'minWords', 3). */
    public function option(string $handle, string $key, mixed $default = null): mixed
    {
        return $this->options->ruleOptions[$handle][$key] ?? $default;
    }

    public function isActive(string $handle): bool
    {
        return isset($this->active[$handle]);
    }

    /** The last $length characters before the run, looking through inline tags. Fewer, or '', at the start of the flow. */
    public function before(int $length = 1): string
    {
        // The pieces are counted one by one and joined once, so the whole flow (widows) costs one walk over it.
        $pieces = [];
        $count = 0;
        for ($i = $this->index - 1; $i >= 0 && $count < $length; $i--) {
            $pieces[] = $piece = $this->flow->done[$i] ?? $this->flow->texts[$i];
            $count += mb_strlen($piece);
        }
        return mb_substr(implode('', array_reverse($pieces)), -$length);
    }

    /** The first $length characters after the run, looking through inline tags. */
    public function after(int $length = 1): string
    {
        $text = '';
        $count = count($this->flow->texts);
        for ($i = $this->index + 1; $i < $count && mb_strlen($text) < $length; $i++) {
            $text .= $this->flow->texts[$i];
        }
        return mb_substr($text, 0, $length);
    }

    /** All the text after the run in its flow, as the page had it. */
    public function textAfter(): string
    {
        $text = '';
        for ($i = $this->index + 1, $n = count($this->flow->texts); $i < $n; $i++) {
            $text .= $this->flow->texts[$i];
        }
        return $text;
    }

    /** The whole flow as the page had it, before any rule ran. */
    public function flowText(): string
    {
        return $this->flow->state['flowText'] ??= implode('', $this->flow->texts);
    }

    /**
     * Whether the text after the run still holds a space between two words. A space
     * at its start counts only when the run itself ends in a word (`two` + ` three`:
     * the gap is after the run; `two ` + `three`: it is the run’s own), and a space
     * at its end never does. Stops at the first one it finds, so it stays cheap in a
     * long flow.
     */
    public function spaceAfter(): bool
    {
        $seenText = preg_match('/\S$/u', $this->flow->texts[$this->index] ?? '') === 1;
        $seenGap = false;
        for ($i = $this->index + 1, $n = count($this->flow->texts); $i < $n; $i++) {
            $text = $this->flow->texts[$i];
            if (trim($text) === '') {
                $seenGap = $seenGap || ($seenText && $text !== '');
                continue;
            }
            if ($seenGap || preg_match('/\S\s+\S/u', $text) === 1) {
                return true;
            }
            if ($seenText && preg_match('/^\s/u', $text) === 1) {
                return true;
            }
            $seenText = true;
            $seenGap = preg_match('/\s$/u', $text) === 1;
        }
        return false;
    }

    /** True the first time it is asked for this run and key. Lets cooperating rules share one pass. */
    public function once(string $key): bool
    {
        $claim = $this->index . ':' . $key;
        if (isset($this->flow->claims[$claim])) {
            return false;
        }
        return $this->flow->claims[$claim] = true;
    }

    /** Scratch space that lasts for the whole flow, keyed by the caller. */
    public function flowState(string $key, mixed $default = null): mixed
    {
        return $this->flow->state[$key] ?? $default;
    }

    public function setFlowState(string $key, mixed $value): void
    {
        $this->flow->state[$key] = $value;
    }

    /** How many matches of a preg pattern lie in the flow after this run, as the page had it. */
    public function countAfter(string $pattern): int
    {
        // Suffix sums over the segments, built once per flow and pattern.
        $key = 'count:' . $pattern;
        if (!isset($this->flow->state[$key])) {
            $texts = $this->flow->texts;
            $sums = array_fill(0, count($texts) + 1, 0);
            for ($i = count($texts) - 1; $i >= 0; $i--) {
                $sums[$i] = $sums[$i + 1] + (int) preg_match_all($pattern, $texts[$i]);
            }
            $this->flow->state[$key] = $sums;
        }
        return $this->flow->state[$key][$this->index + 1];
    }
}
