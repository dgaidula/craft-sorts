// ddev craft shell < tests/audit-test.php
// The audit utility (Pro): the dry-run scan, its page in the control panel, and the fix through the queue,
// which must leave the revision count and every entry's Date Updated alone unless the box is ticked.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$revisions = static fn (int $id): int => (int) \craft\elements\Entry::find()->revisionOf($id)->revisions(true)->status(null)->site('*')->unique()->count();
$updated = static fn (int $id): string => sortsFresh($id)->dateUpdated->format('Y-m-d H:i:s.u');
$sectionId = static fn (): int => Craft::$app->getEntries()->getSectionByHandle('sortsTest')->id;
$primary = static fn (): int => Craft::$app->getSites()->getPrimarySite()->id;
$queued = static fn (): int => (int) (new \craft\db\Query())->from(\craft\db\Table::QUEUE)->where(['like', 'job', 'FixEntriesJob'])->count();

/** Submit the utility's fix form from a CP client; returns the response. */
$postFix = static function (\GuzzleHttp\Client $cp, array $entries, bool $revisions, string $scanQuery) {
    $page = (string) $cp->get('/admin/utilities/sorts?' . $scanQuery)->getBody();
    if (!preg_match('/name="CRAFT_CSRF_TOKEN" value="([^"]+)"/', $page, $m)) {
        throw new \RuntimeException('No CSRF input on the utility page.');
    }
    return $cp->post('/admin/utilities/sorts', ['form_params' => array_filter(['CRAFT_CSRF_TOKEN' => $m[1], 'action' => 'sorts/audit/fix', 'entries' => $entries, 'createRevisions' => $revisions ? '1' : null])]);
};

sortsTry('setup', function () {
    sortsClean();
    sortsConfigure('pro');   // fix-on-save off: the entries go in straight
});

$a = $b = $c = null;
sortsTry('seed', function () use (&$a, &$b, &$c) {
    // sortsBody is shared with the French site, so the audit leaves it to the page pass; sortsBodySite is per site.
    $a = sortsEntry(['title' => 'First "one" -- here', 'sortsPlain' => 'Plain it\'s...', 'sortsBody' => '<p>"Shared" text</p>', 'sortsBodySite' => '<p>"Body" text</p>']);
    $b = sortsEntry(['title' => 'Second one', 'sortsPlain' => 'Nothing to fix', 'sortsBody' => '<p>Calm text</p>']);
    $c = sortsEntry(['title' => 'Third "two"', 'sortsCode' => 'code "stays" -- ok']);
});

sortsTry('the dry run', function () use (&$a, &$b, &$c, $revisions, $updated, $sectionId, $primary) {
    $before = [$a->id => [$revisions($a->id), $updated($a->id)], $c->id => [$revisions($c->id), $updated($c->id)]];
    $result = sorts()->auditScanner->scan($sectionId(), $primary(), 0);
    sortsEq('scan: three entries looked at', 3, $result['scanned']);
    $ids = array_column($result['items'], 'id');
    sortsCheck('scan: lists the entries with something to fix', in_array($a->id, $ids, true) && in_array($c->id, $ids, true), json_encode($ids));
    sortsCheck('scan: does not list the clean one', !in_array($b->id, $ids, true));
    $item = $result['items'][array_search($a->id, $ids, true)];
    $labels = array_column($item['changes'], 'label');
    sortsEq('scan: names each changed value (title, plain text, CKEditor); not the field shared with French', ['Title', 'Sorts Plain', 'Sorts Body Site'], $labels);
    sortsEq('scan: the title preview shows before and after', ['before' => 'First "one" -- here', 'after' => 'First “one” — here'], $item['changes'][0]['excerpts'][0]);
    sortsCheck('scan: the code field is not offered', !in_array('Sorts Code', array_column($result['items'][array_search($c->id, $ids, true)]['changes'], 'label'), true));
    foreach ([$a, $c] as $e) {
        sortsEq("scan: entry {$e->id} is unchanged (revisions, Date Updated)", $before[$e->id], [$revisions($e->id), $updated($e->id)]);
    }
    sortsEq('scan: nothing stored changed', 'First "one" -- here', sortsFresh($a->id)->title);
    $every = sorts()->auditScanner->scan(null, $primary(), 0);
    sortsCheck('scan: with no section, every entry is looked at', $every['scanned'] >= 3 && in_array($a->id, array_column($every['items'], 'id'), true));
    $other = sorts()->auditScanner->scan($sectionId() + 1000, $primary(), 0);
    sortsEq('scan: another section holds nothing of ours', 0, $other['scanned']);
    $partial = sorts()->auditScanner->scan($sectionId(), $primary(), 0, 2);
    sortsCheck('scan: batches say when there is more', $partial['more'] === true && $partial['scanned'] === 2, json_encode([$partial['more'], $partial['scanned']]));
    $next = sorts()->auditScanner->scan($sectionId(), $primary(), $partial['lastId'], 2);
    sortsEq('scan: the next batch carries on after the last id', 1, $next['scanned']);
});

