<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * 3x4 and 1920 x 1080 to ×. Off by default. The x needs a number on each side
 * and the same spacing on both (all closed up, or a space each side). It leaves
 * hexadecimal (0x1F), a number that is part of a word (a3x4, x86x64) and anything
 * inside a URL or a path.
 */
final class MultiplicationRule extends AbstractRule
{
    public function handle(): string
    {
        return 'multiplication';
    }

    public function priority(): int
    {
        return 60;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function triggers(): string
    {
        return 'xX';
    }

    public function apply(Run $run, Context $context): void
    {
        $text = $run->text();
        $urls = self::urlSpans($text);
        $run->replaceAll(
            '/(?<=\d)( ?)[xX]( ?)(?=\d)/',
            function (array $m, int $offset) use ($text, $urls): ?string {
                if ($m[1] !== $m[2] || self::inSpans($urls, $offset)) {
                    return null;
                }
                $number = '';
                for ($i = $offset - 1; $i >= 0 && preg_match('/[\d.,]/', $text[$i]); $i--) {
                    $number = $text[$i] . $number;
                }
                $start = $offset - strlen($number);
                $chained = $start > 1 && $text[$start - 1] === $m[0][strlen($m[1])] && ctype_digit($text[$start - 2]);
                if ($start > 0 && preg_match('/[A-Za-z_\-]/', $text[$start - 1]) && !$chained) {
                    return null;
                }
                if ($number === '0' && $m[1] === '') {
                    return null;
                }
                return $m[1] . '×' . $m[2];
            }
        );
    }
}
