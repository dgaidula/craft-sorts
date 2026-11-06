<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\engine\Run;

/**
 * Decides what each straight quote in a run is: an opening or closing quote,
 * an apostrophe, or a prime. Quotes, apostrophes and primes depend on each
 * other (a quote after a digit is a prime or a closing quote, depending on
 * whether a quotation is open), so the three rules share this one scan; the
 * first of them to run does it for all the rules that are on.
 *
 * What it reads: the characters on either side of a quote, looking through
 * inline tags and character entities; which quotations are open in the flow
 * (kept in flow state, so a quotation can span tags); and, for ambiguous
 * cases, how many straight quotes are still to come. It changes only straight
 * quotes and their entity forms. Curly quotes are left as they are, though an
 * unambiguous one still counts as open or closed for the ones that follow.
 *
 * Which glyph a quotation gets is a separate choice from what it is (see
 * {@see Options::$quoteNesting}): by default the mark typed picks the level of an
 * outermost quotation (`"` the locale’s primary pair, `'` its secondary pair) and
 * a quotation inside another takes the other level from its parent, whatever was
 * typed; in `depth` mode the nesting depth alone does. A curly mark already in the
 * text counts as the level its glyph has in the locale (a glyph that is in both
 * pairs, as in French, counts as primary). Apostrophes and primes do not depend on it.
 */
final class QuoteScanner
{
    public const TRIGGERS = "\"'&“”‘’„‚«»‹›";

    private const START = 0;
    private const SPACE = 1;
    private const OPEN = 2;
    private const DASH = 3;
    private const WORD = 4;
    private const PUNCT = 5;
    private const CLOSE = 6;

    private const D_OPEN = 1;
    private const D_CLOSE = 2;
    private const D_APOSTROPHE = 3;
    private const D_PRIME = 4;
    private const D_DOUBLE_PRIME = 5;

    // Byte patterns: the curly quotes are matched as their UTF-8 bytes, which is much faster than /u.
    // Entity names are case-sensitive, as HTML has them (&quot; &QUOT; &apos;, never &Quot; or &APOS;);
    // the x of a hexadecimal reference is not.
    private const TOKENS_HTML = '/"|\'|&(?:quot|QUOT|apos|#0*(?:34|39)|#[xX]0*(?:22|27));|(?:“|”|‘|’|„|‚|«|»|‹|›)/';
    private const TOKENS_TEXT = '/"|\'|(?:“|”|‘|’|„|‚|«|»|‹|›)/';

    private const DOUBLE_ENTITY = '/^&(?:quot|QUOT|#0*34|#[xX]0*22);$/';
    private const SINGLE_ENTITY = '/^&(?:apos|#0*39|#[xX]0*27);$/';
    private const ANY_DOUBLE = '/"|&(?:quot|QUOT|#0*34|#[xX]0*22);/';
    private const ANY_SINGLE = '/\'|&(?:apos|#0*39|#[xX]0*27);/';

    // Byte patterns (no /u): the text before a quote may start inside a character.
    private const SINGLE_MARK = '(?:\'|&#0*39;|&#[xX]0*27;|&apos;|\xE2\x80\xB2)';
    private const DOUBLE_MARK = '(?:"|&quot;|&QUOT;|&#0*34;|&#[xX]0*22;|\xE2\x80\xB3)';

    private const LEADING_WORD = '/^(?:tis|twas|twere|twill|twould|em|cause|cos|bout|til|neath|t|s)(?![\p{L}\p{N}])/iu';
    private const LEADING_YEAR = '/^\d\d(?![\d\'’])/u';

