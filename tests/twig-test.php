// ddev craft shell < tests/twig-test.php
// The |sorts Twig filter in Lite and Pro: HTML and text modes, the escaping guarantee,
// {% apply sorts %}, a rich-text field value, and rules by name.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$render = static fn (string $template, array $vars = []): string => Craft::$app->getView()->renderString($template, $vars, \craft\web\View::TEMPLATE_MODE_SITE, true);

// Twig reuses a template compiled before RawInputVisitor changed (README, Known limits): start clean.
\craft\helpers\FileHelper::clearDirectory(Craft::$app->getPath()->getCompiledTemplatesPath(false));

foreach (['lite', 'pro'] as $edition) {
    sortsTry("filter ($edition)", function () use ($render, $edition) {
        sortsConfigure($edition);
        sortsEq("$edition: html mode", 'She said “hi” — it’s fine…', $render('{{ s|sorts }}', ['s' => 'She said "hi" -- it\'s fine...']));
        sortsEq("$edition: XSS, a plain string is escaped", '&lt;script&gt;alert(1)&lt;/script&gt;', $render('{{ \'<script>alert(1)</script>\'|sorts }}'));
        sortsEq("$edition: XSS, a variable is escaped", '&lt;img src=x onerror=alert(1)&gt; “a”', $render('{{ s|sorts }}', ['s' => '<img src=x onerror=alert(1)> "a"']));
        sortsEq("$edition: XSS, an escaped tag is text, typeset as text", '&lt;a href=“x”&gt;', $render('{{ s|sorts }}', ['s' => '<a href="x">']));
        sortsEq("$edition: XSS, no attribute breakout", '<p title="x” onmouseover=“alert(1)">', $render('<p title="{{ s|sorts }}">', ['s' => 'x" onmouseover="alert(1)']));
        sortsEq("$edition: |raw input is read as HTML", '<b>“bold”</b> — raw', $render('{{ (s|raw)|sorts }}', ['s' => '<b>"bold"</b> -- raw']));
        sortsEq("$edition: a literal in the template is not trusted either", '&lt;b&gt;“x”&lt;/b&gt;', $render('{{ \'<b>"x"</b>\'|sorts }}'));
        sortsEq("$edition: |raw further up the chain is not trusted", '&lt;b&gt;“x”&lt;/b&gt;', $render('{{ (s|raw|upper|lower)|sorts }}', ['s' => '<b>"x"</b>']));
        sortsEq("$edition: a Markup value is trusted HTML", '<i>“x”</i>', $render('{{ m|sorts }}', ['m' => new \Twig\Markup('<i>"x"</i>', 'UTF-8')]));
        sortsEq("$edition: {% apply sorts %}", '<i>“inside”</i> — apply', $render('{% apply sorts %}<i>"inside"</i> -- apply{% endapply %}'));
        sortsEq("$edition: {% apply sorts %} still escapes what was interpolated", '<i>“a”</i> &lt;b&gt;', $render('{% apply sorts %}<i>"a"</i> {{ s }}{% endapply %}', ['s' => '<b>']));
        sortsEq("$edition: text mode is not marked safe, so it is escaped when printed", 'a “b” — &lt;c&gt;', $render('{{ s|sorts(\'text\') }}', ['s' => 'a "b" -- <c>']));
        sortsEq("$edition: text mode keeps an ampersand once-escaped", 'Tom &amp; “Jerry”', $render('{{ s|sorts(\'text\') }}', ['s' => 'Tom & "Jerry"']));
        sortsEq("$edition: text mode by name, with a variable mode", 'a “b” — &lt;c&gt;', $render('{{ s|sorts(mode: \'text\') }}', ['s' => 'a "b" -- <c>']));
        sortsEq("$edition: text mode in an attribute", '<p title="a “b”">', $render('<p title="{{ s|sorts(\'text\') }}">', ['s' => 'a "b"']));
        sortsEq("$edition: null passes through", '', $render('{{ s|sorts }}', ['s' => null]));
        sortsEq("$edition: an empty string passes through", '', $render('{{ s|sorts }}', ['s' => '']));
        sortsEq("$edition: a number prints", '42', $render('{{ n|sorts }}', ['n' => 42]));
        sortsEq("$edition: idempotent", '“hi” — there', $render('{{ s|sorts|sorts }}', ['s' => '"hi" -- there']));
        sortsEq("$edition: a bad mode is an error", 'The sorts filter’s mode must be “html” or “text”, not “xml”.', (function () use ($render) {
            try {
                $render('{{ s|sorts(\'xml\') }}', ['s' => 'x']);
            } catch (\Throwable $e) {
                return ($e->getPrevious() ?? $e)->getMessage();
            }
            return 'no exception';
        })());
    });
}

