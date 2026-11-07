<?php

namespace iceboxind\sorts;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\ElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlDirectivesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\TemplateEvent;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\services\Elements;
use craft\services\Gql;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\View;
use iceboxind\sorts\ckeditor\CkeditorSupport;
use iceboxind\sorts\engine\Presets;
use iceboxind\sorts\gql\directives\Sorts as SortsDirective;
use iceboxind\sorts\models\Settings;
use iceboxind\sorts\services\AuditScanner;
use iceboxind\sorts\services\ContentFixer;
use iceboxind\sorts\services\EngineService;
use iceboxind\sorts\services\PagePass;
use iceboxind\sorts\utilities\AuditUtility;
use iceboxind\sorts\web\twig\SortsExtension;
use yii\base\Event;

/**
 * Sorts: typographer-grade punctuation for Craft CMS: smart quotes, apostrophes, primes,
 * dashes and more, fixed site-wide with no template edits.
 *
 * Lite: the `|sorts` Twig filter (English quotes, apostrophes, dashes, ellipsis) and
 * CKEditor as-you-type quotes. Pro: the automatic whole-page pass, every rule and locale,
 * exclusions, the register-rules extension API, the `@sorts` GraphQL directive, fix-on-save
 * and the audit utility. The engine is plain PHP in `src/engine/`; this class wires it in.
 *
 * @property-read Settings $settings
 * @property-read EngineService $engine
 * @property-read PagePass $pagePass
 * @property-read ContentFixer $contentFixer
 * @property-read AuditScanner $auditScanner
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public const PERMISSION_MANAGE = 'sorts:manageConfig';

    /**
     * Fired once per request, when the engine is first built, so a module or plugin can add
     * its own rules (Pro). See {@see \iceboxind\sorts\events\RegisterRulesEvent}.
     */
    public const EVENT_REGISTER_RULES = 'registerRules';

    /**
     * LITE (free) / PRO (paid). Lite is what the market already gives away: the `|sorts`
     * filter and CKEditor as-you-type quotes. Pro is everything that works on its own:
     * the page pass, every rule and locale, exclusions, the extension API, GraphQL,
     * fix-on-save and the audit. Gates control features, never safety: a downgrade stops
     * the page pass and queues no new fixes, and never undoes or half-finishes content
     * (a queued fix job that has started finishes). Removing access and deleting data are
     * never gated. The table of gates is in docs/DEVELOPMENT.md.
     */
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public static function editions(): array
    {
        return [self::EDITION_LITE, self::EDITION_PRO];
    }

    public static function config(): array
    {
        return [
            'components' => [
                'engine' => EngineService::class,
                'pagePass' => PagePass::class,
                'contentFixer' => ContentFixer::class,
                'auditScanner' => AuditScanner::class,
            ],
        ];
    }

    /** Single place the Pro gates ask. */
    public function isPro(): bool
    {
        return $this->is(self::EDITION_PRO);
    }

    public function init(): void
    {
        parent::init();

        // Optional rename: let a site relabel the plugin in the CP (Plugins
        // screen, settings breadcrumb, and the sidebar when the plugin has a CP
        // section). Blank keeps "Sorts". Guarded so a partially-constructed
        // settings model can't blank it.
        $name = $this->getSettings()->pluginName;
        if ($name !== '') {
            $this->name = $name;
        }

        $this->registerPermissions();
        $this->registerTemplateRoots();
        $this->registerTwigExtension();
        $this->registerPagePass();
        $this->registerFixOnSave();
        $this->registerGqlDirective();
        $this->registerUtility();

        $request = Craft::$app->getRequest();
        if ($this->getSettings()->ckeditorAsYouType && !$request->getIsConsoleRequest() && $request->getIsCpRequest()) {
            CkeditorSupport::register();
        }
    }

    /**
     * The settings, typed.
     *
     * @return Settings
     */
    public function getSettings(): Settings
    {
        /** @var Settings $settings */
        $settings = parent::getSettings();
        return $settings;
    }

    /**
     * Craft hands the plugin its settings from project config and `config/sorts.php` here
     * without validating them (services/Plugins.php:934-941, base/Plugin.php:206-214 in Craft
     * 5.12.0.1); a CP save goes through validation instead. A number that would not validate
     * falls back to its default, with a warning, so `'2MB'` cannot switch the page pass off.
     */
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);
        $this->getSettings()->defaultInvalidNumbers($settings);
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        // The template needs the keys config/sorts.php overrides and the values
        // stored in project config (see the comment at the top of settings.twig).
        $fileConfig = Craft::$app->getConfig()->getConfigFromFile($this->handle);
        $locales = $this->engine->engine()->locales();
        $variants = [];
        foreach ($locales->ids() as $id) {
            $locale = $locales->resolve($id);
            if ($locale !== null && $locale->variants()) {
                $variants[$id] = $locale->variants();
            }
        }
        return Craft::$app->getView()->renderTemplate('sorts/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'isPro' => $this->isPro(),
            'overrides' => is_array($fileConfig) ? array_keys($fileConfig) : [],
            // Project config keeps a map in a packed form that remembers its key order; the template needs it as it was.
            'stored' => ProjectConfigHelper::unpackAssociativeArrays(Craft::$app->getProjectConfig()->get(ProjectConfig::PATH_PLUGINS . '.' . $this->handle . '.settings') ?? []),
            'variants' => $variants,
            'ruleDefaults' => Settings::RULE_DEFAULTS,
            'presetRules' => Presets::rules(Presets::BRINGHURST),
            'ckeditorInstalled' => class_exists(\craft\ckeditor\helpers\CkeditorConfig::class),
        ]);
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function (RegisterUserPermissionsEvent $event): void {
                $event->permissions[] = [
                    'heading' => Craft::t('sorts', 'Sorts'),
                    'permissions' => [
                        self::PERMISSION_MANAGE => [
                            'label' => Craft::t('sorts', 'Manage sorts configuration'),
                        ],
                    ],
                ];
            }
        );
    }

    private function registerTemplateRoots(): void
    {
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            static function (RegisterTemplateRootsEvent $event): void {
                $event->roots['sorts'] = __DIR__ . '/templates';
            }
        );
        // No SITE template root by default: registering src/templates/ for site
        // requests makes every CP template routable on the front end (Sesame
        // d27cb13). If the front end needs plugin templates, register a narrow
        // folder only:  $event->roots['sorts'] = __DIR__ . '/templates/_site';
    }

    /** The `|sorts` filter. Lite and Pro; the engine applies the edition’s rules. */
    private function registerTwigExtension(): void
    {
        Craft::$app->getView()->registerTwigExtension(new SortsExtension());
    }

    /** The whole-page pass (Pro). Site page requests only, so no handler is attached elsewhere. */
    private function registerPagePass(): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
            return;
        }
        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function (TemplateEvent $event): void {
                $this->pagePass->handle($event);
            }
        );
    }

    /** Fix-on-save (Pro, off by default). The handler asks the edition and the setting itself. */
    private function registerFixOnSave(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event): void {
                $this->contentFixer->onBeforeSave($event);
            }
        );
    }

    /**
     * The `@sorts` directive. Registered on every edition, so a query written for Pro
     * still runs after a downgrade; the directive passes values through unchanged in Lite.
     */
    private function registerGqlDirective(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_DIRECTIVES,
            static function (RegisterGqlDirectivesEvent $event): void {
                $event->directives[] = SortsDirective::class;
            }
        );
    }

    /** CP → Utilities → Sorts (Pro). Not listed on Lite; the controller asks the edition again. */
    private function registerUtility(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function (RegisterComponentTypesEvent $event): void {
                if ($this->isPro()) {
                    $event->types[] = AuditUtility::class;
                }
            }
        );
    }
}
