// ddev craft shell < tests/settings-test.php
// Settings: coercion of odd values, validation, the control panel page in Lite and Pro (locked fields marked Pro),
// saving through the real form (a Lite save keeps what Pro configured), and config/sorts.php overrides.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$make = static function (array $values): \iceboxind\sorts\models\Settings {
    $s = new \iceboxind\sorts\models\Settings();
    $s->setAttributes($values, false);
    return $s;
};

sortsTry('defaults', function () {
    $engineDefaults = [];
    foreach (sorts()->engine->engine()->rules() as $rule) {
        if (array_key_exists($rule->handle(), \iceboxind\sorts\models\Settings::RULE_DEFAULTS)) {
            $engineDefaults[$rule->handle()] = $rule->defaultEnabled();
        }
    }
    $mine = \iceboxind\sorts\models\Settings::RULE_DEFAULTS;
    ksort($engineDefaults);
    ksort($mine);
    sortsEq('the rule defaults match the engine’s and SPEC §3', $engineDefaults, $mine);
    $s = new \iceboxind\sorts\models\Settings();
    sortsCheck('page pass on, head off, fix-on-save off', $s->pagePass === true && $s->processHead === false && $s->fixOnSave === false);
    sortsEq('defaults validate', true, $s->validate());
});

sortsTry('coercion', function () use ($make) {
    $s = $make(['skipTags' => '', 'skipClasses' => '', 'skipUris' => '', 'rules' => '', 'variants' => '']);
    sortsEq('empty strings become empty lists, and every rule follows the preset', [[], [], [], [], []], [$s->skipTags, $s->skipClasses, $s->skipUris, $s->rules, $s->variants]);
    $s = $make(['skipTags' => "figcaption\n blockquote ,figcaption\r\n\n", 'skipUris' => [' /api/* ', '', 7, ['nested'], null, '/api/*']]);
    sortsEq('a string splits on newlines and commas, trimmed and de-duplicated', ['figcaption', 'blockquote'], $s->skipTags);
    sortsEq('an array drops blanks, nested values and duplicates; numbers become strings', ['/api/*', '7'], $s->skipUris);
    $s = $make(['rules' => ['quotes' => '', 'fractions' => '1', 'ranges' => 'on', 'nonsense' => '1', 'hook-amp' => 'maybe']]);
    sortsEq('rules: an old "" off a rule that is on by default is off', 'off', $s->ruleState('quotes'));
    sortsEq('rules: an old "1" on a rule that is off by default is on', 'on', $s->ruleState('fractions'));
    sortsEq('rules: the word "on" is on', 'on', $s->ruleState('ranges'));
    sortsEq('rules: an unreadable value follows the preset', 'follow', $s->ruleState('hook-amp'));
    sortsCheck('rules: unknown handles are dropped', !array_key_exists('nonsense', $s->rules) && $s->ruleState('nonsense') === 'follow');
    $s = $make(['fixOnSave' => '1', 'pagePass' => '', 'processHead' => 'true', 'fixTitles' => 0, 'widowsMinWords' => '5', 'pluginName' => "  Typeset \n"]);
    sortsEq('booleans and integers coerce', [true, false, true, false, 5, 'Typeset'], [$s->fixOnSave, $s->pagePass, $s->processHead, $s->fixTitles, $s->widowsMinWords, $s->pluginName]);
    sortsEq('normalizeQuotes: off by default, "1" is on, a word it cannot read keeps the default', [false, true, false], [$make([])->normalizeQuotes, $make(['normalizeQuotes' => '1'])->normalizeQuotes, $make(['normalizeQuotes' => 'maybe'])->normalizeQuotes]);
    $s = $make(['normalizeQuotes' => true]);
    $s->setAttributes(['normalizeQuotes' => 'maybe'], false);
    sortsEq('normalizeQuotes: a word it cannot read keeps the value it had (on)', true, $s->normalizeQuotes);
    sortsEq('fineTypography: off by default, "1" is on, a word it cannot read keeps the default', [false, true, false], [$make([])->fineTypography, $make(['fineTypography' => '1'])->fineTypography, $make(['fineTypography' => 'maybe'])->fineTypography]);
    $s = $make(['widowsMinWords' => 'lots', 'pluginName' => ['x']]);
    sortsEq('a non-number widow count fails validation instead of throwing', false, $s->validate(['widowsMinWords']));
    sortsEq('a non-string plugin name becomes blank', '', $s->pluginName);
});

