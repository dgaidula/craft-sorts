<?php

/**
 * Unit tests: the Run edit API and settled characters, the engine API, the
 * locale registry, rule scopes, what a rule sees in its Context, and the
 * tokenizer’s promise that markup comes out byte for byte.
 */

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\DataLocale;
use iceboxind\sorts\engine\Engine;
use iceboxind\sorts\engine\LocaleRegistry;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\engine\Run;
use iceboxind\sorts\engine\rules\AbstractRule;
use iceboxind\sorts\tests\engine\RecipeFractionsRule;

function throws(string $name, string $class, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($name, $class, get_class($e));
        return;
    }
    fail($name, "  expected $class, nothing was thrown");
}

// ---- Run: edits and settled characters --------------------------------------

$run = new Run('abcdef');
check('run: replace returns true', true, $run->replace(1, 2, 'XYZ'));
check('run: replace changes text', 'aXYZdef', $run->text());
check('run: replacement is settled', true, $run->isSettled(1) && $run->isSettled(3));
check('run: text around is not settled', false, $run->isSettled(0) || $run->isSettled(4));
check('run: edit touching settled text is refused', false, $run->replace(2, 1, 'Q'));
check('run: refused edit leaves text', 'aXYZdef', $run->text());
check('run: edit just before settled text is allowed', true, $run->replace(0, 1, 'AA'));
check('run: text after edit before settled span', 'AAXYZdef', $run->text());
check('run: settled spans shifted by an earlier edit, and the new text settled', [true, true, true, true, true, false], array_map([$run, 'isSettled'], [0, 1, 2, 3, 4, 5]));
check('run: edit after settled text is allowed', true, $run->replace(7, 1, 'FFF'));
check('run: settled spans kept after a later edit', [true, false, true], [$run->isSettled(4), $run->isSettled(5), $run->isSettled(7)]);
check('run: text after later edit', 'AAXYZdeFFF', $run->text());

$run = new Run('abc');
$run->replace(1, 1, 'Q', false);
check('run: replace with settle=false is not settled', false, $run->isSettled(1));
check('run: unsettled text can be edited again', true, $run->replace(1, 1, 'R'));

$run = new Run('abcdef');
check('run: edit() applies several', 2, $run->edit([[0, 1, 'X'], [4, 1, 'Y']]));
check('run: edit() result', 'XbcdYf', $run->text());
check('run: edit() skips only the refused edit', 1, $run->edit([[0, 1, 'Z'], [2, 1, 'W']]));
check('run: edit() mixed result', 'XbWdYf', $run->text());

$run = new Run('abcdef');
$run->replace(2, 2, 'LONGER');
check('run: insertion at the start of a settled span is allowed', true, $run->replace(2, 0, '<'));
check('run: insertion at the end of a settled span is allowed', true, $run->replace(9, 0, '>'));
check('run: insertion inside a settled span is refused', false, $run->replace(5, 0, '!'));
check('run: text after insertions', 'ab<LONGER>ef', $run->text());

$run = new Run('1-1/2 and 3-4');
$count = $run->replaceAll('~1/2~', static fn (): string => '½');
check('run: replaceAll counts', 1, $count);
$count = $run->replaceAll('~(\d)-(\S)~u', static fn (array $m): string => $m[1] . '–' . $m[2]);
check('run: replaceAll skips a match over settled text, keeps the other', '1-½ and 3–4', $run->text());
check('run: replaceAll count excludes the skipped', 1, $count);

$run = new Run('keep');
check('run: replaceAll with no match', 0, $run->replaceAll('~zzz~', static fn (): string => 'x'));
check('run: replaceAll callback returning null leaves the match', 0, $run->replaceAll('~e~', static fn (): ?string => null));
check('run: text unchanged', 'keep', $run->text());

$run = new Run('é-é');
$run->replaceAll('~-~u', static fn (): string => '–');
check('run: multibyte offsets', 'é–é', $run->text());

$run = new Run('a');
$run->replace(0, 1, "\u{E000}x");
check('run: text holds only what rules wrote (no markers)', "\u{E000}x", $run->text());

// ---- Engine API --------------------------------------------------------------

$engine = newEngine();
$handles = array_map(static fn ($r) => $r->handle(), $engine->rules());
check('engine: nineteen built-in rules plus four fixtures', 23, count($handles));
check('engine: built-in handles', [
    'symbols', 'ellipsis', 'ellipsis-spacing', 'dialogue-dash', 'dashes', 'typed-dashes', 'fractions', 'ranges', 'multiplication',
    'primes', 'apostrophes', 'quotes', 'nbsp', 'locale-spacing', 'widows', 'ordinals', 'hook-amp', 'hook-caps', 'hook-quotes',
], array_slice(array_values(array_diff($handles, ['recipe-fractions', 'fixture-en-dash', 'fixture-markup', 'fixture-german'])), 0, 19));
$priorities = array_map(static fn ($r) => $r->priority(), $engine->rules());
$sorted = $priorities;
sort($sorted);
check('engine: rules() is in priority order', $sorted, $priorities);

throws('engine: duplicate handle is refused', InvalidArgumentException::class, static fn () => $engine->register(new RecipeFractionsRule()));
$bad = new class extends AbstractRule {
    public function handle(): string
    {
        return 'Bad Handle';
    }
    public function priority(): int
    {
        return 1;
    }
    public function defaultEnabled(): bool
    {
        return false;
    }
    public function apply(Run $run, Context $context): void
    {
    }
};
throws('engine: handle with capitals and spaces is refused', InvalidArgumentException::class, static fn () => $engine->register($bad));
throws('engine: naming an unknown rule is refused', InvalidArgumentException::class, static fn () => $engine->apply('x', ['nope']));

