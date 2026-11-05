// ddev craft shell < tests/fix-on-save-test.php
// Fix-on-save (Pro, off by default): what it changes, what it never touches, and the saves it skips
// (resaves, propagation, provisional drafts, revisions, migrations) unless a setting asks. On the
// testbed's English and French sites a value is converted only when every site it reaches is in
// the saving site's language: a new entry reaches both, and so does the shared sortsBody field;
// the title, sortsPlain and sortsBodySite are translated per site.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

const FIX_STRAIGHT_TITLE = 'The "Title" -- it\'s here...';
const FIX_FIXED_TITLE = 'The “Title” — it’s here…';
const FIX_STRAIGHT_PLAIN = "Line one \"a\" -- b\nLine two it's... 10 kg for Dr. Smith";
const FIX_FIXED_PLAIN = "Line one “a” — b\nLine two it’s… 10 kg for Dr. Smith";
const FIX_STRAIGHT_BODY = '<p>"Quoted" -- <a href="/x?a=\'b\'">link</a> it\'s...</p>';
const FIX_FIXED_BODY = '<p>“Quoted” — <a href="/x?a=\'b\'">link</a> it’s…</p>';

$straight = static fn (array $extra = []): array => $extra + [
    'title' => FIX_STRAIGHT_TITLE,
    'sortsPlain' => FIX_STRAIGHT_PLAIN,
    'sortsCode' => FIX_STRAIGHT_PLAIN,
    'sortsBody' => FIX_STRAIGHT_BODY,
    'sortsBodySite' => FIX_STRAIGHT_BODY,
];

/**
 * A sortsTest entry as fix-on-save leaves it after an edit on the English site: created (a new
 * entry reaches the French site too, so it goes in as typed), then saved again with the same values.
 */
$edited = static function (array $values): \craft\elements\Entry {
    $entry = sortsEntry($values);
    $en = sortsFresh($entry->id);
    unset($values['slug']);
    if (isset($values['title'])) {
        $en->title = $values['title'];
        unset($values['title']);
    }
    $en->setFieldValues($values);
    if (!Craft::$app->getElements()->saveElement($en)) {
        throw new \RuntimeException('The edit did not save: ' . json_encode($en->getErrors()));
    }
    return $en;
};

sortsTry('off by default', function () use ($straight) {
    sortsClean();
    sortsConfigure('pro');
    sortsCheck('fix-on-save is off after a fresh install', sorts()->getSettings()->fixOnSave === false);
    $e = sortsEntry($straight());
    $f = sortsFresh($e->id);
    sortsEq('off: title untouched', FIX_STRAIGHT_TITLE, $f->title);
    sortsEq('off: plain text untouched', FIX_STRAIGHT_PLAIN, sortsRaw($f, 'sortsPlain'));
    sortsEq('off: CKEditor untouched', FIX_STRAIGHT_BODY, sortsRaw($f, 'sortsBody'));
});

sortsTry('on, one save', function () use ($straight) {
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true]);
    $e = sortsEntry($straight());
    $en = sortsFresh($e->id);
    $en->title = FIX_STRAIGHT_TITLE;
    $en->setFieldValues(array_diff_key($straight(), ['title' => 1]));
    $saves = 0;
    $count = static function (\craft\events\ElementEvent $ev) use (&$saves) {
        if ($ev->element instanceof \craft\elements\Entry && !$ev->element->propagating && !$ev->element->getIsRevision()) {
            $saves++;
        }
    };
    \yii\base\Event::on(\craft\services\Elements::class, \craft\services\Elements::EVENT_AFTER_SAVE_ELEMENT, $count);
    Craft::$app->getElements()->saveElement($en);
    \yii\base\Event::off(\craft\services\Elements::class, \craft\services\Elements::EVENT_AFTER_SAVE_ELEMENT, $count);
    $f = sortsFresh($e->id);
    sortsEq('on: title fixed', FIX_FIXED_TITLE, $f->title);
    sortsEq('on: plain text fixed', FIX_FIXED_PLAIN, sortsRaw($f, 'sortsPlain'));
    sortsEq('on: CKEditor fixed, attributes untouched', FIX_FIXED_BODY, sortsRaw($f, 'sortsBodySite'));
    sortsEq('on: a code field is never touched', FIX_STRAIGHT_PLAIN, sortsRaw($f, 'sortsCode'));
    sortsEq('on: the fix rides the one save (no second save)', 1, $saves);
    sortsCheck('on: no spacing rules in stored text (10 kg and Dr. Smith keep plain spaces)', !str_contains(sortsRaw($f, 'sortsPlain'), "\u{00A0}") && str_contains(sortsRaw($f, 'sortsPlain'), '10 kg for Dr. Smith'), sortsRaw($f, 'sortsPlain'));
});

