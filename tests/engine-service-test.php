// ddev craft shell < tests/engine-service-test.php
// The engine component (`Plugin::getInstance()->engine`) in Lite and Pro: what each edition applies,
// the language from the site, settings, variants, and the edition gate on named rules.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

sortsTry('lite', function () {
    sortsConfigure('lite');
    $e = sorts()->engine;
    sortsEq('lite: English quotes, apostrophes, dashes, ellipsis', 'She said “hi” — it’s fine…', $e->apply('She said "hi" -- it\'s fine...'));
    sortsEq('lite: primes stay (they keep the quote scan honest)', '5′10″ and “quoted”', $e->apply('5\'10" and "quoted"'));
    sortsEq('lite: no symbols', '(c) 2026', $e->apply('(c) 2026'));
    sortsEq('lite: no no-break spaces', '10 kg', $e->apply('10 kg'));
    sortsEq('lite: no ranges', '1-5', $e->apply('1-5', ['ranges']));
    sortsEq('lite: a named Pro rule is skipped, not an error', '“a” 1/2', $e->apply('"a" 1/2', ['fractions', 'no-such-rule']));
    sortsEq('lite: a French site gets English quotes', '“Bonjour”', $e->apply('"Bonjour"', [], 'fr'));
    sortsEq('lite: data-sorts-lang cannot reach another locale', '<p data-sorts-lang="de">“x”</p>', $e->apply('<p data-sorts-lang="de">"x"</p>'));
    sortsEq('lite: en-GB is English', '“hi” – there', $e->apply('"hi" -- there', [], 'en-GB'));
    sortsEq('lite: text mode', 'say “hi” — ok', $e->apply('say "hi" -- ok', [], null, 'text'));
    sortsEq('lite: a typed single is the inner pair', 'the ‘best’ one', $e->apply('the \'best\' one'));
    sortsEq('lite: the normalizeQuotes setting is stored but never read', 'the ‘best’ one', (function () {
        sortsConfigure('lite', ['normalizeQuotes' => true]);
        return sorts()->engine->apply('the \'best\' one');
    })());
    sortsEq('lite: options asked for depth are set to typed', 'the ‘best’ one', sorts()->engine->applyWith('the \'best\' one', sorts()->engine->options()->with(quoteNesting: 'depth')));
    sortsConfigure('lite');
    $e = sorts()->engine;
    sortsEq('lite: a variant asked for in applyWith() is not applied', '“She said ‘no’”', $e->applyWith('"She said \'no\'"', $e->options('en-GB')->with(variants: ['en-GB' => 'single-outer'])));
    sortsEq('lite: applyWith() keeps Lite’s rules even when the options allow every rule', '“a” 1/2', $e->applyWith('"a" 1/2', $e->options()->with(rules: ['fractions' => true], allowed: null)));
    sortsEq('lite: the engine’s English has no variants, so engine()->apply() cannot reach one either', ['“She said ‘no’”', []], [
        $e->engine()->apply('"She said \'no\'"', [], new \iceboxind\sorts\engine\Options(locale: 'en-GB', variants: ['en-GB' => 'single-outer'])),
        $e->engine()->locales()->resolve('en-GB')->variants(),
    ]);
});

