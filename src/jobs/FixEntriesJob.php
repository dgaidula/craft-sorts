<?php

namespace iceboxind\sorts\jobs;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use iceboxind\sorts\Plugin;
use Throwable;

/**
 * Fixes the punctuation of the entries the audit listed (Pro).
 *
 * The edition is asked when the job is queued, not here: a job that has started finishes,
 * so a downgrade never leaves a batch half done.
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

    public function execute($queue): void
    {
        $fixer = Plugin::getInstance()->contentFixer;
        $total = count($this->entries);
        foreach ($this->entries as $i => [$id, $siteId]) {
            $this->setProgress($queue, $i / max(1, $total), Craft::t('app', '{step, number} of {total, number}', ['step' => $i + 1, 'total' => $total]));
            $entry = Entry::find()->id($id)->siteId($siteId)->status(null)->drafts(false)->provisionalDrafts(false)->revisions(false)->one();
            if (!$entry instanceof Entry) {
                continue;
            }
            try {
                if ($fixer->apply($entry) === 0) {
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
     * Queue a fix for a list of entries, in batches small enough to run in a request or a worker.
     *
     * @param list<array{0: int, 1: int}> $entries
     */
    public static function enqueue(array $entries, bool $createRevisions): void
    {
        foreach (array_chunk($entries, 100) as $chunk) {
            Queue::push(new self(['entries' => $chunk, 'createRevisions' => $createRevisions]));
        }
    }
}