check('engine: empty string comes back', '', $engine->apply(''));
$latin1 = "caf\xE9 \"x\"";
check('engine: invalid UTF-8 comes back untouched', $latin1, $engine->apply($latin1));
check('engine: invalid UTF-8 in text mode comes back untouched', $latin1, $engine->apply($latin1, [], new Options(mode: 'text')));

// equal priorities keep registration order
$trace = [];
$makeRule = static function (string $handle, int $priority) use (&$trace): AbstractRule {
    return new class ($handle, $priority, $trace) extends AbstractRule {
        public function __construct(private string $h, private int $p, private array &$trace)
        {
        }
        public function handle(): string
        {
            return $this->h;
        }
        public function priority(): int
        {
            return $this->p;
        }
        public function defaultEnabled(): bool
        {
            return true;
        }
        public function apply(Run $run, Context $context): void
        {
            $this->trace[] = $this->h;
        }
    };
};
$e2 = new Engine();
$e2->register($makeRule('late-b', 5000));
$e2->register($makeRule('early', -5));
$e2->register($makeRule('late-a', 5000));
$e2->apply('text');
check('engine: rules run by priority, ties in registration order', ['early', 'late-b', 'late-a'], $trace);

// triggers: a rule is not called for a run without its characters
$calls = 0;
$e3 = new Engine();
$e3->register(new class ($calls) extends AbstractRule {
    public function __construct(private int &$calls)
    {
    }
    public function handle(): string
    {
        return 'needs-hash';
    }
    public function priority(): int
    {
        return 1;
    }
    public function defaultEnabled(): bool
    {
        return true;
    }
    public function triggers(): ?string
    {
        return '#';
    }
    public function apply(Run $run, Context $context): void
    {
        $this->calls++;
    }
});
$e3->apply('<p>no marker here</p><p>this has # in it</p>', [], new Options(rules: ['quotes' => false]));
check('engine: trigger characters gate the call', 1, $calls);

// ---- Locales -----------------------------------------------------------------

$registry = new LocaleRegistry();
$expectedIds = ['cs', 'da', 'de', 'de-CH', 'en-GB', 'en-US', 'es', 'fi', 'fr', 'fr-CH', 'it', 'nb', 'nl', 'pl', 'pt', 'sv'];
$ids = $registry->ids();
sort($ids);
check('locales: the sixteen first-release locales load', $expectedIds, $ids);
foreach ($expectedIds as $id) {
    $l = $registry->resolve($id);
    check("locales: $id round-trips", $id, $l?->id());
    check("locales: $id has two marks in each pair", [2, 2], [count($l->quotes()), count($l->nestedQuotes())]);
}
check('locales: no resolves to nb', 'nb', $registry->resolve('no')?->id());
check('locales: de-AT resolves to de', 'de', $registry->resolve('de-AT')?->id());
check('locales: en resolves to en-US', 'en-US', $registry->resolve('en')?->id());
check('locales: unknown resolves to null', null, $registry->resolve('xx'));
check('locales: fallback is the caller’s default', 'fr', $registry->resolveOrFallback('xx', 'fr')->id());
check('locales: then en-US', 'en-US', $registry->resolveOrFallback('xx', 'yy')->id());
check('locales: CLDR en-GB quotes', [['“', '”'], ['‘', '’']], [$registry->resolve('en-GB')->quotes(), $registry->resolve('en-GB')->nestedQuotes()]);
check('locales: CLDR de-CH quotes', [['„', '“'], ['‚', '‘']], [$registry->resolve('de-CH')->quotes(), $registry->resolve('de-CH')->nestedQuotes()]);
check('locales: French spacing data', ["\u{202F}", "\u{00A0}", "\u{202F}"], [
    $registry->resolve('fr')->spacing()['before']['?'],
    $registry->resolve('fr')->spacing()['before'][':'],
    $registry->resolve('fr')->spacing()['guillemets'],
]);
check('locales: English has no spacing rules', [], $registry->resolve('en-US')->spacing());

// variants: CLDR is the default, another convention is a named alternative
$gb = $registry->resolve('en-GB');
check('variants: en-GB offers single-outer', ['single-outer'], $gb->variants());
check('variants: other locales offer none', [], $registry->resolve('fr')->variants());
$single = $gb->withVariant('single-outer');
check('variants: single-outer swaps the pairs', [['‘', '’'], ['“', '”'], 'en-GB'], [$single->quotes(), $single->nestedQuotes(), $single->id()]);
check('variants: a variant keeps the keys it does not change', [$gb->dash(), $gb->apostrophe()], [$single->dash(), $single->apostrophe()]);
check('variants: the locale itself is untouched', [['“', '”'], ['‘', '’']], [$gb->quotes(), $gb->nestedQuotes()]);
check('variants: an unknown name returns the locale itself', true, $gb->withVariant('nonsense') === $gb);
throws('variants: a variant with one mark in a pair is refused', InvalidArgumentException::class, static fn () => DataLocale::fromArray(['id' => 'q', 'quotes' => ['a', 'b'], 'nested' => ['a', 'b'], 'apostrophe' => 'x', 'dash' => [], 'variants' => ['v' => ['quotes' => ['a']]]]));
check('variants: Options keeps them', ['en-GB' => 'single-outer'], (new Options(variants: ['en-GB' => 'single-outer']))->with(locale: 'de')->variants);
check('variants: de-CH offers guillemets, es offers guillemets-outer', [['guillemets'], ['guillemets-outer']], [$registry->resolve('de-CH')->variants(), $registry->resolve('es')->variants()]);
check('variants: de-CH guillemets swap the pairs', [['«', '»'], ['‹', '›']], [$registry->resolve('de-CH')->withVariant('guillemets')->quotes(), $registry->resolve('de-CH')->withVariant('guillemets')->nestedQuotes()]);
check('variants: es guillemets-outer swap the pairs', [['«', '»'], ['“', '”']], [$registry->resolve('es')->withVariant('guillemets-outer')->quotes(), $registry->resolve('es')->withVariant('guillemets-outer')->nestedQuotes()]);

