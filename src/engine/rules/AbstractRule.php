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
     * next space. Number rules leave what lies inside one alone. A path may follow a space, an
     * opening bracket or a quotation mark, straight, curly or written as an entity (the form Twig and
     * the filter hand a plain string’s quotes in), as a later rule curls the straight one and the guard
     * must hold on a second pass (the marks are byte-escaped, the pattern has no /u).
     *
     * @return list<array{int, int}>
     */
    protected static function urlSpans(string $text): array
    {
        if (strpos($text, '/') === false && stripos($text, 'www.') === false) {
            return [];
        }
        if (!preg_match_all('~(?:\bhttps?://|\bwww\.|(?:(?<![^\s(\[{"\'])|(?<=\xE2\x80\x9C|\xE2\x80\x9D|\xE2\x80\x98|\xE2\x80\x99|\xE2\x80\x9E|\xE2\x80\x9A|\xE2\x80\xB9|\xE2\x80\xBA|\xC2\xAB|\xC2\xBB|\xE2\x80\xB2|\xE2\x80\xB3|\xC2\xA0|\xE2\x80\xAF|\xE2\x80\x89|&nbsp;|&quot;|&#34;|&#039;|&#39;|&apos;|&#x27;))/(?=[^\s/]))\S*~i', $text, $m, PREG_OFFSET_CAPTURE)) {
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
        $lo = 0;
        $hi = count($spans);
        while ($lo < $hi) {
            $mid = ($lo + $hi) >> 1;
            if ($spans[$mid][1] <= $offset) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }
        return $lo < count($spans) && $spans[$lo][0] <= $offset;
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
