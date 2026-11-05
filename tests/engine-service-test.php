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
});

sortsTry('pro', function () {
    sortsConfigure('pro');
    $e = sorts()->engine;
    sortsEq('pro: symbols and no-break spaces', "© 2026 and 10\u{00A0}kg", $e->apply('(c) 2026 and 10 kg'));
    sortsEq('pro: French site, French quotes and spacing', "\u{00AB}\u{202F}Bonjour\u{202F}\u{00BB}", $e->apply('"Bonjour"', [], 'fr'));
    sortsEq('pro: German by language', '„Hallo“', $e->apply('"Hallo"', [], 'de'));
    sortsEq('pro: data-sorts-lang switches locale', '<p data-sorts-lang="de">„x“</p>', $e->apply('<p data-sorts-lang="de">"x"</p>'));
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

    sortsConfigure('pro', ['variants' => ['en-GB' => 'single-outer']]);
    $e = sorts()->engine;
    sortsEq('pro: en-GB single-outer variant', '‘She said “no”’', $e->apply('"She said \'no\'"', [], 'en-GB'));
    sortsEq('pro: the variant leaves en-US alone', '“She said ‘no’”', $e->apply('"She said \'no\'"', [], 'en-US'));

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

sortsDefaults();
sortsDone();
