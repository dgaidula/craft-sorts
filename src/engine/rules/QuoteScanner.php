<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\DataLocale;
use iceboxind\sorts\engine\LocaleInterface;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\engine\Run;
use WeakMap;

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
 * pairs, as in French, counts as primary), and is read as an opening or closing mark
 * by its role there, not its shape (German “ closes); one that is both (fi, sv ”) is
 * read by its sides. Apostrophes and primes do not depend on it.
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
    // Not a class: one of the locale’s marks that both opens and closes (see marks()).
    private const BOTH = 7;

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
    // The single-quote shapes among the curly marks, and a pattern for a single mark in the rest of a flow
    // that could close a quotation: not after a space or an opening mark, not before a letter or digit. A run
    // starts and ends in the middle of the flow, so a mark at either end is counted as one that could.
    private const SINGLE_MARKS = ['‘' => true, '’' => true, '‹' => true, '›' => true];
    private const CLOSING_SINGLE_MARK = '/(?<![\s(\[{"“‘«‹])(?:\'|&(?:apos|#0*39|#[xX]0*27);)(?![\p{L}\p{N}])|[‘’‹›](?![\p{L}\p{N}])/u';
    private const ANY_DOUBLE = '/"|&(?:quot|QUOT|#0*34|#[xX]0*22);/';
    private const ANY_SINGLE = '/\'|&(?:apos|#0*39|#[xX]0*27);/';

    // Byte patterns (no /u): the text before a quote may start inside a character.
    private const SINGLE_MARK = '(?:\'|&#0*39;|&#[xX]0*27;|&apos;|\xE2\x80\xB2)';
    private const DOUBLE_MARK = '(?:"|&quot;|&QUOT;|&#0*34;|&#[xX]0*22;|\xE2\x80\xB3)';

    /** @var WeakMap<LocaleInterface, array{list<string>, list<string>, array<string, int>}>|null {@see marks()}, kept per locale object */
    private static ?WeakMap $marks = null;

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
        self::$marks ??= new WeakMap();
        [$opening, $closing, $roles] = self::$marks[$locale] ??= self::marks($locale);
        [$q0, $n0, $t0] = $opening;
        [$q1, $n1] = $closing;
        $third = $t0 !== '';
        $apostrophe = $locale->apostrophe();
        $wantQuotes = $context->isActive('quotes');
        $wantApostrophes = $context->isActive('apostrophes');
        $wantPrimes = $context->isActive('primes');
        $typed = $context->quoteNesting === Options::NESTING_TYPED;

        // One object for the whole flow, changed in place, so a run does not copy the open quotations.
        $state = $context->flowState('quotes');
        if ($state === null) {
            $state = (object) ['stack' => [], 'levels' => [], 'curly' => [], 'at' => ['d' => [], 's' => []], 'd' => 0, 's' => 0];
            $context->setFlowState('quotes', $state);
        }
        $stack = &$state->stack;
        $levels = &$state->levels;
        $curly = &$state->curly;
        // Where in the stack the open quotations of each kind are, so the nearest one is found at once.
        $at = &$state->at;
        $openD = &$state->d;
        $openS = &$state->s;

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
                // A curly quote: leave it, but let one of the locale’s own marks open or close a quotation.
                $role = $roles[$raw] ?? null;
                if ($role !== null) {
                    // The glyph says which pair it is; a glyph in more than one pair is the outer one.
                    $level = $raw === $q0 || $raw === $q1 ? 0 : ($raw === $n0 || $raw === $n1 ? 1 : 2);
                    $markKind = $level === 0 ? 'd' : 's';
                    $open = $markKind === 'd' ? $openD : $openS;
                    if ($role === self::BOTH) {
                        // fi, sv: ” and ’ both open and close. Tell them apart by what is on either side,
                        // as a straight mark is told: after a space or the start and not before one it
                        // opens; after a word or punctuation, with a quotation of its pair open, it closes.
                        $prevClass = $offset === $lastEnd ? $lastClass : self::classBefore($text, $offset, $context, $html, $roles)[0];
                        $nextClass = self::classAfter($text, $end, $context, $html, $roles)[0];
                        $head = substr($text, $end, 16);
                        if (in_array($prevClass, [self::START, self::SPACE, self::OPEN, self::DASH], true)
                            && $nextClass !== self::START && $nextClass !== self::SPACE
                            && ($raw !== $apostrophe || !(preg_match(self::LEADING_WORD, $head) || preg_match(self::LEADING_YEAR, $head)))) {
                            $role = self::OPEN;
                        } elseif ($open > 0 && in_array($prevClass, [self::WORD, self::PUNCT, self::CLOSE], true)
                            && ($raw !== $apostrophe || in_array($nextClass, [self::START, self::SPACE, self::PUNCT, self::CLOSE], true))) {
                            $role = self::CLOSE;
                        } else {
                            $role = null;
                        }
                    } elseif ($role === self::CLOSE && $raw === $apostrophe && !($open > 0
                        && in_array(self::classBefore($text, $offset, $context, $html, $roles)[0], [self::WORD, self::PUNCT], true)
                        && in_array(self::classAfter($text, $end, $context, $html, $roles)[0], [self::START, self::SPACE, self::PUNCT, self::CLOSE], true))) {
                        // The closing mark is also the apostrophe: it closes only where a quotation can end.
                        $role = null;
                    }
                    if ($role === self::OPEN) {
                        $at[$markKind][] = count($stack);
                        $stack[] = $markKind;
                        $levels[] = $level;
                        $curly[] = true;
                        $markKind === 'd' ? $openD++ : $openS++;
                    } elseif ($role === self::CLOSE) {
                        self::pop($stack, $levels, $curly, $at, $markKind, $openD, $openS);
                    }
                }
                $lastEnd = $end;
                $lastClass = $role === self::OPEN || $role === self::CLOSE ? $role : self::classOf($raw);
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
                : self::classBefore($text, $offset, $context, $html, $roles);
            [$nextClass, $nextChar] = self::classAfter($text, $end, $context, $html, $roles);
            $digitBefore = $offset !== $lastEnd && $prevChar !== ''
                && (isset($prevChar[1]) ? preg_match('/^\p{N}$/u', $prevChar) === 1 : ctype_digit($prevChar));
            // The innermost quotation, or past one opened straight of the other kind the nearest of this
            // mark's kind; when it was opened with a curly mark, its closing mark still to come tells an
            // inch mark or a possessive from its end.
            $top = count($stack) - 1;
            $target = $top >= 0 && !$curly[$top] && $stack[$top] !== $kind
                ? ($at[$kind] === [] ? -1 : $at[$kind][array_key_last($at[$kind])])
                : $top;
            $curlyPair = $target >= 0 && $curly[$target]
                ? [$opening[$levels[$target]], $closing[$levels[$target]], $apostrophe, $html, $roles]
                : null;
            // en-GB single-outer: “ is the secondary pair, so a straight ' closes it by kind, even from inside a
            // curly ‘…’ opened within it. A ' there has the shape of the ‘…’ it sits in, not of the “ it would
            // close, so after a digit or as a possessive it stays a prime or an apostrophe (FU2, D867).
            $pastCurlySingle = $kind === 's' && $top >= 0 && $curly[$top] && $stack[$top] === 'd'
                && isset(self::SINGLE_MARKS[$opening[$levels[$top]]])
                && $at['s'] !== [] && $curly[$at['s'][array_key_last($at['s'])]];

            if ($kind === 'd') {
                $decision = self::decideDouble($text, $offset, $prevClass, $nextClass, $digitBefore, $openD > 0, $tokens, $k, $context, $suffix, $curlyPair);
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
                    $suffix,
                    $roles,
                    $curlyPair,
                    $stack === [],
                    $pastCurlySingle
                );
            }

            $glyph = null;
            switch ($decision) {
                case self::D_OPEN:
                    // Typed: an outermost quotation takes the level its mark asks for (" primary, ' secondary),
                    // one inside another the other level from its parent. Depth: how deep the quotation is.
                    // With a third pair, three deep takes it, and deeper the nested and third pairs alternate.
                    $depth = count($stack);
                    if ($typed) {
                        $parent = $depth > 0 ? $levels[$depth - 1] : null;
                        $level = match (true) {
                            $parent === null => $kind === 'd' ? 0 : 1,
                            $parent === 2 => 1,
                            $third && $depth >= 2 => 2,
                            default => 1 - $parent,
                        };
                    } else {
                        $level = $third && $depth >= 2 ? 2 - $depth % 2 : $depth % 2;
                    }
                    $glyph = $wantQuotes ? $opening[$level] : null;
                    $at[$kind][] = $depth;
                    $stack[] = $kind;
                    $levels[] = $level;
                    $curly[] = false;
                    $kind === 'd' ? $openD++ : $openS++;
                    $lastClass = self::OPEN;
                    break;
                case self::D_CLOSE:
                    // The closing mark matches the level the quotation was opened at; one that closes
                    // nothing open takes the level its mark asks for (typed) or its depth (depth).
                    $closed = self::pop($stack, $levels, $curly, $at, $kind, $openD, $openS);
                    $depth = count($stack);
                    $level = $closed ?? ($typed ? ($kind === 'd' ? 0 : 1) : ($third && $depth >= 2 ? 2 - $depth % 2 : $depth % 2));
                    $glyph = $wantQuotes ? $closing[$level] : null;
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

        if ($edits) {
            $run->edit($edits);
        }
    }

    /**
     * Close the nearest open quotation of this kind, and any opened inside it. Each quotation is
     * removed once, from the top, so the cost is the number closed, not the depth.
     *
     * @return int|null the level (0 primary, 1 secondary, 2 third) the closed quotation was opened at; null if none was open
     */
    private static function pop(array &$stack, array &$levels, array &$curly, array &$at, string $kind, int &$openD, int &$openS): ?int
    {
        if (($kind === 'd' ? $openD : $openS) === 0) {
            return null;
        }
        do {
            $closed = array_pop($stack);
            $level = array_pop($levels);
            array_pop($curly);
            array_pop($at[$closed]);
            $closed === 'd' ? $openD-- : $openS--;
        } while ($closed !== $kind && $stack !== []);
        return $level;
    }

    /**
     * Whether the closing mark of a quotation opened with a curly mark is still to come in the
     * flow after token $k: more of its closing marks than its opening ones. A closing mark that
     * is also the apostrophe counts only where it is not followed by a letter or digit, and, when
     * $skipPossessive is true, not after an s with a space and a lowercase word after it (a plural
     * possessive, as decideSingle() reads one; see headAfter()). A pair whose marks are the same glyph
     * (fi, sv) cannot tell its openers from its closers, so an odd number of its marks still to come
     * counts, as the straight-quote test counts its pairs; when that glyph is also the apostrophe, a ’
     * before a word counts where the scan would read it as an opener.
     *
     * $skipPossessive is true on the single-quote path, where a closing ’ after an s is more likely a
     * possessive than the end of the quotation (R4-1, D850 a), and false on the double-quote path,
     * where an inch mark is being judged and the ’ after an s is a real closer. The two read the same
     * pair but count differently, so the flag is part of the cache key: dropping it from the key makes
     * whichever path asked first decide for the other.
     *
     * @param array{string, string, string, bool, array<string, int>} $pair opening and closing mark, apostrophe, html, roles
     * @param array<string, array<int, int>> $suffix
     * @param bool $skipPossessive whether a ’ after an s, a space and a lowercase word is not counted as a closer
     */
    private static function closerAhead(array $pair, string $text, array $tokens, int $k, Context $context, array &$suffix, bool $skipPossessive = true): bool
    {
        [$open, $close, $apostrophe, $html, $roles] = $pair;
        $key = 'closer:' . $close . $open . ($skipPossessive ? 'p' : '');
        if (!isset($suffix[$key])) {
            $count = count($tokens);
            $sums = array_fill(0, $count + 1, 0);
            for ($i = $count - 1; $i >= 0; $i--) {
                [$raw, $at] = $tokens[$i];
                $step = 0;
                if ($raw === $open && $open !== $close) {
                    $step = -1;
                } elseif ($raw === $close && ($close !== $apostrophe
                    || (self::classAfter($text, $at + strlen($raw), $context, $html, $roles)[0] !== self::WORD
                        && !($skipPossessive && preg_match('/[sS]$/', substr($text, max(0, $at - 1), 1))
                            && preg_match('/^\s+\p{Ll}/u', self::headAfter($text, $at + strlen($raw), $context, $html))))
                    // fi, sv ’…’: the opener is the apostrophe too, so it is counted where the scan reads one (R4-2).
                    || ($open === $close
                        && in_array(self::classBefore($text, $at, $context, $html, $roles)[0], [self::START, self::SPACE, self::OPEN, self::DASH], true)
                        && !(preg_match(self::LEADING_WORD, $head = substr($text, $at + strlen($raw), 16)) || preg_match(self::LEADING_YEAR, $head))))) {
                    $step = 1;
                }
                $sums[$i] = $sums[$i + 1] + $step;
            }
            $suffix[$key] = $sums;
        }
        // After the run a mark is counted by its shape; a same-glyph ’ after a space or the start and before a letter opens.
        $closePattern = '/' . preg_quote($close, '/') . ($close === $apostrophe
            ? '(?![\p{L}\p{N}])' . ($open === $close ? '|(?<!\S)' . preg_quote($open, '/') . '(?=\p{L})' : '') . '/u'
            : '/');
        $opensAfter = $open === $close ? 0 : $context->countAfter('/' . preg_quote($open, '/') . '/');
        $ahead = $suffix[$key][$k + 1] + $context->countAfter($closePattern) - $opensAfter;
        return $open === $close ? $ahead % 2 === 1 : $ahead > 0;
    }

    /**
     * The text after a mark for the possessive test, read as classAfter() reads it: on past the end of
     * the run (players’ <a>bus</a>) and with a character entity as its character (players’&nbsp;bus).
     * The bytes may end inside a character, which /u refuses, so that part is dropped (R4-4).
     */
    private static function headAfter(string $text, int $offset, Context $context, bool $html): string
    {
        $head = substr($text, $offset, 8);
        if (strlen($head) < 8) {
            $head .= $context->after(8);
        }
        $head = preg_replace('/(?:[\xC0-\xDF]|[\xE0-\xEF][\x80-\xBF]?|[\xF0-\xF7][\x80-\xBF]{0,2})$/', '', $head);
        return $html && str_contains($head, '&') ? html_entity_decode($head, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $head;
    }

    /**
     * Whether only whitespace comes before $offset in its flow. HTML does not show a newline and an indent
     * after `<p>` or `<br>`, or a `&nbsp;` there, so a mark after them opens its block as one with nothing
     * before it does (D876).
     */
    private static function opensBlock(string $text, int $offset, Context $context, bool $html): bool
    {
        $blank = $html ? '/(?:\s|\x{00A0}|&(?:nbsp|#160|#[xX]0*[aA]0);)+$/u' : '/[\s\x{00A0}]+$/u';
        if (preg_replace($blank, '', substr($text, 0, $offset)) !== '') {
            return false;
        }
        // The earlier runs, read further back until a character that is not whitespace or the start of the flow.
        for ($length = 16; ; $length *= 4) {
            $before = $context->before($length);
            $rest = preg_replace($blank, '', $before);
            if (mb_strlen($before) < $length) {
                return $rest === '';
            }
            // An entity the cut split (`bsp;`) is read again, whole, on the next round.
            if ($rest !== '' && !preg_match('/^[#A-Za-z0-9]*;$/', (string) $rest)) {
                return false;
            }
        }
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
        array &$suffix,
        ?array $curlyPair
    ): int {
        if ($digitBefore) {
            // 5'10" and 27" are inches unless this is the quotation’s end. Inside a
            // quotation, an odd number of quotes still to come means one of them closes it,
            // and so does its own closing mark still to come when it was opened curly.
            $before = substr($text, max(0, $offset - 24), min($offset, 24));
            if (preg_match('/\d' . self::SINGLE_MARK . '\s?\d+(?:[.,]\d+)?$/', $before)
                || !$openD
                || self::remaining(self::ANY_DOUBLE, $tokens, $k, $context, $suffix) % 2 === 1
                || ($curlyPair !== null && self::closerAhead($curlyPair, $text, $tokens, $k, $context, $suffix, false))) {
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
        array &$suffix,
        array $roles,
        ?array $curlyPair,
        bool $noneOpen,
        bool $pastCurlySingle
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
            // 6' is a foot mark unless this is the quotation’s end; before a space or a word it is
            // not when the quotation was opened curly and its own closing mark still follows.
            return $openS && !$pastCurlySingle && !(($nextClass === self::SPACE || $nextClass === self::WORD)
                && $curlyPair !== null && self::closerAhead($curlyPair, $text, $tokens, $k, $context, $suffix))
                ? self::D_CLOSE
                : self::D_PRIME;
        }
        $opening = in_array($prevClass, [self::START, self::SPACE, self::OPEN, self::DASH], true);
        if ($nextClass === self::WORD) {
            // rock 'n' roll: both marks are apostrophes.
            if (($nextChar === 'n' || $nextChar === 'N') && substr($text, $end + 1, 1) === "'") {
                $after = self::classAfter($text, $end + 2, $context, $html, $roles)[0];
                if ($after !== self::WORD) {
                    $apostrophes[$end + 1] = true;
                    return self::D_APOSTROPHE;
                }
            }
            $head = substr($text, $end, 16);
            if (preg_match(self::LEADING_WORD, $head) || preg_match(self::LEADING_YEAR, $head)) {
                return self::D_APOSTROPHE;
            }
            // 'Áshįįh 'Asdzáán: at the start of a word, with nothing later that could close it, it opens
            // no quotation (Bringhurst 5.5.2). Only where no quotation is open: inside one, a lone mark is
            // as likely a nested quotation left open ('hood in dialogue reads either way) and keeps its old reading.
            // In running text only: a mark that opens its block opens a quotation, since a quotation over several
            // paragraphs opens each one and closes only the last (D876).
            if ($opening && $noneOpen && !self::opensBlock($text, $offset, $context, $html) && self::singleClosersAfter($text, $tokens, $k, $context, $html, $roles, $suffix) === 0) {
                return self::D_APOSTROPHE;
            }
            return self::D_OPEN;
        }
        if (!$opening) {
            // dogs' bowls: an apostrophe, unless a quotation is open and this ends it.
            if (!$openS) {
                return self::D_APOSTROPHE;
            }
            // The eight bytes after it may end inside a character (dogs' bowls”), which /u refuses: drop that part.
            $possessive = preg_match('/[sS]$/', substr($text, max(0, $offset - 1), 1))
                && $nextClass === self::SPACE
                && preg_match('/^\s+\p{Ll}/u', preg_replace('/(?:[\xC0-\xDF]|[\xE0-\xEF][\x80-\xBF]?|[\xF0-\xF7][\x80-\xBF]{0,2})$/', '', substr($text, $end, 8)))
                && ($pastCurlySingle || self::remaining(self::ANY_SINGLE, $tokens, $k, $context, $suffix) > 0
                    || ($curlyPair !== null && self::closerAhead($curlyPair, $text, $tokens, $k, $context, $suffix)));
            return $possessive ? self::D_APOSTROPHE : self::D_CLOSE;
        }
        if ($nextClass === self::SPACE || $nextClass === self::START) {
            return $openS ? self::D_CLOSE : self::D_APOSTROPHE;
        }
        return self::D_OPEN;
    }

    /**
     * How many single-quote marks that could close a quotation are still to come after token $k: a
     * straight one after a word, punctuation or a closing mark and before a space, punctuation, a
     * closing mark or the end, and a curly one the locale closes with (that is not an apostrophe
     * inside a word). In the rest of the flow the marks are counted by their shape in their own run
     * (see CLOSING_SINGLE_MARK), so a doubt is read as a possible closer and the mark stays an opening one.
     *
     * @param array<string, int> $roles
     * @param array<string, array<int, int>> $suffix
     */
    private static function singleClosersAfter(string $text, array $tokens, int $k, Context $context, bool $html, array $roles, array &$suffix): int
    {
        if (!isset($suffix['single-closers'])) {
            $count = count($tokens);
            $sums = array_fill(0, $count + 1, 0);
            for ($i = $count - 1; $i >= 0; $i--) {
                [$raw, $at] = $tokens[$i];
                $step = 0;
                if ($raw === "'" || ($html && $raw[0] === '&' && preg_match(self::SINGLE_ENTITY, $raw))) {
                    $step = in_array(self::classBefore($text, $at, $context, $html, $roles)[0], [self::WORD, self::PUNCT, self::CLOSE], true)
                        && in_array(self::classAfter($text, $at + strlen($raw), $context, $html, $roles)[0], [self::START, self::SPACE, self::PUNCT, self::CLOSE], true)
                        ? 1 : 0;
                } elseif (isset(self::SINGLE_MARKS[$raw]) && in_array($roles[$raw] ?? null, [self::CLOSE, self::BOTH], true)
                    && self::classAfter($text, $at + strlen($raw), $context, $html, $roles)[0] !== self::WORD) {
                    $step = 1;
                }
                $sums[$i] = $sums[$i + 1] + $step;
            }
            $suffix['single-closers'] = $sums;
        }
        return $suffix['single-closers'][$k + 1] + $context->countAfter(self::CLOSING_SINGLE_MARK);
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

    /**
     * A locale’s opening and closing marks by level (primary, nested, third; '' for no third
     * pair, as most locales have), and the class of each of its marks next to a straight quote:
     * its opening marks OPEN and its closing marks CLOSE, whatever their shape (German “ and ‘
     * close, and the book style’s » and › open), and BOTH for a mark that is both (fi and sv ”
     * and ’).
     *
     * @return array{list<string>, list<string>, array<string, int>}
     */
    private static function marks(LocaleInterface $locale): array
    {
        [$q0, $q1] = $locale->quotes();
        [$n0, $n1] = $locale->nestedQuotes();
        // A locale may have a third pair (es guillemets-outer): three deep, then it alternates with the nested pair.
        [$t0, $t1] = ($locale instanceof DataLocale ? $locale->thirdQuotes() : null) ?? ['', ''];
        $opening = [$q0, $n0, $t0];
        $closing = [$q1, $n1, $t1];
        $roles = [];
        foreach ($opening as $mark) {
            if ($mark !== '') {
                $roles[$mark] = self::OPEN;
            }
        }
        foreach ($closing as $mark) {
            if ($mark !== '') {
                $roles[$mark] = ($roles[$mark] ?? self::CLOSE) === self::CLOSE ? self::CLOSE : self::BOTH;
            }
        }
        return [$opening, $closing, $roles];
    }

    /**
     * @param array<string, int> $roles
     * @return array{int, string} the class and the character just before $offset
     */
    private static function classBefore(string $text, int $offset, Context $context, bool $html, array $roles): array
    {
        if ($offset > 0) {
            // The usual case, an ASCII character before: no need to look further.
            $char = $text[$offset - 1];
            if ($char < "\x80" && $char !== ';') {
                return [self::classOf($char), $char];
            }
        }
        // Before the run, this is what the earlier runs became: a mark there may be this scan’s own.
        $tail = $offset >= 14 ? substr($text, $offset - 14, 14) : $context->before(14) . substr($text, 0, $offset);
        if ($tail === '') {
            return [self::START, ''];
        }
        if ($html && $tail[-1] === ';' && preg_match('/&(#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});$/', $tail, $m)) {
            $char = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return [$char === $m[0] ? self::PUNCT : self::markClass($char, $roles, substr($tail, 0, -strlen($m[0])), $html), $char];
        }
        $i = strlen($tail) - 1;
        while ($i > 0 && (ord($tail[$i]) & 0xC0) === 0x80) {
            $i--;
        }
        $char = substr($tail, $i);
        return [self::markClass($char, $roles, substr($tail, 0, $i), $html), $char];
    }

    /**
     * classOf() for a character with the text before it, so that a mark that both opens and
     * closes (fi, sv) is told by its own left side, as the scan decided it: after a space, an
     * opening bracket, a dash or the start it opened; after anything else it closed. A character
     * entity there is read as the character it stands for, as in classBefore().
     *
     * @param array<string, int> $roles
     */
    private static function markClass(string $char, array $roles, string $before, bool $html): int
    {
        if (($roles[$char] ?? null) !== self::BOTH) {
            return self::classOf($char, $roles);
        }
        if ($before === '') {
            return self::OPEN;
        }
        if ($html && $before[-1] === ';' && preg_match('/&(#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});$/', $before, $m)) {
            $decoded = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded !== $m[0]) {
                return in_array(self::classOf($decoded), [self::SPACE, self::OPEN, self::DASH], true) ? self::OPEN : self::CLOSE;
            }
        }
        $i = strlen($before) - 1;
        while ($i > 0 && (ord($before[$i]) & 0xC0) === 0x80) {
            $i--;
        }
        return in_array(self::classOf(substr($before, $i)), [self::SPACE, self::OPEN, self::DASH], true) ? self::OPEN : self::CLOSE;
    }

    /**
     * @param array<string, int> $roles
     * @return array{int, string} the class and the character at $offset
     */
    private static function classAfter(string $text, int $offset, Context $context, bool $html, array $roles): array
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
            return [$char === $m[0] ? self::PUNCT : self::classOf($char, $roles), $char];
        }
        $lead = ord($head[0]);
        $length = $lead < 0x80 ? 1 : ($lead >= 0xF0 ? 4 : ($lead >= 0xE0 ? 3 : 2));
        $char = substr($head, 0, $length);
        return [self::classOf($char, $roles), $char];
    }

    /**
     * A character’s class. One of the locale’s own quotation marks has the class of its role there
     * (see {@see marks()}); any other mark the class its shape has in English and French.
     *
     * @param array<string, int> $roles
     */
    private static function classOf(string $char, array $roles = []): int
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
        $role = $roles[$char] ?? self::BOTH;
        if ($role !== self::BOTH) {
            return $role;
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