check('variants: de offers low-high, nl offers double-outer', [['low-high'], ['double-outer']], [$registry->resolve('de')->variants(), $registry->resolve('nl')->variants()]);
check('variants: de low-high is CLDR’s pair', [['„', '“'], ['‚', '‘']], [$registry->resolve('de')->withVariant('low-high')->quotes(), $registry->resolve('de')->withVariant('low-high')->nestedQuotes()]);
check('variants: the retired de book name falls back to the default, which is book style', [['»', '«'], ['›', '‹']], [$registry->resolve('de')->withVariant('book')->quotes(), $registry->resolve('de')->withVariant('book')->nestedQuotes()]);
check('variants: nl double-outer swaps the pairs', [['“', '”'], ['‘', '’']], [$registry->resolve('nl')->withVariant('double-outer')->quotes(), $registry->resolve('nl')->withVariant('double-outer')->nestedQuotes()]);
check('variants: nl’s default is CLDR’s, de’s is book style (D844)', [['»', '«'], ['‘', '’']], [$registry->resolve('de')->quotes(), $registry->resolve('nl')->quotes()]);
check('variants: de-AT and de-DE take de’s default, de-CH keeps CLDR’s', [['»', '«'], ['»', '«'], ['„', '“']], [$registry->resolve('de-AT')->quotes(), $registry->resolve('de-DE')->quotes(), $registry->resolve('de-CH')->quotes()]);

// quoteNesting: typed by default, depth on request, anything else means typed
check('quoteNesting: typed is the default', 'typed', (new Options())->quoteNesting);
check('quoteNesting: with() keeps it and changes it', ['depth', 'depth', 'typed'], [
    (new Options(quoteNesting: 'depth'))->with(locale: 'de')->quoteNesting,
    (new Options())->with(quoteNesting: 'depth')->quoteNesting,
    (new Options(quoteNesting: 'depth'))->with(quoteNesting: 'typed')->quoteNesting,
]);
$nesting = new Engine();
$typedOpts = new Options(rules: ['primes' => false]);
check('quoteNesting: typed keeps a typed single as the secondary pair', '‘x’ “y”', $nesting->apply("'x' \"y\"", [], $typedOpts));
check('quoteNesting: depth sets the outermost quotation as primary', '“x” “y”', $nesting->apply("'x' \"y\"", [], $typedOpts->with(quoteNesting: 'depth')));
check('quoteNesting: an unknown value means typed', '‘x’', $nesting->apply("'x'", [], $typedOpts->with(quoteNesting: 'sideways')));

// adding a locale is adding data
$dir = sys_get_temp_dir() . '/sorts-locale-test-' . bin2hex(random_bytes(4));
mkdir($dir);
file_put_contents($dir . '/tlh.php', "<?php\nreturn ['id' => 'tlh', 'aliases' => ['klingon'], 'quotes' => ['<<', '>>'], 'nested' => ['<', '>'], 'apostrophe' => \"'\", 'dash' => ['mark' => '=', 'spaced' => false]];\n");
$custom = new Engine(new Options(), new LocaleRegistry($dir));
check('locales: a file in the folder is a locale', '<<a <b>>>', $custom->apply('"a \'b\'"', [], new Options(locale: 'klingon', rules: ['primes' => false])));
unlink($dir . '/tlh.php');
rmdir($dir);
$one = DataLocale::fromArray(['id' => 'zz', 'quotes' => ['[', ']'], 'nested' => ['(', ')'], 'apostrophe' => '`', 'dash' => ['mark' => '-', 'spaced' => false]]);
$reg = new LocaleRegistry($dir . '-none');
$reg->register($one);
check('locales: register() adds a locale object', 'zz', $reg->resolve('zz')?->id());
throws('locales: data missing a key is refused', InvalidArgumentException::class, static fn () => DataLocale::fromArray(['id' => 'q']));
throws('locales: a pair with one mark is refused', InvalidArgumentException::class, static fn () => DataLocale::fromArray(['id' => 'q', 'quotes' => ['a'], 'nested' => ['a', 'b'], 'apostrophe' => 'x', 'dash' => []]));
throws('locales: a third pair with one mark is refused', InvalidArgumentException::class, static fn () => DataLocale::fromArray(['id' => 'q', 'quotes' => ['a', 'b'], 'nested' => ['a', 'b'], 'third' => ['c'], 'apostrophe' => 'x', 'dash' => []]));
check('locales: the third pair is optional, and a variant can add one (es guillemets-outer)', [null, ['‘', '’']], [$registry->resolve('es')->thirdQuotes(), $registry->resolve('es')->withVariant('guillemets-outer')->thirdQuotes()]);

// ---- Scopes: one rule, three ways in, identical output ------------------------

$text = 'Add 1.5 cups, 0.875 kg of flour and 0.25 cup of oil.';
$expected = 'Add 1½ cups, 0.875 kg of flour and ¼ cup of oil.';
$only = new Options(rules: array_fill_keys($handles, false));

