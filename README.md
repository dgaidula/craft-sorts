# Sorts

**Typographer-grade punctuation for Craft CMS: smart quotes, apostrophes, primes, dashes and more, fixed site-wide with no template edits.**

A Craft 5 plugin by Danniel T. Gaidula.

**Hardened:** 2026-11-05 · 98db482 · gate CLOSED WITH FOLLOW-UPS (`gates/2026-11-05-sorts-release-recheck-2.json`, after a FAIL and two fix rounds)

Editors type and paste straight quotes. Craft 5 leaves them alone, and Twig prints them as `&quot;` and `&#039;`. Install Sorts and every page is typeset correctly (titles, every field type, text hard-coded in your templates) with no template edits. In the control panel, editors get the right characters as they type.

```html
<!-- before -->
<p>"She said 'no'" -- and left... It's 5'10" tall (c) 2026</p>

<!-- after -->
<p>“She said ‘no’” — and left… It’s 5′10″ tall © 2026</p>
```

## Requirements

**Craft CMS 5.0+ · PHP 8.2+.** Sorts is a Craft 5 plugin and does not run on Craft 4. Verified against Craft 5.12.0.1 and `craftcms/ckeditor` 5.9.0.

## Editions

Sorts ships two editions. Lite holds only what the market already gives away; Pro is everything that works on its own.

| | Lite (free) | Pro |
| --- | :---: | :---: |
| The `\|sorts` Twig filter and `{% apply sorts %}` (English quotes, apostrophes, primes, dashes, ellipsis) | ✅ | ✅ |
| CKEditor as-you-type quotes | ✅ | ✅ |
| Automatic whole-page pass, no template edits <sup>PRO</sup> | — | ✅ |
| Every rule and every locale <sup>PRO</sup> | — | ✅ |
| Rule switches, quotation styles, normalizing quotes by depth and the widow setting <sup>PRO</sup> | — | ✅ |
| `data-sorts="off"` and `data-sorts-lang` on an element | ✅ | ✅ |
| Exclusion settings: skipped tags, classes and paths <sup>PRO</sup> | — | ✅ |
| Page title and meta descriptions <sup>PRO</sup> | — | ✅ |
| Extension API: register your own rules <sup>PRO</sup> | — | ✅ |
| GraphQL directive `@sorts` <sup>PRO</sup> | — | ✅ |
| Fix on save <sup>PRO</sup> | — | ✅ |
| Audit utility: scan, preview and fix stored content <sup>PRO</sup> | — | ✅ |

Pro settings show on Lite, marked and locked, so you can see what Pro does. A downgrade to Lite stops the automatic pass and queues no new fixes; it never undoes or half-finishes content. A fix that has already been queued finishes.

Edition gates control features, never safety. The opt-out (`data-sorts="off"`) and the language override (`data-sorts-lang`) are safety controls, so they work in every edition; what Pro adds is the exclusion *settings*. In Lite, `data-sorts-rules` and a rule named in a call can run only Lite’s own rules: Lite tells the engine which rules it allows, and nothing in a page or a call can add another.

## What it does