    public static function scan(Run $run, Context $context): void
    {
        if (!$context->once('quote-scan')) {
            return;
        }
        $text = $run->text();
        $html = $context->mode === Options::HTML;
        if (!preg_match_all($html ? self::TOKENS_HTML : self::TOKENS_TEXT, $text, $found, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $tokens = $found[0];
        $locale = $context->locale;
        [$q0, $q1] = $locale->quotes();
        [$n0, $n1] = $locale->nestedQuotes();
        $apostrophe = $locale->apostrophe();
        $wantQuotes = $context->isActive('quotes');
        $wantApostrophes = $context->isActive('apostrophes');
        $wantPrimes = $context->isActive('primes');
        $typed = $context->quoteNesting === Options::NESTING_TYPED;

        $state = $context->flowState('quotes', ['stack' => [], 'levels' => [], 'd' => 0, 's' => 0]);
        $stack = $state['stack'];
        $levels = $state['levels'];
        $openD = $state['d'];
        $openS = $state['s'];

        $edits = [];
        $lastEnd = -1;
        $lastClass = self::PUNCT;
        $apostrophes = [];
        $suffix = [];
        $count = count($tokens);

        for ($k = 0; $k < $count; $k++) {
            [$raw, $offset] = $tokens[$k];
            $end = $offset + strlen($raw);

            $kind = null;
            if ($raw === '"' || ($html && $raw[0] === '&' && preg_match(self::DOUBLE_ENTITY, $raw))) {
                $kind = 'd';
            } elseif ($raw === "'" || ($html && $raw[0] === '&' && preg_match(self::SINGLE_ENTITY, $raw))) {
                $kind = 's';
            }

            if ($kind === null) {
                // A curly quote: leave it, but let an unambiguous one open or close a quotation.
                $opens = $raw === $q0 || $raw === $n0;
                $closes = $raw === $q1 || $raw === $n1;
                if ($opens && !$closes) {
                    // The glyph says which pair it is; a glyph in both pairs is the primary one.
                    $isPrimary = $raw === $q0;
                    $stack[] = $isPrimary ? 'd' : 's';
                    $levels[] = $isPrimary ? 0 : 1;
                    $isPrimary ? $openD++ : $openS++;
                } elseif ($closes && !$opens) {
                    $closeKind = $raw === $q1 ? 'd' : 's';
                    $open = $closeKind === 'd' ? $openD : $openS;
                    $mayClose = $raw !== $apostrophe || ($open > 0
                        && in_array(self::classBefore($text, $offset, $context, $html)[0], [self::WORD, self::PUNCT], true)
                        && in_array(self::classAfter($text, $end, $context, $html)[0], [self::START, self::SPACE, self::PUNCT, self::CLOSE], true));
                    if ($mayClose) {
                        self::pop($stack, $levels, $closeKind, $openD, $openS);
                    }
                }
                continue;
            }

            if ($raw === "'" && $offset > 0 && ctype_alpha($text[$offset - 1]) && ctype_alpha($text[$end] ?? '')) {
                // don't, o'clock: an apostrophe between two letters needs no further look.
                if ($wantApostrophes) {
                    $edits[] = [$offset, 1, $apostrophe, false];
                }
                $lastEnd = $end;
                $lastClass = self::PUNCT;
                continue;
            }

            [$prevClass, $prevChar] = $offset === $lastEnd
                ? [$lastClass, '']
                : self::classBefore($text, $offset, $context, $html);
            [$nextClass, $nextChar] = self::classAfter($text, $end, $context, $html);
            $digitBefore = $offset !== $lastEnd && $prevChar !== ''
                && (isset($prevChar[1]) ? preg_match('/^\p{N}$/u', $prevChar) === 1 : ctype_digit($prevChar));

            if ($kind === 'd') {
                $decision = self::decideDouble($text, $offset, $prevClass, $nextClass, $digitBefore, $openD > 0, $tokens, $k, $context, $suffix);
            } else {
                $decision = self::decideSingle(
                    $text,
                    $offset,
                    $end,
                    $prevClass,
                    $nextClass,
                    $nextChar,
                    $digitBefore,
                    $openS > 0,
                    $apostrophes,
                    $tokens,
                    $k,
                    $context,
                    $html,
                    $suffix
                );
            }

            $glyph = null;
            switch ($decision) {
                case self::D_OPEN:
                    // Typed: an outermost quotation takes the level its mark asks for (" primary, ' secondary),
                    // one inside another the other level from its parent. Depth: how deep the quotation is.
                    $level = $typed
                        ? ($stack ? 1 - $levels[count($levels) - 1] : ($kind === 'd' ? 0 : 1))
                        : count($stack) % 2;
                    $glyph = $wantQuotes ? ($level === 0 ? $q0 : $n0) : null;
                    $stack[] = $kind;
                    $levels[] = $level;
                    $kind === 'd' ? $openD++ : $openS++;
                    $lastClass = self::OPEN;
                    break;
                case self::D_CLOSE:
                    // The closing mark matches the level the quotation was opened at; one that closes
                    // nothing open takes the level its mark asks for.
                    $closed = self::pop($stack, $levels, $kind, $openD, $openS);
                    $level = $typed ? ($closed ?? ($kind === 'd' ? 0 : 1)) : count($stack) % 2;
                    $glyph = $wantQuotes ? ($level === 0 ? $q1 : $n1) : null;
                    $lastClass = self::CLOSE;
                    break;
                case self::D_APOSTROPHE:
                    $glyph = $wantApostrophes ? $apostrophe : null;
                    $lastClass = self::PUNCT;
                    break;
                case self::D_PRIME:
                    $glyph = $wantPrimes ? '′' : null;
                    $lastClass = self::PUNCT;
                    break;
                case self::D_DOUBLE_PRIME:
                    $glyph = $wantPrimes ? '″' : null;
                    $lastClass = self::PUNCT;
                    break;
            }
            $lastEnd = $end;
            if ($glyph !== null) {
                $edits[] = [$offset, strlen($raw), $glyph, false];
            }
        }

        $context->setFlowState('quotes', ['stack' => $stack, 'levels' => $levels, 'd' => $openD, 's' => $openS]);
        if ($edits) {
            $run->edit($edits);
        }
    }

    /**
     * Close the nearest open quotation of this kind, and any opened inside it.
     *
     * @return int|null the level (0 primary, 1 secondary) the closed quotation was opened at; null if none was open
     */
    private static function pop(array &$stack, array &$levels, string $kind, int &$openD, int &$openS): ?int
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i] === $kind) {
                $level = $levels[$i];
                array_splice($levels, $i);
                foreach (array_splice($stack, $i) as $closed) {
                    $closed === 'd' ? $openD-- : $openS--;
                }
                return $level;
            }
        }
        return null;
    }

    private static function decideDouble(
        string $text,
        int $offset,
        int $prevClass,
        int $nextClass,
        bool $digitBefore,
        bool $openD,
        array $tokens,
        int $k,
        Context $context,
        array &$suffix
    ): int {
        if ($digitBefore) {
            // 5'10" and 27" are inches unless this is the quotation’s end. Inside a
            // quotation, an odd number of quotes still to come means one of them closes it.
            $before = substr($text, max(0, $offset - 24), min($offset, 24));
            if (preg_match('/\d' . self::SINGLE_MARK . '\s?\d+(?:[.,]\d+)?$/', $before)
                || !$openD
                || self::remaining(self::ANY_DOUBLE, $tokens, $k, $context, $suffix) % 2 === 1) {
                return self::D_DOUBLE_PRIME;
            }
            return self::D_CLOSE;
        }
        $opening = in_array($prevClass, [self::START, self::SPACE, self::OPEN, self::DASH], true);
        $beforeBreak = in_array($nextClass, [self::START, self::SPACE], true);
        if ($opening) {
            return $beforeBreak && $openD ? self::D_CLOSE : self::D_OPEN;
        }
        if (($prevClass === self::PUNCT || $prevClass === self::CLOSE) && $nextClass === self::WORD && !$openD) {
            return self::D_OPEN;
        }
        return self::D_CLOSE;
    }

    private static function decideSingle(
        string $text,
        int $offset,
        int $end,
        int $prevClass,
        int $nextClass,
        string $nextChar,
        bool $digitBefore,
        bool $openS,
        array &$apostrophes,
        array $tokens,
        int $k,
        Context $context,
        bool $html,
        array &$suffix
    ): int {
        if (isset($apostrophes[$offset])) {
            return self::D_APOSTROPHE;
        }
        if ($digitBefore) {
            $before = substr($text, max(0, $offset - 24), min($offset, 24));
            $after = substr($text, $end, 24);
            if (preg_match('/\xC2\xB0\s?\d+(?:[.,]\d+)?$/', $before)
                || preg_match('/^\s?\d+(?:[.,]\d+)?\s?' . self::DOUBLE_MARK . '/', $after)) {
                return self::D_PRIME;
            }
        }
        if ($prevClass === self::WORD && $nextClass === self::WORD) {
            return self::D_APOSTROPHE;
        }
        if ($digitBefore) {
            return $openS ? self::D_CLOSE : self::D_PRIME;
        }
        $opening = in_array($prevClass, [self::START, self::SPACE, self::OPEN, self::DASH], true);
        if ($nextClass === self::WORD) {
            // rock 'n' roll: both marks are apostrophes.
            if (($nextChar === 'n' || $nextChar === 'N') && substr($text, $end + 1, 1) === "'") {
                $after = self::classAfter($text, $end + 2, $context, $html)[0];
                if ($after !== self::WORD) {
                    $apostrophes[$end + 1] = true;
                    return self::D_APOSTROPHE;
                }
            }
            $head = substr($text, $end, 16);
            if (preg_match(self::LEADING_WORD, $head) || preg_match(self::LEADING_YEAR, $head)) {
                return self::D_APOSTROPHE;
            }
            return self::D_OPEN;
        }
        if (!$opening) {
            // dogs' bowls: an apostrophe, unless a quotation is open and this ends it.
            if (!$openS) {
                return self::D_APOSTROPHE;
            }
            $possessive = preg_match('/[sS]$/', substr($text, max(0, $offset - 1), 1))
                && $nextClass === self::SPACE
                && preg_match('/^\s+\p{Ll}/u', substr($text, $end, 8))
                && self::remaining(self::ANY_SINGLE, $tokens, $k, $context, $suffix) > 0;
            return $possessive ? self::D_APOSTROPHE : self::D_CLOSE;
        }
        if ($nextClass === self::SPACE || $nextClass === self::START) {
            return $openS ? self::D_CLOSE : self::D_APOSTROPHE;
        }
        return self::D_OPEN;
    }

    /**
     * Straight quotes of one kind still to come after token $k, in this run and the rest of the flow.
     * The counts for the run are suffix sums, built once per scan and pattern, so each call is O(1).
     *
     * @param array<string, array<int, int>> $suffix
     */
    private static function remaining(string $pattern, array $tokens, int $k, Context $context, array &$suffix): int
    {
        if (!isset($suffix[$pattern])) {
            $count = count($tokens);
            $sums = array_fill(0, $count + 1, 0);
            for ($i = $count - 1; $i >= 0; $i--) {
                $sums[$i] = $sums[$i + 1] + preg_match($pattern, $tokens[$i][0]);
            }
            $suffix[$pattern] = $sums;
        }
        return $suffix[$pattern][$k + 1] + $context->countAfter($pattern);
    }

    /** @return array{int, string} the class and the character just before $offset */
    private static function classBefore(string $text, int $offset, Context $context, bool $html): array
    {
        if ($offset > 0) {
            // The usual case, an ASCII character before: no need to look further.
            $char = $text[$offset - 1];
            if ($char < "\x80" && $char !== ';') {
                return [self::classOf($char), $char];
            }
        }
        $tail = $offset >= 14 ? substr($text, $offset - 14, 14) : $context->before(14) . substr($text, 0, $offset);
        if ($tail === '') {
            return [self::START, ''];
        }
        if ($html && $tail[-1] === ';' && preg_match('/&(#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});$/', $tail, $m)) {
            $char = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return [$char === $m[0] ? self::PUNCT : self::classOf($char), $char];
        }
        $i = strlen($tail) - 1;
        while ($i > 0 && (ord($tail[$i]) & 0xC0) === 0x80) {
            $i--;
        }
        $char = substr($tail, $i);
        return [self::classOf($char), $char];
    }

    /** @return array{int, string} the class and the character at $offset */
    private static function classAfter(string $text, int $offset, Context $context, bool $html): array
    {
        if (isset($text[$offset])) {
            $char = $text[$offset];
            if ($char < "\x80" && $char !== '&') {
                return [self::classOf($char), $char];
            }
        }
        $head = substr($text, $offset, 16);
        if (strlen($head) < 16) {
            $head .= $context->after(14);
        }
        if ($head === '') {
            return [self::START, ''];
        }
        if ($html && $head[0] === '&' && preg_match('/^&(#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});/', $head, $m)) {
            $char = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return [$char === $m[0] ? self::PUNCT : self::classOf($char), $char];
        }
        $lead = ord($head[0]);
        $length = $lead < 0x80 ? 1 : ($lead >= 0xF0 ? 4 : ($lead >= 0xE0 ? 3 : 2));
        $char = substr($head, 0, $length);
        return [self::classOf($char), $char];
    }

    private static function classOf(string $char): int
    {
        if ($char === '') {
            return self::START;
        }
        if (strlen($char) === 1) {
            if (ctype_alnum($char)) {
                return self::WORD;
            }
            if (ctype_space($char)) {
                return self::SPACE;
            }
            return match ($char) {
                '(', '[', '{', '<' => self::OPEN,
                ')', ']', '}', '>' => self::CLOSE,
                '-' => self::DASH,
                default => self::PUNCT,
            };
        }
        switch ($char) {
            case "\u{00A0}": case "\u{202F}": case "\u{2000}": case "\u{2001}": case "\u{2002}": case "\u{2003}":
            case "\u{2004}": case "\u{2005}": case "\u{2006}": case "\u{2007}": case "\u{2008}": case "\u{2009}":
            case "\u{200A}": case "\u{200B}": case "\u{205F}": case "\u{3000}": case "\u{FEFF}":
                return self::SPACE;
            case '—': case '–': case '―': case '‒': case '−':
                return self::DASH;
            case '“': case '‘': case '„': case '‚': case '‟': case '‛': case '«': case '‹': case '¿': case '¡':
                return self::OPEN;
            case '”': case '’': case '»': case '›':
                return self::CLOSE;
        }
        return preg_match('/^[\p{L}\p{N}\p{M}]$/u', $char) ? self::WORD : self::PUNCT;
    }
}
