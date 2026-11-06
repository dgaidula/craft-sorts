<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * 3x4 and 1920 x 1080 to ×. Off by default. The x needs a number on each side
 * and the same spacing on both (all closed up, or a space each side); a number may end
 * in a Unicode fraction (8½ x 11, Bringhurst 5.2.6). It leaves hexadecimal (0x1F), a
 * number that is part of a word (a3x4, x86x64) and anything inside a URL or a path.
 */
final class MultiplicationRule extends AbstractRule
{
    /** The precomposed fractions, as UTF-8: a number may end in one. */
    private const FRACTION = '½|⅓|⅔|¼|¾|⅕|⅖|⅗|⅘|⅙|⅚|⅐|⅛|⅜|⅝|⅞|⅑|⅒';

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
            '/(?<=\d|' . self::FRACTION . ')( ?)[xX]( ?)(?=\d)/',
            function (array $m, int $offset) use ($text, $urls): ?string {
                if ($m[1] !== $m[2] || self::inSpans($urls, $offset)) {
                    return null;
                }
                $number = '';
                for ($i = $offset - 1; $i >= 0 && preg_match('/[\d.,]/', $text[$i]); $i--) {
                    $number = $text[$i] . $number;
                }
                $start = $offset - strlen($number);
                // A fraction ends the number: the whole number before it is part of it (the 8 of 8½).
                if ($number === '' && preg_match('/(\d*)(?:' . self::FRACTION . ')$/', substr($text, max(0, $offset - 16), min(16, $offset)), $tail)) {
                    $start = $offset - strlen($tail[0]);
                    $number = $tail[0];
                }
                $chained = $start > 1 && $text[$start - 1] === $m[0][strlen($m[1])]
                    && preg_match('/(?:\d|' . self::FRACTION . ')$/', substr($text, max(0, $start - 4), min(3, $start - 1))) === 1;
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
