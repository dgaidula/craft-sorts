<?php

namespace iceboxind\sorts\ckeditor;

use Craft;
use craft\ckeditor\events\ModifyConfigEvent;
use craft\ckeditor\Field;
use craft\ckeditor\helpers\CkeditorConfig;
use craft\helpers\Cp;
use iceboxind\sorts\engine\LocaleInterface;
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
 * language’s pairs are enabled, so typing `"` in an English field gives “…”, in an en-GB
 * single-outer field gives ‘…’, and in a German or French field is left straight, to be
 * set by the page pass or fix-on-save. Apostrophes, primes, ranges and spacing are not
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

    private const SINGLE_MARKS = ['‘', '‚', '‹', '›'];

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
            'include' => self::transformations($locale, $options->rules),
        ]];
    }

    /**
     * The CKEditor transformations that do what Sorts’ rules do, for a locale.
     *
     * @param array<string, bool> $rules rule handle => on or off, as the options hold them
     * @return list<string>
     */
    public static function transformations(LocaleInterface $locale, array $rules): array
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
            array_push($include, ...self::quoteTransformations($locale));
        }
        return $include;
    }

    /**
     * The quote transformations whose pair is the one the locale uses for the key it is typed with.
     *
     * @return list<string>
     */
    private static function quoteTransformations(LocaleInterface $locale): array
    {
        $outer = $locale->quotes();
        $nested = $locale->nestedQuotes();
        $singleOuter = in_array($outer[0], self::SINGLE_MARKS, true);
        $pairs = $singleOuter ? ["'" => $outer, '"' => $nested] : ['"' => $outer, "'" => $nested];

        $found = [];
        foreach (self::QUOTES as $name => [$typed, $open, $close]) {
            if (!isset($found[$typed]) && $pairs[$typed] === [$open, $close]) {
                $found[$typed] = $name;
            }
        }
        return array_values($found);
    }
}