sortsTry('toggles per type', function () use ($straight, $edited) {
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true, 'fixTitles' => false]);
    $f = sortsFresh($edited($straight())->id);
    sortsEq('titles off: title untouched', FIX_STRAIGHT_TITLE, $f->title);
    sortsEq('titles off: plain text still fixed', FIX_FIXED_PLAIN, sortsRaw($f, 'sortsPlain'));
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true, 'fixPlainText' => false]);
    $f = sortsFresh($edited($straight())->id);
    sortsEq('plain text off: plain text untouched', FIX_STRAIGHT_PLAIN, sortsRaw($f, 'sortsPlain'));
    sortsEq('plain text off: CKEditor still fixed', FIX_FIXED_BODY, sortsRaw($f, 'sortsBodySite'));
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true, 'fixCkeditor' => false]);
    $f = sortsFresh($edited($straight())->id);
    sortsEq('CKEditor off: CKEditor untouched', FIX_STRAIGHT_BODY, sortsRaw($f, 'sortsBodySite'));
    sortsEq('CKEditor off: title still fixed', FIX_FIXED_TITLE, $f->title);
    sortsClean();
});

sortsTry('rules follow the settings', function () use ($edited) {
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true, 'rules' => ['quotes' => false, 'dashes' => false, 'fractions' => true]]);
    $f = sortsFresh($edited(['title' => 'Use 1/2 cup "now" -- ok'])->id);
    sortsEq('rules: only the rules switched on', "Use ½ cup \"now\" -- ok", $f->title);
    sortsClean();
});

sortsTry('limits', function () {
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true]);
    $frId = Craft::$app->getSites()->getSiteByHandle('testbedFr')->id;
    $e = sortsEntry(['title' => 'Limits']);
    // A French site: `--` becomes a spaced en dash, two characters longer than the six the field allows.
    $fr = sortsFresh($e->id, $frId);
    $fr->setFieldValue('sortsShort', 'a--bcd');
    sortsCheck('limit: the French entry saves', Craft::$app->getElements()->saveElement($fr), json_encode($fr->getErrors()));
    sortsEq('limit: a fix that would break the field’s character limit is skipped', 'a--bcd', sortsRaw(sortsFresh($e->id, $frId), 'sortsShort'));
    $en = sortsFresh($e->id);
    $en->setFieldValue('sortsShort', 'a--bcd');
    Craft::$app->getElements()->saveElement($en);
    sortsEq('limit: on the English site the same value fits and is fixed', 'a—bcd', sortsRaw(sortsFresh($e->id), 'sortsShort'));
    sortsClean();
});

