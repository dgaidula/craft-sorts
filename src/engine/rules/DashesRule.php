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
 *
 * The dashes it writes, and the spaces it adds, are not settled: `typed-dashes` restyles the
 * em dash `---` gives as it does a typed one, and widows can join the word after a dash, all
 * in the pass a second one would. Rule option `keepDialogue` (true for stored content when the
 * page reads dialogue, see {@see DialogueDashRule}) leaves a `-- ` that opens a block for the page.
 */
final class DashesRule extends AbstractRule
{
    /** What may stand before a dash that takes a space there, and after one that takes a space there. */
    private const LEAD = '/^[\p{L}\p{N}\p{M}.,;:!?)\]}%”’»›…]$/u';
    private const TRAIL = '/^[\p{L}\p{N}\p{M}(\[{“‘«‹„‚"\']$/u';

    /** LEAD for a spaced hyphen, with the straight marks: quotes run later and curl them, so a second pass would see ” or ’. */
    private const SPACED_LEAD = '/^[\p{L}\p{N}\p{M}.,;:!?)\]}%”’»›…"\']$/u';

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
        $keepDialogue = (bool) $context->option('dashes', 'keepDialogue', false);
        $run->replaceAll(
            '/(?<!-)-{2,3}(?!-)/',
            function (array $m, int $offset) use ($dash, $allBreaks, $context, $text, $keepDialogue): ?string {
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
                if ($keepDialogue && $offset === 0 && $length === 2 && $before === '' && preg_match('/^--\x20\S/', $text)) {
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
            },
            false
        );
        if ($allBreaks && strpos($run->text(), ' - ') !== false) {
            $this->spacedHyphens($run, $context, $dash['mark']);
        }
    }

    /**
     * A hyphen with a space each side and a word, number or closing mark before and a word,
     * number or opening mark after is a break (Bringhurst 5.2.1: a spaced hyphen is not a dash).
     * A minus in an equation (`5 - 3 = 2`) is left alone. Only the hyphen is replaced: the spaces stay
     * open to later rules (widows), as a second pass would find them.
     */
    private function spacedHyphens(Run $run, Context $context, string $mark): void
    {
        $text = $run->text();
        $run->replaceAll('/(?<= )-(?= )/', function (array $m, int $offset) use ($context, $mark, $text): ?string {
            $before = self::charBefore($text, $offset - 1, $context);
            $after = self::charAfter($text, $offset + 2, $context);
            if ($before === '' || $after === '' || !preg_match(self::SPACED_LEAD, $before) || !preg_match(self::TRAIL, $after)
                || preg_match('/^\S{1,12}\s?=/', substr($text, $offset + 2, 16))) {
                return null;
            }
            return $mark;
        });
    }

}
