<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * Styling hook: wraps an ampersand in `<span class="amp">`. Off by default. Writes
 * markup, so it never runs in plain-text mode. The class is the rule option `class`. An ampersand
 * inside a URL or a path is left as typed.
 */
final class HookAmpRule extends AbstractRule
{
    public function handle(): string
    {
        return 'hook-amp';
    }

    public function priority(): int
    {
        return 140;
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
        return '&';
    }

    public function apply(Run $run, Context $context): void
    {
        $class = (string) $context->option('hook-amp', 'class', 'amp');
        if ($context->hasAncestorClass($class)) {
            return;
        }
        $urls = self::urlSpans($run->text());
        $run->replaceAll(
            '/&amp;|&(?![#A-Za-z0-9]+;)/',
            static fn (array $m, int $offset): ?string => self::inSpans($urls, $offset) ? null : '<span class="' . $class . '">&amp;</span>'
        );
    }
}