sortsTry('skipped saves', function () use ($straight) {
    sortsClean();
    sortsConfigure('pro');
    $e = sortsEntry($straight());          // saved straight: fix-on-save was off
    $elements = Craft::$app->getElements();

    sortsConfigure('pro', ['fixOnSave' => true]);

    // A resave.
    $r = sortsFresh($e->id);
    $r->resaving = true;
    $elements->saveElement($r, false, false);
    sortsEq('resave: skipped by default', FIX_STRAIGHT_TITLE, sortsFresh($e->id)->title);

    // A provisional draft autosave.
    $p = sortsFresh($e->id);
    $draft = Craft::$app->getDrafts()->createDraft($p, 1, null, null, [], true);
    $draft->title = FIX_STRAIGHT_TITLE . ' draft';
    $draft->setFieldValue('sortsBody', FIX_STRAIGHT_BODY);
    sortsCheck('setup: it is a provisional draft', (bool) $draft->isProvisionalDraft);
    $elements->saveElement($draft);
    $d = \craft\elements\Entry::find()->id($draft->id)->drafts(true)->provisionalDrafts(true)->status(null)->one();
    sortsEq('provisional draft: title skipped', FIX_STRAIGHT_TITLE . ' draft', $d->title);
    sortsEq('provisional draft: CKEditor skipped', FIX_STRAIGHT_BODY, sortsRaw($d, 'sortsBody'));

    // A draft saved on purpose (not provisional) is a normal save.
    $named = Craft::$app->getDrafts()->createDraft(sortsFresh($e->id), 1, 'On purpose');
    $named->title = 'Named "draft"';
    $elements->saveElement($named);
    $n = \craft\elements\Entry::find()->id($named->id)->drafts(true)->status(null)->one();
    sortsEq('named draft: fixed like any save', 'Named “draft”', $n->title);

    // A revision is a snapshot and is never touched.
    $rev = Craft::$app->getRevisions()->createRevision(sortsFresh($e->id), 1, 'snapshot');
    $revision = \craft\elements\Entry::find()->id($rev)->revisions(true)->status(null)->one();
    $revision->title = 'Rev "straight"';
    $elements->saveElement($revision);
    sortsEq('revision: skipped', 'Rev "straight"', \craft\elements\Entry::find()->id($rev)->revisions(true)->status(null)->one()->title);

    // A migration.
    $migration = new class extends \craft\db\Migration {
        public $entryId;
        public function safeUp(): bool
        {
            $entry = \craft\elements\Entry::find()->id($this->entryId)->status(null)->one();
            $entry->resaving = false;
            Craft::$app->getElements()->saveElement($entry, false, false);
            return true;
        }
        public function safeDown(): bool
        {
            return true;
        }
    };
    $migration->entryId = $e->id;
    $migration->up();
    sortsEq('migration: skipped by default', FIX_STRAIGHT_TITLE, sortsFresh($e->id)->title);

    // Now ask for it.
    sortsConfigure('pro', ['fixOnSave' => true, 'fixOnSaveBulk' => true]);
    $r = sortsFresh($e->id);
    $r->resaving = true;
    $elements->saveElement($r, false, false);
    sortsEq('bulk setting: a resave is fixed', FIX_FIXED_TITLE, sortsFresh($e->id)->title);

    $e2 = sortsEntry($straight(['slug' => 'migration-two']));
    sortsConfigure('pro', ['fixOnSave' => true, 'fixOnSaveBulk' => true]);
    $e2 = sortsFresh($e2->id);
    // (the entry above was already fixed on its way in, so reset it quietly to straight and migrate)
    sortsConfigure('pro');
    $e3 = sortsEntry($straight(['slug' => 'migration-three']));
    sortsConfigure('pro', ['fixOnSave' => true, 'fixOnSaveBulk' => true]);
    $migration->entryId = $e3->id;
    $migration->up();
    sortsEq('bulk setting: a migration is fixed', FIX_FIXED_TITLE, sortsFresh($e3->id)->title);
    sortsClean();
});

sortsTry('propagation', function () use ($straight) {
    sortsClean();
    $frId = Craft::$app->getSites()->getSiteByHandle('testbedFr')->id;
    sortsConfigure('pro');
    $e = sortsEntry($straight());
    sortsEq('setup: the French entry exists and is straight (fix-on-save was off)', FIX_STRAIGHT_TITLE, sortsFresh($e->id, $frId)->title);

    sortsConfigure('pro', ['fixOnSave' => true]);
    // Edit the English entry; Craft propagates the save to the French one.
    $en = sortsFresh($e->id);
    $en->setFieldValue('sortsShort', 'x');
    Craft::$app->getElements()->saveElement($en);
    sortsEq('propagation: the saved site is fixed', FIX_FIXED_TITLE, sortsFresh($e->id)->title);
    sortsEq('propagation: the other site’s own title is left to its own save', FIX_STRAIGHT_TITLE, sortsFresh($e->id, $frId)->title);
    sortsEq('propagation: its translatable text is untouched too', FIX_STRAIGHT_PLAIN, sortsRaw(sortsFresh($e->id, $frId), 'sortsPlain'));
    sortsClean();
});