sortsTry('excerpts', function () {
    $x = \iceboxind\sorts\services\AuditScanner::excerpts('one two "three" four five six seven eight "nine" ten', 'one two “three” four five six seven eight “nine” ten', false);
    sortsEq('excerpts: two separate changes, with context', 2, count($x));
    sortsEq('excerpts: context words around the first', ['before' => 'one two "three" four five six …', 'after' => 'one two “three” four five six …'], $x[0]);
    $y = \iceboxind\sorts\services\AuditScanner::excerpts('<p>He said "hi"</p>', '<p>He said “hi”</p>', true);
    sortsEq('excerpts: HTML is shown as text', [['before' => 'He said "hi"', 'after' => 'He said “hi”']], $y);
    $z = \iceboxind\sorts\services\AuditScanner::excerpts('a -- b', 'a – b', false);
    sortsEq('excerpts: words that line up', [['before' => 'a -- b', 'after' => 'a – b']], $z);
});

sortsTry('the utility page, as a person', function () use (&$a, $sectionId, $primary) {
    $cp = sortsCpClient();
    $page = (string) $cp->get('/admin/utilities/sorts')->getBody();
    sortsCheck('page: the utility is in the control panel', str_contains($page, 'Scan the first 100 entries'), substr($page, 0, 200));
    $page = (string) $cp->get('/admin/utilities/sorts?scan=1&section=' . $sectionId() . '&site=' . $primary())->getBody();
    sortsCheck('page: lists the entry to fix, with before and after', str_contains($page, 'First &quot;one&quot; -- here') && str_contains($page, 'First “one” — here'), '');
    sortsCheck('page: each entry has a tick box', str_contains($page, 'name="entries[]" value="' . $a->id . ':' . $primary() . '"'));
    sortsCheck('page: the revision box is off by default', (bool) preg_match('/name="createRevisions"[^>]*value="1"(?![^>]*checked)/', $page) || !str_contains($page, 'name="createRevisions" value="1" checked'));
    $empty = (string) $cp->get('/admin/utilities/sorts?scan=1&section=999999&site=' . $primary())->getBody();
    sortsCheck('page: an unknown section scans nothing', str_contains($empty, 'Nothing to change in the 0 entries scanned.'));
});