foreach (['lite', 'pro'] as $edition) {
    sortsTry("filter, safe-HTML filters ($edition)", function () use ($render, $edition) {
        sortsConfigure($edition);
        sortsEq("$edition: |nl2br output is read as HTML (escaped once, not twice)", "Line “one”<br />\n&lt;script&gt;alert(“x”)&lt;/script&gt;", $render('{{ s|nl2br|sorts }}', ['s' => "Line \"one\"\n<script>alert(\"x\")</script>"]));
        sortsEq("$edition: |e output is read as HTML (escaped once, not twice)", '&lt;script&gt;“x”&lt;/script&gt; Tom &amp; “Jerry”', $render('{{ s|e|sorts }}', ['s' => '<script>"x"</script> Tom & "Jerry"']));
        sortsEq("$edition: |escape('html') too", '&lt;b&gt;“x”&lt;/b&gt;', $render('{{ s|escape(\'html\')|sorts }}', ['s' => '<b>"x"</b>']));
        // Craft's md filter passes raw HTML through and is declared safe, so Twig prints {{ s|md }} as it is; sorts reads it the same way.
        sortsEq("$edition: |md output is read as HTML, exactly as Twig prints |md", str_replace('"x"', '“x”', $render('{{ s|md }}', ['s' => '*"x"* <i>y</i>'])), $render('{{ s|md|sorts }}', ['s' => '*"x"* <i>y</i>']));
        sortsEq("$edition: |upper is not safe, so its output is escaped", '&lt;B&gt;“X”&lt;/B&gt;', $render('{{ s|upper|sorts }}', ['s' => '<b>"x"</b>']));
        sortsEq("$edition: |nl2br further up the chain is not trusted (escaped again, as Twig would)", "&amp;LT;B&amp;GT;&amp;QUOT;X&amp;QUOT;&amp;LT;/B&amp;GT;&lt;BR /&gt;\n", $render('{{ s|nl2br|upper|sorts }}', ['s' => "<b>\"x\"</b>\n"]));
    });
}

sortsTry('lite filter limits', function () use ($render) {
    sortsConfigure('lite');
    sortsEq('lite: no symbols', '(c) “x”', $render('{{ s|sorts }}', ['s' => '(c) "x"']));
    sortsEq('lite: a named rule is skipped', 'x', $render('{{ s|sorts(rules: [\'recipe-fractions\']) }}', ['s' => 'x']));
});