// D864: every rule is on, off or follows the preset; the three rules that exist for the preset have a switch too.
sortsTry('rule states', function () use ($make) {
    $S = \iceboxind\sorts\models\Settings::class;
    sortsEq('every rule that was ever switchable, and the three preset-only rules, has a switch', true, count(array_intersect(['typed-dashes', 'dialogue-dash', 'ellipsis-spacing'], array_keys($S::RULE_DEFAULTS))) === 3);
    $s = $make(['rules' => ['fractions' => 'on', 'dashes' => 'off', 'ranges' => 'follow', 'typed-dashes' => ' Off ', 'dialogue-dash' => ' ON ', 'ellipsis-spacing' => 'follow']]);
    sortsEq('the three states are read, in any case and with spaces', ['on', 'off', 'follow', 'off', 'on', 'follow'], array_map($s->ruleState(...), ['fractions', 'dashes', 'ranges', 'typed-dashes', 'dialogue-dash', 'ellipsis-spacing']));
    $kept = $s->rules;
    ksort($kept);
    sortsEq('only on and off are kept; a rule that follows is no entry', ['dashes' => 'off', 'dialogue-dash' => 'on', 'fractions' => 'on', 'typed-dashes' => 'off'], $kept);
    $handed = $s->ruleSwitches();
    ksort($handed);
    sortsEq('the engine is handed on and off only, a rule that follows left out', ['dashes' => false, 'dialogue-dash' => true, 'fractions' => true, 'typed-dashes' => false], $handed);
    sortsCheck('rules: a rule that follows has no switch handed on', !array_key_exists('ranges', $s->ruleSwitches()) && !array_key_exists('ellipsis-spacing', $s->ruleSwitches()));
    sortsEq('a settings model with nothing switched has every rule following', [], (new $S())->ruleSwitches());
    // Before there were three states the settings kept a plain on or off for every rule, and only one off its default
    // counted as the site's word. Those values read as exactly that: nothing at its default is a switch.
    $old = array_map(null, array_keys($S::RULE_DEFAULTS), array_values($S::RULE_DEFAULTS));
    $s = $make(['rules' => array_column($old, 1, 0)]);
    sortsEq('an old full map of defaults, as booleans, switches nothing', [], $s->rules);
    $s = $make(['rules' => ['quotes' => false, 'dashes' => true, 'ranges' => false, 'fractions' => true, 'multiplication' => '1', 'ordinals' => '', 'widows' => 0, 'nbsp' => 'false']]);
    sortsEq('an old boolean off its default is on or off, one at its default follows', ['off', 'follow', 'follow', 'on', 'on', 'follow', 'off', 'off'], array_map($s->ruleState(...), ['quotes', 'dashes', 'ranges', 'fractions', 'multiplication', 'ordinals', 'widows', 'nbsp']));
    sortsEq('an old stored value and a new one validate', true, $s->validate());
});

