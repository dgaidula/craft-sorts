<?php

/**
 * HTML fuzz: seeded, messy, real-world-shaped pages through the engine.
 *
 * - every page, all rules off: the output must be byte-identical to the input;
 * - pages built from nothing convertible, default rules on: byte-identical too;
 * - pages with convertible text, default rules on: running twice must equal
 *   running once, and the number of "<" must not change;
 * - the same with the Bringhurst preset (D817): all rules off, byte-identical; nothing
 *   convertible, the preset on, byte-identical; with convertible text, twice equals once.
 *
 * The pages are generated from FUZZ_SEED, so a failure reproduces. No page is
 * scraped from the web.
 */

use iceboxind\sorts\engine\Options;

const FUZZ_SEED = 20261105;
const FUZZ_PAGES = 400;
const FUZZ_LARGE_PAGES = 6;

final class FuzzPages
{
    private const WORDS = ['the', 'quick', 'brown', 'fox', 'jumps', 'over', 'lazy', 'dog', 'and', 'then', 'Sorts', 'typesets', 'pages', 'with', 'care', 'Craft', 'content', 'editors', 'paste', 'text'];

    /** Text with nothing the default rules would change. */
    private const INERT = [
        'plain words here', 'Tom &amp; Jerry', 'a &lt; b &gt; c', 'x &#8212; y', '&nbsp;', 'see http://example.com/a?b=1&c=2', 'e-mail and well-known',
        '3.14 and 1,000 and 2024-01-15', 'curly “already” ‘done’ don’t', 'caf&eacute; &copy; &#169;', 'emoji 🎉 and 日本語', 'a/b and and/or',
        'Word. Another sentence.', 'dots.. and ....... and e.g. this', '- - not here: -x', 'ends with colon:',
    ];

    /** Text the default rules do change. */
    private const CONVERTIBLE = [
        '"quoted"', "don't", '5\'10" tall', 'wait...', 'a--b', '(c) 2024', '10 kg', 'Dr. Smith', '&quot;x&quot;', '&#039;y&#039;', "rock 'n' roll",
        "'90s", 'she said "it\'s fine"', 'He said--"yes"', '&#x22;z&#x22;', "The dogs' bowls", '45°30\'', '"nested \'quote\' here"', 'Tom & Jerry "ok"',
        // F1 (gate 2026-11-06): a straight-quoted word before a spaced hyphen, and `---` (the preset, in any language).
        'the word "foo" - which', 'he came---and went',
    ];

    private const INLINE = ['a', 'b', 'i', 'em', 'strong', 'span', 'sup', 'sub', 'small', 'mark', 'abbr', 'cite', 'q', 'u', 's', 'code', 'kbd'];
    private const BLOCK = ['p', 'div', 'li', 'td', 'blockquote', 'section', 'article'];

    private bool $inert;

    public function __construct(bool $inert)
    {
        $this->inert = $inert;
    }

    private function pick(array $a): string
    {
        return $a[mt_rand(0, count($a) - 1)];
    }

    private function inertOr(array $inert, array $any): string
    {
        return $this->pick($this->inert ? $inert : $any);
    }

