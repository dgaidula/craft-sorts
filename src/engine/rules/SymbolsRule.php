<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * (c) (r) (tm) to © ® ™. A bare (c) or (r) is left alone when the same paragraph
 * also holds (b) or (q): that is a list marker, not a symbol.
 */
final class SymbolsRule extends AbstractRule
{
    public function handle(): string
    {
        return 'symbols';
    }

    public function priority(): int
    {
        return 10;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function triggers(): string
    {
        return '(';
    }

    public function apply(Run $run, Context $context): void
    {
        $run->replaceAll(
            '/(?<![\p{L}\p{N}])\((c|r)\)/i',
            static function (array $m) use ($context): ?string {
                $letter = strtolower($m[1]);
                if (preg_match($letter === 'c' ? '/\(b\)/i' : '/\(q\)/i', $context->flowText())) {
                    return null;
                }
                return $letter === 'c' ? '©' : '®';
            }
        );
        $run->replaceAll('/\(tm\)/i', static fn (): string => '™');
    }
}