// The same settings reach the engine from project config, written by an older Sorts or by this one, and survive a save.
sortsTry('rule states in project config', function () {
    $legacy = [];
    foreach (\iceboxind\sorts\models\Settings::RULE_DEFAULTS as $handle => $default) {
        if (!in_array($handle, ['typed-dashes', 'dialogue-dash', 'ellipsis-spacing'], true)) {
            $legacy[$handle] = $default;
        }
    }
    // As the testbed's committed YAML has it: sixteen plain booleans, with dashes moved off its default.
    $legacy['dashes'] = false;
    $legacy['fractions'] = true;
    sortsConfigure('pro');
    $pc = sortsRefresh();
    $pc->set('plugins.sorts.settings.rules', $legacy);
    $pc->saveModifiedConfigData();
    sorts()->setSettings(sortsStored());
    sorts()->engine->reset();
    sortsEq('old booleans in project config: read as the same switches', ['off', 'on', 'follow', 'follow'], array_map(sorts()->getSettings()->ruleState(...), ['dashes', 'fractions', 'ranges', 'quotes']));
    sortsEq('old booleans in project config: the engine behaves as before (dashes off, fractions on)', 'Use ½ cup “now” -- ok 1-5', sorts()->engine->apply('Use 1/2 cup "now" -- ok 1-5', [], 'en-US', \iceboxind\sorts\engine\Options::TEXT));
    sortsEq('old booleans in project config: and the live page', true, str_contains((string) sortsHttp()->get('/sorts-lab')->getBody(), '“Hello” -- she said'));
    // Old booleans and the preset: a rule off by default and stored off was never a veto, and still is not.
    $legacy['ranges'] = false;
    $pc = sortsRefresh();
    $pc->set('plugins.sorts.settings.rules', $legacy);
    $pc->set('plugins.sorts.settings.fineTypography', true);
    $pc->saveModifiedConfigData();
    sorts()->setSettings(sortsStored());
    sorts()->engine->reset();
    sortsEq('old booleans under the preset: ranges stored off is no veto, as before', 'pages 10–12', sorts()->engine->apply('pages 10-12'));

    // The three states, written by this version.
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['ranges' => 'off', 'fractions' => 'on', 'typed-dashes' => 'off', 'dialogue-dash' => 'follow', 'ellipsis-spacing' => 'on']]);
    $stored = sortsStored()['rules'] ?? [];
    ksort($stored);
    sortsEq('saved: on and off are stored, a rule that follows is not', ['ellipsis-spacing' => 'on', 'fractions' => 'on', 'ranges' => 'off', 'typed-dashes' => 'off'], $stored);
    sorts()->setSettings(sortsStored());
    sortsEq('round trip: the states come back', ['off', 'on', 'off', 'follow', 'on', 'follow'], array_map(sorts()->getSettings()->ruleState(...), ['ranges', 'fractions', 'typed-dashes', 'dialogue-dash', 'ellipsis-spacing', 'quotes']));
    sortsEq('round trip: a veto is a veto', 'pages 10-12 and a—b', sorts()->engine->apply('pages 10-12 and a—b'));
    sortsConfigure('lite');
});

sortsTry('validation', function () use ($make) {
    sortsEq('tag names: valid', true, $make(['skipTags' => ['figcaption', 'my-element', 'h1']])->validate());
    foreach (['Bad Tag', 'UPPER', '1abc', 'a<b', '-x', 'a_b'] as $bad) {
        $s = $make(['skipTags' => [$bad]]);
        sortsCheck("tag names: “{$bad}” is refused", !$s->validate(['skipTags']) && $s->hasErrors('skipTags'));
    }
    sortsEq('classes: valid', true, $make(['skipClasses' => ['no-typography', 'sm:flex', 'w-1/2']])->validate());
    foreach (['two words', 'a"b', "a'b", 'a=b', 'a<b>'] as $bad) {
        sortsCheck("classes: “{$bad}” is refused", !$make(['skipClasses' => [$bad]])->validate(['skipClasses']));
    }
    sortsEq('paths: valid', true, $make(['skipUris' => ['/api/*', 'sitemap.xml', '/a/b?']])->validate());
    sortsCheck('paths: a space is refused', !$make(['skipUris' => ['/a b']])->validate(['skipUris']));
    sortsCheck('paths: a control character is refused', !$make(['skipUris' => ["/a\x01b"]])->validate(['skipUris']));
    sortsCheck('lists: more than 200 entries are refused', !$make(['skipTags' => array_map(fn ($i) => 'tag' . $i, range(1, 201))])->validate(['skipTags']));
    sortsCheck('lists: a very long entry is refused', !$make(['skipUris' => [str_repeat('a', 201)]])->validate(['skipUris']));
    sortsEq('variants: en-GB single-outer is valid', true, $make(['variants' => ['en-GB' => 'single-outer']])->validate());
    sortsCheck('variants: an unknown name is refused', !$make(['variants' => ['en-GB' => 'nope']])->validate(['variants']));
    sortsCheck('variants: a locale without variants is refused', !$make(['variants' => ['de' => 'single-outer']])->validate(['variants']));
    sortsCheck('variants: an unknown locale is refused', !$make(['variants' => ['xx' => 'single-outer']])->validate(['variants']));
    sortsEq('variants: de-CH guillemets and es guillemets-outer are valid', true, $make(['variants' => ['de-CH' => 'guillemets', 'es' => 'guillemets-outer']])->validate());
    sortsCheck('variants: a variant of another locale is refused', !$make(['variants' => ['es' => 'guillemets']])->validate(['variants']));
    sortsEq('variants: de low-high and nl double-outer are valid', true, $make(['variants' => ['de' => 'low-high', 'nl' => 'double-outer']])->validate());
    sortsEq('variants: en-US spaced-en-dash is valid', true, $make(['variants' => ['en-US' => 'spaced-en-dash']])->validate());
    sortsEq('variants: the retired de book name is refused (it is the default now)', false, $make(['variants' => ['de' => 'book']])->validate());
    sortsCheck('widows: 1 is refused', !$make(['widowsMinWords' => 1])->validate(['widowsMinWords']));
    sortsCheck('widows: 21 is refused', !$make(['widowsMinWords' => 21])->validate(['widowsMinWords']));
    sortsEq('widows: 2 to 20 are valid', true, $make(['widowsMinWords' => 2])->validate() && $make(['widowsMinWords' => 20])->validate());
    sortsEq('page size: defaults to 1,000,000 bytes', 1_000_000, $make([])->maxPageBytes);
    sortsEq('page size: a numeric string coerces', 2_500_000, $make(['maxPageBytes' => '2500000'])->maxPageBytes);
    sortsCheck('page size: 0 is refused', !$make(['maxPageBytes' => 0])->validate(['maxPageBytes']));
    sortsCheck('page size: a non-number is refused, not thrown', !$make(['maxPageBytes' => 'lots'])->validate(['maxPageBytes']));
    sortsCheck('saving invalid settings fails and stores nothing', !Craft::$app->getPlugins()->savePluginSettings(sorts(), ['skipTags' => ['Bad Tag']]));
    sortsConfigure('lite');
});

