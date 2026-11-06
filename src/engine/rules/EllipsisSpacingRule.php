<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\LocaleInterface;
use iceboxind\sorts\engine\Run;

/**
 * An ellipsis between two words takes a word space either side, and one that meets a full stop,
 * comma, exclamation mark or question mark closes up to the word before it (Bringhurst 5.2.7):
 * `wait…then` becomes `wait … then`, and `wait …. Then` becomes `wait…. Then`. It spaces the ellipsis
 * the `ellipsis` rule writes from `...` as well as one the writer typed. English only, never French.
 * An ellipsis at the start or end of a block, or beside a bracket or quotation mark, is left as typed.
 * Off by default: the Bringhurst preset turns it on.
 */
final class EllipsisSpacingRule extends AbstractRule
{
    public function handle(): string
    {
        return 'ellipsis-spacing';
    }

    public function priority(): int
    {
        return 21;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function supportsLocale(LocaleInterface $locale): bool
    {
        return str_starts_with($locale->id(), 'en');
    }

    public function triggers(): string
    {
        return '…';
    }

    public function apply(Run $run, Context $context): void
    {
        $text = $run->text();
        // The ellipsis itself is left; only the spaces beside it change. Between two words:
        // a word, spaces, the ellipsis, spaces, a word (the words may be in another run).
        $edits = [];
        preg_match_all('/\x20*(…)\x20*/u', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($found as $m) {
            [$whole, $offset] = $m[0];
            $mark = $m[1][0];
            $markAt = $m[1][1];
            $before = self::charBefore($text, $offset, $context);
            $after = self::charAfter($text, $offset + strlen($whole), $context);
            if ($before === '' || $after === '' || !preg_match('/^[\p{L}\p{N}\p{M}]$/u', $before)) {
                continue;
            }
            $lead = substr($whole, 0, $markAt - $offset);
            $trail = substr($whole, $markAt - $offset + strlen($mark));
            if (preg_match('/^[.,!?]$/', $after) && $trail === '') {
                // wait ….  closes up to the word before it
                if ($lead !== '') {
                    $edits[] = [$offset, strlen($lead), ''];
                }
                continue;
            }
            if (!preg_match('/^[\p{L}\p{N}\p{M}]$/u', $after)) {
                continue;
            }
            if ($lead !== ' ') {
                $edits[] = [$offset, strlen($lead), ' '];
            }
            if ($trail !== ' ') {
                $edits[] = [$markAt + strlen($mark), strlen($trail), ' '];
            }
        }
        if ($edits) {
            $run->edit(array_map(static fn (array $e): array => [...$e, false], $edits));
        }
    }
}