sortsTry('reach: a new entry goes in as typed on every site', function () {
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true]);
    $frId = Craft::$app->getSites()->getSiteByHandle('testbedFr')->id;
    $e = null;
    $logged = sortsLogged(function () use (&$e) {
        $e = sortsEntry(['title' => '"Bonjour" -- toi', 'slug' => 'reach-new', 'sortsPlain' => '"Bonjour" toi', 'sortsBody' => '<p>"Bonjour" toi</p>', 'sortsBodySite' => '<p>"Bonjour" toi</p>']);
    }, ['info']);
    foreach (['en' => null, 'fr' => $frId] as $site => $siteId) {
        $f = sortsFresh($e->id, $siteId);
        sortsEq("new entry, $site: title as typed", '"Bonjour" -- toi', $f->title);
        sortsEq("new entry, $site: translatable plain text as typed", '"Bonjour" toi', sortsRaw($f, 'sortsPlain'));
        sortsEq("new entry, $site: shared CKEditor as typed", '<p>"Bonjour" toi</p>', sortsRaw($f, 'sortsBody'));
        sortsEq("new entry, $site: per-site CKEditor as typed", '<p>"Bonjour" toi</p>', sortsRaw($f, 'sortsBodySite'));
    }
    sortsCheck('new entry: each value left as typed is logged at info, naming the French site', count(array_filter($logged, static fn (string $m): bool => str_contains($m, 'as typed') && str_contains($m, 'testbedFr'))) >= 4, json_encode($logged, JSON_UNESCAPED_UNICODE));
    // The page pass then sets each page in its own language.
    $fr = (string) sortsHttp()->get('/fr/sorts-test/reach-new')->getBody();
    $en = (string) sortsHttp()->get('/sorts-test/reach-new')->getBody();
    sortsCheck('new entry: the French page shows French quotes', str_contains($fr, "<h1>«\u{202F}Bonjour\u{202F}» –\u{00A0}toi</h1>") && !str_contains($fr, '“Bonjour”'), substr($fr, 0, 500));
    sortsCheck('new entry: the English page shows English quotes', str_contains($en, '<h1>“Bonjour” —&nbsp;toi</h1>') || str_contains($en, "<h1>“Bonjour” —\u{00A0}toi</h1>"), substr($en, 0, 500));
    sortsClean();
});

// Craft's draft path: a new entry saved as a draft is not propagateAll, and it is on no site
// yet, so every other site counts as reached (R2-1).
sortsTry('reach: an unpublished draft, edited, then published', function () {
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true]);
    $frId = Craft::$app->getSites()->getSiteByHandle('testbedFr')->id;
    $section = Craft::$app->getEntries()->getSectionByHandle('sortsTest');
    $entry = new \craft\elements\Entry(['sectionId' => $section->id, 'typeId' => $section->getEntryTypes()[0]->id, 'siteId' => Craft::$app->getSites()->getPrimarySite()->id]);
    $entry->title = '"Draft" -- one';
    $entry->slug = 'reach-draft';
    $entry->setFieldValues(['sortsPlain' => '"Draft" plain', 'sortsBodySite' => '<p>"Draft" site</p>']);
    sortsCheck('draft: saved', Craft::$app->getDrafts()->saveElementAsDraft($entry, 1, null, null, false), json_encode($entry->getErrors()));
    $copy = static fn (int $siteId): ?\craft\elements\Entry => \craft\elements\Entry::find()->id($entry->id)->drafts(null)->status(null)->siteId($siteId)->one();
    sortsEq('draft, fr: the title is as typed', '"Draft" -- one', $copy($frId)?->title);
    sortsEq('draft, fr: translatable plain text as typed', '"Draft" plain', sortsRaw($copy($frId), 'sortsPlain'));
    sortsEq('draft, fr: per-site CKEditor as typed', '<p>"Draft" site</p>', sortsRaw($copy($frId), 'sortsBodySite'));
    $draft = \craft\elements\Entry::find()->id($entry->id)->drafts(true)->status(null)->one();
    $draft->title = '"Draft" -- two';
    Craft::$app->getElements()->saveElement($draft);
    sortsEq('draft edited on en: fixed in English', '“Draft” — two', $copy(Craft::$app->getSites()->getPrimarySite()->id)?->title);
    sortsEq('draft edited on en: the fr title is still as typed', '"Draft" -- one', $copy($frId)?->title);
    $published = Craft::$app->getDrafts()->applyDraft($draft);
    sortsEq('published, fr: the title is as typed', '"Draft" -- one', sortsFresh($published->id, $frId)?->title);
    $fr = (string) sortsHttp()->get('/fr/sorts-test/reach-draft')->getBody();
    sortsCheck('published: the French page shows French quotes', str_contains($fr, "<h1>«\u{202F}Draft\u{202F}» –\u{00A0}one</h1>"), substr($fr, 0, 600));
    sortsClean();
});

