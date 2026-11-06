<?php

namespace iceboxind\sorts\engine;

/**
 * A preset is a coherent set of choices that sits above the individual rule switches, as a
 * locale variant does for quotation marks: it says which rules run when no switch says otherwise,
 * which variant a locale uses when none is chosen, and how guillemets are spaced.
 *
 * How it combines with the options (Options::$rules, Options::$variants):
 *
 *  - A rule named in `Options::$rules`, on or off, wins over the preset. The preset only supplies
 *    the answer for a rule the options leave out. (The Craft layer leaves out a rule whose switch
 *    still has its default, so a switch moved off its default is the site’s own word.)
 *  - A variant chosen in `Options::$variants` wins over the preset’s variant for that locale.
 *  - A rule scope (`data-sorts-rules`, a rule named in a call) and `Options::$allowed` work as
 *    they always do: the preset never turns on a rule the options do not allow.
 *
 * `bringhurst`: the choices of Robert Bringhurst, *The Elements of Typographic Style*, 3rd ed.,
 * chapter 5, each with its section below (D817, Dan Gaidula, 2026-11-06). Without a preset every rule
 * and every locale behaves as it always did, byte for byte.
 */
final class Presets
{
    public const BRINGHURST = 'bringhurst';

    /** The no-break narrow space put inside guillemets (5.4.1: thin spaces are customary between them and the text). */
    public const GUILLEMET_SPACE = "\u{202F}";

    /** @return list<string> */
    public static function names(): array
    {
        return [self::BRINGHURST];
    }

    /**
     * The rules a preset turns on, by handle.
     *
     * - `ranges` (5.2.2), `multiplication` (5.2.6) and `fractions` (5.2.5): on, with clock times, the
     *   fraction before an x and the joined mixed number.
     * - `typed-dashes` (5.2.1): an em dash the writer typed takes the locale’s break dash.
     * - `dialogue-dash` (5.2.3): an em dash and a thin space open a line of dialogue.
     * - `ellipsis-spacing` (5.2.7): a word space either side of an ellipsis, in English.
     * - `locale-spacing` (5.4.1): runs everywhere, for the thin spaces inside guillemets.
     *
     * @return array<string, bool>
     */
    public static function rules(string $preset): array
    {
        return $preset === self::BRINGHURST ? [
            'ranges' => true,
            'multiplication' => true,
            'fractions' => true,
            'typed-dashes' => true,
            'dialogue-dash' => true,
            'ellipsis-spacing' => true,
            'locale-spacing' => true,
        ] : [];
    }

    /**
     * The variant a preset uses for a locale when the options choose none: a spaced en dash for
     * breaks in US English (5.2.1).
     *
     * @return array<string, string>
     */
    public static function variants(string $preset): array
    {
        return $preset === self::BRINGHURST ? ['en-US' => 'spaced-en-dash'] : [];
    }

    /**
     * Whether a preset spaces the guillemets of a locale: every locale that uses them, German
     * excepted, whose book style »…« sets none (Duden Band 9, decision D823). Swiss German
     * guillemets follow the Bundeskanzlei, which sets none inside either.
     */
    public static function spacesGuillemets(string $preset, LocaleInterface $locale): bool
    {
        if ($preset !== self::BRINGHURST || str_starts_with($locale->id(), 'de') || isset($locale->spacing()['guillemets'])) {
            return false;
        }
        $marks = array_merge($locale->quotes(), $locale->nestedQuotes(), $locale instanceof DataLocale ? ($locale->thirdQuotes() ?? []) : []);
        return array_intersect($marks, ['«', '»', '‹', '›']) !== [];
    }
}