$everywhere = new Engine();
$everywhere->register(new RecipeFractionsRule(true));
$a = $everywhere->apply("<p>$text</p>", [], $only->with(rules: array_merge($only->rules, ['recipe-fractions' => true])));

$engine = newEngine();
$b = $engine->apply("<p data-sorts-rules=\"recipe-fractions\">$text</p>", [], $only);
$c = $engine->apply($text, ['recipe-fractions'], $only);
$cInPage = $engine->apply("<p>$text</p>", ['recipe-fractions'], $only);

check('scope (a) everywhere', "<p>$expected</p>", $a);
check('scope (b) inside a data-sorts-rules element', "<p data-sorts-rules=\"recipe-fractions\">$expected</p>", $b);
check('scope (c) named in the call', $expected, $c);
check('scope (c) named in the call, on a page', "<p>$expected</p>", $cInPage);
check('scopes: page-style call and direct apply() give the same text', $c, preg_replace('~</?p[^>]*>~', '', $b));
check('scopes: all three agree once the marker attribute is set aside', [$a, $a, $a], [$a, str_replace(' data-sorts-rules="recipe-fractions"', '', $b), $cInPage]);
check('scopes: a rule that is off by default does nothing unnamed', $text, $engine->apply($text, [], $only));

// ---- Context: what a rule can see ---------------------------------------------

$seen = [];
$spy = new class ($seen) extends AbstractRule {
    public function __construct(private array &$seen)
    {
    }
    public function handle(): string
    {
        return 'spy';
    }
    public function priority(): int
    {
        return 1;
    }
    public function defaultEnabled(): bool
    {
        return true;
    }
    public function apply(Run $run, Context $context): void
    {
        $this->seen[$run->text()] = [
            'locale' => $context->locale->id(),
            'mode' => $context->mode,
            'ancestors' => $context->ancestors(),
            'block' => $context->block(),
            'before' => $context->before(3),
            'after' => $context->after(3),
            'class' => $context->hasAncestorClass('lead'),
            'em' => $context->hasAncestor('EM'),
            'after-all' => $context->textAfter(),
        ];
    }
};
$e4 = new Engine();
$e4->register($spy);
$e4->apply('<div lang="de"><p class="lead">one <em>two</em> three</p></div><p>four</p>', [], new Options(rules: array_fill_keys($handles, false)));
check('context: locale from lang', 'de', $seen['one ']['locale']);
check('context: mode', 'html', $seen['one ']['mode']);
check('context: ancestors outermost first', ['div', 'p'], $seen['one ']['ancestors']);
check('context: block', 'p', $seen['one ']['block']);
check('context: before at flow start is empty', '', $seen['one ']['before']);
check('context: after looks through inline tags', 'two', $seen['one ']['after']);
check('context: class of an ancestor', true, $seen['one ']['class']);
check('context: no class on a sibling flow', false, $seen['four']['class']);
check('context: text of the earlier run is before', 'ne ', $seen['two']['before']);
check('context: em is an ancestor of two', true, $seen['two']['em']);
check('context: em is not an ancestor of one', false, $seen['one ']['em']);
check('context: ancestors include the inline element', ['div', 'p', 'em'], $seen['two']['ancestors']);
check('context: textAfter is the rest of the flow', ' three', $seen['two']['after-all']);
check('context: a new flow does not see the last', ['', 'en-US'], [$seen['four']['before'], $seen['four']['locale']]);

// ---- The tokenizer: everything not changed comes out byte for byte -----------

$allOff = new Options(rules: array_fill_keys($handles, false));
$awkward = [
    '', ' ', "\n", "\u{FEFF}", "\u{FEFF}<p>\"x\"</p>", "<p>a\r\n\"b\"\r\n</p>\r\n",
    '<p>unclosed', '<p>"a<p>"b', '<div><span>"x"</div>', '</b>"x"</i>', 'a < b > c', '1<2 and 3>2', '<3 "x"',
    '<a href="a>b">"x"</a>', "<a href='a\"b>c'>\"x\"</a>", '<a title="it\'s" data-x=\'say "hi"\'>x</a>',
    '<a href=foo"bar>"x"', '<a href="unterminated>"x" more', '<a', '<', '<!', '<!-', '<!--', '<!-- never closed "x"',
    '<!-->"x"', '<!--->"x"', '<!---->"x"', '<!-- a --> "x" <!-- b -->', '<!--[if IE]><p>"x"</p><![endif]-->',
    '<![CDATA[ "x" ]]> "y"', '<![CDATA[ never closed "x"', '<!DOCTYPE html>"x"', '<!doctype html PUBLIC "a" "b">',
    '<?xml version="1.0"?>"x"', '<? php "x" ?>', '</ >"x"', '</>"x"', '</3>"x"',
    '<script>if (a < b) { x = "</p>"; }</script>"x"', '<script>never closed "x"', '<SCRIPT TYPE="x">"</script >"x"',
    '<style>p::before{content:"x"}</style>"y"', '<textarea>"x" <b>y</b></textarea>"z"', '<title>"x"</title>',
    '<pre>"x"\n  "y"</pre>', '<svg viewBox="0 0 1 1"><text>"x"</text></svg>', '<math><mi>"x"</mi></math>',
    '<template><p>"x"</p></template>', '<noscript><img src=x>"y"</noscript>', '<xmp><b>"x"</b></xmp>',
    '<iframe src="a">"x"</iframe>', '<p data-sorts="off">"x"</p>', '<p lang="fr">"x"</p>',
    '<br/><br />\'x\'<hr>', '<img src="a.png" alt="it\'s"><input value="">', '<table><tr><td>"x"</table>',
    'Tom &amp; Jerry &quot;x&quot; &#39;y&#39; &lt;b&gt; &hellip;', '&amp', '&;', '&#;', '&#x;', '&bogus;',
    "tab\there\x0Bvt\x0Cff", "nul\x00byte", "\u{200B}zero\u{200D}width", 'emoji 🎉 "x"', '日本語 "x"',
];
foreach ($awkward as $i => $input) {
    check("tokenizer: all rules off is byte-identical (#$i)", $input, $engine->apply($input, [], $allOff));
}