sortsTry('reach: a shared CKEditor field', function () {
    sortsClean();
    sortsConfigure('pro');
    $frId = Craft::$app->getSites()->getSiteByHandle('testbedFr')->id;
    $e = sortsEntry(['title' => 'Shared', 'slug' => 'reach-shared']);
    sortsConfigure('pro', ['fixOnSave' => true]);
    $en = sortsFresh($e->id);
    $en->setFieldValues(['sortsBody' => '<p>"Shared" edit</p>', 'sortsBodySite' => '<p>"Own" edit</p>']);
    $logged = sortsLogged(static fn () => Craft::$app->getElements()->saveElement($en), ['info']);
    sortsEq('shared edit on en: the shared field is left as typed', '<p>"Shared" edit</p>', sortsRaw(sortsFresh($e->id), 'sortsBody'));
    sortsEq('shared edit on en: ...and reaches fr as typed', '<p>"Shared" edit</p>', sortsRaw(sortsFresh($e->id, $frId), 'sortsBody'));
    sortsEq('shared edit on en: a per-site field in the same save is fixed', '<p>“Own” edit</p>', sortsRaw(sortsFresh($e->id), 'sortsBodySite'));
    sortsCheck('shared edit on en: logged at info', (bool) array_filter($logged, static fn (string $m): bool => str_contains($m, 'sortsBody of element') && str_contains($m, 'testbedFr')), json_encode($logged, JSON_UNESCAPED_UNICODE));
    $fr = (string) sortsHttp()->get('/fr/sorts-test/reach-shared')->getBody();
    sortsCheck('shared edit on en: the French page shows French quotes', str_contains($fr, "<p>«\u{202F}Shared\u{202F}» edit</p>"), substr($fr, 0, 600));
    sortsClean();
});

sortsTry('reach: a translatable Plain Text field', function () {
    sortsClean();
    sortsConfigure('pro');
    $frId = Craft::$app->getSites()->getSiteByHandle('testbedFr')->id;
    $e = sortsEntry(['title' => 'Translated', 'sortsPlain' => 'x']);
    sortsConfigure('pro', ['fixOnSave' => true]);
    $en = sortsFresh($e->id);
    $en->setFieldValue('sortsPlain', '"Hello" -- you');
    Craft::$app->getElements()->saveElement($en);
    $fr = sortsFresh($e->id, $frId);
    $fr->setFieldValue('sortsPlain', '"Bonjour" -- toi');
    Craft::$app->getElements()->saveElement($fr);
    sortsEq('translatable, en: fixed in English', '“Hello” — you', sortsRaw(sortsFresh($e->id), 'sortsPlain'));
    sortsEq('translatable, fr: fixed in French (quotes and dash; spacing belongs to the page)', '«Bonjour» – toi', sortsRaw(sortsFresh($e->id, $frId), 'sortsPlain'));
    sortsClean();
});

sortsTry('lite does nothing', function () use ($straight) {
    sortsClean();
    sortsConfigure('lite', ['fixOnSave' => true]);
    $f = sortsFresh(sortsEntry($straight())->id);
    sortsEq('lite: the setting is stored but not obeyed', FIX_STRAIGHT_TITLE, $f->title);
    sortsClean();
});

sortsTry('a failing fix never stops a save', function () use ($straight) {
    sortsClean();
    sortsConfigure('pro', ['fixOnSave' => true]);
    sorts()->set('engine', new class extends \iceboxind\sorts\services\EngineService {
        public function storedOptions(?string $language, string $mode): \iceboxind\sorts\engine\Options
        {
            throw new \RuntimeException('The engine fails.');
        }
    });
    $e = sortsEntry($straight());
    sortsEq('failing fix: the entry saved as typed', FIX_STRAIGHT_TITLE, sortsFresh($e->id)->title);
    sorts()->set('engine', ['class' => \iceboxind\sorts\services\EngineService::class]);
    sortsClean();
});

sortsDefaults();
sortsDone();