sortsTry('the fix is quiet', function () use (&$a, &$b, &$c, $revisions, $updated, $sectionId, $primary, $queued, $postFix) {
    $before = [];
    foreach ([$a, $b, $c] as $e) {
        $before[$e->id] = [$revisions($e->id), $updated($e->id)];
    }
    $cp = sortsCpClient();
    $q = $queued();
    $r = $postFix($cp, [$a->id . ':' . $primary(), $c->id . ':' . $primary(), 'junk', '1:2:3'], false, 'scan=1');
    sortsCheck('fix: the form post redirects', in_array($r->getStatusCode(), [302, 303], true), (string) $r->getStatusCode() . ' ' . substr((string) $r->getBody(), 0, 300));
    sortsEq('fix: one job queued', $q + 1, $queued());
    Craft::$app->getQueue()->run();
    $fa = sortsFresh($a->id);
    sortsEq('fix: title fixed', 'First “one” — here', $fa->title);
    sortsEq('fix: plain text fixed', 'Plain it’s…', sortsRaw($fa, 'sortsPlain'));
    sortsEq('fix: CKEditor fixed', '<p>“Body” text</p>', sortsRaw($fa, 'sortsBodySite'));
    sortsEq('fix: a CKEditor field shared with the French site is left as typed', '<p>"Shared" text</p>', sortsRaw($fa, 'sortsBody'));
    sortsEq('fix: the second entry was not ticked, so is as it was', 'Second one', sortsFresh($b->id)->title);
    sortsEq('fix: a code field is as it was', 'code "stays" -- ok', sortsRaw(sortsFresh($c->id), 'sortsCode'));
    foreach ([$a, $b, $c] as $e) {
        sortsEq("fix: entry {$e->id} has the same revision count and Date Updated", $before[$e->id], [$revisions($e->id), $updated($e->id)]);
    }
    $result = sorts()->auditScanner->scan($sectionId(), $primary(), 0);
    sortsEq('fix: a second scan finds nothing left', [], $result['items']);
});

sortsTry('flagged, not changed (D859)', function () use (&$b, $sectionId, $primary, $updated, $revisions) {
    sortsConfigure('pro');
    // Already curly, as the fix leaves them, so there is nothing to change: the plurals are only flagged.
    $f = sortsEntry(['title' => 'The 1930’s', 'sortsPlain' => 'Clean text', 'sortsBodySite' => '<p>Built in the 1950’s and 2 × 4’s.</p>']);
    $before = [$revisions($f->id), $updated($f->id)];
    $result = sorts()->auditScanner->scan($sectionId(), $primary(), 0);
    $flagged = array_column($result['flagged'], null, 'id');
    sortsCheck('flagged: the entry with plurals is listed', isset($flagged[$f->id]), json_encode(array_keys($flagged)));
    sortsCheck('flagged: a clean entry is not', !isset($flagged[$b->id]));
    sortsCheck('flagged: it is not offered for a fix, for there is nothing to change', !in_array($f->id, array_column($result['items'], 'id'), true));
    $flags = $flagged[$f->id]['flags'] ?? [];
    sortsEq('flagged: names each value that has one', ['Title', 'Sorts Body Site'], array_column($flags, 'label'));
    sortsEq('flagged: the title note shows the match, what to write and the words around it', [['match' => '1930’s', 'suggestion' => '1930s', 'context' => 'The 1930’s']], $flags[0]['notes'] ?? null);
    sortsEq('flagged: a rich-text note shows plain words, and finds every plural', [['match' => '1950’s', 'suggestion' => '1950s', 'context' => 'Built in the 1950’s and 2 ×'], ['match' => '4’s', 'suggestion' => '4s', 'context' => 'and 2 × 4’s.']], $flags[1]['notes'] ?? null);
    $page = (string) sortsCpClient()->get('/admin/utilities/sorts?scan=1&section=' . $sectionId() . '&site=' . $primary())->getBody();
    sortsCheck('flagged: the page has a section for them', str_contains($page, 'Flagged, not changed') && str_contains($page, 'id="sorts-flagged"'));
    sortsCheck('flagged: the page shows what was found and what to write', str_contains($page, 'The 1930’s') && str_contains($page, '1930s') && str_contains($page, 'Sorts Body Site'));
    sortsCheck('flagged: the page gives them no tick box', !str_contains($page, 'value="' . $f->id . ':' . $primary() . '"'));
    sortsEq('flagged: nothing stored changed', 'The 1930’s', sortsFresh($f->id)->title);
    sortsEq('flagged: the entry has the same revision count and Date Updated', $before, [$revisions($f->id), $updated($f->id)]);
});

