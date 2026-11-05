<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * `--` to the locale’s dash, spaced or closed up as the locale prefers; `---`
 * to an em dash. Left alone: a command-line option (` --force`), an arrow
 * (`-->`, `<--`) and runs of four or more hyphens.
 */
final class DashesRule extends AbstractRule
{
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
        $text = $run->text();
        $run->replaceAll(
            '/(?<!-)-{2,3}(?!-)/',
            function (array $m, int $offset) use ($dash, $context, $text): ?string {
                $length = strlen($m[0]);
                $before = self::charBefore($text, $offset, $context);
                $after = self::charAfter($text, $offset + $length, $context);
                $blankBefore = $before === '' || preg_match('/^\s$/u', $before);

                if ($before === '<' || $after === '>' || str_starts_with(substr($text, $offset + $length), '&gt;')) {
                    return null;
                }
                if ($blankBefore && $after !== '' && preg_match('/^[A-Za-z0-9]$/', $after)) {
                    return null;
                }
                if ($length === 3) {
                    return '—';
                }
                if (!$dash['spaced']) {
                    return $dash['mark'];
                }
                $lead = $before !== '' && preg_match('/^[\p{L}\p{N}\p{M}.,;:!?)\]}%”’»›…]$/u', $before) ? ' ' : '';
                $trail = $after !== '' && preg_match('/^[\p{L}\p{N}\p{M}(\[{“‘«‹„‚"\']$/u', $after) ? ' ' : '';
                return $lead . $dash['mark'] . $trail;
            }
        );
    }

}
