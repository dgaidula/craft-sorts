<?php

namespace iceboxind\sorts\services;

use Craft;
use craft\base\Component;
use iceboxind\sorts\engine\Engine;
use iceboxind\sorts\engine\LocaleRegistry;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\engine\RuleInterface;
use iceboxind\sorts\events\RegisterRulesEvent;
use iceboxind\sorts\Plugin;
use Throwable;

/**
 * The plugin’s one door to the typography engine: `Plugin::getInstance()->engine`.
 *
 * It builds the engine once per request, asks other code for its rules (Pro),
 * and turns the plugin’s settings and the edition into the engine’s options, so
 * the page pass, the Twig filter, the GraphQL directive and a PHP call all give
 * the same result for the same text.
 *
 * Lite gets the English quotes, apostrophes, primes, dashes and ellipsis, in
 * English only, and the engine is told that those are the only rules allowed, so
 * no `data-sorts-rules` scope or rule named in a call can run another one. Pro gets
 * every rule and locale, the settings, and the rules other code registers.
 */
class EngineService extends Component
{
    /** What Lite applies. Primes stay in because the quote scan needs them to tell 5'10" from a quotation. */
    public const LITE_RULES = ['quotes', 'apostrophes', 'primes', 'dashes', 'ellipsis'];

    /** The locales Lite knows. */
    private const LITE_LOCALES = ['en-US', 'en-GB'];

    /**
     * What stored content gets: the rules that swap one character for another.
     * Spacing and markup belong to the page, and a site’s own rules are its own
     * to run where it chooses, so none of those are written into the database.
     */
    public const STORED_RULES = ['symbols', 'ellipsis', 'dashes', 'fractions', 'ranges', 'multiplication', 'primes', 'apostrophes', 'quotes'];

    private ?Engine $engine = null;

    private ?Engine $storedEngine = null;

    /**
     * The engine, with the edition’s locales and any rules other code registered.
     */
    public function engine(): Engine
    {
        if ($this->engine !== null) {
            return $this->engine;
        }
        $isPro = Plugin::getInstance()->isPro();
        $engine = new Engine(new Options(allowed: $isPro ? null : self::LITE_RULES), $isPro ? null : $this->liteLocales());
        if ($isPro) {
            $this->registerOtherRules($engine);
        }
        return $this->engine = $engine;
    }

    /** Forget the built engine, so the next call asks again (after a settings or edition change). */
    public function reset(): void
    {
        $this->engine = null;
        $this->storedEngine = null;
    }

    /**
     * Fix the typography of $input.
     *
     * @param list<string> $rules handles of rules to run in addition, by name (Pro rules outside the Lite set are skipped on Lite)
     * @param string|null $language the language of the text; the current site’s by default
     * @param string $mode Options::HTML or Options::TEXT
     * @throws \InvalidArgumentException in Pro, if a handle in $rules is not registered
     */
    public function apply(string $input, array $rules = [], ?string $language = null, string $mode = Options::HTML): string
    {
        return $this->applyWith($input, $this->options($language, $mode), $rules);
    }

    /**
     * Like {@see apply()}, with the options already built.
     *
     * @param list<string> $rules
     */
    public function applyWith(string $input, Options $options, array $rules = []): string
    {
        $rules = array_values(array_filter($rules, 'is_string'));
        if (!Plugin::getInstance()->isPro()) {
            $rules = array_values(array_intersect($rules, self::LITE_RULES));
        }
        return $this->engine()->apply($input, $rules, $options);
    }

    /**
     * The options for a call: Lite’s fixed ones, or Pro’s from the settings.
     */
    public function options(?string $language = null, string $mode = Options::HTML): Options
    {
        $plugin = Plugin::getInstance();
        $language ??= $this->siteLanguage();
        if (!$plugin->isPro()) {
            $rules = [];
            foreach ($this->engine()->rules() as $rule) {
                $rules[$rule->handle()] = in_array($rule->handle(), self::LITE_RULES, true);
            }
            return new Options(
                locale: str_starts_with(strtolower($language), 'en') ? $language : 'en-US',
                mode: $mode,
                rules: $rules,
                allowed: self::LITE_RULES,
            );
        }
        $settings = $plugin->getSettings();
        return new Options(
            locale: $language,
            mode: $mode,
            rules: $settings->rules,
            ruleOptions: ['widows' => ['minWords' => $settings->widowsMinWords]],
            skipTags: $settings->skipTags,
            skipClasses: $settings->skipClasses,
            variants: $settings->variants,
        );
    }

    /**
     * Options that run only the rules that belong in stored content ({@see STORED_RULES}),
     * as the settings have them. They are also the only rules allowed, so markup in the
     * content (a `data-sorts-rules` scope) cannot add another.
     */
    public function storedOptions(?string $language, string $mode): Options
    {
        $options = $this->options($language, $mode);
        $rules = [];
        foreach ($this->engine()->rules() as $rule) {
            $handle = $rule->handle();
            $rules[$handle] = in_array($handle, self::STORED_RULES, true) && ($options->rules[$handle] ?? $rule->defaultEnabled());
        }
        $allowed = array_values(array_intersect(self::STORED_RULES, $options->allowed ?? self::STORED_RULES));
        return $options->with(rules: $rules, allowed: $allowed);
    }

    /**
     * Fix stored content with options resolved earlier ({@see storedOptions()}), on an
     * engine with every built-in rule and locale whatever the edition is now. An audit
     * fix queued under Pro carries the options it was queued with, so a downgrade
     * before it runs cannot turn its French quotes into English ones.
     */
    public function applyStored(string $input, Options $options): string
    {
        $this->storedEngine ??= new Engine(new Options(allowed: self::STORED_RULES));
        return $this->storedEngine->apply($input, [], $options->with(allowed: array_values(array_intersect(self::STORED_RULES, $options->allowed ?? self::STORED_RULES))));
    }

    /** The language of the site this request is for. */
    public function siteLanguage(): string
    {
        return Craft::$app->getSites()->getCurrentSite()->language;
    }

    private function liteLocales(): LocaleRegistry
    {
        $all = new LocaleRegistry();
        $lite = new LocaleRegistry('/nonexistent');
        foreach (self::LITE_LOCALES as $id) {
            $locale = $all->resolve($id);
            if ($locale !== null) {
                $lite->register($locale);
            }
        }
        return $lite;
    }

    private function registerOtherRules(Engine $engine): void
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->hasEventHandlers(Plugin::EVENT_REGISTER_RULES)) {
            return;
        }
        $event = new RegisterRulesEvent();
        $plugin->trigger(Plugin::EVENT_REGISTER_RULES, $event);
        foreach ($event->rules as $rule) {
            // One bad rule must not take the others, or the page, down with it.
            try {
                if (!$rule instanceof RuleInterface) {
                    throw new \InvalidArgumentException('A registered rule must implement ' . RuleInterface::class . '.');
                }
                $engine->register($rule);
            } catch (Throwable $e) {
                Craft::error('Sorts skipped a registered rule: ' . $e->getMessage(), __METHOD__);
            }
        }
    }
}
