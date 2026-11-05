<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * 1/2 to ½, for the fractions Unicode has a character for. Off by default. It
 * leaves dates (1/2/2024), URLs (/1/2) and anything glued to letters or digits.
 * A mixed number written 1-1/2 becomes 1-½: the hyphen is the author’s.
 */
final class FractionsRule extends AbstractRule
{
    private const MAP = [
        '1/2' => '½', '1/3' => '⅓', '2/3' => '⅔', '1/4' => '¼', '3/4' => '¾', '1/5' => '⅕', '2/5' => '⅖',
        '3/5' => '⅗', '4/5' => '⅘', '1/6' => '⅙', '5/6' => '⅚', '1/7' => '⅐', '1/8' => '⅛', '3/8' => '⅜',
        '5/8' => '⅝', '7/8' => '⅞', '1/9' => '⅑', '1/10' => '⅒',
    ];

    public function handle(): string
    {
        return 'fractions';
    }

    public function priority(): int
    {
        return 40;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function triggers(): string
    {
        return '/';
    }

    public function apply(Run $run, Context $context): void
    {
        $text = $run->text();
        $run->replaceAll(
            '/(?<!\d)(\d{1,2})\/(\d{1,2})(?!\d)/',
            function (array $m, int $offset) use ($text, $context): ?string {
                if (!isset(self::MAP[$m[0]])) {
                    return null;
                }
                $end = $offset + strlen($m[0]);
                $before = self::charBefore($text, $offset, $context);
                $after = self::charAfter($text, $end, $context);
                if ($before !== '' && preg_match('/[\p{L}\p{N}\/\\\\.:_@#=&%~+]/u', $before)) {
                    return null;
                }
                if ($before === '-' && !($offset > 1 && ctype_digit($text[$offset - 2]))) {
                    return null;
                }
                if ($after !== '' && preg_match('/[\p{N}\/_@#=&~+]/u', $after)) {
                    return null;
                }
                $next2 = substr($text, $end, 2);
                if (preg_match('/^[.,]\d/', $next2)) {
                    return null;
                }
                return self::MAP[$m[0]];
            }
        );
    }
}