// F1: a data-sorts-rules scope (or a rule named in a call) never runs a rule Lite does not have.
$scoped = '{% apply sorts %}<p data-sorts-rules="fractions ranges multiplication ordinals hook-amp hook-caps symbols nbsp widows">1/2 cup, pages 1-5, 3x4, 1st place, A&amp;B NASA (c) 10 kg "x" -- y</p>{% endapply %}';
sortsTry('lite: scopes cannot add rules', function () use ($render, $scoped) {
    sortsConfigure('lite');
    sortsEq('lite: an apply block with data-sorts-rules gets only Lite’s rules', '<p data-sorts-rules="fractions ranges multiplication ordinals hook-amp hook-caps symbols nbsp widows">1/2 cup, pages 1-5, 3x4, 1st place, A&amp;B NASA (c) 10 kg “x” — y</p>', $render($scoped));
    sortsEq('lite: |raw|sorts with data-sorts-rules', '<span data-sorts-rules="fractions symbols">1/2 (c) “x”</span>', $render('{{ s|raw|sorts }}', ['s' => '<span data-sorts-rules="fractions symbols">1/2 (c) "x"</span>']));
    sortsEq('lite: the PHP call with data-sorts-rules', '<p data-sorts-rules="fractions">1/2 “x”</p>', sorts()->engine->apply('<p data-sorts-rules="fractions">1/2 "x"</p>'));
    sortsEq('lite: the engine itself, called directly', '<p data-sorts-rules="fractions">1/2</p>', sorts()->engine->engine()->apply('<p data-sorts-rules="fractions">1/2</p>'));
    sortsEq('lite: a built-in rule named in the call is skipped', '1/2 (c)', sorts()->engine->apply('1/2 (c)', ['fractions', 'symbols']));
});
sortsTry('pro: scopes add rules', function () use ($render, $scoped) {
    sortsConfigure('pro');
    sortsEq('pro: the same apply block converts', "<p data-sorts-rules=\"fractions ranges multiplication ordinals hook-amp hook-caps symbols nbsp widows\">½ cup, pages 1–5, 3×4, 1<sup>st</sup> place, A<span class=\"amp\">&amp;</span>B <span class=\"caps\">NASA</span> © 10\u{00A0}kg “x” — y</p>", $render($scoped));
});

// FU8: the opt-out and the language override are safety controls, so they work in every edition.
foreach (['lite', 'pro'] as $edition) {
    sortsTry("data-sorts attributes ($edition)", function () use ($render, $edition) {
        sortsConfigure($edition);
        sortsEq("$edition: data-sorts=\"off\" leaves the element alone", '<p data-sorts="off">"x" -- it\'s</p><p>“y”</p>', $render('{% apply sorts %}<p data-sorts="off">"x" -- it\'s</p><p>"y"</p>{% endapply %}'));
        sortsEq("$edition: data-sorts-lang picks the locale (en-GB: spaced en dash)", '<p data-sorts-lang="en-GB">a – b</p><p>a — b</p>', $render('{% apply sorts %}<p data-sorts-lang="en-GB">a -- b</p><p>a -- b</p>{% endapply %}'));
    });
}

sortsTry('pro filter', function () use ($render) {
    sortsConfigure('pro');
    sortsEq('pro: all rules', "© “x” 10\u{00A0}kg", $render('{{ s|sorts }}', ['s' => '(c) "x" 10 kg']));
    sortsEq('pro: rules by name', 'Add ⅞ cup and 1½ cups', $render('{{ s|sorts(rules: [\'recipe-fractions\']) }}', ['s' => 'Add 0.875 cup and 1.5 cups']));
    sortsEq('pro: rules by name, text mode', '½ cup of “x”', $render('{{ s|sorts(\'text\', [\'recipe-fractions\']) }}', ['s' => '.5 cup of "x"']));
    sortsEq('pro: not scoped means untouched', 'Add 0.875 cup', $render('{{ s|sorts }}', ['s' => 'Add 0.875 cup']));
    sortsEq('pro: an unknown rule is an error', 'No rule is registered with the handle “nope”.', (function () use ($render) {
        try {
            $render('{{ s|sorts(rules: [\'nope\']) }}', ['s' => 'x']);
        } catch (\Throwable $e) {
            return ($e->getPrevious() ?? $e)->getMessage();
        }
        return 'no exception';
    })());
});

sortsTry('rich-text field value', function () use ($render) {
    sortsConfigure('pro');
    sortsClean();
    $entry = sortsEntry(['title' => 'Filter', 'sortsBody' => '<p>"Quoted" -- <b>bold</b> text</p>']);
    $fresh = sortsFresh($entry->id);
    sortsEq('a CKEditor field value is trusted HTML, not double-escaped', '<p>“Quoted” — <b>bold</b> text</p>', $render('{{ entry.sortsBody|sorts }}', ['entry' => $fresh]));
    sortsClean();
});

sortsDefaults();
sortsDone();