$convertible = [
    ['<p lang="DE">"x"</p>', null],
    ['<title>"x"</title>', '<title>“x”</title>'],
    ['<TITLE>"x"</TITLE>', '<TITLE>“x”</TITLE>'],
    ['<p>"x"<script>"y"</script>"z"</p>', '<p>“x”<script>"y"</script>“z”</p>'],
    ['<a href=foo"bar>"x"', '<a href=foo"bar>“x”'],
    ["<p>a</p>\r\n<p>\"b\"</p>\r\n", "<p>a</p>\r\n<p>“b”</p>\r\n"],
];
foreach ($convertible as $i => [$input, $want]) {
    if ($want === null) {
        $want = str_replace('"x"', '»x«', $input);
    }
    check("tokenizer: only text runs change (#$i)", $want, $engine->apply($input, [], new Options(rules: array_merge($allOff->rules, ['quotes' => true]))));
}

// nothing convertible with the default rules: the very same bytes
$plain = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Plain page</title><style>a{}</style></head><body><h1>Plain words</h1><p>Nothing here needs fixing.</p><pre>"code"</pre><script>var a = "b";</script></body></html>';
check('tokenizer: nothing convertible with defaults is byte-identical', $plain, $engine->apply($plain));

// a pathologically long attribute / many tags do not break the pass
$big = str_repeat('<a href="' . str_repeat('x', 5000) . '" title="it\'s">y</a> ', 400);
check('tokenizer: long attributes and many tags survive', $big, $engine->apply($big, [], $allOff));
$deep = str_repeat('<div>', 1000) . '"x"' . str_repeat('</div>', 1000);
check('tokenizer: nesting to the limit is still typeset', str_replace('"x"', '“x”', $deep), $engine->apply($deep));
$tooDeep = str_repeat('<b>', 300000) . '"x"';
check('tokenizer: absurd nesting comes back unchanged, without crashing', $tooDeep, $engine->apply($tooDeep));

// ---- The allow-list: nothing turns on a rule the options do not allow ---------

$lite = new Options(allowed: ['quotes', 'apostrophes', 'primes', 'dashes', 'ellipsis']);
$scoped = '<p data-sorts-rules="fractions ranges multiplication ordinals hook-amp hook-caps symbols nbsp widows">1/2 cup, pages 1-5, 3x4, 1st place, A&amp;B NASA (c) 10 kg "x"</p>';
check('allowed: a data-sorts-rules scope cannot turn on a rule outside it', '<p data-sorts-rules="fractions ranges multiplication ordinals hook-amp hook-caps symbols nbsp widows">1/2 cup, pages 1-5, 3x4, 1st place, A&amp;B NASA (c) 10 kg “x”</p>', $engine->apply($scoped, [], $lite));
check('allowed: nor can naming it in the call', '1/2 (c) “x”', $engine->apply('1/2 (c) "x"', ['fractions', 'symbols'], $lite));
check('allowed: nor can the rules option', '1/2 “x”', $engine->apply('1/2 "x"', [], $lite->with(rules: ['fractions' => true])));
check('allowed: a rule switched off may still run by scope when it is allowed', '<p data-sorts-rules="dashes">a — b “x”</p>', $engine->apply('<p data-sorts-rules="dashes">a -- b "x"</p>', [], $lite->with(rules: ['dashes' => false])));
check('allowed: null allows every rule (the scope converts)', '<p data-sorts-rules="fractions">½</p>', $engine->apply('<p data-sorts-rules="fractions">1/2</p>'));
check('allowed: with() keeps it', $lite->allowed, $lite->with(locale: 'de')->allowed);

// ---- Long raw-text bodies and comments: no lazy quantifier, no silent skip -----

