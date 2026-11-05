<?php

namespace iceboxind\sorts\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\events\ElementEvent;
use craft\fields\PlainText;
use craft\helpers\ElementHelper;
use craft\htmlfield\HtmlFieldData;
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
 */
class ContentFixer extends Component
{
    /** The longest title Craft saves. */
    private const MAX_TITLE_LENGTH = 255;

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
     * @return list<array{attribute: string, handle: ?string, label: string, before: string, after: string, html: bool}>
     */
    public function changes(ElementInterface $element): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $service = Plugin::getInstance()->engine;
        $language = $this->languageOf($element);
        $text = $service->storedOptions($language, Options::TEXT);
        $html = $service->storedOptions($language, Options::HTML);
        $changes = [];

        if ($settings->fixTitles && $element::hasTitles() && is_string($element->title) && $element->title !== '') {
            $after = $service->applyWith($element->title, $text);
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
            if (!is_string($before) || $before === '') {
                continue;
            }
            $after = $service->applyWith($before, $isCkeditor ? $html : $text);
            if ($after === $before || !$this->fits($field, $after)) {
                continue;
            }
            $changes[] = ['attribute' => 'field', 'handle' => $field->handle, 'label' => (string) $field->name, 'before' => $before, 'after' => $after, 'html' => $isCkeditor];
        }
        return $changes;
    }

    /**
     * Put the fixed values on the element (it still has to be saved). Returns how many changed.
     */
    public function apply(ElementInterface $element): int
    {
        $changes = $this->changes($element);
        foreach ($changes as $change) {
            if ($change['attribute'] === 'title') {
                $element->title = $change['after'];
            } else {
                $element->setFieldValue($change['handle'], $change['after']);
            }
        }
        return count($changes);
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
