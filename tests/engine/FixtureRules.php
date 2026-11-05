<?php

namespace iceboxind\sorts\tests\engine;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;
use iceboxind\sorts\engine\rules\AbstractRule;

/**
 * The extension API’s example from SPEC §5.5: a recipe site turns scaled
 * decimal quantities into Unicode fractions (0.875 to ⅞, 1.5 to 1½), except
 * before metric units and ounces. Off by default, so it runs only where a page
 * marks it (data-sorts-rules) or a call names it. It settles what it writes.
 */
final class RecipeFractionsRule extends AbstractRule
{
    private const FRACTIONS = ['0.125' => '⅛', '0.25' => '¼', '0.375' => '⅜', '0.5' => '½', '0.625' => '⅝', '0.75' => '¾', '0.875' => '⅞'];

    public function __construct(private readonly bool $everywhere = false)
    {
    }

    public function handle(): string
    {
        return 'recipe-fractions';
    }

    public function priority(): int
    {
        return 45;
    }

    public function defaultEnabled(): bool
    {
        return $this->everywhere;
    }

    public function triggers(): ?string
    {
        return '.';
    }

    public function apply(Run $run, Context $context): void
    {
        $run->replaceAll(
            '/(?<![\d.])(\d*)(\.\d+)(?!\d)(?! ?(?:ml|mL|l|L|g|kg|mg|cm|mm|oz)\b)/',
            static function (array $m): ?string {
                $fraction = self::FRACTIONS['0' . $m[2]] ?? null;
                if ($fraction === null) {
                    return null;
                }
                return ($m[1] === '0' ? '' : $m[1]) . $fraction;
            }
        );
    }
}

/**
 * A later rule that would, unprotected, turn the hyphen of 1-½ into an en dash.
 * Its match includes the fraction character, so it overlaps text a fixture rule
 * settled and the engine must skip it.
 */
final class HyphenToEnDashRule extends AbstractRule
{
    public function handle(): string
    {
        return 'fixture-en-dash';
    }

    public function priority(): int
    {
        return 60;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function apply(Run $run, Context $context): void
    {
        $run->replaceAll('/(\d)-([\d½⅛¼⅜⅝¾⅞]+)/u', static fn (array $m): string => $m[1] . '–' . $m[2]);
    }
}

/** Writes markup, to check the engine keeps markup-writing rules out of text mode. */
final class MarkupRule extends AbstractRule
{
    public function handle(): string
    {
        return 'fixture-markup';
    }

    public function priority(): int
    {
        return 200;
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
        if (!$context->hasAncestor('b')) {
            $run->replaceAll('/\bfoo\b/', static fn (): string => '<b>foo</b>');
        }
    }
}

/** A rule that cares about its locale: it only runs for German, and says so. */
final class GermanOnlyRule extends AbstractRule
{
    public function handle(): string
    {
        return 'fixture-german';
    }

    public function priority(): int
    {
        return 210;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function supportsLocale(\iceboxind\sorts\engine\LocaleInterface $locale): bool
    {
        return $locale->id() === 'de';
    }

    public function apply(Run $run, Context $context): void
    {
        $run->replaceAll('/\bBeispiel\b/', static fn (): string => 'Muster');
    }
}
