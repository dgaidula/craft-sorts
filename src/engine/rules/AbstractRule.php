<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\LocaleInterface;
use iceboxind\sorts\engine\RuleInterface;

/** Defaults for the common case: any locale, no markup, called for every run. */
abstract class AbstractRule implements RuleInterface
{
    public function supportsLocale(LocaleInterface $locale): bool
    {
        return true;
    }

    public function emitsMarkup(): bool
    {
        return false;
    }

    public function triggers(): ?string
    {
        return null;
    }

    /** The character just before $offset in $text, or the one before the run if $offset is 0. '' at the start of the flow. */
    protected static function charBefore(string $text, int $offset, Context $context): string
    {
        if ($offset <= 0) {
            return $context->before(1);
        }
        $i = $offset - 1;
        while ($i > 0 && (ord($text[$i]) & 0xC0) === 0x80) {
            $i--;
        }
        return substr($text, $i, $offset - $i);
    }

    /**
     * The URL-shaped words in $text, as sorted [start, end) byte spans: anything from
     * `http://`, `https://` or `www.`, and a path (a word that starts with `/`), to the
     * next space. Number rules leave what lies inside one alone.
     *
     * @return list<array{int, int}>
     */
    protected static function urlSpans(string $text): array
    {
        if (strpos($text, '/') === false && stripos($text, 'www.') === false) {
            return [];
        }
        if (!preg_match_all('~(?:\bhttps?://|\bwww\.|(?<![^\s(\[{"\'])/(?=[^\s/]))\S*~i', $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $spans = [];
        foreach ($m[0] as [$url, $at]) {
            $spans[] = [$at, $at + strlen($url)];
        }
        return $spans;
    }

    /** @param list<array{int, int}> $spans */
    protected static function inSpans(array $spans, int $offset): bool
    {
        foreach ($spans as [$start, $end]) {
            if ($offset < $start) {
                return false;
            }
            if ($offset < $end) {
                return true;
            }
        }
        return false;
    }

    /** The character at $offset in $text, or the one after the run if $offset is past its end. '' at the end of the flow. */
    protected static function charAfter(string $text, int $offset, Context $context): string
    {
        if ($offset >= strlen($text)) {
            return $context->after(1);
        }
        $lead = ord($text[$offset]);
        return substr($text, $offset, $lead < 0x80 ? 1 : ($lead >= 0xF0 ? 4 : ($lead >= 0xE0 ? 3 : 2)));
    }
}