// Settings that arrive from project config or config/sorts.php are not validated by Craft (R2-9).
sortsTry('numbers from project config or config/sorts.php', function () {
    sortsConfigure('pro');
    $logged = sortsLogged(static fn () => sorts()->setSettings(['maxPageBytes' => '2MB', 'widowsMinWords' => 'lots']), ['warning']);
    sortsEq('loaded: a page size that does not validate falls back to the default', 1_000_000, sorts()->getSettings()->maxPageBytes);
    sortsEq('loaded: so does a widow count', 3, sorts()->getSettings()->widowsMinWords);
    $naming = static fn (string $value): int => count(array_filter($logged, static fn (string $m): bool => str_contains($m, $value)));
    sortsCheck('loaded: one warning for each, naming the value', $naming('"2MB"') === 1 && $naming('"lots"') === 1, json_encode($logged, JSON_UNESCAPED_UNICODE));
    sorts()->setSettings(['maxPageBytes' => '-5']);
    sortsEq('loaded: a negative page size falls back too', 1_000_000, sorts()->getSettings()->maxPageBytes);
    sorts()->setSettings(['maxPageBytes' => '2500000', 'widowsMinWords' => '4']);
    sortsEq('loaded: valid numbers are kept', [2_500_000, 4], [sorts()->getSettings()->maxPageBytes, sorts()->getSettings()->widowsMinWords]);
    $pc = sortsRefresh();
    $pc->set('plugins.sorts.settings.maxPageBytes', '2MB');
    $pc->saveModifiedConfigData();
    sortsCheck('a page size of "2MB" in project config leaves the page pass on', str_contains((string) sortsHttp()->get('/sorts-lab')->getBody(), '“Hello” — she said'));
    sortsConfigure('lite');
});

