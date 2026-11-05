<?php

/**
 * Shared helpers for the Sorts integration suites. Each suite loads this with
 * `require_once '/var/www/html/plugins/craft-sorts/tests/support.php';` (the path the
 * testbed container sees the repo at) and ends with `sortsDone();`.
 *
 * Everything here runs against the testbed (Craft 5.12 plus the sortsTest section, the
 * French site and the sortstest module), never a client site.
 */

use craft\elements\Entry;
use iceboxind\sorts\Plugin;

const SORTS_BASE = 'http://127.0.0.1';
const SORTS_HOST = 'craft5-testbed.ddev.site';

$GLOBALS['sorts'] = ['pass' => 0, 'fail' => 0];

/**
 * Each suite leaves the testbed as it found it: the plugin's project config (edition and
 * settings, from the YAML, which is the committed state) goes back into the database, every
 * project config YAML file gets its bytes back, and the queue jobs pushed while it ran are
 * deleted. sortsDone() puts it back and checks. (The shell never writes YAML, ProjectConfig.php:405,419-426 in Craft 5.12.0.1, so only a web
 * request in a suite can change the files.)
 */
function sortsSnapshot(): array
{
    $pc = sortsRefresh();
    $files = [];
    foreach (\craft\helpers\FileHelper::findFiles(Craft::$app->getPath()->getProjectConfigPath(), ['only' => ['*.yaml']]) as $file) {
        $files[$file] = file_get_contents($file);
    }
    return [
        'plugin' => $pc->get('plugins.sorts', true),
        'dateModified' => $pc->get('dateModified', true),
        'files' => $files,
        'queue' => (int) (new \craft\db\Query())->from(\craft\db\Table::QUEUE)->max('id'),
    ];
}

/** Put back what sortsSnapshot() saw. Returns what it could not put back, empty when all is well. */
function sortsRestore(): array
{
    $snap = $GLOBALS['sortsSnapshot'] ?? null;
    if ($snap === null) {
        return [];
    }
    $GLOBALS['sortsSnapshot'] = null;
    $problems = [];
    foreach ($snap['files'] as $file => $bytes) {
        if (@file_get_contents($file) !== $bytes && file_put_contents($file, $bytes) === false) {
            $problems[] = "could not write $file back";
        }
    }
    foreach (\craft\helpers\FileHelper::findFiles(Craft::$app->getPath()->getProjectConfigPath(), ['only' => ['*.yaml']]) as $file) {
        if (!isset($snap['files'][$file])) {
            $problems[] = "a new project config file appeared: $file";
        }
    }
    $pc = sortsRefresh();
    $pc->set('plugins.sorts', $snap['plugin'], 'Sorts tests: the plugin as the suite found it', false);
    $pc->set('dateModified', $snap['dateModified'], null, false);
    $pc->saveModifiedConfigData();
    sorts()->edition = $snap['plugin']['edition'] ?? 'lite';
    sorts()->engine->reset();
    Craft::$app->getDb()->createCommand()->delete(\craft\db\Table::QUEUE, ['>', 'id', $snap['queue']])->execute();
    $diff = \craft\helpers\ProjectConfig::diff();
    if ($diff !== '') {
        $problems[] = 'project config still differs from the YAML: ' . substr($diff, 0, 300);
    }
    return $problems;
}

// `craft shell` runs no shutdown functions, so the put-back is sortsDone()'s job; run-all.mjs checks afterwards.
$GLOBALS['sortsSnapshot'] = sortsSnapshot();

/** Record one check. */
function sortsCheck(string $label, bool $ok, string $detail = ''): void
{
    $GLOBALS['sorts'][$ok ? 'pass' : 'fail']++;
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . ($ok || $detail === '' ? '' : "\n       " . $detail) . "\n";
}

/** Record that two values are equal; the detail shows both, with invisible characters made visible. */
function sortsEq(string $label, mixed $expected, mixed $actual): void
{
    $show = static fn (mixed $v): string => is_string($v)
        ? str_replace(["\u{00A0}", "\u{202F}", "\n"], ['\u{A0}', '\u{202F}', '\n'], $v)
        : json_encode($v, JSON_UNESCAPED_UNICODE);
    sortsCheck($label, $expected === $actual, 'expected ' . $show($expected) . "\n       actual   " . $show($actual));
}

