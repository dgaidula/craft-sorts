<?php

namespace iceboxind\sorts\jobs;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\Plugin;
use Throwable;

/**
 * Fixes the punctuation of the entries the audit listed (Pro).
 *
 * The edition is asked when the job is queued, not here: a job that has started finishes,
 * so a downgrade never leaves a batch half done. The job carries the options each site’s
 * entries were to be fixed with, resolved when it was queued, so a downgrade before it
 * runs cannot put Lite’s English quotes into a French entry.
 *
 * It runs as the queue, so it carries the user who queued it, and skips any entry that user
 * may not save on that site (`Elements::canSave()`, which in Craft 5.12.0.1 checks the site’s
 * `editSite` permission and then `EVENT_AUTHORIZE_SAVE` or the element’s own `canSave()`:
 * services/Elements.php:4994-5007, :5159-5181).
 *
 * Each entry is saved through Craft’s own `Elements::saveElement()` with `resaving` set,
 * as Craft’s resave job does. That is what keeps the save from creating a revision or
 * changing the entry’s Date Updated, so nothing here writes to Craft’s tables. Ticking
 * “create revisions” on the audit sets `$createRevisions`, and the save is then an ordinary one.
 */
class FixEntriesJob extends BaseJob
{
    /** @var list<array{0: int, 1: int}> entry id and site id pairs */
    public array $entries = [];

    /** Save ordinary saves, with a revision and a new Date Updated, rather than quiet resaves. */
    public bool $createRevisions = false;

    /** The user who queued the fix. Without one the job saves nothing. */
    public ?int $userId = null;

    /** @var array<int, array{text: array<string, mixed>, html: array<string, mixed>}> site id => the stored-content options, as {@see Options} arguments */
    public array $options = [];

    public function execute($queue): void
    {
        $fixer = Plugin::getInstance()->contentFixer;
        $user = $this->userId ? User::find()->id($this->userId)->status(null)->one() : null;
        if (!$user instanceof User) {
            Craft::warning('Sorts fixed nothing: the job does not say which user queued it, so it cannot check who may save the entries.', __METHOD__);
            return;
        }
        $total = count($this->entries);
        foreach ($this->entries as $i => [$id, $siteId]) {
            $this->setProgress($queue, $i / max(1, $total), Craft::t('app', '{step, number} of {total, number}', ['step' => $i + 1, 'total' => $total]));
            $entry = Entry::find()->id($id)->siteId($siteId)->status(null)->drafts(false)->provisionalDrafts(false)->revisions(false)->one();
            if (!$entry instanceof Entry) {
                continue;
            }
            if (!isset($this->options[$siteId]['text'], $this->options[$siteId]['html'])) {
                Craft::warning("Sorts skipped entry {$id}: the job carries no options for site {$siteId}.", __METHOD__);
                continue;
            }
            if (!Craft::$app->getElements()->canSave($entry, $user)) {
                Craft::info("Sorts skipped entry {$id} on site {$siteId}: user {$user->id} may not save it.", __METHOD__);
                continue;
            }
            $options = ['text' => new Options(...$this->options[$siteId]['text']), 'html' => new Options(...$this->options[$siteId]['html'])];
            try {
                if ($fixer->apply($entry, $options) === 0) {
                    continue;
                }
                $entry->setScenario(Element::SCENARIO_ESSENTIALS);
                $entry->resaving = !$this->createRevisions;
                if ($this->createRevisions) {
                    $entry->revisionNotes = Craft::t('sorts', 'Punctuation fixed by Sorts');
                }
                if (!Craft::$app->getElements()->saveElement($entry, saveContent: true)) {
                    Craft::warning("Sorts could not save entry {$id}: " . implode(' ', $entry->getFirstErrors()), __METHOD__);
                }
            } catch (Throwable $e) {
                Craft::$app->getErrorHandler()->logException($e);
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('sorts', 'Fixing punctuation in {count, number} {count, plural, =1{entry} other{entries}}', ['count' => count($this->entries)]);
    }

    /**
     * Queue a fix for a list of entries, in batches small enough to run in a request or a worker,
     * with each site’s options resolved now.
     *
     * @param list<array{0: int, 1: int}> $entries
     * @param int $userId the user asking; an entry they may not save is skipped when the job runs
     */
    public static function enqueue(array $entries, bool $createRevisions, int $userId): void
    {
        $fixer = Plugin::getInstance()->contentFixer;
        $options = [];
        foreach ($entries as [, $siteId]) {
            if (!isset($options[$siteId]) && ($site = Craft::$app->getSites()->getSiteById($siteId)) !== null) {
                $options[$siteId] = array_map('get_object_vars', $fixer->optionsFor($site->language));
            }
        }
        foreach (array_chunk($entries, 100) as $chunk) {
            $sites = array_fill_keys(array_column($chunk, 1), true);
            Queue::push(new self([
                'entries' => $chunk,
                'createRevisions' => $createRevisions,
                'userId' => $userId,
                'options' => array_intersect_key($options, $sites),
            ]));
        }
    }
}
