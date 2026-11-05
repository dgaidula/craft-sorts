<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * 1-5 and pages 10-12 to an en dash. Off by default, and careful when on: it
 * converts only a pair of plain numbers that stand alone. It leaves phone
 * numbers (555-1234, 555-123-4567), ISBNs, SKUs, dates (2024-01-15, 2024-01)
 * and anything glued to letters or other punctuation. A pair whose second
 * number is shorter (1990-95) converts when it reads as a continuation.
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
        $run->replaceAll(
            '/(?<![\p{L}\p{N}_.,\-\/:@#+=&%~])(' . self::NUMBER . ')-(' . self::NUMBER . ')(?![\p{L}\p{N}_\-\/:@#+=&~]|[.,]\d)/u',
            static function (array $m): ?string {
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
}
