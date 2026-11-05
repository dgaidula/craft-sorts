<?php

namespace iceboxind\sorts\engine;

/**
 * One stretch of editable text and the characters in it that are settled.
 *
 * Offsets are bytes (what PCRE reports). Every edit goes through here, so the
 * engine can enforce the settled protection itself: an edit that touches a
 * settled character is skipped, whatever rule asked for it. A rule that writes
 * text marks it settled by default; pass `$settle = false` for text that later
 * rules may still change.
 *
 * Settled marks are positions, not characters, so nothing is added to the text
 * and nothing can leak into the output.
 */
final class Run
{
    /** @var list<array{int, int}> settled [start, end) spans, sorted, not overlapping */
    private array $settled = [];

    public function __construct(private string $text)
    {
    }

    public function text(): string
    {
        return $this->text;
    }

    public function isSettled(int $offset): bool
    {
        return $this->spanAt($offset) !== null;
    }

    /** Whether any settled character lies in [start, start + length), or the point is inside a span when length is 0. */
    public function overlapsSettled(int $start, int $length = 1): bool
    {
        $end = $start + $length;
        foreach ($this->settled as [$s, $e]) {
            if ($s >= $end) {
                break;
            }
            if ($length === 0 ? $s < $start && $start < $e : $start < $e) {
                return true;
            }
        }
        return false;
    }

    /** Replace one range. Returns false, changing nothing, if it touches a settled character. */
    public function replace(int $start, int $length, string $replacement, bool $settle = true): bool
    {
        return $this->edit([[$start, $length, $replacement, $settle]]) === 1;
    }

    /**
     * Apply several edits at once. Each is [start, length, replacement] with an
     * optional fourth element, whether to settle the replacement (default true).
     * Edits must be in ascending order and must not overlap each other. Edits
     * that touch a settled character are skipped. Returns how many were applied.
     *
     * @param list<array{0: int, 1: int, 2: string, 3?: bool}> $edits
     */
    public function edit(array $edits): int
    {
        if ($this->settled) {
            // Edits and spans are both in order, so one walk over each checks them all
            // (the same test as overlapsSettled(), without starting again for every edit).
            $kept = [];
            $si = 0;
            $spans = count($this->settled);
            foreach ($edits as $edit) {
                [$start, $length] = $edit;
                while ($si < $spans && $this->settled[$si][1] <= $start) {
                    $si++;
                }
                if ($si < $spans && $this->settled[$si][0] < ($length === 0 ? $start : $start + $length)) {
                    continue;
                }
                $kept[] = $edit;
            }
            $edits = $kept;
        }
        if (!$edits) {
            return 0;
        }

        $out = '';
        $pos = 0;
        $shift = 0;
        $fresh = [];
        $moved = [];
        $oi = 0;
        $old = $this->settled;
        $oldCount = count($old);
        foreach ($edits as $edit) {
            [$start, $length, $replacement] = $edit;
            // Old spans wholly before this edit keep their text and take the shift so far.
            while ($oi < $oldCount && $old[$oi][1] <= $start) {
                $moved[] = [$old[$oi][0] + $shift, $old[$oi][1] + $shift];
                $oi++;
            }
            $out .= substr($this->text, $pos, $start - $pos) . $replacement;
            if (($edit[3] ?? true) && $replacement !== '') {
                $newStart = $start + $shift;
                $fresh[] = [$newStart, $newStart + strlen($replacement)];
            }
            $shift += strlen($replacement) - $length;
            $pos = $start + $length;
        }
        for (; $oi < $oldCount; $oi++) {
            $moved[] = [$old[$oi][0] + $shift, $old[$oi][1] + $shift];
        }
        $this->text = $out . substr($this->text, $pos);

        if (!$moved) {
            $this->settled = $fresh;
        } elseif ($fresh) {
            $this->settled = self::mergeSpans($moved, $fresh);
        } else {
            $this->settled = $moved;
        }
        return count($edits);
    }

    /**
     * Two sorted lists of spans, both in the new text’s offsets and each free of
     * overlaps inside itself, as one sorted list.
     *
     * @param list<array{int, int}> $a
     * @param list<array{int, int}> $b
     * @return list<array{int, int}>
     */
    private static function mergeSpans(array $a, array $b): array
    {
        $merged = [];
        $i = $j = 0;
        $na = count($a);
        $nb = count($b);
        while ($i < $na || $j < $nb) {
            $span = $j >= $nb || ($i < $na && $a[$i][0] <= $b[$j][0]) ? $a[$i++] : $b[$j++];
            $last = count($merged) - 1;
            if ($last >= 0 && $span[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $span[1]);
            } else {
                $merged[] = $span;
            }
        }
        return $merged;
    }

    /**
     * Replace every match of a pattern. The callback gets the match’s captured
     * strings (index 0 is the whole match, unmatched groups are '') and the
     * match’s byte offset, and returns the replacement, or null to leave the
     * match alone. Matches that touch settled characters are skipped.
     * Returns the number of replacements made.
     *
     * @param callable(list<string>, int): (string|null) $callback
     */
    public function replaceAll(string $pattern, callable $callback, bool $settle = true): int
    {
        $count = preg_match_all($pattern, $this->text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if (!$count) {
            return 0;
        }
        $edits = [];
        foreach ($matches as $match) {
            $groups = array_column($match, 0);
            $replacement = $callback($groups, $match[0][1]);
            if ($replacement !== null) {
                $edits[] = [$match[0][1], strlen($match[0][0]), $replacement, $settle];
            }
        }
        return $edits ? $this->edit($edits) : 0;
    }

    /** @return array{int, int}|null */
    private function spanAt(int $offset): ?array
    {
        foreach ($this->settled as $span) {
            if ($offset < $span[0]) {
                return null;
            }
            if ($offset < $span[1]) {
                return $span;
            }
        }
        return null;
    }
}
