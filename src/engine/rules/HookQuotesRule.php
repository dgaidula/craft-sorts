<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * Styling hook for hanging punctuation: wraps an opening quote at the very start
 * of a block in `<span class="dquo">` (double) or `<span class="quo">` (single).
 * Off by default. Writes markup, so it never runs in plain-text mode. The classes
 * are the rule options `doubleClass` and `singleClass`.
 */
final class HookQuotesRule extends AbstractRule
{
    public function handle(): string
    {
        return 'hook-quotes';
    }

    public function priority(): int
    {
        return 142;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function emitsMarkup(): bool
    {
        return true;
    }

    public function triggers(): string
    {
        return '“‘„‚«‹‟‛';
    }

    public function apply(Run $run, Context $context): void
    {
        if ($context->before(1) !== '' || $context->block() === null) {
            return;
        }
        $double = (string) $context->option('hook-quotes', 'doubleClass', 'dquo');
        $single = (string) $context->option('hook-quotes', 'singleClass', 'quo');
        if ($context->hasAncestorClass($double) || $context->hasAncestorClass($single)) {
            return;
        }
        if (!preg_match('/^(?:(["“„«‟])|([‘‚‹‛]))/u', $run->text(), $m)) {
            return;
        }
        $class = $m[1] !== '' ? $double : $single;
        $run->replace(0, strlen($m[0]), '<span class="' . $class . '">' . $m[0] . '</span>');
    }
}