    private function words(int $n): string
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $this->pick(self::WORDS);
        }
        return implode(' ', $out);
    }

    private function text(): string
    {
        $t = $this->words(mt_rand(1, 6));
        if (mt_rand(0, 2) === 0) {
            $t .= ' ' . $this->pick($this->inert ? self::INERT : array_merge(self::INERT, self::CONVERTIBLE));
        }
        if (mt_rand(0, 4) === 0) {
            $t = $this->pick(["\n", "\n  ", ' ', "\t", "\r\n"]) . $t;
        }
        if (mt_rand(0, 4) === 0) {
            $t .= $this->pick(["\n", ' ', "\r\n", '']);
        }
        return $t;
    }

    private function attrs(): string
    {
        $a = '';
        for ($i = mt_rand(0, 3); $i > 0; $i--) {
            $a .= $this->pick([
                ' class="x y"', ' id=main', " title='say \"hi\"'", ' title="it\'s a > b"', ' data-x=1', ' hidden', ' lang="en"', ' href="/a?b=c&amp;d=e"',
                ' alt="don\'t"', "\n  data-y='z'", ' style="font-family: \'A\', \"B\""', ' onclick="a = \'b\' > \"c\'"', ' data-sorts-rules="ranges"',
            ]);
        }
        return $a;
    }

    private function piece(int $depth): string
    {
        $r = mt_rand(0, 99);
        if ($r < 30 || $depth > 5) {
            return $this->text();
        }
        if ($r < 52) {
            $t = $this->pick(self::INLINE);
            return "<$t" . $this->attrs() . '>' . $this->children($depth + 1) . (mt_rand(0, 9) === 0 ? '' : "</$t>");
        }
        if ($r < 70) {
            $t = $this->pick(self::BLOCK);
            return "<$t" . $this->attrs() . '>' . $this->children($depth + 1) . (mt_rand(0, 6) === 0 ? '' : "</$t>") . $this->pick(["\n", '', "\r\n"]);
        }
        if ($r < 74) {
            // F1: a heading that ends in a spaced hyphen or a spaced em dash (widows beside a dash the preset restyles).
            // A closed one (a—b) adds words on the first pass, which widows counts only on the second (FU8).
            return '<h' . ($h = mt_rand(1, 2)) . '>' . $this->words(mt_rand(1, 2)) . ($this->inert ? '' : $this->pick(['', '', ' a - b', ' a — b'])) . "</h$h>";
        }
        if ($r < 77) {
            return $this->pick(['<br>', '<br/>', '<BR />', '<hr>', '<img src="a.png" alt="x">', '<input value="">', '<wbr>']);
        }
        if ($r < 81) {
            return $this->inertOr([
                '<!-- a comment -->', '<!--[if IE]><p class="ie">x</p><![endif]-->', '<![CDATA[ x > y ]]>', '<!DOCTYPE html>',
                '<?xml version="1.0"?>', '<!---->', '<!-- multi' . "\n" . 'line comment -->', '<![if !IE]>',
            ], [
                '<!-- a "comment" with \'quotes\' -->', '<!--[if IE]><p class="ie">"x"</p><![endif]-->', '<![CDATA[ "x" > y ]]>', '<!DOCTYPE html>',
                '<?xml version="1.0"?>', '<!---->', '<!-- multi' . "\n" . 'line "comment" -->', '<![if !IE]>',
            ]);
        }
        if ($r < 86) {
            $t = $this->pick(['script', 'style', 'pre', 'code', 'textarea', 'svg', 'math', 'template', 'noscript', 'title']);
            $body = $t === 'svg' ? '<text>' . ($this->inert ? 'x y' : '"x" y') . '</text>' : ($t === 'script' ? 'var a = "x < y"; // it\'s' : ($this->inert ? 'plain raw text' : '"raw" \'text\' 5\'10" ...'));
            return "<$t>$body</$t>";
        }
        if ($r < 90) {
            // a stray "</" is a bogus comment that runs to the next ">", which can expose
            // text a page meant as an attribute: browsers do the same, so only the other page kind gets it
            return $this->inertOr(['a < b', '1<2', '<3', '5 > 4', 'x &lt; y', '< ', '<=>'], ['a < b', '1<2', '<3', '5 > 4', 'x &lt; y', '<', '< ', '</', '<-', '<=>']);
        }
        if ($r < 93) {
            return $this->pick(['</div>', '</b>', '</p>', '</span>', '</unknown>']);
        }
        if ($r < 96) {
            return '<p data-sorts="off">' . $this->pick(self::CONVERTIBLE) . '</p>';
        }
        return '<p lang="' . $this->pick($this->inert ? ['en', 'de', 'sv', 'xx', ''] : ['en', 'de', 'fr-CH', 'xx', '']) . '">' . $this->text() . '</p>';
    }

    private function children(int $depth): string
    {
        $out = '';
        for ($i = mt_rand(0, 4); $i > 0; $i--) {
            $out .= $this->piece($depth);
        }
        return $out;
    }

    public function page(int $minBytes = 0): string
    {
        $head = $this->pick(["\u{FEFF}", '', '', '<!DOCTYPE html>']);
        $body = '';
        do {
            $body .= $this->piece(0);
        } while (strlen($body) < $minBytes);
        if (mt_rand(0, 9) === 0) {
            $body = substr($body, 0, mt_rand(0, strlen($body)));
            // a cut may land inside a multi-byte character; keep the page valid UTF-8
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
        }
        return $head . '<html lang="en"><body>' . $body . '</body></html>';
    }
}

$engine = newEngine();
$handles = array_map(static fn ($r) => $r->handle(), $engine->rules());
$off = new Options(rules: array_fill_keys($handles, false));
$bringhurst = new Options(preset: Options::PRESET_BRINGHURST);
$byteSum = 0;
$changedPages = 0;
$total = FUZZ_PAGES + FUZZ_LARGE_PAGES;

