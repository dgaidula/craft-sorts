<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * `--` to the locale’s dash, spaced or closed up as the locale prefers; `---`
 * to an em dash. Left alone: a command-line option (` --force`), an arrow
 * (`-->`, `<--`) and runs of four or more hyphens.
 *
 * A locale whose dash sets `allBreaks` (the en-US variant `spaced-en-dash`) gives
 * `---` and a hyphen with a space each side, between two words or numbers, the same
 * dash as `--`. A hyphen at the start of a line or block (a typed list bullet) has
 * no word before it, so it stays.
 */
final class DashesRule extends AbstractRule
{
    /** What may stand before a dash that takes a space there, and after one that takes a space there. */
    private const LEAD = '/^[\p{L}\p{N}\p{M}.,;:!?)\]}%”’»›…]$/u';
    private const TRAIL = '/^[\p{L}\p{N}\p{M}(\[{“‘«‹„‚"\']$/u';

    public function handle(): string
    {
        return 'dashes';
    }

    public function priority(): int
    {
        return 30;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function triggers(): string
    {
        return '-';
    }

    public function apply(Run $run, Context $context): void
    {
        $dash = $context->locale->dash();
        $allBreaks = !empty($dash['allBreaks']);
        $text = $run->text();
        $run->replaceAll(
            '/(?<!-)-{2,3}(?!-)/',
            function (array $m, int $offset) use ($dash, $allBreaks, $context, $text): ?string {
                $length = strlen($m[0]);
                $before = self::charBefore($text, $offset, $context);
                $after = self::charAfter($text, $offset + $length, $context);
                $blankBefore = $before === '' || preg_match('/^\s$/u', $before);

                // Four bytes, not the rest of the run: a copy per match made a long run quadratic.
                if ($before === '<' || $after === '>' || substr($text, $offset + $length, 4) === '&gt;') {
                    return null;
                }
                if ($blankBefore && $after !== '' && preg_match('/^[A-Za-z0-9]$/', $after)) {
                    return null;
                }
                if ($length === 3 && !$allBreaks) {
                    return '—';
                }
                if (!$dash['spaced']) {
                    return $dash['mark'];
                }
                $lead = $before !== '' && preg_match(self::LEAD, $before) ? ' ' : '';
                $trail = $after !== '' && preg_match(self::TRAIL, $after) ? ' ' : '';
                return $lead . $dash['mark'] . $trail;
            }
        );
        if ($allBreaks && strpos($run->text(), ' - ') !== false) {
            $this->spacedHyphens($run, $context, $dash['mark']);
        }
    }

    /**
     * A hyphen with a space each side and a word, number or closing mark before and a word,
     * number or opening mark after is a break (Bringhurst 5.2.1: a spaced hyphen is not a dash).
     * A minus in an equation (`5 - 3 = 2`) is left alone.
     */
    private function spacedHyphens(Run $run, Context $context, string $mark): void
    {
        $text = $run->text();
        $run->replaceAll('/ - /', function (array $m, int $offset) use ($context, $mark, $text): ?string {
            $before = self::charBefore($text, $offset, $context);
            $after = self::charAfter($text, $offset + 3, $context);
            if ($before === '' || $after === '' || !preg_match(self::LEAD, $before) || !preg_match(self::TRAIL, $after)
                || preg_match('/^\S{1,12}\s?=/', substr($text, $offset + 3, 16))) {
                return null;
            }
            return ' ' . $mark . ' ';
        });
    }

}
