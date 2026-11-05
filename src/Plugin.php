<?php

namespace iceboxind\sorts;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use iceboxind\sorts\models\Settings;
use iceboxind\sorts\web\twig\PluginVariable;
use yii\base\Event;

/**
 * Sorts plugin (self-contained, Plugin-Store-bound).
 *
 * Typographer-grade punctuation for Craft CMS: smart quotes, apostrophes, primes, dashes and more, fixed site-wide with no template edits.
 *
 * This is a stamped starting point from craft-plugin-base — replace this
 * docblock with a description of what the plugin actually does.
 *
 * @property-read Settings $settings
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public const PERMISSION_MANAGE = 'sorts:manageConfig';

    /**
     * LITE (free) / PRO (paid). Document the dividing line here once it's
     * decided — e.g. Downtoll's "captured and stored" vs. "delivered to you".
     * Decide it BEFORE the first Store submission: a feature can move from
     * Pro to Lite later, never back. Gates control authoring and convenience,
     * never enforcement. Removing access and deleting data are never gated
     * either (Sesame 8fdaeb4, e432e29).
     */
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public static function editions(): array
    {
        return [self::EDITION_LITE, self::EDITION_PRO];
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
        $name = $this->getSettings()->pluginName ?? '';
        if ($name !== '') {
            $this->name = $name;
        }

        $this->registerPermissions();
        $this->registerTwigVariable();
        $this->registerTemplateRoots();

        // Example of gating a site request. Uncomment and adapt to intercept
        // element routing (e.g. a password gate, a redirect, a feature flag):
        //
        // Event::on(Entry::class, Element::EVENT_SET_ROUTE, function (SetElementRouteEvent $e) {
        //     $entry = $e->sender;
        //     // gate your requests here
        // });

        // Reacting to element saves? Most saves are not the live element
        // (needs: use craft\services\Elements; craft\events\ElementEvent;
        // craft\helpers\ElementHelper):
        // Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function (ElementEvent $e) {
        //     $el = $e->element;
        //     if (ElementHelper::isDraftOrRevision($el) || $el->propagating) {
        //         return; // provisional drafts autosave under their own uid; propagation saves once per site
        //     }
        // });
        // A first publish applies a draft onto itself (draft uid === canonical uid); in a
        // normal apply the draft is deleted before Drafts::EVENT_AFTER_APPLY_DRAFT fires
        // (Sesame 5eff31d, 6e9c503; craft-plugin-base docs/PATTERNS.md §12).
        //
        // Retention / purge: register on Gc::EVENT_RUN and NEVER gate it on isPro() —
        // only writing data may be Pro; deleting it runs on every edition (Sesame 8fdaeb4).
        // (needs: use craft\services\Gc)
        // Event::on(Gc::class, Gc::EVENT_RUN, fn () => $this->exampleLog->purgeExpired());
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
        return Craft::$app->getView()->renderTemplate('sorts/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'isPro' => $this->isPro(),
            'overrides' => is_array($fileConfig) ? array_keys($fileConfig) : [],
            'stored' => Craft::$app->getProjectConfig()->get(ProjectConfig::PATH_PLUGINS . '.' . $this->handle . '.settings') ?? [],
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

    private function registerTwigVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('sorts', PluginVariable::class);
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
}
