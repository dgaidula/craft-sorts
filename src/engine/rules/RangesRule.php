<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * 1-5 and pages 10-12 to an en dash. Off by default, and careful when on: it
 * converts only a pair of plain numbers that stand alone. It leaves phone
 * numbers (555-1234, 555-123-4567), ISBNs, SKUs, dates (2024-01-15, 2024-01)
 * anything inside a URL or a path, and anything glued to letters or other punctuation. A pair whose second
 * number is shorter (1990-95) converts when it reads as a continuation.
 *
 * A pair of clock times (4:30-5:00, Bringhurst 5.2.2) converts the same way, with the same
 * guards, and a time that ends in a UTC offset (2024-01-15 10:30-05:00) is left alone.
 */
final class RangesRule extends AbstractRule
{
    private const NUMBER = '\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:\.\d+)?';

    public function handle(): string
    {
        return 'ranges';
    }

    public function priority(): int
    {
        return 50;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function triggers(): string
    {
        return '-';
    }

    public function apply(Run $run, Context $context): void
    {
        $urls = self::urlSpans($run->text());
        if (strpos($run->text(), ':') !== false) {
            // Clock ranges change the run's length, so the URL spans are found again after them.
            if ($this->clockTimes($run, $urls) > 0) {
                $urls = self::urlSpans($run->text());
            }
        }
        $run->replaceAll(
            '/(?<![\p{L}\p{N}_.,\-\/:@#+=&%~])(' . self::NUMBER . ')-(' . self::NUMBER . ')(?![\p{L}\p{N}_\-\/:@#+=&~]|[.,]\d)/u',
            static function (array $m, int $offset) use ($urls): ?string {
                if (self::inSpans($urls, $offset)) {
                    return null;
                }
                $a = str_replace(',', '', $m[1]);
                $b = str_replace(',', '', $m[2]);
                if (preg_match('/^\d+$/', $a) && preg_match('/^\d+$/', $b)) {
                    if (strlen($a) === 3 && strlen($b) === 4 && !str_contains($m[1], ',') && !str_contains($m[2], ',')) {
                        return null;
                    }
                    if (strlen($a) > 4 && !str_contains($m[1], ',')) {
                        return null;
                    }
                }
                $fine = (float) $b > (float) $a;
                if (!$fine && strlen($a) >= 4 && strlen($b) >= 2 && strlen($b) < strlen($a) && preg_match('/^\d+$/', $a . $b)) {
                    $fine = (int) $b > (int) substr($a, -strlen($b));
                }
                return $fine ? $m[1] . '–' . $m[2] : null;
            }
        );
    }

    /**
     * 4:30-5:00 to 4:30–5:00. Hours up to 24 and minutes under sixty; either way round,
     * since a range may run past midnight (22:00-06:00).
     *
     * @param list<array{int, int}> $urls
     * @return int how many ranges it converted
     */
    private function clockTimes(Run $run, array $urls): int
    {
        $text = $run->text();
        return $run->replaceAll(
            '/(?<![\p{L}\p{N}_.,\-\/:@#+=&%~])((?:[01]?\d|2[0-4]):[0-5]\d)-((?:[01]?\d|2[0-4]):[0-5]\d)(?![\p{L}\p{N}_\-\/:@#+=&~]|[.,]\d)/u',
            static function (array $m, int $offset) use ($text, $urls): ?string {
                if (self::inSpans($urls, $offset)) {
                    return null;
                }
                // 2024-01-15 10:30-05:00 is a time and its UTC offset.
                if (preg_match('/\d{4}-\d{2}-\d{2} $/', substr($text, max(0, $offset - 11), min(11, $offset)))) {
                    return null;
                }
                return $m[1] . '–' . $m[2];
            }
        );
    }
}