sortsTry('the settings page, Lite', function () {
    sortsConfigure('pro', ['skipTags' => ['figcaption'], 'skipUris' => ['/api/*'], 'rules' => ['fractions' => 'on', 'ranges' => 'off', 'typed-dashes' => 'off'], 'fixOnSave' => true, 'variants' => ['en-GB' => 'single-outer'], 'widowsMinWords' => 6, 'normalizeQuotes' => true, 'fineTypography' => true]);
    sortsConfigure('lite', array_merge((array) sorts()->getSettings()->getAttributes(), []));
    $cp = sortsCpClient();
    $html = (string) $cp->get('/admin/settings/plugins/sorts')->getBody();
    sortsCheck('lite page: Pro settings are visible', str_contains($html, 'Fix the typography of every page') && str_contains($html, 'Tags to skip'));
    sortsCheck('lite page: marked Pro', substr_count($html, 'Needs Sorts Pro.') >= 5 && str_contains($html, '<span class="code">Pro</span>'));
    sortsCheck('lite page: and locked', (bool) preg_match('/<textarea[^>]*name="settings\[skipTags\]"[^>]*\bdisabled\b/', $html) || (bool) preg_match('/<textarea[^>]*\bdisabled\b[^>]*name="settings\[skipTags\]"/', $html), '');
    sortsCheck('lite page: CKEditor setting is live', (bool) preg_match('/name="settings\[ckeditorAsYouType\]"/', $html) && !preg_match('/<button[^>]*id="settings-ckeditorAsYouType"[^>]*disabled/', $html));
    sortsCheck('lite page: every rule shows its state, locked', preg_match_all('/<select[^>]*name="settings\[rules\]\[[a-z-]+\]"[^>]*\bdisabled\b/', $html) >= 19 || preg_match_all('/<select[^>]*\bdisabled\b[^>]*name="settings\[rules\]\[[a-z-]+\]"/', $html) >= 19, (string) preg_match_all('/name="settings\[rules\]\[/', $html));
    sortsCheck('lite page: the normalize quotes switch is locked and marked Pro', (bool) preg_match('/<button[^>]*id="settings-normalizeQuotes"[^>]*\bdisabled\b/', $html) && (bool) preg_match('/id="settings-normalizeQuotes-warning"(?:(?!<\/p>).)*Needs Sorts Pro\./s', $html));

    // Save from Lite: the Pro values must survive.
    $fields = sortsFormFields($html, 'plugins/save-plugin-settings');
    $fields['settings[pluginName]'] = 'Typeset';
    $r = $cp->post('/admin/settings/plugins/sorts', ['form_params' => $fields]);
    sortsCheck('lite save: accepted', in_array($r->getStatusCode(), [200, 302], true), (string) $r->getStatusCode());
    $stored = sortsStored();
    sortsEq('lite save: the name changed', 'Typeset', $stored['pluginName'] ?? null);
    sortsEq('lite save: Pro skip tags survived', ['figcaption'], $stored['skipTags'] ?? null);
    sortsEq('lite save: Pro skip paths survived', ['/api/*'], $stored['skipUris'] ?? null);
    sortsEq('lite save: Pro rules survived, all three kinds (the veto of a preset rule too)', ['on', 'off', 'off', null], [$stored['rules']['fractions'] ?? null, $stored['rules']['ranges'] ?? null, $stored['rules']['typed-dashes'] ?? null, $stored['rules']['quotes'] ?? null]);
    sortsEq('lite save: fix-on-save survived', true, $stored['fixOnSave'] ?? null);
    sortsEq('lite save: variants survived', ['en-GB' => 'single-outer'], $stored['variants'] ?? null);
    sortsEq('lite save: the widow count survived', 6, $stored['widowsMinWords'] ?? null);
    sortsEq('lite save: normalize quotes survived', true, $stored['normalizeQuotes'] ?? null);
    sortsEq('lite save: fine typography survived', true, $stored['fineTypography'] ?? null);
});

