<?php

namespace iceboxind\sorts\ckeditor;

use Craft;
use craft\ckeditor\events\ModifyConfigEvent;
use craft\ckeditor\Field;
use craft\ckeditor\helpers\CkeditorConfig;
use craft\helpers\Cp;
use iceboxind\sorts\engine\LocaleInterface;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\Plugin;
use Throwable;
use yii\base\Event;

/**
 * As-you-type quotes in CKEditor (Lite and Pro), by loading CKEditor’s own
 * `TextTransformation` plugin and telling it which of its transformations to run.
 *
 * `craftcms/ckeditor` leaves `TextTransformation` off. Registering it with
 * `CkeditorConfig::registerFirstPartyPackage()` adds it to the core `ckeditor5` package, and
 * `CkeditorConfig::getImports()` loads every plugin of that package that no toolbar button
 * gates, so every CKEditor field gets it with nothing to switch on per field (a field’s own
 * config can still opt out with `removePlugins: ['TextTransformation']`). Left to its own
 * defaults it would also turn `->` into an arrow and `<=` into a symbol, and `--` into an en
 * dash in American English, which is not what Sorts does, so {@see modifyConfig()} narrows it.
 *
 * What this can and cannot do, because the editor’s config reaches the browser as JSON and
 * JSON cannot carry a regular expression: CKEditor’s built-in transformations are the only
 * ones available by name. Of those, only the ones whose quotation marks match the site
 * language’s pairs are enabled, and the key typed picks the pair as it does in Sorts
 * (typing `"` gives the language’s primary pair, `'` its secondary pair; see
 * `Options::$quoteNesting`), so typing `"` in an English field gives “…” and `'` gives ‘…’,
 * and in a German or French field both are left straight, to be set by the page pass or
 * fix-on-save, as are the keys of an en-GB single-outer field (CKEditor’s en-GB pairs put
 * ‘…’ on `'`, Sorts’ put it on `"`). With the Pro setting that normalizes quotes by depth,
 * `'` is left straight everywhere too: CKEditor cannot tell that a typed `'` is
 * outermost, where depth gives it the primary pair. Apostrophes, primes, ranges and spacing are not
 * transformations CKEditor offers. It acts only on text typed, never on pasted text or on
 * content already stored. The language is the control panel’s requested site (`?site=`), which is
 * what an element’s edit page uses; an editor opened some other way gets the current site’s.
 */
final class CkeditorSupport
{
    /**
     * CKEditor’s quote transformations: name => [the key typed, opening mark, closing mark].
     * Read from `ckeditor5.js` 5.9.0 (`quotesPrimary` and the rest).
     */
    private const QUOTES = [
        'quotesPrimary' => ['"', '“', '”'],
        'quotesSecondary' => ["'", '‘', '’'],
        'quotesPrimaryEnGb' => ["'", '‘', '’'],
        'quotesSecondaryEnGb' => ['"', '“', '”'],
        'quotesPrimaryPl' => ['"', '„', '”'],
        'quotesSecondaryPl' => ["'", '‚', '’'],
    ];

    public static function register(): void
    {
        if (!class_exists(CkeditorConfig::class) || !class_exists(Field::class)) {
            return;
        }
        CkeditorConfig::registerFirstPartyPackage(['TextTransformation']);
        Event::on(Field::class, Field::EVENT_MODIFY_CONFIG, [self::class, 'modifyConfig']);
    }

    public static function modifyConfig(ModifyConfigEvent $event): void
    {
        // A failure here must not take a CKEditor field down: leave its config as Craft built it.
        try {
            self::narrow($event);
        } catch (Throwable $e) {
            Craft::error('Sorts left the CKEditor config as it was: ' . get_class($e) . ': ' . $e->getMessage(), __METHOD__);
        }
    }

    private static function narrow(ModifyConfigEvent $event): void
    {
        $plugin = Plugin::getInstance();
        $service = $plugin->engine;
        // The editor’s content language is its element’s site: the control panel's requested site (?site=).
        $options = $service->options(Cp::requestedSite()?->language);
        $locale = $service->engine()->locales()->resolveOrFallback($options->locale);
        $variant = $options->variants[$locale->id()] ?? null;
        if ($variant !== null && in_array($variant, $locale->variants(), true)) {
            $locale = $locale->withVariant($variant);
        }

        $event->baseConfig['typing'] = ['transformations' => [
            'include' => self::transformations($locale, $options->rules, $options->quoteNesting === Options::NESTING_DEPTH),
        ]];
    }

    /**
     * The CKEditor transformations that do what Sorts’ rules do, for a locale.
     *
     * @param array<string, bool> $rules rule handle => on or off, as the options hold them
     * @param bool $normalizeQuotes whether quotes are set by depth, not by the mark typed
     * @return list<string>
     */
    public static function transformations(LocaleInterface $locale, array $rules, bool $normalizeQuotes = false): array
    {
        $on = static fn (string $handle, bool $default = true): bool => $rules[$handle] ?? $default;
        $include = [];

        if ($on('symbols')) {
            array_push($include, 'copyright', 'registeredTrademark', 'trademark');
        }
        if ($on('ellipsis')) {
            $include[] = 'horizontalEllipsis';
        }
        if ($on('dashes')) {
            $include[] = 'emDash';
            // CKEditor’s `--` is a spaced en dash: right where Sorts’ `--` is one, wrong where it is an em dash.
            $dash = $locale->dash();
            if (($dash['mark'] ?? '') === '–' && !empty($dash['spaced'])) {
                $include[] = 'enDash';
            }
        }
        if ($on('fractions', false)) {
            array_push($include, 'oneHalf', 'oneThird', 'twoThirds', 'oneForth', 'threeQuarters');
        }
        if ($on('quotes')) {
            array_push($include, ...self::quoteTransformations($locale, $normalizeQuotes));
        }
        return $include;
    }

    /**
     * The quote transformations whose pair is the one the locale uses for the key it is typed with:
     * the primary pair for `"`, the secondary pair for `'`.
     *
     * @return list<string>
     */
    private static function quoteTransformations(LocaleInterface $locale, bool $normalizeQuotes): array
    {
        $pairs = ['"' => $locale->quotes()];
        if (!$normalizeQuotes) {
            $pairs["'"] = $locale->nestedQuotes();
        }

        $found = [];
        foreach (self::QUOTES as $name => [$typed, $open, $close]) {
            if (!isset($found[$typed]) && isset($pairs[$typed]) && $pairs[$typed] === [$open, $close]) {
                $found[$typed] = $name;
            }
        }
        return array_values($found);
    }
}