mt_srand(FUZZ_SEED);
for ($i = 0; $i < $total; $i++) {
    $large = $i >= FUZZ_PAGES;
    $inert = $i % 2 === 0;
    $gen = new FuzzPages($inert);
    $page = $gen->page($large ? 120000 : 600);
    $byteSum += strlen($page);

    check("fuzz #$i rules off: byte-identical", $page, $engine->apply($page, [], $off));
    check("fuzz #$i rules off, Bringhurst preset: byte-identical", $page, $engine->apply($page, [], $off->with(preset: Options::PRESET_BRINGHURST)));
    if ($inert) {
        check("fuzz #$i nothing convertible, defaults on: byte-identical", $page, $engine->apply($page));
        check("fuzz #$i nothing convertible, Bringhurst preset: byte-identical", $page, $engine->apply($page, [], $bringhurst));
    } else {
        $fine = $engine->apply($page, [], $bringhurst);
        check("fuzz #$i Bringhurst preset: running twice equals once", $fine, $engine->apply($fine, [], $bringhurst));
        check("fuzz #$i Bringhurst preset: markup characters unchanged", substr_count($page, '<') . '/' . substr_count($page, '>'), substr_count($fine, '<') . '/' . substr_count($fine, '>'));
        $once = $engine->apply($page);
        $changedPages += $once !== $page ? 1 : 0;
        check("fuzz #$i defaults on: running twice equals once", $once, $engine->apply($once));
        check("fuzz #$i defaults on: markup characters unchanged", substr_count($page, '<') . '/' . substr_count($page, '>'), substr_count($once, '<') . '/' . substr_count($once, '>'));
    }
}
echo 'fuzz: seed=' . FUZZ_SEED . ' pages=' . $total . ' (' . FUZZ_LARGE_PAGES . ' of 120 KB+) bytes=' . $byteSum . ' convertible-pages-changed=' . $changedPages . "\n";

// ---- Script data: escaped and double-escaped scripts are never edited -------------
//
// Scripts built from the pieces that move the HTML5 tokenizer between its script-data
// states (<!--, <script, </script>, -->, near misses), with convertible text around and
// inside them. With all rules off every page is byte-identical; with the defaults on, the
// text of every script, as PHP's own HTML5 parser (Dom\HTMLDocument, PHP 8.4+) reads it,
// is the same before and after, and so is the number of scripts.

const SCRIPT_FUZZ_SEED = 20261106;
const SCRIPT_FUZZ_PAGES = 400;
const SCRIPT_PIECES = [
    '<!--', '-->', '--->', '<!-->', '<script>', '<script type="x">', '<script/>', '<SCRIPT >', '</script>', '</SCRIPT >',
    '</script/>', '<scripts>', '</scripts>', '</scrip>', '--!>', '-', '<', '>', '"x" -- it\'s', ' var a = "b"; ',
    "document.write('<p>\"y\"</p>');", '<p>"z"</p>', "\n",
];

$scriptsOf = static function (string $html): ?string {
    if (!class_exists(\Dom\HTMLDocument::class)) {
        return null;
    }
    $doc = \Dom\HTMLDocument::createFromString('<!DOCTYPE html>' . $html, LIBXML_NOERROR);
    $texts = [];
    foreach ($doc->querySelectorAll('script') as $script) {
        $texts[] = $script->textContent;
    }
    return json_encode($texts, JSON_UNESCAPED_UNICODE);
};

mt_srand(SCRIPT_FUZZ_SEED);
$compared = 0;
for ($i = 0; $i < SCRIPT_FUZZ_PAGES; $i++) {
    $page = '<p>"a" -- it\'s</p><script>';
    for ($n = mt_rand(1, 12); $n > 0; $n--) {
        $page .= SCRIPT_PIECES[mt_rand(0, count(SCRIPT_PIECES) - 1)];
    }
    $page .= (mt_rand(0, 4) === 0 ? '' : '</script>') . '<p>"b" -- it\'s</p>';
    check("script fuzz #$i rules off: byte-identical", $page, $engine->apply($page, [], $off));
    $once = $engine->apply($page);
    check("script fuzz #$i defaults on: running twice equals once", $once, $engine->apply($once));
    $before = $scriptsOf($page);
    if ($before !== null) {
        $compared++;
        check("script fuzz #$i defaults on: every script's text is unchanged (Dom\\HTMLDocument)", $before, $scriptsOf($once));
    }
}
echo 'script fuzz: seed=' . SCRIPT_FUZZ_SEED . ' pages=' . SCRIPT_FUZZ_PAGES . ' compared-with-Dom\HTMLDocument=' . $compared . "\n";