sortsTry('the settings page, Pro, saving through the form', function () {
    sortsConfigure('pro', ['pluginName' => '']);
    $cp = sortsCpClient();
    $html = (string) $cp->get('/admin/settings/plugins/sorts')->getBody();
    sortsCheck('pro page: nothing is marked as needing Pro', !str_contains($html, 'Needs Sorts Pro.'));
    sortsCheck('pro page: every rule, the three preset-only ones too, has a three-way choice', substr_count($html, '<select id="settings-rules-') + substr_count($html, 'name="settings[rules][') >= 19 && preg_match_all('/<option value="follow"/', $html) >= 19 && str_contains($html, 'name="settings[rules][typed-dashes]"') && str_contains($html, 'name="settings[rules][dialogue-dash]"') && str_contains($html, 'name="settings[rules][ellipsis-spacing]"'), (string) substr_count($html, 'name="settings[rules]['));
    sortsCheck('pro page: each starts on follow the preset', substr_count($html, 'value="follow" selected') + substr_count($html, 'selected value="follow"') >= 19, (string) substr_count($html, 'selected'));
    sortsCheck('pro page: each rule says its default, and the six that Fine typography turns on say so', substr_count($html, 'Default: on.') === 9 && substr_count($html, 'Default: off.') === 10 && substr_count($html, 'Default: off. Fine typography turns it on.') === 6, substr_count($html, 'Default: on.') . ' on, ' . substr_count($html, 'Default: off.') . ' off, ' . substr_count($html, 'Default: off. Fine typography turns it on.') . ' turned on');
    sortsCheck('pro page: a rule on by default does not say Fine typography turns it on (locale spacing)', substr_count($html, 'Default: on. Fine typography turns it on.') === 0,(string) substr_count($html, 'Default: on. Fine typography turns it on.'));
    sortsCheck('pro page: the variants are offered', str_contains($html, 'single-outer') && str_contains($html, 'value="guillemets"') && str_contains($html, 'value="guillemets-outer"') && str_contains($html, 'value="low-high"') && str_contains($html, 'value="spaced-en-dash"') && str_contains($html, 'value="double-outer"'));
    sortsCheck('pro page: the fine typography switch is there, off, and names the book', (bool) preg_match('/name="settings\[fineTypography\]"/', $html) && !preg_match('/<button[^>]*id="settings-fineTypography"[^>]*aria-checked="true"/', $html) && str_contains($html, 'Fine typography (Bringhurst)') && str_contains($html, 'The Elements of Typographic Style, 3rd ed.'));
    sortsCheck('pro page: the normalize quotes switch is there and off', (bool) preg_match('/name="settings\[normalizeQuotes\]"/', $html) && !preg_match('/<button[^>]*id="settings-normalizeQuotes"[^>]*aria-checked="true"/', $html));
    $fields = sortsFormFields($html, 'plugins/save-plugin-settings');
    $fields['settings[skipTags]'] = "figcaption\nblockquote";
    $fields['settings[skipClasses]'] = 'no-typography';
    $fields['settings[skipUris]'] = "/api/*\n/feed.xml";
    $fields['settings[variants][en-GB]'] = 'single-outer';
    $fields['settings[rules][fractions]'] = 'on';
    $fields['settings[rules][quotes]'] = 'off';
    $fields['settings[rules][ranges]'] = 'off';
    $fields['settings[rules][typed-dashes]'] = 'off';
    $fields['settings[rules][ellipsis-spacing]'] = 'on';
    $fields['settings[rules][multiplication]'] = '1';
    $fields['settings[widowsMinWords]'] = '4';
    $fields['settings[fixOnSave]'] = '1';
    $fields['settings[processHead]'] = '1';
    $fields['settings[normalizeQuotes]'] = '1';
    $fields['settings[fineTypography]'] = '1';
    $r = $cp->post('/admin/settings/plugins/sorts', ['form_params' => $fields]);
    sortsCheck('pro save: accepted', in_array($r->getStatusCode(), [200, 302], true), (string) $r->getStatusCode());
    $stored = sortsStored();
    sortsEq('pro save: tags', ['figcaption', 'blockquote'], $stored['skipTags'] ?? null);
    sortsEq('pro save: classes', ['no-typography'], $stored['skipClasses'] ?? null);
    sortsEq('pro save: paths', ['/api/*', '/feed.xml'], $stored['skipUris'] ?? null);
    sortsEq('pro save: variant', ['en-GB' => 'single-outer'], $stored['variants'] ?? null);
    sortsEq('pro save: rules, a veto of a preset rule and a forced preset-only one among them', ['on', 'off', 'off', 'off', 'on', 'on', null], [$stored['rules']['fractions'] ?? null, $stored['rules']['quotes'] ?? null, $stored['rules']['ranges'] ?? null, $stored['rules']['typed-dashes'] ?? null, $stored['rules']['ellipsis-spacing'] ?? null, $stored['rules']['multiplication'] ?? null, $stored['rules']['dialogue-dash'] ?? null]);
    $yaml = (string) @file_get_contents(Craft::$app->getPath()->getProjectConfigPath() . '/project.yaml');
    sortsCheck('pro save: the states are in the project config YAML as words, and the veto is quoted, so a YAML 1.1 reader does not take it for a boolean', (bool) preg_match("/- typed-dashes\n\s+- ['\"]off['\"]\n/", $yaml) && (bool) preg_match('/- fractions\n\s+- [\'"]?on[\'"]?\n/', $yaml), (string) substr((string) strstr($yaml, 'rules:'), 0, 500));
    $html2 = (string) $cp->get('/admin/settings/plugins/sorts')->getBody();
    sortsCheck('pro save: the page shows the states chosen', substr_count($html2, 'value="off" selected') + substr_count($html2, 'selected value="off"') === 3 && substr_count($html2, 'value="on" selected') + substr_count($html2, 'selected value="on"') === 3, (string) substr_count($html2, 'selected'));
    sortsEq('pro save: widow count', 4, $stored['widowsMinWords'] ?? null);
    sortsEq('pro save: switches', [true, true, true, true], [$stored['fixOnSave'] ?? null, $stored['processHead'] ?? null, $stored['normalizeQuotes'] ?? null, $stored['fineTypography'] ?? null]);

    $fields['settings[skipTags]'] = 'Bad Tag';
    $r = $cp->post('/admin/settings/plugins/sorts', ['form_params' => $fields]);
    $stored = sortsStored();
    sortsEq('pro save: an invalid list is refused and the old value kept', ['figcaption', 'blockquote'], $stored['skipTags'] ?? null);
    sortsCheck('pro save: the page says why', str_contains((string) $r->getBody(), 'Bad Tag'), (string) $r->getStatusCode());

    // Emptying a list through the form works too (a multi-line box posts '').
    $fields['settings[skipTags]'] = '';
    $cp->post('/admin/settings/plugins/sorts', ['form_params' => $fields]);
    sortsEq('pro save: an emptied list is stored empty (or not at all, which reads the same)', [], sortsStored()['skipTags'] ?? []);
});