sortsTry('pro', function () {
    sortsConfigure('pro');
    $e = sorts()->engine;
    sortsEq('pro: symbols and no-break spaces', "© 2026 and 10\u{00A0}kg", $e->apply('(c) 2026 and 10 kg'));
    sortsEq('pro: French site, French quotes and spacing', "\u{00AB}\u{202F}Bonjour\u{202F}\u{00BB}", $e->apply('"Bonjour"', [], 'fr'));
    sortsEq('pro: German by language (book style by default, D844)', '»Hallo«', $e->apply('"Hallo"', [], 'de'));
    sortsEq('pro: data-sorts-lang switches locale', '<p data-sorts-lang="de">»x«</p>', $e->apply('<p data-sorts-lang="de">"x"</p>'));
    sortsEq('pro: an unknown handle is an error', 'No rule is registered with the handle “nope”.', (function () use ($e) {
        try {
            $e->apply('x', ['nope']);
        } catch (\InvalidArgumentException $ex) {
            return $ex->getMessage();
        }
        return 'no exception';
    })());

    sortsConfigure('pro', ['rules' => ['fractions' => true, 'ranges' => true, 'quotes' => false]]);
    $e = sorts()->engine;
    sortsEq('pro: rule toggles from settings', 'a "b" ½ 1–5', $e->apply('a "b" 1/2 1-5'));

    sortsConfigure('pro');
    sortsEq('pro: a typed single is the inner pair by default', 'the ‘best’ one', sorts()->engine->apply('the \'best\' one'));
    sortsConfigure('pro', ['normalizeQuotes' => true]);
    sortsEq('pro: normalizeQuotes sets it by depth', 'the “best” one', sorts()->engine->apply('the \'best\' one'));
    sortsEq('pro: normalizeQuotes reaches the stored options', 'the “best” one', sorts()->engine->applyStored('the \'best\' one', sorts()->engine->storedOptions('en-US', 'text')));
    sortsConfigure('pro');
    sortsEq('pro: stored options keep the typed single by default', 'the ‘best’ one', sorts()->engine->applyStored('the \'best\' one', sorts()->engine->storedOptions('en-US', 'text')));

    sortsConfigure('pro', ['variants' => ['en-GB' => 'single-outer', 'de-CH' => 'guillemets', 'es' => 'guillemets-outer']]);
    $e = sorts()->engine;
    sortsEq('pro: en-GB single-outer variant', '‘She said “no”’', $e->apply('"She said \'no\'"', [], 'en-GB'));
    sortsEq('pro: de-CH guillemets variant', '«Er sagte ‹nein›»', $e->apply('"Er sagte \'nein\'"', [], 'de-CH'));
    sortsEq('pro: es guillemets-outer variant', '«Dijo “no”»', $e->apply('"Dijo \'no\'"', [], 'es'));
    sortsEq('pro: the variant leaves en-US alone', '“She said ‘no’”', $e->apply('"She said \'no\'"', [], 'en-US'));
    sortsEq('pro: a quotation inside one of the same kind takes the other level', '“She said ‘no’ today”', $e->apply('"She said "no" today"', [], 'en-US'));

    sortsConfigure('pro');
    sortsEq('pro: de is book style by default (D844)', '»Er sagte ›nein‹«', sorts()->engine->apply('"Er sagte \'nein\'"', [], 'de'));
    sortsEq('pro: de-AT follows de', '»Er sagte ›nein‹«', sorts()->engine->apply('"Er sagte \'nein\'"', [], 'de-AT'));
    sortsConfigure('pro', ['variants' => ['de' => 'low-high', 'nl' => 'double-outer']]);
    $e = sorts()->engine;
    sortsEq('pro: de low-high variant (CLDR’s marks)', '„Er sagte ‚nein‘“', $e->apply('"Er sagte \'nein\'"', [], 'de'));
    sortsEq('pro: de-CH keeps its own default under a de variant', '„Er sagte ‚nein‘“', $e->apply('"Er sagte \'nein\'"', [], 'de-CH'));
    sortsEq('pro: nl double-outer variant', '“Hij zei ‘nee’”', $e->apply('"Hij zei \'nee\'"', [], 'nl'));
    sortsEq('pro: the de and nl variants leave en-US alone', '“She said ‘no’”', $e->apply('"She said \'no\'"', [], 'en-US'));

    sortsConfigure('pro', ['widowsMinWords' => 5]);
    sortsEq('pro: widows minimum word count from settings', '<h2>Four words in here</h2>', sorts()->engine->apply('<h2>Four words in here</h2>'));
    sortsConfigure('pro', ['widowsMinWords' => 3]);
    sortsEq('pro: widows joins at three words', "<h2>Three words\u{00A0}here</h2>", sorts()->engine->apply('<h2>Three words here</h2>'));

    sortsConfigure('pro', ['skipTags' => ['figcaption'], 'skipClasses' => ['raw']]);
    sortsEq('pro: skip tags and classes from settings', '<figcaption>"x"</figcaption><p class="raw">"y"</p><p>“z”</p>', sorts()->engine->apply('<figcaption>"x"</figcaption><p class="raw">"y"</p><p>"z"</p>'));
});

sortsTry('stored options', function () {
    sortsConfigure('pro', ['rules' => ['fractions' => true]]);
    $svc = sorts()->engine;
    $o = $svc->storedOptions('en-US', 'text');
    sortsEq('stored: only character rules run', "© ½ “a” and 10 kg", $svc->applyWith('(c) 1/2 "a" and 10 kg', $o));
    sortsEq('stored: spacing is left to the page pass', 'Bonjour : «x»', $svc->applyWith('Bonjour : "x"', $svc->storedOptions('fr', 'text')));
});