sortsTry('the revision box', function () use (&$a, $revisions, $updated, $primary, $postFix, $sectionId) {
    sortsConfigure('pro');
    $d = sortsEntry(['title' => 'Fourth "three"']);
    sleep(1);
    $before = [$revisions($d->id), $updated($d->id)];
    $r = $postFix(sortsCpClient(), [$d->id . ':' . $primary()], true, 'scan=1');
    Craft::$app->getQueue()->run();
    sortsEq('revision box: the title is fixed', 'Fourth “three”', sortsFresh($d->id)->title);
    sortsEq('revision box: one more revision', $before[0] + 1, $revisions($d->id));
    sortsCheck('revision box: Date Updated moved', $updated($d->id) !== $before[1], $updated($d->id));
});

sortsTry('gate on enqueue, not on run', function () use (&$a, $revisions, $updated, $primary, $postFix, $queued) {
    sortsConfigure('pro');
    $e = sortsEntry(['title' => 'Fifth "four" -- done']);
    $cp = sortsCpClient();
    $pageUrl = '/admin/utilities/sorts?scan=1';
    $page = (string) $cp->get($pageUrl)->getBody();
    preg_match('/name="CRAFT_CSRF_TOKEN" value="([^"]+)"/', $page, $m);
    $token = $m[1];
    $q = $queued();
    $cp->post('/admin/utilities/sorts', ['form_params' => ['CRAFT_CSRF_TOKEN' => $token, 'action' => 'sorts/audit/fix', 'entries' => [$e->id . ':' . $primary()]]]);
    sortsEq('queued under Pro', $q + 1, $queued());

    sortsConfigure('lite');
    $q = $queued();
    $r = $cp->post('/admin/utilities/sorts', ['form_params' => ['CRAFT_CSRF_TOKEN' => $token, 'action' => 'sorts/audit/fix', 'entries' => [$e->id . ':' . $primary()]]]);
    sortsEq('lite: no new job is queued', $q, $queued());
    sortsEq('lite: the post is refused', 403, $r->getStatusCode());
    $lite = (string) $cp->get('/admin/utilities/sorts')->getBody();
    sortsCheck('lite: the utility is not offered', !str_contains($lite, 'Scan the first 100 entries'));

    Craft::$app->getQueue()->run();
    sortsEq('a job queued under Pro finishes after a downgrade (Lite’s characters, the whole entry)', 'Fifth “four” — done', sortsFresh($e->id)->title);
    sortsConfigure('pro');
});

/** A fix job as the utility queues it (options resolved now, the admin as the user), without the queue. */
$job = static function (array $entries): \iceboxind\sorts\jobs\FixEntriesJob {
    $before = (int) (new \craft\db\Query())->from(\craft\db\Table::QUEUE)->max('id');
    \iceboxind\sorts\jobs\FixEntriesJob::enqueue($entries, false, 1);
    $row = (new \craft\db\Query())->from(\craft\db\Table::QUEUE)->where(['>', 'id', $before])->one();
    Craft::$app->getDb()->createCommand()->delete(\craft\db\Table::QUEUE, ['id' => $row['id']])->execute();
    return unserialize(is_resource($row['job']) ? stream_get_contents($row['job']) : $row['job']);
};

sortsTry('a job skips what is gone or stale', function () use ($primary, $job) {
    sortsConfigure('pro');
    $job([[999999, $primary()]])->execute(Craft::$app->getQueue());
    sortsCheck('a deleted entry is skipped without an error', true);
    $e = sortsEntry(['title' => 'Clean title']);
    $job([[$e->id, $primary()]])->execute(Craft::$app->getQueue());
    sortsEq('an entry with nothing to fix is left alone', 'Clean title', sortsFresh($e->id)->title);
    $f = sortsEntry(['title' => 'No "user" here']);
    $nobody = new \iceboxind\sorts\jobs\FixEntriesJob(['entries' => [[$f->id, $primary()]]] + array_intersect_key(get_object_vars($job([[$f->id, $primary()]])), ['options' => 1]));
    $nobody->execute(Craft::$app->getQueue());
    sortsEq('a job that does not say who queued it saves nothing', 'No "user" here', sortsFresh($f->id)->title);
});