/** Run a block; an exception is a failed check, not a silent skip. */
function sortsTry(string $label, callable $fn): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        sortsCheck($label . ' threw', false, get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    }
}

/**
 * Run $fn and return the messages Sorts logged at the given levels while it ran (through a log
 * target of our own, removed afterwards).
 *
 * @param list<string> $levels
 * @return list<string>
 */
function sortsLogged(callable $fn, array $levels): array
{
    $target = new class extends \yii\log\Target {
        public array $seen = [];
        public function export(): void
        {
            foreach ($this->messages as $message) {
                $this->seen[] = is_string($message[0]) ? $message[0] : json_encode($message[0]);
            }
        }
    };
    $target->levels = $levels;
    $target->logVars = [];
    $logger = Craft::getLogger();
    $logger->flush(true);
    $logger->dispatcher->targets['sortsTest'] = $target;
    try {
        $fn();
        $logger->flush(true);
    } finally {
        unset($logger->dispatcher->targets['sortsTest']);
    }
    return array_values(array_filter($target->seen, static fn (string $m): bool => str_starts_with($m, 'Sorts')));
}

function sortsDone(): void
{
    $problems = sortsRestore();
    sortsCheck('isolation: the testbed is as the suite found it (plugin config, YAML, queue)', $problems === [], implode("\n       ", $problems));
    echo "RESULT: {$GLOBALS['sorts']['pass']} passed, {$GLOBALS['sorts']['fail']} failed\n";
}

function sorts(): Plugin
{
    return Plugin::getInstance();
}

/** Forget what this process loaded of project config: a web request may have changed it since. */
function sortsRefresh(): \craft\services\ProjectConfig
{
    Craft::$app->getIsInstalled(true);   // drops the cached info row, whose config version the project config checks
    $pc = Craft::$app->getProjectConfig();
    $pc->reset();
    return $pc;
}

/**
 * Put the plugin in an edition with the given settings (everything else at its default),
 * written to project config so a web request sees it too.
 */
function sortsConfigure(string $edition, array $settings = []): void
{
    $plugin = sorts();
    $pc = sortsRefresh();
    $pc->set('plugins.sorts.edition', $edition);
    $plugin->edition = $edition;
    $defaults = (new \iceboxind\sorts\models\Settings())->getAttributes();
    if (!Craft::$app->getPlugins()->savePluginSettings($plugin, array_replace($defaults, $settings))) {
        throw new \RuntimeException('Settings did not save: ' . json_encode($plugin->getSettings()->getErrors()));
    }
    $pc->saveModifiedConfigData();
    $plugin->engine->reset();
}

/** Rule-flag file for the testbed module: the fixture rule runs everywhere while it exists. */
function sortsEverywhere(bool $on): void
{
    $file = Craft::getAlias('@storage') . '/sortstest-everywhere';
    if ($on) {
        file_put_contents($file, '1');
    } elseif (is_file($file)) {
        unlink($file);
    }
}

/** A Guzzle client for the site from inside the container. */
function sortsHttp(): \GuzzleHttp\Client
{
    return new \GuzzleHttp\Client([
        'base_uri' => SORTS_BASE,
        'headers' => ['Host' => SORTS_HOST],
        'allow_redirects' => false,
        'http_errors' => false,
        'cookies' => new \GuzzleHttp\Cookie\CookieJar(),
        'timeout' => 60,
    ]);
}

/** Sign in to the control panel as the testbed admin with a client; returns the client. */
function sortsCpClient(): \GuzzleHttp\Client
{
    $http = sortsHttp();
    $login = (string) $http->get('/admin/login')->getBody();
    if (!preg_match('/csrfTokenValue":\s*"([^"]+)"/', $login, $m)) {
        throw new \RuntimeException('No CSRF token on the login page.');
    }
    $res = $http->post('/admin/actions/users/login', [
        'headers' => ['Accept' => 'application/json', 'X-CSRF-Token' => $m[1]],
        'form_params' => ['loginName' => 'admin', 'password' => 'Password123!', 'CRAFT_CSRF_TOKEN' => $m[1]],
    ]);
    if ($res->getStatusCode() !== 200) {
        throw new \RuntimeException('CP login failed: ' . $res->getStatusCode() . ' ' . substr((string) $res->getBody(), 0, 200));
    }
    return $http;
}