sortsTry('fine typography, the Bringhurst preset (D817)', function () {
    $page = '8 1/2 x 11 -- a—b and pages 10-12';
    sortsConfigure('pro');
    sortsEq('fine: off by default, the page is as it always was', '8 1/2 x 11 — a—b and pages 10-12', sorts()->engine->apply($page));
    sortsConfigure('pro', ['fineTypography' => true]);
    sortsEq('fine: on, a spaced en dash, a typed em dash restyled, fractions, multiplication and ranges', '8½ × 11 – a – b and pages 10–12', sorts()->engine->apply($page));
    sortsEq('fine: dialogue opens with an em dash and a narrow no-break space', "—\u{202F}So it ends.", sorts()->engine->apply('-- So it ends.'));
    sortsEq('fine: thin spaces inside guillemets, but not in German', ["«\u{202F}Sì\u{202F}»", '»Ja«'], [sorts()->engine->apply('«Sì»', [], 'it'), sorts()->engine->apply('»Ja«', [], 'de')]);
    sortsEq('fine: an ellipsis between words in English, not in French', ['wait … then', 'attends…puis'], [sorts()->engine->apply('wait...then'), sorts()->engine->apply('attends...puis', [], 'fr')]);
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['dashes' => false, 'multiplication' => false]]);
    sortsEq('fine: a switch moved off its default wins (dashes off, and the typed em dash with it, F7); one switched off that is off by default is not moved, so the preset decides it', '8½ × 11 -- a—b and pages 10–12', sorts()->engine->apply($page));
    sortsEq('fine, stored: dashes off leaves a typed em dash as typed (F7)', 'a—b and c — d', sorts()->engine->applyStored('a—b and c — d', sorts()->engine->storedOptions('en-US', 'text')));
    sortsConfigure('pro', ['fineTypography' => true]);
    sortsEq('fine: a variant chosen for the language wins (an unknown name, which settings refuse, is CLDR’s default)', '8½ × 11 — a—b and pages 10–12', sorts()->engine->applyWith($page, sorts()->engine->options()->with(variants: ['en-US' => 'nonsense'])));
    sortsConfigure('lite', ['fineTypography' => true]);
    sortsEq('lite: the setting is stored but never read', '8 1/2 x 11 — a—b and pages 10-12', sorts()->engine->apply($page));
    sortsEq('lite: options asked for the preset are set to none', '8 1/2 x 11 — a—b and pages 10-12', sorts()->engine->applyWith($page, sorts()->engine->options()->with(preset: 'bringhurst')));
    sortsConfigure('pro', ['fineTypography' => true]);
    $svc = sorts()->engine;
    $o = $svc->storedOptions('en-US', 'text');
    sortsEq('fine, stored: characters only (a typed em dash, fractions, ranges), no spacing', 'a – b and 8½ and 4:30–5:00 wait…then', $svc->applyStored('a—b and 8 1/2 and 4:30-5:00 wait...then', $o));
    sortsEq('fine, stored: a dialogue line opens with the em dash and its space (D897), and no guillemet spaces', ['— So.', '«Sì»'], [$svc->applyStored('-- So.', $svc->storedOptions('en-US', 'text')), $svc->applyStored('«Sì»', $svc->storedOptions('it', 'text'))]);
    $dialogue = $svc->applyStored('<p>-- So this is it.</p>', $svc->storedOptions('en-GB', 'html'));
    sortsEq('fine, stored twice: the dialogue line is stored once and then left alone, in a spaced-en-dash language too (D897)', ['<p>— So this is it.</p>', '<p>— So this is it.</p>'], [$dialogue, $svc->applyStored($dialogue, $svc->storedOptions('en-GB', 'html'))]);
    sortsEq('fine, stored then the page: the dialogue dash, as for text never stored (F6)', ["<p>—\u{202F}So this is it.</p>", "<p>—\u{202F}So this is it.</p>"], [$svc->apply($svc->applyStored('<p>-- So this is it.</p>', $svc->storedOptions('en-US', 'html'))), $svc->apply('<p>-- So this is it.</p>')]);
    $stored = $svc->applyStored('"Yes" - she said', $o);
    sortsEq('fine, stored: one pass gives what two would (F1)', ['“Yes” – she said', '“Yes” – she said'], [$stored, $svc->applyStored($stored, $o)]);
    sortsEq('fine, stored: the options a queued job carries keep the preset', 'bringhurst', (new \iceboxind\sorts\engine\Options(...get_object_vars($svc->storedOptions('en-US', 'html'))))->preset);
});

