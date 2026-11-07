<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * 1/2 to ½, for the fractions Unicode has a character for. Off by default. It
 * leaves dates (1/2/2024), URLs and paths (/1/2) and anything glued to letters or digits.
 * A mixed number written 1-1/2 becomes 1-½: the hyphen is the author’s. A whole number of
 * one to three digits and a space before the fraction join it (8 1/2 becomes 8½, Bringhurst
 * 5.2.5), unless that number is part of a longer one, a date, a time, a version, a path or a word.
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
        $urls = self::urlSpans($text);
        $run->replaceAll(
            '~(?:(?<![\p{L}\p{N}/\\\\.,:;_@#=&%\~+-])(\d{1,3}) )?(?<!\d)(\d{1,2})/(\d{1,2})(?!\d)~u',
            function (array $m, int $matchOffset) use ($text, $context, $urls): ?string {
                $whole = $m[1];
                $fraction = $m[2] . '/' . $m[3];
                if (!isset(self::MAP[$fraction]) || self::inSpans($urls, $matchOffset)) {
                    return null;
                }
                // The whole number joins only when it is in this run, or what comes before the run allows it.
                // One that may not join keeps its space, and the fraction still has to pass the checks below.
                $offset = $matchOffset + ($whole === '' ? 0 : strlen($whole) + 1);
                $lead = $whole;
                if ($whole !== '' && $matchOffset === 0 && preg_match('~[\p{L}\p{N}/\\\\.,:;_@#=&%\~+-]~u', $context->before(1))) {
                    $lead = $whole . ' ';
                }
                $end = $offset + strlen($fraction);
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
                return $lead . self::MAP[$fraction];
            }
        );
    }
}