// FU7: the job saves only what the user who queued it may save. Craft Solo lets every user do
// everything (elements/User.php:1870-1877 in 5.12.0.1), so the refusal comes through Craft's own
// EVENT_AUTHORIZE_SAVE, which Elements::canSave() asks (services/Elements.php:4994-5007, :5159-5172).
sortsTry('a job skips an entry the user may not save', function () use ($primary, $job) {
    sortsConfigure('pro');
    $denied = sortsEntry(['title' => 'Denied "one"']);
    $allowed = sortsEntry(['title' => 'Allowed "two"']);
    $fixJob = $job([[$denied->id, $primary()], [$allowed->id, $primary()]]);
    sortsEq('the job carries the user who queued it', 1, $fixJob->userId);
    $refuse = static function (\craft\events\AuthorizationCheckEvent $event) use ($denied): void {
        if ((int) $event->element->id === (int) $denied->id) {
            $event->authorized = false;
        }
    };
    \yii\base\Event::on(\craft\services\Elements::class, \craft\services\Elements::EVENT_AUTHORIZE_SAVE, $refuse);
    try {
        $fixJob->execute(Craft::$app->getQueue());
    } finally {
        \yii\base\Event::off(\craft\services\Elements::class, \craft\services\Elements::EVENT_AUTHORIZE_SAVE, $refuse);
    }
    sortsEq('permission refused: that entry is left alone', 'Denied "one"', sortsFresh($denied->id)->title);
    sortsEq('permission given: the other entry is fixed', 'Allowed “two”', sortsFresh($allowed->id)->title);
});

// FU9: a job carries the options it was queued with, so a downgrade cannot turn French quotes English.
sortsTry('a job queued under Pro keeps a French entry French after a downgrade', function () use ($job) {
    sortsConfigure('pro');
    $frId = Craft::$app->getSites()->getSiteByHandle('testbedFr')->id;
    $e = sortsEntry(['title' => 'x']);
    $fr = sortsFresh($e->id, $frId);
    $fr->title = '"Bonjour" -- toi';
    Craft::$app->getElements()->saveElement($fr);
    $fixJob = $job([[$e->id, $frId]]);
    sortsEq('the job carries the French locale', 'fr', $fixJob->options[$frId]['text']['locale'] ?? null);
    sortsConfigure('lite');
    $fixJob->execute(Craft::$app->getQueue());
    sortsEq('after the downgrade, the French title is fixed in French', '«Bonjour» – toi', sortsFresh($e->id, $frId)->title);
    sortsConfigure('pro');
});

// D818: stored content follows the quote type, or the depth setting, and a queued job keeps the one it was queued with.
sortsTry('quote type and normalizing by depth', function () use ($primary, $job, $sectionId) {
    sortsClean();
    sortsConfigure('pro');
    $e = sortsEntry(['title' => 'The \'best\' and "worst"']);
    $title = static fn (array $result): ?string => $result['items'][0]['changes'][0]['excerpts'][0]['after'] ?? null;
    sortsEq('scan: a typed single is the inner pair by default', 'The ‘best’ and “worst”', $title(sorts()->auditScanner->scan($sectionId(), $primary(), 0)));
    sortsConfigure('pro', ['normalizeQuotes' => true]);
    sortsEq('scan: normalizeQuotes shows the depth result', 'The “best” and “worst”', $title(sorts()->auditScanner->scan($sectionId(), $primary(), 0)));
    $fixJob = $job([[$e->id, $primary()]]);
    sortsEq('the job carries the setting', 'depth', $fixJob->options[$primary()]['text']['quoteNesting'] ?? null);
    sortsConfigure('lite');
    $fixJob->execute(Craft::$app->getQueue());
    sortsEq('a job queued with normalizeQuotes keeps it after a downgrade', 'The “best” and “worst”', sortsFresh($e->id)->title);
    sortsConfigure('pro');
});

sortsClean();
sortsDefaults();
sortsDone();
