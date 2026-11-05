<?php

namespace iceboxind\sorts\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\events\ElementEvent;
use craft\fields\PlainText;
use craft\helpers\ElementHelper;
use craft\htmlfield\HtmlFieldData;
use iceboxind\sorts\engine\LocaleRegistry;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\Plugin;
use Throwable;
use yii\db\Migration;

/**
 * Fixes the punctuation in stored content (Pro): an element’s title and its Plain Text
 * and CKEditor field values.
 *
 * {@see onBeforeSave()} is fix-on-save: it changes the values on the element while the
 * element is being saved, so there is no second save. {@see changes()} and {@see apply()}
 * are what the audit uses on elements that are already saved.
 *
 * Only text is touched: never a slug, URI, handle, URL or email, and never a Plain Text
 * field set to code. Only the rules in {@see EngineService::STORED_RULES} run (characters,
 * not spacing or markup). A converted value that would not fit its field’s character limit
 * is left as it was, so a fix can never make a save fail.
 *
 * Quotes and dashes depend on the language, so a value is converted only when every site
 * it will reach on this save sets type the same way as the element’s own site (the same
 * locale and variant). Craft copies a new element’s title and fields to every site it
 * supports, and a field that is not translated per site to every site that shares its
 * translation key; a value that reaches a site in another language is left as typed, and
 * the page pass sets it on each page in that page’s language. See {@see otherSites()}.
 */
class ContentFixer extends Component
{
    /** The longest title Craft saves. */
    private const MAX_TITLE_LENGTH = 255;

    private ?LocaleRegistry $locales = null;

