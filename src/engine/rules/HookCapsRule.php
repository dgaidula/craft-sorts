<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * Styling hook: wraps a word of capital letters (two or more, such as NASA or
 * HTML5) in `<span class="caps">`. Off by default. Writes markup, so it never
 * runs in plain-text mode. The class is the rule option `class`. A word inside a URL or a path
 * is left as typed.
 */
final class HookCapsRule extends AbstractRule
{
    public function handle(): string
    {
        return 'hook-caps';
    }

    public function priority(): int
    {
        return 141;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function emitsMarkup(): bool
    {
        return true;
    }

    public function apply(Run $run, Context $context): void
    {
        $class = (string) $context->option('hook-caps', 'class', 'caps');
        if ($context->hasAncestorClass($class)) {
            return;
        }
        $urls = self::urlSpans($run->text());
        $run->replaceAll(
            '/&\#?[A-Za-z0-9]+;(*SKIP)(*FAIL)|(?<![\p{L}\p{N}_])\p{Lu}[\p{Lu}\p{N}]*\p{Lu}[\p{Lu}\p{N}]*(?![\p{L}\p{N}_])/u',
            static fn (array $m, int $offset): ?string => self::inSpans($urls, $offset) ? null : '<span class="' . $class . '">' . $m[0] . '</span>'
        );
    }
}
