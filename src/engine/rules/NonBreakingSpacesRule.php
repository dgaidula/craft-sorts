<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * A no-break space where a line should not break: between a number and its unit
 * (10 kg), after an initial (J. R. R. Tolkien) or an honorific (Dr. Smith), and
 * after § or ¶. Only a plain space is replaced.
 */
final class NonBreakingSpacesRule extends AbstractRule
{
    private const UNITS = 'kg|mg|µg|μg|g|lbs?|oz|km/h|km|cm|mm|m|mi|ft|yd|ml|mL|dl|cl|l|L|kB|MB|GB|TB|KB|kb|Mb|Gb|Hz|kHz|MHz|GHz|mph|kph|°[CF]|°|min|sec|ms|h|hrs?|s|px|pt|%';

    private const HONORIFICS = 'Dr|Mr|Mrs|Ms|Mx|Prof|Rev|Hon|Sen|Rep|Gen|Col|Capt|Lt|Sgt|St|Mt|Hr|Fr';

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
        return '0123456789.§¶';
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
        if (strpbrk($text, '§¶') !== false) {
            $run->replaceAll('~(?<![A-Za-z0-9\x80-\xFF])(?:(?:§){1,2}|(?:¶){1,2})\K\x20(?=\d)~', $nbsp);
        }
    }
}
