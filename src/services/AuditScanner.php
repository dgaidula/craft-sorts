<?php

namespace iceboxind\sorts\services;

use craft\base\Component;
use craft\elements\Entry;
use iceboxind\sorts\engine\Finding;
use iceboxind\sorts\Plugin;

/**
 * Looks through saved entries for punctuation Sorts would change (the audit’s dry run).
 * It reads and never writes. A scan covers one batch of entries; the caller asks for the
 * next batch from where the last one stopped, so no request has to read a whole site.
 */
class AuditScanner extends Component
{
    public const BATCH_SIZE = 100;

    /** Most excerpts shown for one value. */
    private const MAX_EXCERPTS = 3;

    /** Words of context kept each side of a change. */
    private const CONTEXT_WORDS = 3;

    /**
     * Scan the next batch of entries, in id order.
     *
     * @param int|null $sectionId only entries of this section, or null for every entry
     * @param int $siteId the site whose entries to read
     * @param int $afterId only entries with an id above this (0 to start)
     * @return array{items: list<array{id: int, siteId: int, title: string, cpEditUrl: ?string, section: string, changes: list<array{label: string, excerpts: list<array{before: string, after: string}>}>}>, flagged: list<array{id: int, siteId: int, title: string, cpEditUrl: ?string, section: string, flags: list<array{label: string, notes: list<array{match: string, suggestion: string, context: string}>}>}>, scanned: int, lastId: int, more: bool}
     */
    public function scan(?int $sectionId, int $siteId, int $afterId = 0, int $limit = self::BATCH_SIZE): array
    {
        $fixer = Plugin::getInstance()->contentFixer;
        $query = Entry::find()
            ->siteId($siteId)
            ->status(null)
            ->drafts(false)
            ->provisionalDrafts(false)
            ->revisions(false)
            ->id('> ' . max(0, $afterId))
            ->orderBy(['elements.id' => SORT_ASC])
            ->limit($limit + 1);
        if ($sectionId !== null) {
            $query->sectionId($sectionId);
        }

        $entries = $query->all();
        $more = count($entries) > $limit;
        $entries = array_slice($entries, 0, $limit);

        $items = [];
        $flagged = [];
        foreach ($entries as $entry) {
            $changes = [];
            foreach ($fixer->changes($entry) as $change) {
                $changes[] = [
                    'label' => $change['label'],
                    'excerpts' => self::excerpts($change['before'], $change['after'], $change['html']),
                ];
            }
            // What the fix will not touch but a writer may want to look at (D859), whether or not the entry also changes.
            $flags = [];
            foreach ($fixer->findings($entry) as $found) {
                $flags[] = [
                    'label' => $found['label'],
                    'notes' => array_map(fn (Finding $f): array => ['match' => $f->match, 'suggestion' => $f->suggestion, 'context' => self::context($found['value'], $f->offset, strlen($f->match), $found['html'])], array_slice($found['findings'], 0, self::MAX_EXCERPTS)),
                ];
            }
            if ($flags) {
                $flagged[] = [
                    'id' => (int) $entry->id,
                    'siteId' => (int) $entry->siteId,
                    'title' => (string) $entry->title,
                    'cpEditUrl' => $entry->getCpEditUrl(),
                    'section' => (string) $entry->getSection()?->name,
                    'flags' => $flags,
                ];
            }
            if ($changes) {
                $items[] = [
                    'id' => (int) $entry->id,
                    'siteId' => (int) $entry->siteId,
                    'title' => (string) $entry->title,
                    'cpEditUrl' => $entry->getCpEditUrl(),
                    'section' => (string) $entry->getSection()?->name,
                    'changes' => $changes,
                ];
            }
        }
        $last = $entries ? end($entries) : null;
        return ['items' => $items, 'flagged' => $flagged, 'scanned' => count($entries), 'lastId' => $last ? (int) $last->id : $afterId, 'more' => $more];
    }

    /** The words around a finding, as plain text: tags dropped and entities decoded. */
    private static function context(string $value, int $offset, int $length, bool $html): string
    {
        $plain = static fn (string $s): string => $html ? html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $s;
        $before = $plain(mb_strcut($value, max(0, $offset - 40), min($offset, 40)));
        $after = $plain(mb_strcut($value, $offset + $length, 40));
        $lead = array_slice(preg_split('/\s+/u', $before, -1, PREG_SPLIT_NO_EMPTY) ?: [], -self::CONTEXT_WORDS);
        $trail = array_slice(preg_split('/\s+/u', $after, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::CONTEXT_WORDS);
        return implode(' ', $lead) . (preg_match('/\s$/u', $before) ? ' ' : '') . $plain(substr($value, $offset, $length)) . (preg_match('/^\s/u', $after) ? ' ' : '') . implode(' ', $trail);
    }

    /**
     * Before and after, cut down to the words that changed with a little around them.
     * Falls back to the start of each when the two do not line up word for word.
     *
     * @return list<array{before: string, after: string}>
     */
    public static function excerpts(string $before, string $after, bool $html): array
    {
        if ($html) {
            $before = html_entity_decode(strip_tags($before), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $after = html_entity_decode(strip_tags($after), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $a = preg_split('/\s+/u', trim($before), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/\s+/u', trim($after), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($a) !== count($b)) {
            return [['before' => self::clip($before), 'after' => self::clip($after)]];
        }

        $excerpts = [];
        $count = count($a);
        for ($i = 0; $i < $count && count($excerpts) < self::MAX_EXCERPTS; $i++) {
            if ($a[$i] === $b[$i]) {
                continue;
            }
            // Take the run of changed words, then the context around it.
            $end = $i;
            while ($end + 1 < $count && $a[$end + 1] !== $b[$end + 1]) {
                $end++;
            }
            $from = max(0, $i - self::CONTEXT_WORDS);
            $to = min($count, $end + 1 + self::CONTEXT_WORDS);
            $excerpts[] = [
                'before' => ($from > 0 ? '… ' : '') . implode(' ', array_slice($a, $from, $to - $from)) . ($to < $count ? ' …' : ''),
                'after' => ($from > 0 ? '… ' : '') . implode(' ', array_slice($b, $from, $to - $from)) . ($to < $count ? ' …' : ''),
            ];
            $i = $end;
        }
        return $excerpts ?: [['before' => self::clip($before), 'after' => self::clip($after)]];
    }

    private static function clip(string $text): string
    {
        $text = trim($text);
        return mb_strlen($text) > 160 ? mb_substr($text, 0, 160) . ' …' : $text;
    }
}
