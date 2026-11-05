// ddev craft shell < tests/twig-test.php
// The |sorts Twig filter in Lite and Pro: HTML and text modes, the escaping guarantee,
// {% apply sorts %}, a rich-text field value, and rules by name.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$render = static fn (string $template, array $vars = []): string => Craft::$app->getView()->renderString($template, $vars, \craft\web\View::TEMPLATE_MODE_SITE, true);

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

sortsTry('lite filter limits', function () use ($render) {
    sortsConfigure('lite');
    sortsEq('lite: no symbols', '(c) “x”', $render('{{ s|sorts }}', ['s' => '(c) "x"']));
    sortsEq('lite: a named rule is skipped', 'x', $render('{{ s|sorts(rules: [\'recipe-fractions\']) }}', ['s' => 'x']));
});

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