/** Remove every sortsTest entry, drafts and revisions with them. */
function sortsClean(): void
{
    foreach (Entry::find()->section('sortsTest')->site('*')->status(null)->drafts(null)->revisions(null)->provisionalDrafts(null)->unique()->all() as $entry) {
        Craft::$app->getElements()->deleteElement($entry, true);
    }
}

/**
 * Create a sortsTest entry on both sites (propagation all) and return it.
 *
 * @param array<string, mixed> $values field handle => value; `title` and `slug` are attributes
 */
function sortsEntry(array $values): Entry
{
    $entry = new Entry();
    $entry->sectionId = Craft::$app->getEntries()->getSectionByHandle('sortsTest')->id;
    $entry->typeId = Craft::$app->getEntries()->getEntryTypeByHandle('sortsTest')->id;
    $entry->siteId = Craft::$app->getSites()->getPrimarySite()->id;
    $entry->authorId = 1;
    $entry->title = $values['title'] ?? 'Sorts test';
    $entry->slug = $values['slug'] ?? 'sorts-test-' . bin2hex(random_bytes(3));
    unset($values['title'], $values['slug']);
    $entry->setFieldValues($values);
    if (!Craft::$app->getElements()->saveElement($entry)) {
        throw new \RuntimeException('Entry did not save: ' . json_encode($entry->getErrors()));
    }
    return $entry;
}

/** The entry as saved, read fresh, for the given site (the primary site by default). */
function sortsFresh(int $id, ?int $siteId = null): ?Entry
{
    return Entry::find()->id($id)->siteId($siteId ?? Craft::$app->getSites()->getPrimarySite()->id)->status(null)->one();
}

/** A value read back from an entry field as the raw string. */
function sortsRaw(?Entry $entry, string $handle): string
{
    $value = $entry?->getFieldValue($handle);
    return $value instanceof \craft\htmlfield\HtmlFieldData ? $value->getRawContent() : (string) $value;
}

/** The plugin's settings as project config holds them, in the shape the plugin reads them. */
function sortsStored(): array
{
    $pc = sortsRefresh();
    return \craft\helpers\ProjectConfig::unpackAssociativeArrays($pc->get('plugins.sorts.settings') ?? []);
}

function sortsDefaults(): void
{
    sortsEverywhere(false);
    sortsConfigure('lite');
}

/** Flag file for the testbed module: a rule that always throws is registered while it exists. */
function sortsThrowing(bool $on): void
{
    $file = Craft::getAlias('@storage') . '/sortstest-throw';
    if ($on) {
        file_put_contents($file, '1');
    } elseif (is_file($file)) {
        unlink($file);
    }
}

/**
 * The fields a browser would submit for the first form on a control panel page that holds
 * a hidden `action` input matching $action, as name => value pairs (checkboxes only when ticked, disabled
 * fields never, a repeated name keeping its last value; names ending in [] collect).
 *
 * @return array<string, string|list<string>>
 */
function sortsFormFields(string $html, string $action): array
{
    $doc = new \DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $xp = new \DOMXPath($doc);
    foreach ($xp->query('//form') as $form) {
        $found = false;
        foreach ($xp->query('.//input[@type="hidden"][@name="action"]', $form) as $a) {
            $found = $found || $a->getAttribute('value') === $action;
        }
        if (!$found) {
            continue;
        }
        $fields = [];
        $put = static function (string $name, string $value) use (&$fields): void {
            if (str_ends_with($name, '[]')) {
                $fields[$name][] = $value;
            } else {
                $fields[$name] = $value;
            }
        };
        foreach ($xp->query('.//input|.//textarea|.//select', $form) as $el) {
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) {
                continue;
            }
            $tag = $el->nodeName;
            if ($tag === 'input') {
                $type = strtolower($el->getAttribute('type'));
                if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                    continue;
                }
                if (in_array($type, ['submit', 'button', 'file'], true)) {
                    continue;
                }
                $put($name, $el->getAttribute('value'));
            } elseif ($tag === 'textarea') {
                $put($name, $el->textContent);
            } else {
                $value = null;
                foreach ($xp->query('.//option', $el) as $option) {
                    $value ??= $option->getAttribute('value');
                    if ($option->hasAttribute('selected')) {
                        $value = $option->getAttribute('value');
                    }
                }
                $put($name, (string) $value);
            }
        }
        return $fields;
    }
    throw new \RuntimeException("No form with action {$action} on the page.");
}