// D864: every rule is on, off or follows the preset; the preset-only ones have a switch of their own.
sortsTry('rule states: on, off, follow the preset (D864)', function () {
    $page = 'pages 10-12 and 8 1/2 x 11 and a—b and wait…then';
    $apply = static fn (string $text): string => sorts()->engine->apply($text);
    sortsConfigure('pro', ['fineTypography' => true]);
    sortsEq('follow: the preset decides every rule', 'pages 10–12 and 8½ × 11 and a – b and wait … then', $apply($page));
    sortsEq('follow: the engine is handed no switch at all', [], sorts()->engine->options()->rules);

    foreach ([
        'ranges' => 'pages 10-12 and 8½ × 11 and a – b and wait … then',
        'fractions' => 'pages 10–12 and 8 1/2 × 11 and a – b and wait … then',
        'multiplication' => 'pages 10–12 and 8½ x 11 and a – b and wait … then',
        'typed-dashes' => 'pages 10–12 and 8½ × 11 and a—b and wait … then',
        'ellipsis-spacing' => 'pages 10–12 and 8½ × 11 and a – b and wait…then',
    ] as $handle => $expected) {
        sortsConfigure('pro', ['fineTypography' => true, 'rules' => [$handle => 'off']]);
        sortsEq("off vetoes {$handle} under the preset and leaves the rest of the preset running", $expected, $apply($page));
        sortsEq("off hands the engine {$handle} => false, and nothing else", [$handle => false], sorts()->engine->options()->rules);
    }
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['dialogue-dash' => 'off']]);
    sortsEq('off vetoes dialogue-dash under the preset: the dashes rule sets the dash as it does anywhere', '<p>– So it ends.</p>', $apply('<p>-- So it ends.</p>'));
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['dialogue-dash' => 'follow', 'typed-dashes' => 'on']]);
    sortsEq('on under the preset is following it', ["<p>—\u{202F}So it ends.</p>", 'a – b'], [$apply('<p>-- So it ends.</p>'), $apply('a—b')]);

    sortsConfigure('pro');
    sortsEq('follow with no preset: the three preset-only rules are off', '<p>— So it ends.</p> a—b wait…then', $apply('<p>-- So it ends.</p> a—b wait…then'));
    sortsConfigure('pro', ['variants' => ['en-US' => 'spaced-en-dash'], 'rules' => ['typed-dashes' => 'on', 'dialogue-dash' => 'on', 'ellipsis-spacing' => 'on']]);
    sortsEq('on with no preset turns on a rule the preset would have: typed dashes, the dialogue dash, ellipsis spacing', ['a – b', "<p>—\u{202F}So it ends.</p>", 'wait … then'], [$apply('a—b'), $apply('<p>-- So it ends.</p>'), $apply('wait…then')]);
    sortsConfigure('pro', ['rules' => ['ranges' => 'on', 'quotes' => 'off']]);
    sortsEq('on turns on a rule that is off by default; off turns off one that is on', 'a "b" 1–5', $apply('a "b" 1-5'));
    sortsConfigure('lite', ['fineTypography' => true, 'rules' => ['typed-dashes' => 'on', 'ranges' => 'on']]);
    sortsEq('lite: the states are stored and never read', 'a—b 1-5', $apply('a—b 1-5'));
});

// D864 and F7: Em and en dashes off takes the preset’s two dash rules with it, wherever they follow.
sortsTry('rule states: dashes off and the dash rules that follow (D864)', function () {
    $apply = static fn (string $text): string => sorts()->engine->apply($text);
    $text = '<p>-- So it ends.</p><p>a—b and c--d</p>';
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['dashes' => 'off']]);
    sortsEq('dashes off, the rest follow: no dash is touched, a dialogue line included', $text, $apply($text));
    sortsEq('dashes off, the rest follow: the engine is handed typed-dashes and dialogue-dash as off', ['dashes' => false, 'typed-dashes' => false, 'dialogue-dash' => false], sorts()->engine->options()->rules);
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['dashes' => 'off', 'dialogue-dash' => 'on']]);
    sortsEq('dashes off, dialogue-dash on: the site named it, so the dialogue dash is set', "<p>—\u{202F}So it ends.</p><p>a—b and c--d</p>", $apply($text));
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['dashes' => 'off', 'typed-dashes' => 'on']]);
    sortsEq('dashes off, typed-dashes on: the site named it, so the typed em dash is set and -- is not', '<p>-- So it ends.</p><p>a – b and c--d</p>', $apply($text));
    $svc = sorts()->engine;
    sortsEq('dashes off, typed-dashes on: stored content gets the same', '<p>-- So it ends.</p><p>a – b and c--d</p>', $svc->applyStored($text, $svc->storedOptions('en-US', 'html')));
    sortsConfigure('pro', ['rules' => ['dashes' => 'off']]);
    sortsEq('dashes off with no preset: nothing to take off, and the options say so', ['dashes' => false], sorts()->engine->options()->rules);
    sortsConfigure('pro', ['fineTypography' => true, 'rules' => ['dashes' => 'on']]);
    sortsEq('dashes on is following: the preset decides', "<p>—\u{202F}So it ends.</p><p>a – b and c – d</p>", $apply($text));
});

sortsDefaults();
sortsDone();