sortsTry('config/sorts.php overrides', function () {
    sortsConfigure('pro', ['pluginName' => '', 'fixOnSave' => false]);
    $file = '/var/www/html/config/sorts.php';
    file_put_contents($file, "<?php\nreturn ['*' => ['pagePass' => false, 'skipTags' => 'figcaption', 'pluginName' => 'From File']];\n");
    try {
        $http = sortsHttp();
        sortsCheck('override: the page pass is off by the file', str_contains((string) $http->get('/sorts-lab')->getBody(), '"Hello" -- she said'));
        $html = (string) sortsCpClient()->get('/admin/settings/plugins/sorts')->getBody();
        sortsCheck('override: the settings page says so', str_contains($html, 'This is being overridden by config/sorts.php.'));
        sortsCheck('override: and the plugin takes the name from the file', str_contains($html, 'From File'));
    } finally {
        unlink($file);
    }
    // The three states from the file too, a plain boolean beside them: dashes off by word, a stray true that is the default.
    file_put_contents($file, "<?php\nreturn ['*' => ['rules' => ['dashes' => 'off', 'quotes' => true, 'typed-dashes' => 'on']]];\n");
    try {
        sortsCheck('override: the file sets dashes off by word, and the page keeps its --', str_contains((string) sortsHttp()->get('/sorts-lab')->getBody(), '“Hello” -- she said'));
        $html = (string) sortsCpClient()->get('/admin/settings/plugins/sorts')->getBody();
        sortsCheck('override: the rule choices are locked and say so', str_contains($html, 'This is being overridden by config/sorts.php.') && (bool) preg_match('/<select[^>]*name="settings\[rules\]\[dashes\]"[^>]*\bdisabled\b/', $html) || (bool) preg_match('/<select[^>]*\bdisabled\b[^>]*name="settings\[rules\]\[dashes\]"/', $html));
    } finally {
        unlink($file);
    }
    sortsCheck('override removed: the page pass is back', str_contains((string) sortsHttp()->get('/sorts-lab')->getBody(), '“Hello” — she said'));
});

sortsDefaults();
sortsDone();
