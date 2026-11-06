<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * An em dash the writer typed takes the locale’s break dash (Bringhurst 5.2.1), as `--` does:
 * in US English with the `spaced-en-dash` variant, `a—b` and `a — b` become `a – b`. Where the
 * locale’s dash is the closed em dash already (Spanish), nothing changes. Off by default: the
 * Bringhurst preset turns it on.
 *
 * Left alone: a dash opening a block (dialogue, see {@see DialogueDashRule}), a run of em dashes
 * (the three-em rule) and a dash that touches a quotation mark (an interruption).
 */
final class TypedDashesRule extends AbstractRule
{
    public function handle(): string
    {
        return 'typed-dashes';
    }

    public function priority(): int
    {
        return 31;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function triggers(): string
    {
        return '—';
    }

    public function apply(Run $run, Context $context): void
    {
        $dash = $context->locale->dash();
        if ($dash['mark'] === '—' && !$dash['spaced']) {
            return;
        }
        $text = $run->text();
        $run->replaceAll('/(?<!—)—(?!—)/u', function (array $m, int $offset) use ($dash, $context, $text): ?string {
            $before = self::charBefore($text, $offset, $context);
            $after = self::charAfter($text, $offset + strlen($m[0]), $context);
            $spacedBefore = $before === ' ' && preg_match('/[\p{L}\p{N}\p{M}.,;:!?)\]}%…]\x20$/u', self::tail($text, $offset, $context));
            $spacedAfter = $after === ' ' && preg_match('/^\x20[\p{L}\p{N}\p{M}(\[{]/u', self::head($text, $offset + strlen($m[0]), $context));
            $closed = preg_match('/^[\p{L}\p{N}\p{M}.,;:!?)\]}%…]$/u', $before) && preg_match('/^[\p{L}\p{N}\p{M}(\[{]$/u', $after);
            if (!$closed && !($spacedBefore && $spacedAfter)) {
                return null;
            }
            if ($closed && $dash['spaced']) {
                return ' ' . $dash['mark'] . ' ';
            }
            return $dash['mark'] === '—' ? null : $dash['mark'];
        });
    }

    /** The two characters before $offset: the one that matters and the space after it. */
    private static function tail(string $text, int $offset, Context $context): string
    {
        $tail = $offset >= 8 ? substr($text, $offset - 8, 8) : $context->before(8) . substr($text, 0, $offset);
        return preg_replace('/^[\x80-\xBF]+/', '', $tail) ?? '';
    }

    /** The two characters after $offset: the space and the one after it. */
    private static function head(string $text, int $offset, Context $context): string
    {
        $head = substr($text, $offset, 8);
        return strlen($head) < 8 ? $head . $context->after(8 - strlen($head)) : $head;
    }
}