$longScript = '<p>"a" -- it\'s</p><script>var d = "' . str_repeat('x', 1_200_000) . '";</script><p>"b"</p>';
check('long script: the text around a 1.2 MB script is still converted', '<p>“a” — it’s</p><script>var d = "' . str_repeat('x', 1_200_000) . '";</script><p>“b”</p>', $engine->apply($longScript));
check('long script: no error is reported', null, $engine->lastError());
$longComment = '<p>"a"</p><!-- ' . str_repeat('"x" ', 300_000) . ' --><p>"b"</p>';
check('long comment: the text around a 1.2 MB comment is still converted', '<p>“a”</p><!-- ' . str_repeat('"x" ', 300_000) . ' --><p>“b”</p>', $engine->apply($longComment));
// A block that opens with any length of whitespace still opens with its mark: no pattern limit cuts the read short (FU1).
$quotesOnly = new Options(locale: 'en-GB', rules: array_merge($allOff->rules, ['quotes' => true, 'apostrophes' => true, 'primes' => true]));
foreach (['spaces' => str_repeat(' ', 100_000), 'no-break spaces' => str_repeat('&nbsp;', 100_000), 'earlier runs' => '&nbsp;<b>' . str_repeat(' ', 100_000) . '</b>'] as $what => $indent) {
    check("long indent ($what): the mark after it opens a quotation", '<p>' . $indent . '‘It was dark.</p>', $engine->apply('<p>' . $indent . "'It was dark.</p>", [], $quotesOnly));
}
check('long indent (text mode): the mark after it opens a quotation', str_repeat(' ', 100_000) . '‘It was dark.', $engine->apply(str_repeat(' ', 100_000) . "'It was dark.", [], $quotesOnly->with(mode: Options::TEXT)));
$limit = ini_get('pcre.backtrack_limit');
foreach (['2' => 'tokenizing', '1' => 'UTF-8'] as $tight => $step) {
    // 2 lets the UTF-8 check through and stops the tokenizer (with or without JIT); 1 stops the UTF-8 check itself.
    ini_set('pcre.backtrack_limit', (string) $tight);
    $unchanged = $engine->apply('<p>"a" <b>"b"</b></p>');
    $why = $engine->lastError();
    ini_set('pcre.backtrack_limit', $limit);
    check("pattern failure ($step): the input comes back unchanged", '<p>"a" <b>"b"</b></p>', $unchanged);
    check("pattern failure ($step): lastError() says which step and why", true, is_string($why) && str_contains($why, $step) && str_contains($why, 'limit'));
}
check('invalid UTF-8 is not an error', null, ($engine->apply("caf\xE9") === "caf\xE9") ? $engine->lastError() : 'changed');
$engine->apply('<p>"ok"</p>');
check('pattern failure: the next call clears it', null, $engine->lastError());

// ---- Speed guards: ratios, not time budgets ------------------------------------
// Each guard times two pages in this process and compares them, so a slow or busy
// machine (PHP 8.2 in Docker, CI) slows both alike and only the shape of the cost is
// tested: 4× the input in under 8× the time passes a linear pass (about 4×) and fails a
// quadratic one (about 16×). Best of 3 for each page.

$slower = static function (string $small, string $large, ?Options $options = null) use ($engine): array {
    $times = [];
    foreach ([$small, $large] as $html) {
        $best = INF;
        for ($try = 0; $try < 3; $try++) {
            $t = hrtime(true);
            $engine->apply($html, [], $options);
            $best = min($best, (hrtime(true) - $t) / 1e6);
        }
        $times[] = $best;
    }
    return [$times[1] / max($times[0], 0.001), $times[0], $times[1]];
};

