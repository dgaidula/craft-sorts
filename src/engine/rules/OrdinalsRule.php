<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\LocaleInterface;
use iceboxind\sorts\engine\Run;

/** 1st, 2nd, 3rd, 4th to 1<sup>st</sup> and so on, never inside a URL or a path. English locales only; off by default. Writes markup. */
final class OrdinalsRule extends AbstractRule
{
    public function handle(): string
    {
        return 'ordinals';
    }

    public function priority(): int
    {
        return 130;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function supportsLocale(LocaleInterface $locale): bool
    {
        return str_starts_with($locale->id(), 'en');
    }

    public function emitsMarkup(): bool
    {
        return true;
    }

    public function triggers(): string
    {
        return 'stndrh';
    }

    public function apply(Run $run, Context $context): void
    {
        if ($context->hasAncestor('sup')) {
            return;
        }
        $urls = self::urlSpans($run->text());
        $run->replaceAll(
            '/(?<![\p{L}\p{N}_]|\d[.,])(\d+(?:,\d{3})*)(st|nd|rd|th)(?![\p{L}\p{N}_])/u',
            static function (array $m, int $offset) use ($urls): ?string {
                if (self::inSpans($urls, $offset)) {
                    return null;
                }
                $n = (int) str_replace(',', '', $m[1]);
                $tens = $n % 100;
                $expected = ($tens >= 11 && $tens <= 13) ? 'th' : (['st', 'nd', 'rd'][$n % 10 - 1] ?? 'th');
                return $m[2] === $expected ? $m[1] . '<sup>' . $m[2] . '</sup>' : null;
            }
        );
    }
}