    /**
     * The fix-on-save handler: fixes the element’s values before they are saved.
     * Skips what is not live content: resaves and migrations (unless the setting asks),
     * propagation, revisions and provisional drafts.
     */
    public function onBeforeSave(ElementEvent $event): void
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        if (!$plugin->isPro() || !$settings->fixOnSave) {
            return;
        }
        $element = $event->element;
        if ($element->propagating || $this->isSnapshot($element)) {
            return;
        }
        if (!$settings->fixOnSaveBulk && ($element->resaving || $this->isMigrating())) {
            return;
        }
        try {
            $this->apply($element);
        } catch (Throwable $e) {
            // A failed fix must not stop the save.
            Craft::error('Sorts saved the element as it was: ' . get_class($e) . ': ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * What fixing the element would change.
     *
     * @param array{text: Options, html: Options}|null $options the stored-content options for the
     *   element’s site ({@see optionsFor()}); the settings’ now by default. The audit’s job passes the
     *   ones it was queued with.
     * @return list<array{attribute: string, handle: ?string, label: string, before: string, after: string, html: bool}>
     */
    public function changes(ElementInterface $element, ?array $options = null): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $service = Plugin::getInstance()->engine;
        ['text' => $text, 'html' => $html] = $options ?? $this->optionsFor($this->languageOf($element));
        $sites = $this->otherSites($element);
        $changes = [];

        if ($settings->fixTitles && $element::hasTitles() && is_string($element->title) && $element->title !== ''
            && $this->onlySameLocale($element, $sites, static fn (ElementInterface $e): string => $e->getTitleTranslationKey(), $text, 'title')) {
            $after = $service->applyStored($element->title, $text);
            if ($after !== $element->title && mb_strlen($after) <= self::MAX_TITLE_LENGTH) {
                $changes[] = ['attribute' => 'title', 'handle' => null, 'label' => Craft::t('app', 'Title'), 'before' => $element->title, 'after' => $after, 'html' => false];
            }
        }

        foreach ($element->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            $isCkeditor = $this->isCkeditor($field);
            if ($isCkeditor ? !$settings->fixCkeditor : !($settings->fixPlainText && $field instanceof PlainText && !$field->code)) {
                continue;
            }
            $value = $element->getFieldValue($field->handle);
            $before = $value instanceof HtmlFieldData ? $value->getRawContent() : $value;
            if (!is_string($before) || $before === ''
                || !$this->onlySameLocale($element, $sites, static fn (ElementInterface $e): string => $field->getTranslationKey($e), $text, $field->handle)) {
                continue;
            }
            $after = $service->applyStored($before, $isCkeditor ? $html : $text);
            if ($after === $before || !$this->fits($field, $after)) {
                continue;
            }
            $changes[] = ['attribute' => 'field', 'handle' => $field->handle, 'label' => (string) $field->name, 'before' => $before, 'after' => $after, 'html' => $isCkeditor];
        }
        return $changes;
    }

    /**
     * Put the fixed values on the element (it still has to be saved). Returns how many changed.
     *
     * @param array{text: Options, html: Options}|null $options see {@see changes()}
     */
    public function apply(ElementInterface $element, ?array $options = null): int
    {
        $changes = $this->changes($element, $options);
        foreach ($changes as $change) {
            if ($change['attribute'] === 'title') {
                $element->title = $change['after'];
            } else {
                $element->setFieldValue($change['handle'], $change['after']);
            }
        }
        return count($changes);
    }

    /**
     * The stored-content options for a language, for plain text and for HTML.
     *
     * @return array{text: Options, html: Options}
     */
    public function optionsFor(string $language): array
    {
        $service = Plugin::getInstance()->engine;
        return ['text' => $service->storedOptions($language, Options::TEXT), 'html' => $service->storedOptions($language, Options::HTML)];
    }

    /**
     * The other sites this save will write to, by id: for each, whether it gets every value
     * (`true`, a site the element is new to, or every site when `propagateAll` is set) or only
     * the values whose translation key it shares (a copy of the element as that site sees it).
     *
     * Verified against Craft 5.12.0.1 (2026-11-05): `Elements::_saveElementInternal()` sets
     * `propagateAll` on a new element that is not a draft or revision before
     * `EVENT_BEFORE_SAVE_ELEMENT` fires (services/Elements.php:4013-4033), then propagates to
     * every other site in `ElementHelper::supportedSitesForElement()` (Elements.php:4345-4377;
     * helpers/ElementHelper.php:333-367, which drops sites marked `propagate => false` by
     * `getSupportedSites()`). `_propagateElement()` clones the whole element for a site it is
     * new to and for `propagateAll` (Elements.php:4688-4708) and copies every field value to a
     * new site (:4783-4786); otherwise it copies the title when `getTitleTranslationKey()`
     * matches (:4724-4732) and a changed field when `Field::getTranslationKey()` matches
     * (:4788-4806; base/Field.php:636-639, helpers/ElementHelper.php:803-821).
     *
     * @return array<int, ElementInterface|true>
     */
    private function otherSites(ElementInterface $element): array
    {
        if (!$element::isLocalized() || !Craft::$app->getIsMultiSite()) {
            return [];
        }
        $existing = $element->id
            ? array_map('intval', (new Query())->select('siteId')->from(Table::ELEMENTS_SITES)->where(['elementId' => $element->id])->column())
            : [];
        $sites = [];
        foreach (ElementHelper::supportedSitesForElement($element) as $info) {
            $siteId = (int) $info['siteId'];
            if ($siteId === (int) $element->siteId) {
                continue;
            }
            if ($element->propagateAll || !in_array($siteId, $existing, true)) {
                $sites[$siteId] = true;
            } else {
                $copy = clone $element;
                $copy->siteId = $siteId;
                $sites[$siteId] = $copy;
            }
        }
        return $sites;
    }

    /**
     * Whether every site a value reaches sets type like the element’s own site. Logs, at info
     * level, a value it leaves as typed.
     *
     * @param array<int, ElementInterface|true> $sites {@see otherSites()}
     * @param callable(ElementInterface): string $key the value’s translation key on an element
     */
    private function onlySameLocale(ElementInterface $element, array $sites, callable $key, Options $options, string $value): bool
    {
        $own = $this->localeKey($this->languageOf($element), $options->variants);
        $ownKey = null;
        foreach ($sites as $siteId => $copy) {
            if ($copy !== true && $key($copy) !== ($ownKey ??= $key($element))) {
                continue;   // Craft does not copy this value to that site
            }
            $site = Craft::$app->getSites()->getSiteById($siteId);
            if ($site !== null && $this->localeKey($site->language, $options->variants) !== $own) {
                Craft::info("Sorts left the {$value} of element {$element->id} as typed: it also reaches the site “{$site->handle}” ({$site->language}), which sets type differently.", __METHOD__);
                return false;
            }
        }
        return true;
    }

    /** The engine locale and variant a site language is set in. */
    private function localeKey(string $language, array $variants): string
    {
        $locale = ($this->locales ??= new LocaleRegistry())->resolveOrFallback($language);
        $variant = $variants[$locale->id()] ?? '';
        return $locale->id() . '|' . (in_array($variant, $locale->variants(), true) ? $variant : '');
    }

    /** The language of the site an element lives in. */
    public function languageOf(ElementInterface $element): string
    {
        $site = $element->siteId ? Craft::$app->getSites()->getSiteById($element->siteId) : null;
        return $site ? $site->language : Plugin::getInstance()->engine->siteLanguage();
    }

    /** A revision, or a draft Craft saved by itself while someone types. */
    private function isSnapshot(ElementInterface $element): bool
    {
        $root = ElementHelper::rootElement($element);
        return $element->getIsRevision() || $root->getIsRevision() || ($root->getIsDraft() && !empty($root->isProvisionalDraft));
    }

    /** Whether a migration, or the migration runner, is on the call stack. */
    private function isMigrating(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 80) as $frame) {
            $class = $frame['class'] ?? null;
            if ($class !== null && (is_a($class, Migration::class, true) || is_a($class, \craft\db\MigrationManager::class, true))) {
                return true;
            }
        }
        return false;
    }

    private function isCkeditor(object $field): bool
    {
        return class_exists(\craft\ckeditor\Field::class) && $field instanceof \craft\ckeditor\Field;
    }

    private function fits(object $field, string $value): bool
    {
        if (!$field instanceof PlainText) {
            return true;
        }
        if ($field->byteLimit !== null) {
            return strlen($value) <= $field->byteLimit;
        }
        return $field->charLimit === null || mb_strlen($value) <= $field->charLimit;
    }
}
