<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\LocaleInterface;
use iceboxind\sorts\engine\Run;

/**
 * The spacing a locale asks for around punctuation. For French: a narrow
 * no-break space before ; ! ? and inside « » (and ‹ ›), a no-break space before
 * a colon. The spaces come from the locale’s `spacing` data. Only runs when the
 * locale has any. A colon, ?, ! or ; is left alone when text follows it
 * directly (10:30, https://, ?id=1, !important).
 */
final class LocaleSpacingRule extends AbstractRule
{
    private const BLANK = '[\x20\x{00A0}\x{202F}]';

    public function handle(): string
    {
        return 'locale-spacing';
    }

    public function priority(): int
    {
        return 110;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function supportsLocale(LocaleInterface $locale): bool
    {
        return $locale->spacing() !== [];
    }

    public function triggers(): string
    {
        return ';:!?«»‹›';
    }

    public function apply(Run $run, Context $context): void
    {
        $spacing = $context->locale->spacing();
        $before = $spacing['before'] ?? [];
        if ($before) {
            $marks = preg_quote(implode('', array_keys($before)), '~');
            $run->replaceAll(
                '~&\#?[A-Za-z0-9]+;(*SKIP)(*FAIL)'
                . '|(?<=[\p{L}\p{N}\p{M}…)\]»”’%°])' . self::BLANK . '*([' . $marks . '])(?=\s|$|[»›”)\]?!:;]|\x{00A0}|\x{202F})~u',
                static function (array $m) use ($before): ?string {
                    $new = $before[$m[1]] . $m[1];
                    return $new === $m[0] ? null : $new;
                }
            );
        }

        $guillemets = $spacing['guillemets'] ?? null;
        if ($guillemets !== null) {
            $run->replaceAll(
                '~(?<=[«‹])' . self::BLANK . '*~u',
                function (array $m, int $offset) use ($guillemets, $run, $context): ?string {
                    if ($offset + strlen($m[0]) >= strlen($run->text()) && preg_match('/^[\s\x{00A0}\x{202F}]?$/u', $context->after(1))) {
                        return $m[0] === '' ? null : $m[0];
                    }
                    return $m[0] === $guillemets ? null : $guillemets;
                }
            );
            $run->replaceAll(
                '~(?<!' . self::BLANK . ')' . self::BLANK . '*(?=[»›])~u',
                function (array $m, int $offset) use ($guillemets, $context): ?string {
                    if ($offset === 0 && preg_match('/^[\s\x{00A0}\x{202F}]?$/u', $context->before(1))) {
                        return null;
                    }
                    return $m[0] === $guillemets ? null : $guillemets;
                }
            );
        }
    }
}