Sorts is a typography engine (plain PHP, no Craft code, in `src/engine/`) with a Craft layer around it. The engine reads HTML with a tokenizer and edits text and nothing else: attributes, `script`, `style`, `pre`, `code`, `kbd`, `samp`, `var`, `textarea`, `math`, `svg`, `template`, `noscript` and comments are never touched in well-formed HTML (two malformed comment forms are the exception; see [Known limits](#known-limits)), and a page with nothing to fix comes out byte for byte the same.

| Rule | Example | Default |
| --- | --- | :---: |
| Quotation marks, nested | `"She said 'no'"` → `“She said ‘no’”` | on |
| Apostrophes, including leading | `don't`, `'90s`, `rock 'n' roll` → `don’t`, `’90s`, `rock ’n’ roll` | on |
| Primes | `5'10"` → `5′10″`; `45°30'` → `45°30′` | on |
| Dashes | `--` → `—` (or a spaced `–`, by language); `---` → `—` | on |
| Ellipsis | `...` → `…` | on |
| Symbols | `(c)` `(r)` `(tm)` → `©` `®` `™` | on |
| Number ranges | `1-5` → `1–5` | off |
| Multiplication | `3x4`, `1920 x 1080` → `3×4`, `1920 × 1080` | off |
| Fractions | `1/2` → `½` (the Unicode set only; never dates or URLs) | off |
| Ordinals | `1st` → `1` and a superscript `st` | off |
| Widows | a no-break space between the last two words of a heading | on |
| No-break spaces | number and unit (`10 kg`), initials, `Dr. Smith`, `§ 5` | on |
| Locale spacing | French narrow no-break space before `; : ! ?` and inside `« »` | by language |
| Styling hooks | `&` in `<span class="amp">`, runs of capitals in `<span class="caps">`, an opening quote in `<span class="dquo">` | off |

### Which quotation marks a quote gets

Sorts keeps the quote type the author typed. A straight `"` pair becomes the language’s **primary** quotation marks and a straight `'` pair its **secondary** (nested) marks, whatever the depth, so the same text typed in a CKEditor field (where `quotesPrimary` and `quotesSecondary` do the same) and pasted in agree on one page. It is a matter of level, not of the shape of the glyph, so a language’s variant still applies:

| Typed | en-US | en-GB `single-outer` | de |
| --- | --- | --- | --- |
| `"no"` | `“no”` | `‘no’` | `„no“` |
| `'no'` | `‘no’` | `“no”` | `‚no‘` |
| `"She said 'no'"` | `“She said ‘no’”` | `‘She said “no”’` | `„She said ‚no‘“` |
| `'She said "no"'` | `‘She said “no”’` | `“She said ‘no’”` | `‚She said „no“‘` |

Apostrophes, primes and the rules that tell an apostrophe or a prime from a quotation (`don't`, `'90s`, `5'10"`) do not depend on this; only the choice of marks for a quotation Sorts has recognised does.

Pro has a setting, *Normalize quotes to house style* (`normalizeQuotes`, off by default), that sets every quotation by its nesting depth instead: the primary marks outermost, the secondary marks inside them, then alternating, whichever mark was typed. With it on, `'She said "no"'` becomes `“She said ‘no’”` in en-US. It applies to the page pass, the filter, `@sorts`, fix on save and the audit. Lite always keeps the quote type typed.

Ranges, multiplication and fractions are off because they would catch phone numbers, ISBNs, SKUs and dates. Ordinals and the styling hooks write markup, so they never run in plain-text mode and are never written into stored content.

## Install

```bash
composer require iceboxind/craft-sorts
php craft plugin/install sorts
```

Lite works at once. Pro starts the page pass the moment it is licensed: open any page and the punctuation is set.

## The automatic page pass <sup>PRO</sup>

Sorts listens for `craft\web\View::EVENT_AFTER_RENDER_PAGE_TEMPLATE` and sets the typography of each page Craft renders for a site visitor.

It runs for front-end page requests that answer with HTML, previews and token requests (share links, live preview) included, so a preview matches the live page. It never runs for the control panel, the console, action requests, or a response that is not HTML (a template that sends `Content-Type: application/json`, or whose file name ends in `.json.twig` or `.xml.twig`, is left alone).

The language comes from the current site’s language setting, and from a `lang` attribute on any element (the nearest one wins). An unknown language falls back to the site’s, then to `en-US`.

**It never breaks a page.** If the engine or a rule you registered throws, the error is logged and the page is sent as the template rendered it. So is a page larger than the *Largest page to fix* setting (`maxPageBytes`, 1,000,000 bytes by default), a page the pass might not have the memory for under PHP’s `memory_limit` (it allows 100 bytes of memory per byte of page, see Performance), and a page the engine could not read because a pattern hit a PCRE limit. Each of those writes one warning to the log, naming the page.

**The `<title>` and `<meta>` content are off by default.** They show in browser tabs and link previews, so one setting, *Also fix the page title and meta descriptions*, turns them on. Only the prose metas are touched (`description`, `og:title`, `og:description`, `og:site_name`, `og:image:alt`, and the `twitter:` title, description and image alt); the values are decoded, set as plain text and encoded again, and a value that needs no change keeps its bytes.

### Caches

`{% cache %}` tags and static-cache plugins (Blitz and the like) store the converted output, because the pass runs on the rendered page. Clear them after you change a Sorts setting.

## Template usage

```twig
{{ entry.body|sorts }}                          {# HTML in, HTML out #}
{{ entry.title|sorts }}                         {# a plain string is escaped first, like any HTML output #}
{{ entry.title|sorts('text') }}                 {# plain text: attributes, JSON, CSV #}
{% apply sorts %}<p>"Any" -- markup</p>{% endapply %}
{{ row.quantity|sorts(rules: ['my-rule']) }}    {# a rule you registered, by name #}
```

In Lite the filter applies the English quotes, apostrophes, primes, dashes and ellipsis. In Pro it applies every rule that is switched on, in the current site’s language.

**Escaping.** In HTML mode the filter never turns unescaped input into safe output. It trusts what Twig already treats as HTML: a rich-text field value, the body of `{% apply sorts %}`, and the output of a filter Twig declares safe for HTML directly in front of it: `|raw`, `|nl2br`, `|e` or `|escape` (HTML strategy), `|md` or `|markdown` (`{{ html|raw|sorts }}`, `{{ text|nl2br|sorts }}`). Any other value (a variable, a plain string, a literal in your template, the output of `|upper` or any filter not declared safe) is escaped first, so `{{ s|sorts }}` with `s` set to `<script>` prints `&lt;script&gt;`, and `{{ s|nl2br|sorts }}` escapes it once, not twice. Text mode returns a plain string, which Twig escapes when it prints it. Put the safe filter right before `|sorts`; further up the chain it is not seen.

On a Lite site, a rule named in `rules:` is skipped rather than raising an error; in Pro, a handle that nothing registered is an error, so a typo shows.

## Settings

CP → Settings → Plugins → Sorts, stored in project config, with every key overridable in `config/sorts.php` (see `config.example.php`). A setting a file overrides shows locked in the control panel. All input is validated: lists accept a list or one value per line, tag names are lower-case letters, digits and hyphens, and a bad value is refused with a message. Craft does not validate what `config/sorts.php` or project config hands the plugin, so a number there that does not validate (`maxPageBytes` set to `'2MB'`, say) falls back to its default, with a warning in the log naming the value.

| Setting | Key | Default | Edition |
| --- | --- | --- | --- |
| Plugin name | `pluginName` | blank | Lite |
| Convert quotes as people type in CKEditor | `ckeditorAsYouType` | on | Lite |
| Run the page pass | `pagePass` | on | Pro |
| Fix the title and meta descriptions | `processHead` | off | Pro |
| Largest page to fix, in bytes | `maxPageBytes` | 1000000 | Pro |
| Rule switches (a map of rule handle to on or off) | `rules` | the table above | Pro |
| Fewest words in a heading before widows are joined | `widowsMinWords` | 3 | Pro |
| Quotation style per language | `variants` | CLDR | Pro |
| Normalize quotes to house style (set every quotation by depth, not by the mark typed) | `normalizeQuotes` | off | Pro |
| Extra tags to skip | `skipTags` | none | Pro |
| Extra classes to skip | `skipClasses` | none | Pro |
| Request paths to skip (`*` matches any run of characters) | `skipUris` | none | Pro |
| Fix on save | `fixOnSave` | off | Pro |
| …titles, Plain Text fields, CKEditor fields | `fixTitles`, `fixPlainText`, `fixCkeditor` | on | Pro |
| …also for resaves and migrations | `fixOnSaveBulk` | off | Pro |

```php
// config/sorts.php
return [
    '*' => [
        'skipTags' => ['figcaption'],
        'skipClasses' => ['no-typography'],
        'skipUris' => ['/api/*', '/sitemap.xml'],
        'variants' => ['en-GB' => 'single-outer'],
        'rules' => ['fractions' => true, 'ranges' => true],
    ],
];
```

### Rename it in the control panel

Sorts can present under any name you like. The sidebar nav, the Plugins screen and the settings breadcrumb all follow **Settings → Plugins → Sorts → Plugin name** (blank = “Sorts”). Set it in `config/sorts.php` so it holds with `allowAdminChanges` off.

## Exclusions

In a template, on any element (`data-sorts="off"` and `data-sorts-lang` work in every edition; `data-sorts-rules` turns on Pro’s rules in Pro only):

```html
<p data-sorts="off">Left exactly as written. So is everything inside this element.</p>
<blockquote data-sorts-lang="de">"Hallo" -- the quotes follow German.</blockquote>
<div data-sorts-rules="recipe-fractions">Turns on a rule that is off, for this element only.</div>
```

In settings <sup>PRO</sup>: extra tags, classes and request paths to skip. A path is matched against the URL path as requested (a site’s own prefix included), without the query string; `*` matches any run of characters, slashes too.

## Locales

Sorts sets quotation marks, nested quotation marks, dashes and spacing by language: `en-US`, `en-GB`, `de`, `de-CH`, `fr`, `fr-CH`, `es`, `it`, `pt`, `nl`, `sv`, `fi`, `da`, `nb` (also `no`), `pl` and `cs`. The quotation marks come from Unicode CLDR **49.0.0**; dash style and spacing come from each language’s style authority, named in its locale file. A `lang` attribute on any element switches the language for everything inside it, and an unknown language falls back to the site’s language, then to `en-US`. Lite has `en-US` and `en-GB`.

CLDR is the default for every language. Where a style authority writes it differently, that convention is a named variant you can choose per language <sup>PRO</sup>. Built so far:

| Language | Variant | Marks | Authority |
| --- | --- | --- | --- |
| `en-GB` | `single-outer` | ‘…’ outermost, “…” inside | New Hart’s Rules (Oxford University Press), 2nd ed. |
| `de-CH` | `guillemets` | «…» outermost, ‹…› inside | Schweizerische Bundeskanzlei, *Schreibweisungen*, 2nd ed. 2013, section 2.1.1 |
| `es` | `guillemets-outer` | «…» outermost, “…” inside | Real Academia Española, *Ortografía de la lengua española* (2010), “Las comillas” |

A variant changes only the quotation marks it names; the spacing and the dash stay the language’s own. The variant sets which marks are the primary and secondary pair, and the quote type typed picks between them (see above). An unknown variant name falls back to CLDR. The authority for each is in its locale file (`src/engine/locales/`).

## CKEditor

When `craftcms/ckeditor` is installed, Sorts loads CKEditor’s own `TextTransformation` plugin in every CKEditor field (`CkeditorConfig::registerFirstPartyPackage(['TextTransformation'])`), so quotes convert as people type. With nothing else set, that plugin would also turn `->` into an arrow, `<=` into a symbol, and `--` into an en dash even in American English, so Sorts narrows it to what its own rules do.

What CKEditor allows, and what Sorts makes of it:

- The editor’s config reaches the browser as JSON, and JSON cannot carry a regular expression, so Sorts can only switch on CKEditor’s built-in transformations by name.

- Quotation marks: the key typed picks the language’s primary pair (`"`) or secondary pair (`'`), as Sorts does elsewhere, and a key is enabled only where one of CKEditor’s transformations has exactly that pair. English gets “…” for `"` and ‘…’ for `'`; Polish gets „…” for `"`. German, French and the rest, and every variant (`single-outer`, `guillemets`, `guillemets-outer`), are left straight as typed, because CKEditor’s config cannot carry a pair it does not know by name; the page pass or fix on save sets them correctly later. CKEditor’s `en-GB` pairs put ‘…’ on `'`, which is not what `single-outer` does (it puts ‘…’ on `"`), so Sorts does not use them.

- With *Normalize quotes to house style* on, only `"` is converted while typing: a typed `'` may be an outermost quotation, which depth sets in the primary marks, and CKEditor cannot know that.

- Dashes, ellipsis and symbols follow the rules that are on. `--` becomes an en dash only for languages where Sorts’ `--` is a spaced en dash.

- Not available in CKEditor: apostrophes, primes, ranges, no-break spaces, widows. Pasted text and existing content are never changed by the editor.

- The language is the control panel’s requested site (`?site=`), which an entry’s edit page uses.

- A field’s own CKEditor config wins: it can set its own `typing`, or switch the plugin off with `removePlugins: ['TextTransformation']`. The setting *Convert quotes as people type* turns it off everywhere. When CKEditor is not installed, Sorts does nothing here.

## Extension API <sup>PRO</sup>

Your site has its own conversions: a recipe site that turns `0.875` into `⅞`, a store that sets product dimensions. Those belong in your code, but they should run through Sorts rather than beside it. Register a rule:

```php
use iceboxind\sorts\events\RegisterRulesEvent;
use iceboxind\sorts\Plugin as Sorts;
use yii\base\Event;

Event::on(Sorts::class, Sorts::EVENT_REGISTER_RULES, function (RegisterRulesEvent $event) {
    $event->rules[] = new RecipeFractionsRule();
});
```

A rule implements `iceboxind\sorts\engine\RuleInterface` (extend `AbstractRule` for the defaults). The built-in rules use the same interface.

```php
use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;
use iceboxind\sorts\engine\rules\AbstractRule;

final class RecipeFractionsRule extends AbstractRule
{
    private const FRACTIONS = ['0.125' => '⅛', '0.25' => '¼', '0.5' => '½', '0.75' => '¾', '0.875' => '⅞'];

    public function handle(): string
    {
        return 'recipe-fractions';          // lower-case letters, digits, hyphens
    }

    public function priority(): int
    {
        return 45;                          // rules run in ascending priority
    }

    public function defaultEnabled(): bool
    {
        return false;                       // true runs it everywhere (scope a)
    }

    public function apply(Run $run, Context $context): void
    {
        // Metric units and ounces are left alone. Whatever the rule writes is settled,
        // so no later rule (a range dash, say) touches it.
        $run->replaceAll(
            '/(?<![\d.])(\d*)(\.\d+)(?!\d)(?! ?(?:ml|mL|l|L|g|kg|mg|cm|mm|oz)\b)/',
            static function (array $m): ?string {
                $fraction = self::FRACTIONS['0' . $m[2]] ?? null;
                return $fraction === null ? null : ($m[1] === '0' ? '' : $m[1]) . $fraction;
            }
        );
    }
}
```

A registered rule has three scopes:

1. **Everywhere in the page pass**, if `defaultEnabled()` returns true.

2. **Only inside elements marked** `data-sorts-rules="recipe-fractions"`. Several handles go in one attribute, separated by spaces.

3. **Only when called by name**, so CSV, XLSX and JSON get the same result as the page: `{{ qty|sorts(rules: ['recipe-fractions']) }}` in Twig, or in PHP:

```php
$text = Sorts::getInstance()->engine->apply('Mix 0.875 cup', ['recipe-fractions']);
// Mix ⅞ cup
$csv = Sorts::getInstance()->engine->apply($cell, ['recipe-fractions'], null, 'text');
```

`apply(string $input, array $rules = [], ?string $language = null, string $mode = 'html')` uses the same settings as the page pass (the language defaults to the current site’s), so the page and the PHP call give the same bytes for the same text. A rule that cannot be registered (a duplicate handle, a bad name, something that is not a rule) is logged and skipped; the others still run.

## GraphQL <sup>PRO</sup>

```graphql
{
  entries(section: "news") {
    title @sorts(mode: "text")
    ... on news_Entry { body { html @sorts } }
  }
}
```

`@sorts` sets the typography of a String field as it is returned. `mode` is `html` (the default) or `text`; `rules` runs registered rules by name. The language is that of the site the value belongs to: the entry’s site for a title or Plain Text field, and the field data’s site for the `html` of a CKEditor field, so a French entry comes back French whichever endpoint you query. Only a value with no site of its own falls back to the site the request is for. A CKEditor field returns an object: put the directive on its `html` or `rawHtml`. On Lite the directive is known to the schema and returns the value unchanged, so a query written for Pro does not fail after a downgrade.

## Fixing stored content <sup>PRO</sup>

The page pass fixes what visitors see. Feeds, APIs, search and exports read what is stored. Two tools fix the stored text.

Both follow the same quote setting as the page (the quote type typed, or *Normalize quotes to house style*), and both change characters only: quotes, apostrophes, primes, dashes, ellipsis and symbols, plus fractions, ranges and multiplication signs when you switch those rules on. They never write spacing or markup, or run a rule you registered, into the database. They never touch slugs, URIs, handles, URLs, email addresses, or Plain Text fields set to code, and a fix that would push a value past its field’s character limit is skipped.

**Across languages.** Quotes and dashes depend on the language, so a value is converted only when every site it reaches on that save is in the same language (the same locale and quotation style) as the site it is saved on. Craft copies a new entry’s title and every field to each site it is propagated to, and copies a field that is not translated per site (Craft’s default for a new field) to every site that shares it. On a site with English and French sites, then, a new entry goes in as typed, and so does an edit to a shared CKEditor field; an edit to a title or a field translated per site is converted in that site’s language. A value left as typed is logged at info, and the page pass sets it on each page in that page’s language. The audit follows the same rule.

**Fix on save** (off by default) changes the title and the Plain Text and CKEditor values on the element as it is saved, so there is no second save. It skips revisions, drafts Craft autosaves while someone types, saves that propagate to other sites, and resaves and migrations (`craft up` included) unless *Also fix when resaving and while migrations run* is on.

**The audit** (CP → Utilities → Sorts) scans entries a hundred at a time, lists those with something to fix with a before and after, and fixes the ones you tick through the queue. The scan only reads. The fix is saved the way Craft’s own resave command saves, so it creates no revision and leaves each entry’s Date Updated alone, unless you tick *Create a revision for each entry and update its Date Updated*. The edition is checked when the job is queued, not when it runs, so a job already queued finishes after a downgrade; it carries the language and rules each site was to be fixed with, so a French entry is still fixed in French. The job also carries the user who queued it and skips any entry that user may not save on that site (Craft’s own `canSave()` check).

## What Sorts assumes

HTML pages rendered by Craft templates. Not handled: JSON and XML responses, emails, PDFs, text that JavaScript inserts after the page loads, hyphenation, and CJK punctuation.

**Hyphenation** is the browser’s job. Use CSS `hyphens: auto` with a correct `lang` attribute (Craft sets one if your layout prints `{{ currentSite.language }}`).

## Known limits

- **A typed quote is taken as the level it was typed at.** Because `"` is always the primary pair and `'` always the secondary pair, a quotation typed with the same mark inside itself gets the same marks both times (`"She said "no""` becomes `“She said “no””`), and a single-quoted quotation at the outermost level gets the secondary marks (`'No,' she said.` becomes `‘No,’ she said.` in en-US, and `“No,” she said.` in en-GB `single-outer`). Switch on *Normalize quotes to house style* to have the nesting depth decide instead.

- **Misnested markup inside code.** Sorts reads the page as it is written and does not rebuild HTML5’s tree: an end tag closes the nearest open element of that name, and Sorts does not run the browser’s repair of misnested formatting (the adoption agency and the reopening of formatting elements). In well-formed pages this changes nothing. In misnested ones, text that a browser would still show inside `code`, `kbd`, `samp` or `var` (after, say, a stray `</figcaption>` inside a `<code>`) can be typeset. Sorts assumes well-formed markup inside code-like elements.
- **Compiled templates after an update.** The filter’s escaping is decided when Twig compiles a template (`RawInputVisitor`). Craft runs Twig with `auto_reload` on, but Twig compares a compiled template only with the template and the extension class files, not the visitor, so a Sorts update that changes how the filter compiles may not reach templates compiled before it. Craft clears compiled templates when `php craft up` runs, after the updater runs migrations, and when Craft’s own version changes (verified against Craft 5.12.0.1: `console/controllers/UpController.php:99-100`, `services/Updates.php:264`, `web/Application.php:257-260`), but not after a Composer update of a plugin on its own. **After updating Sorts, run `php craft up` or clear compiled templates** (Utilities → Caches → Compiled templates, or `php craft clear-caches/compiled-templates`).
- **Two malformed comment forms.** HTML5 also ends a comment at `--!>`, and reads a `<![CDATA[` outside SVG or MathML as a bogus comment that ends at the first `>`. Sorts ends a comment only at `-->` and a CDATA section only at `]]>`. Both forms are parse errors, and on a page that uses one Sorts can leave text a browser shows unconverted, or typeset text a browser reads as part of a later `<script>` (in `<!-- a --!> <script> --><p>"x"</p>` the quotes Sorts curls are inside what the browser reads as script).
- **A vertical tab in a script tag.** Inside a script’s `<!--`, Sorts takes a vertical tab (U+000B) after `<script` or `</script` as the end of the tag name; HTML5 does not. In that case Sorts can leave text after the script unconverted. It only ever leaves text alone this way; it never edits the script.
- **Widows beside an inline tag with spaces on both sides.** In a heading such as `<h2>A new <a href="…"> library</a></h2>`, with a space before the tag and another inside it, widow control puts a no-break space at both, which shows as a double gap. Keep one of the two spaces, or turn widows off for that heading with `data-sorts="off"`.
- **The audit lists every section.** The audit utility’s scan shows titles and before-and-after excerpts from every section to anyone who may open the utility, whether or not they may view those entries. The fix itself skips any entry the user who queued it may not save (Craft’s `canSave()`). Give the utility permission only to people who may see every entry. This is not covered by a test: the testbed runs Craft Solo, which has one user.

## Performance

The engine is measured on a generated, realistic page of about 100 KB (a news article with navigation, a sidebar and a footer; `php tests/engine/perf.php`), with the default rules on, opcache on and the JIT off. Median of 300 runs after a warm-up:

| PHP | Where | Typical article | Dense stress page |
|---|---|---|---|
| 8.5.12 | MacBook Pro (Apple M1 Max, 32 GB), native | 3.4 ms | 4.7 ms |
| 8.4.27 | `php:8.4-cli` in Docker Desktop on the same Mac | 3.4 ms | 4.8 ms |
| 8.2.35 | `php:8.2-cli` in Docker Desktop on the same Mac | 3.6 ms | 5.0 ms |

About three sentences in ten on the typical article need typesetting; every sentence on the stress page does.

The budget is 5 ms for a 100 KB page on PHP 8.2, the oldest version Sorts supports (measured 2026-11-05). The medians meet it for both pages on every version above. The dense page on PHP 8.2 has no margin: the final gate measured a median of 4.87 ms over five runs, with single runs up to 5.28 ms.

**Memory.** Peak memory for one pass, per megabyte of page, measured on PHP 8.5.12 (2026-11-05): about 3.5 MB for an article, 15 MB for a product table, 37 MB for a page of nothing but tiny inline elements, and up to 73 MB for one paragraph of quotes a megabyte long. The page pass allows 100 bytes of memory per byte of page and sends a page it might not have the memory for as rendered (see *It never breaks a page*), and it skips any page over `maxPageBytes` (1,000,000 bytes by default) whatever the memory. A 6 MB page at `memory_limit = 256M` is sent as rendered with a warning, not a fatal error.

## Development

See [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) for architecture, the table of Pro gates and the local dev loop.

## License

Licensed under the Craft License; see [`LICENSE.md`](LICENSE.md). Copyright © Danniel T. Gaidula.