// The quote scan is linear on a long text node.
$page = static fn (int $n): string => "<p>'" . str_repeat("the dogs' bowls ", $n) . "'</p>";
[$ratio, $a, $b] = $slower($page(3_200), $page(12_800));
check(sprintf('perf guard: a possessive paragraph 4× as long takes under 8× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 8);

// And on dialogue, every opening mark asking whether it opens the block (R1-1), in HTML and in plain text.
$page = static fn (int $n): string => '<p>' . str_repeat("'Hello,' she said. ", $n) . '</p>';
[$ratio, $a, $b] = $slower($page(1_600), $page(6_400), new Options(locale: 'en-GB'));
check(sprintf('perf guard: a paragraph of single-quoted dialogue 4× as long takes under 8× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 8);
[$ratio, $a, $b] = $slower(str_repeat("'Hello,' she said. ", 1_600), str_repeat("'Hello,' she said. ", 6_400), new Options(locale: 'en-GB', mode: Options::TEXT));
check(sprintf('perf guard: single-quoted dialogue in plain text 4× as long takes under 8× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 8);
// After an indent as long, which is measured once for the run, not once for each mark.
$page = static fn (int $n): string => '<p>' . str_repeat('&nbsp;', $n) . str_repeat("'Hello,' she said. ", $n) . '</p>';
[$ratio, $a, $b] = $slower($page(1_600), $page(6_400), new Options(locale: 'en-GB'));
check(sprintf('perf guard: single-quoted dialogue after an indent, both 4× as long, takes under 8× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 8);

// And on nesting: closing a quotation costs the quotations it closes, not the depth (F3).
$page = static fn (int $n): string => '<p>' . str_repeat('"a \'b ', $n) . str_repeat('c\' d" ', $n) . '</p>';
[$ratio, $a, $b] = $slower($page(1_000), $page(4_000));
check(sprintf('perf guard: 4× as many nested quotations in one node take under 8× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 8);

// Open quotations across inline tags: a run does not copy the quotations open before it (F3).
$page = static fn (int $n): string => '<p>' . str_repeat('<b> "a</b>', $n) . '</p>';
[$ratio, $a, $b] = $slower($page(4_000), $page(16_000));
check(sprintf('perf guard: open quotations across 4× as many inline tags take under 8× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 8);

// So are the dashes on one: a match does not copy the rest of the run (R2-4).
$page = static fn (int $n): string => '<p>' . str_repeat('a -- b. ', $n) . '</p>';
[$ratio, $a, $b] = $slower($page(16_384), $page(65_536), new Options(rules: array_merge($allOff->rules, ['dashes' => true])));
check(sprintf('perf guard: a paragraph of dashes 4× as long takes under 8× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 8);

// An end tag that matches no open element costs the same at any depth (R2-5): the same
// stray </font> tags under 1,000 open divs and under 250.
$page = static fn (int $depth): string => str_repeat('<div>', $depth) . str_repeat('</font>y ', 25_000) . str_repeat('</div>', $depth);
[$ratio, $a, $b] = $slower($page(250), $page(1_000), $allOff);
check(sprintf('perf guard: stray end tags under 4× the nesting take under 2× as long (%.1f×: %.1f → %.1f ms)', $ratio, $a, $b), true, $ratio < 2);

// ---- Settled marks move with an earlier, shrinking edit in the same call -------

$run = new Run('abcdef ghij klmn');
$run->replace(7, 4, 'GHIJ');
check('run: two edits in one call around a settled span', 2, $run->edit([[0, 6, 'x'], [12, 4, 'y']]));
check('run: text after the shrinking edit', 'x GHIJ y', $run->text());
check('run: the settled span moved with the shrink', [true, false, true, true, true, true, false], array_map([$run, 'isSettled'], [0, 1, 2, 3, 4, 5, 6]));
check('run: a later edit of the moved span is refused', false, $run->replace(2, 1, 'Q'));
check('run: and the text is as it was', 'x GHIJ y', $run->text());

// overlapsSettled(), which rules may call, and the walk in edit() agree on where a settled span ends.
$run = new Run('abcdef');
$run->replace(2, 2, 'XY');
check('run: overlapsSettled() inside a span', true, $run->overlapsSettled(3, 1));
check('run: overlapsSettled() just after a span is free', false, $run->overlapsSettled(4, 1));
check('run: overlapsSettled() just before a span is free', false, $run->overlapsSettled(1, 1));
check('run: overlapsSettled() a point at the end of a span is free', false, $run->overlapsSettled(4, 0));
check('run: overlapsSettled() a point inside a span is not', true, $run->overlapsSettled(3, 0));
check('run: edit() right after a settled span is allowed', 1, $run->edit([[4, 1, 'Z']]));

// ---- Findings (D859): read-only notes the engine makes and never acts on -----

$summary = static fn (array $findings): array => array_map(static fn ($f): array => [$f->code, $f->match, $f->suggestion], $findings);
$finder = new Engine();
$html = '<p>Born in the 1930\'s, and 2 × 4\'s are lumber.</p>';
$found = $finder->findings($html);
check('findings: a numerical plural is found, with the plural to write', [['numerical-plural', "1930's", '1930s'], ['numerical-plural', "4's", '4s']], $summary($found));
check('findings: each offset is where the match starts in the input', [strpos($html, "1930's"), strpos($html, "4's")], array_map(static fn ($f): int => $f->offset, $found));
check('findings: the message is plain text for a person', true, str_contains($found[0]->message, '1930s') && !str_contains($found[0]->message, '<'));
check('findings: the same text through apply() is untouched by the call', '<p>Born in the 1930’s, and 2 × 4’s are lumber.</p>', $finder->apply($html, [], new Options(rules: ['multiplication' => true])));
check('findings: a curly apostrophe', [['numerical-plural', '1930’s', '1930s']], $summary($finder->findings('<p>the 1930’s</p>')));
check('findings: a word processor’s wrong-way apostrophe', [['numerical-plural', '1930‘s', '1930s']], $summary($finder->findings('<p>the 1930‘s</p>')));
check('findings: an entity apostrophe in HTML', [['numerical-plural', '1930&#39;s', '1930s'], ['numerical-plural', '1940&apos;S', '1940S']], $summary($finder->findings('<p>the 1930&#39;s and 1940&apos;S</p>')));
check('findings: in plain text an entity is only text', [], $finder->findings('the 1930&#39;s', new Options(mode: 'text')));
check('findings: plain text has its offsets from the start', [4], array_map(static fn ($f): int => $f->offset, $finder->findings("the 1930's", new Options(mode: 'text'))));
check('findings: a plural without the apostrophe, a possessive of a word and a leading apostrophe are not found', [], $finder->findings('<p>the 1930s, the dog\'s bowl, the \'90s, 1930\'s1 and A1\'s</p>'));
check('findings: code, attributes, scripts, comments and an off element are not read', [], $finder->findings('<p title="1930\'s"><code>1930\'s</code></p><script>var a = "1930\'s";</script><!-- 1930\'s --><div data-sorts="off">1930\'s</div><pre>1930\'s</pre>'));
check('findings: a title is read, as apply() reads it', [['numerical-plural', "1930's", '1930s']], $summary($finder->findings("<title>The 1930's</title>")));
check('findings: nothing found is an empty list', [], $finder->findings('<p>Nothing to see.</p>'));
check('findings: an input that is not UTF-8 has none', [], $finder->findings("1930's \xFF"));
check('findings: an empty input has none', [], $finder->findings(''));
check('findings: no rule switch, locale or variant changes them', $summary($found), $summary($finder->findings($html, new Options(locale: 'de', rules: array_fill_keys(array_map(static fn ($r): string => $r->handle(), $finder->rules()), false)))));
check('findings: the call leaves a later apply() as it would have been', $finder->apply($html), (new Engine())->apply($html));
check('findings: lastError is not set by it', null, $finder->lastError());

// ---- The Bringhurst preset (D817) -------------------------------------------

use iceboxind\sorts\engine\Presets;

$fine = new Engine(new Options(preset: Options::PRESET_BRINGHURST));
check('preset: Options has none by default and keeps one through with()', ['', 'bringhurst'], [(new Options())->preset, (new Options())->with(preset: 'bringhurst', locale: 'de')->preset]);
check('preset: its rules and variants are data a caller can read', [true, ['en-US' => 'spaced-en-dash'], [], []], [Presets::rules('bringhurst')['ranges'], Presets::variants('bringhurst'), Presets::rules(''), Presets::variants('nonsense')]);
check('preset: a page it changes', '<p>8½ × 11 – 4:30–5:00</p>', $fine->apply('<p>8 1/2 x 11 -- 4:30-5:00</p>'));
check('preset: the same page without it', '<p>8 1/2 x 11 — 4:30-5:00</p>', (new Engine())->apply('<p>8 1/2 x 11 -- 4:30-5:00</p>'));
check('preset: a rule in the options wins, off', '<p>8 1/2 x 11 – 4:30–5:00</p>', $fine->apply('<p>8 1/2 x 11 -- 4:30-5:00</p>', [], new Options(preset: 'bringhurst', rules: ['fractions' => false, 'multiplication' => false])));
check('preset: a rule in the options wins, on, without the preset', '<p>8½ x 11 — 4:30-5:00</p>', (new Engine())->apply('<p>8 1/2 x 11 -- 4:30-5:00</p>', [], new Options(rules: ['fractions' => true])));
check('preset: the allowed list still caps it', '<p>pages 10-12 and 8 1/2</p>', $fine->apply('<p>pages 10-12 and 8 1/2</p>', [], new Options(preset: 'bringhurst', allowed: ['quotes', 'dashes'])));
check('preset: a rule named in a call still runs without it', '<p>—' . "\u{202F}" . 'One</p>', (new Engine())->apply('<p>-- One</p>', ['dialogue-dash']));
check('preset: a data-sorts-rules scope still runs without it', '<p data-sorts-rules="ranges">pages 10–12</p><p>pages 10-12</p>', (new Engine())->apply('<p data-sorts-rules="ranges">pages 10-12</p><p>pages 10-12</p>'));
check('preset: data-sorts="off" is respected', '<p data-sorts="off">a—b 8 1/2</p>', $fine->apply('<p data-sorts="off">a—b 8 1/2</p>'));
check('preset: a lang attribute switches the locale and the spacing with it', "<p lang=\"it\">«\u{202F}Sì\u{202F}»</p><p lang=\"de\">»Ja«</p><p>«Sì»</p>", $fine->apply('<p lang="it">«Sì»</p><p lang="de">»Ja«</p><p>«Sì»</p>'));
$registry = new LocaleRegistry();
$fine->apply('<p>«Sì»</p><p lang="it">«Sì»</p>', [], new Options(locale: 'it', preset: 'bringhurst'));
check('preset: it leaves the registry’s locale as it was', [[], ['“', '”']], [$registry->resolve('it')->spacing(), $registry->resolve('en-US')->quotes()]);
$battery = ['-- So this.', 'a—b', 'wait…then', 'wait...then', '<p>«Sì»</p>', 'pages 10-12 and 8 1/2 x 11 at 4:30-5:00', 'a - b'];
check('preset: off, the rules it adds stay off', array_map(static fn (string $s): string => (new Engine())->apply($s), $battery), array_map(static fn (string $s): string => (new Engine())->apply($s, [], new Options(rules: ['typed-dashes' => false, 'dialogue-dash' => false, 'ellipsis-spacing' => false])), $battery));
check('preset: off, the new rules change nothing a page had', ['— So this.', 'a—b', 'wait…then', 'wait…then', '<p>«Sì»</p>', 'pages 10-12 and 8 1/2 x 11 at 4:30-5:00', 'a - b'], array_map(static fn (string $s): string => (new Engine())->apply($s), $battery));

// ---- Linear time for the D817–D859 work ---------------------------------------
//
// Each of these puts one construct in a single paragraph, 4,096 and 16,384 times (a few hundred KB, under the page pass's
// own 1,000,000 byte limit): 4× the input must take under 8× the time.

$guard = static function (string $name, string $unit, ?Options $options = null) use ($slower): void {
    $page = static fn (int $n): string => '<p>' . str_repeat($unit, $n) . '</p>';
    [$ratio, $a, $b] = $slower($page(4_096), $page(16_384), $options);
    check(sprintf('perf guard: %s 4× as long takes under 8× as long (%.1f×: %.1f → %.1f ms)', $name, $ratio, $a, $b), true, $ratio < 8);
};
$guard('word-initial apostrophes', "'Áshįįh ");
$guard('honorifics with and without a period', 'Main St Louis and Dr Smith, Mrs. Jones ');
$guard('mixed numbers and fractions before an x', '8 1/2 x 11½ x 3 ', new Options(rules: ['fractions' => true, 'multiplication' => true]));
$guard('clock-time ranges', '4:30-5:00 and 22:00-06:00 ', new Options(rules: ['ranges' => true]));
$guard('the dash variant', 'a - b -- c --- d ', new Options(variants: ['en-US' => 'spaced-en-dash']));
$guard('the Bringhurst preset', 'wait...then a—b 8 1/2 x 11 4:30-5:00 -- ok «Sì» ', new Options(preset: 'bringhurst'));
$findingsTime = static function (string $html) use ($finder): float {
    $best = INF;
    for ($try = 0; $try < 3; $try++) {
        $t = hrtime(true);
        $finder->findings($html);
        $best = min($best, (hrtime(true) - $t) / 1e6);
    }
    return $best;
};
$small = $findingsTime('<p>' . str_repeat("1930's ", 4_096) . '</p>');
$large = $findingsTime('<p>' . str_repeat("1930's ", 16_384) . '</p>');
check(sprintf('perf guard: findings() on a paragraph 4× as long takes under 8× as long (%.1f×: %.1f → %.1f ms)', $large / max($small, 0.001), $small, $large), true, $large / max($small, 0.001) < 8);
