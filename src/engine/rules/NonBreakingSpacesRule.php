<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * A no-break space where a line should not break: between a number and its unit
 * (10 kg), after an initial (J. R. R. Tolkien) or an honorific (Dr. Smith), and
 * after § or ¶. Only a plain space is replaced.
 *
 * An honorific written without its period (Mr, Mrs, Ms, Dr, St, Msgr, Fr: the Oxford
 * spelling, Bringhurst 5.4.4) is joined to a capitalised word after it the same way.
 * `St` after a capitalised word or a number that does not open its sentence is a street
 * (Main St Louis, 42nd St Station) and is left alone.
 */
final class NonBreakingSpacesRule extends AbstractRule
{
    private const UNITS = 'kg|mg|µg|μg|g|lbs?|oz|km/h|km|cm|mm|m|mi|ft|yd|ml|mL|dl|cl|l|L|kB|MB|GB|TB|KB|kb|Mb|Gb|Hz|kHz|MHz|GHz|mph|kph|°[CF]|°|min|sec|ms|h|hrs?|s|px|pt|%';

    private const HONORIFICS = 'Dr|Mr|Mrs|Ms|Mx|Prof|Rev|Hon|Sen|Rep|Gen|Col|Capt|Lt|Sgt|St|Mt|Hr|Fr';

    /** The honorifics that may be written without a period (Bringhurst 5.4.4). */
    private const PERIODLESS = 'Mrs|Mr|Ms|Dr|St|Msgr|Fr';

    /** An uppercase letter as UTF-8 bytes: ASCII, Latin-1, Greek and Cyrillic capitals. */
    private const CAPITAL = '(?:[A-Z]|\xC3[\x80-\x96\x98-\x9E]|\xCE[\x86\x88-\x8A\x8C\x8E-\xA9]|\xD0[\x81-\x8F\x90-\xAF])';

    public function handle(): string
    {
        return 'nbsp';
    }

    public function priority(): int
    {
        return 100;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function triggers(): string
    {
        return '0123456789.§¶MDSF';
    }

    public function apply(Run $run, Context $context): void
    {
        $text = $run->text();
        $nbsp = static fn (): string => "\u{00A0}";
        // These patterns are byte patterns, with no /u flag: that is several times
        // faster here, and each starts on a fixed character so the regex engine can
        // skip ahead (\K then moves the match to the space alone). A byte above 0x7F
        // counts as a letter. The cheap tests keep a pattern off text that cannot
        // hold its case.
        if (preg_match('/\d\x20/', $text)) {
            $run->replaceAll('~\d\K\x20(?=(?:' . self::UNITS . ')(?![A-Za-z0-9_\x80-\xFF]))~', $nbsp);
        }
        if (strpos($text, '. ') !== false) {
            $run->replaceAll(
                '~(?<![A-Za-z0-9\x80-\xFF])(?:(?:' . self::HONORIFICS . ')|' . self::CAPITAL . ')\.\K\x20(?=' . self::CAPITAL . ')~',
                $nbsp
            );
        }
        if (preg_match('/(?:Mrs?|Ms|Msgr|Dr|St|Fr)\x20/', $text)) {
            // The passes above have edited the run: the street guard reads it as it is now.
            $text = $run->text();
            $run->replaceAll(
                '~(?<![A-Za-z0-9\x80-\xFF])(' . self::PERIODLESS . ')\K\x20(?=' . self::CAPITAL . ')~',
                fn (array $m, int $offset): ?string => $m[1] === 'St' && self::afterStreetName($text, $offset - 2, $context) ? null : "\u{00A0}"
            );
        }
        if (strpbrk($text, '§¶') !== false) {
            $run->replaceAll('~(?<![A-Za-z0-9\x80-\xFF])(?:(?:§){1,2}|(?:¶){1,2})\K\x20(?=\d)~', $nbsp);
        }
    }

    /**
     * Whether the `St` at $offset follows a street name: a capitalised word or a number just
     * before it, which does not open its sentence (St Louis after Visit is a saint; after Main it is a street).
     */
    private static function afterStreetName(string $text, int $offset, Context $context): bool
    {
        $head = $offset >= 48 ? substr($text, $offset - 48, 48) : $context->before(48) . substr($text, 0, $offset);
        // The window can start inside a character; drop its stray continuation bytes.
        $head = preg_replace('/^[\x80-\xBF]+/', '', $head) ?? '';
        if (!preg_match('/(?<![\p{L}\p{N}’\'-])([\p{Lu}\p{N}][\p{L}\p{N}’\'-]*) $/u', $head, $m, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        $before = rtrim(substr($head, 0, $m[1][1]), " \t\n\u{00A0}“‘\"'(«[¿¡");
        return $before !== '' && !preg_match('/[.!?…:][”’"\')\]»]*$/u', $before);
    }
}
