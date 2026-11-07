# Sorts

**Typographer-grade punctuation for Craft CMS: smart quotes, apostrophes, primes, dashes and more, fixed site-wide with no template edits.**

A Craft 5 plugin by Danniel T. Gaidula.

**Hardened:** 2026-11-06 · 0bb7844 · gate CLOSED WITH FOLLOW-UPS (`gates/2026-11-06-sorts-r4-1-recheck.json`; the D818–D824 quote work after a FAIL, three fix rounds and four re-checks; first hardened 2026-11-05 at 98db482)

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
| Apostrophes, including leading | `don't`, `'90s`, `rock 'n' roll`, `'Áshįįh` → `don’t`, `’90s`, `rock ’n’ roll`, `’Áshįįh` | on |
| Primes | `5'10"` → `5′10″`; `45°30'` → `45°30′` | on |
| Dashes | `--` → `—` (or a spaced `–`, by language); `---` → `—` | on |
| Ellipsis | `...` → `…` | on |
| Symbols | `(c)` `(r)` `(tm)` → `©` `®` `™` | on |
| Number ranges | `1-5`, `4:30-5:00` → `1–5`, `4:30–5:00` | off |
| Multiplication | `3x4`, `1920 x 1080`, `8½ x 11` → `3×4`, `1920 × 1080`, `8½ × 11` | off |
| Fractions | `1/2` → `½`, `8 1/2` → `8½` (the Unicode set only; never dates or URLs) | off |
| Ordinals | `1st` → `1` and a superscript `st` | off |
| Widows | a no-break space between the last two words of a heading | on |
| No-break spaces | number and unit (`10 kg`), initials, `Dr. Smith` and `Dr Smith`, `§ 5` | on |
| Locale spacing | French narrow no-break space before `; : ! ?` and inside `« »` | by language |
| Typed em dash, dialogue dash, ellipsis spacing | the rules behind [Fine typography](#fine-typography-bringhurst) | off, or on with that setting |
| Styling hooks | `&` in `<span class="amp">`, runs of capitals in `<span class="caps">`, an opening quote in `<span class="dquo">` | off |

### Which quotation marks a quote gets

Sorts keeps the quote type the author typed for the outermost quotation. A straight `"` pair becomes the language’s **primary** quotation marks and a straight `'` pair its **secondary** (nested) marks, so the same text typed in a CKEditor field (where `quotesPrimary` and `quotesSecondary` do the same) and pasted in agree on one page. A quotation inside another always takes the other level from the one around it, whichever mark was typed. It is a matter of level, not of the shape of the glyph, so a language’s variant still applies:

| Typed | en-US | en-GB `single-outer` | de |
| --- | --- | --- | --- |
| `"no"` | `“no”` | `‘no’` | `„no“` |
| `'no'` | `‘no’` | `“no”` | `‚no‘` |
| `"She said 'no'"` | `“She said ‘no’”` | `‘She said “no”’` | `„She said ‚no‘“` |
| `'She said "no"'` | `‘She said “no”’` | `“She said ‘no’”` | `‚She said „no“‘` |
| `"She said "no" today"` | `“She said ‘no’ today”` | `‘She said “no” today’` | `„She said ‚no‘ today“` |

A quotation already set in curly marks counts as the level its marks have in the language, so `‘She said "no"’` in en-GB `single-outer` (where ‘…’ is the primary pair) becomes `‘She said “no”’`. A curly mark is read by what it does in the language, not by its shape: in German “ and ‘ close a quotation, and in the `book` style » and › open one, so `»"Nein," sagte er«` becomes `»›Nein,‹ sagte er«`. In Swedish and Finnish, where ” and ’ both open and close, a curly mark is read by what is on either side of it, as a straight one is: `”Han sa: "nej."”` becomes `”Han sa: ’nej.’”`. Three deep, the levels go primary, secondary, primary; `es` `guillemets-outer` has a third pair, so there a quotation typed with `"` (and, by nesting depth, every quotation) goes «…», “…”, ‘…’, and deeper “…” and ‘…’ alternate, while one typed with `'` starts at the secondary pair and goes “…”, «…», ‘…’.

Apostrophes, primes and the rules that tell an apostrophe or a prime from a quotation (`don't`, `'90s`, `5'10"`) do not depend on this; only the choice of marks for a quotation Sorts has recognised does.

A `'` at the start of a word, before a letter, is an apostrophe when nothing later in the text could close it and no quotation is open: Navajo `'Áshįįh 'Asdzáán` becomes `’Áshįįh ’Asdzáán` (the case Robert Bringhurst gives, *The Elements of Typographic Style*, 3rd ed., §5.5.2, along with Dutch `’s-Hertogenbosch`, which Sorts already set right). Inside an open quotation, with a closing mark later in the text, or at the very start of a paragraph (a quotation that runs over several paragraphs opens each one and closes only the last), it stays an opening quotation mark.

An honorific written without its period (`Mr`, `Mrs`, `Ms`, `Dr`, `St`, `Msgr`, `Fr`: the Oxford spelling, §5.4.4) is joined to the capitalised word after it with a no-break space, as `Dr. Smith` is. `St` after a capitalised word or a number that does not open its sentence is a street (`Main St Louis`, `42nd St Station`) and is left alone.

Pro has a setting, *Normalize quotes to house style* (`normalizeQuotes`, off by default), that sets every quotation by its nesting depth instead: the primary marks outermost, the secondary marks inside them, then alternating, whichever mark was typed. With it on, `'She said "no"'` becomes `“She said ‘no’”` in en-US. It applies to the page pass, the filter, `@sorts`, fix on save and the audit. Lite always keeps the quote type typed.

Ranges, multiplication and fractions are off because they would catch phone numbers, ISBNs, SKUs and dates. Ordinals and the styling hooks write markup, so they never run in plain-text mode and are never written into stored content.

## Fine typography (Bringhurst) <sup>PRO</sup>

One Pro setting, *Fine typography (Bringhurst)* (`fineTypography`, off by default), switches Sorts to the choices of Robert Bringhurst, *The Elements of Typographic Style*, 3rd ed., chapter 5. Each choice is a rule or a variant that exists on its own; the setting turns on the set:

| Section | What it sets |
| --- | --- |
| §5.2.1 | A break in US English is a spaced en dash (` – `), not a closed em dash: the `spaced-en-dash` variant, so `--`, `---` and a spaced hyphen all give it, and a closed or spaced em dash the writer typed (`a—b`) is restyled too (rule `typed-dashes`). A dash that opens a block, a run of em dashes and a dash that touches a quotation mark are left. Spanish, Polish and Portuguese keep their own dash. |
| §5.2.2 | Number ranges are on, clock times included (`4:30–5:00`). |
| §5.2.3 | A line of dialogue opens with an em dash and a narrow no-break space: `-- So this` or `— So this` at the start of a block gives `—` U+202F `So this`, in every language (rule `dialogue-dash`). |
| §5.2.5 | Fractions are on, and join their whole number: `8 1/2` gives `8½`. Dates and URLs keep the virgule. |
| §5.2.6 | Multiplication is on: `26 x 42`, `8½ x 11`. |
| §5.2.7 | An ellipsis between two words has a word space either side, and one that meets a full stop, comma, exclamation mark or question mark closes up to the word before it (rule `ellipsis-spacing`). English only, never French. |
| §5.4.1 | Thin spaces (U+202F) inside guillemets, in every language that uses them, German excepted: the book style sets none (Duden), nor does the Swiss federal guillemet style. |

`Dr Smith` with a no-break space (§5.4.4) and the word-initial apostrophe (§5.5.2) are not part of it: they hold in every setting.

**How it combines with your other settings.** The preset sits above the individual rules, as a quotation variant does for quotation marks, and a choice of yours wins over it. Every rule in *Rules* (the three that exist only for the preset, `typed-dashes`, `dialogue-dash` and `ellipsis-spacing`, included) is **on**, **off** or **follows the preset**:

| State | What the rule does |
| --- | --- |
| Follow the preset (the default) | What the preset says; with *Fine typography* off, the rule’s own default (the table under [What it does](#what-it-does)). A site that has switched nothing gets exactly this. |
| Off | Off, whatever the preset says: your veto of one rule in the set (ranges off while the rest of the preset runs, say). |
| On | On, even when the preset leaves it off: with *Fine typography* off you can still have ranges, a dialogue dash or spaced ellipses. |

- Em and en dashes off also takes the preset’s two dash rules off, wherever they follow the preset: with *Em and en dashes* off, `--` is left as typed, so a typed em dash (`typed-dashes`) is left as typed and a `-- ` that opens a line of dialogue (`dialogue-dash`) is not turned into an em dash and a thin space either. Switch either of those *on* and it runs, dashes off or not. `ellipsis-spacing` has no such tie to *Ellipsis*: it only spaces an ellipsis that is already there, and is its own switch.
- A site’s settings from before there were three states (a plain on or off for every rule, kept in project config or `config/sorts.php`) read as they always did: a switch at its rule’s default was only the default and follows the preset; one moved off it is on or off. Nothing is rewritten until the settings are next saved. Write `'on'`, `'off'` or `'follow'` in new configuration, because a plain `false` for a rule that is off already follows the preset.
- A quotation or dash variant you choose for a language wins over the preset’s (the preset’s only choice is `spaced-en-dash` for `en-US`).
- The Pro rules cannot be reached from Lite, so Lite ignores the setting. `data-sorts="off"` and `data-sorts-lang` work as always.
- It applies to the page pass, the filter, `@sorts`, fix on save and the audit. Stored content only gets the choices that swap one character for another (dashes, a typed em dash, ranges, fractions, multiplication); the spaces (dialogue, ellipsis, guillemets) belong to the page, and are never written to the database. A `-- ` that opens a block is stored as typed, for the page to set as dialogue.

With the setting off, output is byte for byte what it was before.

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
| Rules: each `on`, `off` or `follow` the preset (a map of rule handle to one of the three; a rule not named follows) | `rules` | follow | Pro |
| Fewest words in a heading before widows are joined | `widowsMinWords` | 3 | Pro |
| Style per language (quotation marks, the US English dash) | `variants` | CLDR (German: book style) | Pro |
| Normalize quotes to house style (set every quotation by depth, not by the mark typed) | `normalizeQuotes` | off | Pro |
| Fine typography (Bringhurst) | `fineTypography` | off | Pro |
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
        'rules' => ['fractions' => 'on', 'ranges' => 'on', 'multiplication' => 'off'],
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

CLDR is the default for every language but one: German defaults to book style `»…« ›…‹`, by the author’s taste (decision D844), and CLDR’s `„…“ ‚…‘` is the variant `low-high`. Where a style authority writes any other language differently, that convention is a named variant you can choose per language <sup>PRO</sup>. Built so far:

| Language | Variant | Marks | Authority |
| --- | --- | --- | --- |
| `en-GB` | `single-outer` | ‘…’ outermost, “…” inside | New Hart’s Rules (Oxford University Press), 2nd ed. |
| `de-CH` | `guillemets` | «…» outermost, ‹…› inside | Schweizerische Bundeskanzlei, *Schreibweisungen*, 2nd ed. 2013, section 2.1.1 |
| `es` | `guillemets-outer` | «…» outermost, “…” inside, ‘…’ inside that | Real Academia Española, *Ortografía de la lengua española* (2010), “Las comillas” |
| `de` | `low-high` | „…“ outermost, ‚…‘ inside | Unicode CLDR 49.0.0, the marks CLDR gives German. Not the default: the default is the book style »…« ›…‹ (Dan Gaidula, typographer, decision D844; Duden Band 9, *Richtiges und gutes Deutsch*, 5th ed. 2001, via a secondary source) |
| `en-US` | `spaced-en-dash` | a break is ` – `: an en dash with a word space each side | Robert Bringhurst, *The Elements of Typographic Style*, 3rd ed., §5.2.1 (decision D855). The default stays The Chicago Manual of Style’s closed em dash |
| `nl` | `double-outer` | “…” outermost, ‘…’ inside | Taalunie, taaladvies.net, “Dubbele of enkele aanhalingstekens bij een citaat” (12 May 2021): no fixed rule, but a traditional preference for double marks around a literal quotation |

A quotation variant changes only the quotation marks it names; the spacing and the dash stay the language’s own (the German book style sets no space inside » « or › ‹, as German practice has it). The dash variant changes only the dash: `--`, `---` and a hyphen with a space each side (`25 December - 3 January`) all become ` – `; a hyphen at the start of a line or block (a typed list bullet), a minus in an equation (`5 - 3 = 2`) and anything in code stay as typed, and ranges stay closed (`10–12`). A variant chosen for `de` or `nl` also applies to the regional forms that fall back to them (`de-AT`, `de-DE`, `nl-BE`), and `de-AT` and `de-DE` take `de`’s book-style default; `de-CH` has its own CLDR default and its own variant. The retired name `book` is no longer a variant of `de` (it is the default); a setting that still names it is refused when saved, and the engine reads it as the default. The variant sets which marks are the primary and secondary pair, and the quote type typed picks between them (see above). An unknown variant name falls back to CLDR. The authority for each is in its locale file (`src/engine/locales/`).

## CKEditor

When `craftcms/ckeditor` is installed, Sorts loads CKEditor’s own `TextTransformation` plugin in every CKEditor field (`CkeditorConfig::registerFirstPartyPackage(['TextTransformation'])`), so quotes convert as people type. With nothing else set, that plugin would also turn `->` into an arrow, `<=` into a symbol, and `--` into an en dash even in American English, so Sorts narrows it to what its own rules do.

What CKEditor allows, and what Sorts makes of it:

- The editor’s config reaches the browser as JSON, and JSON cannot carry a regular expression, so Sorts can only switch on CKEditor’s built-in transformations by name.

- Quotation marks: the key typed picks the language’s primary pair (`"`) or secondary pair (`'`), as Sorts does elsewhere, and a key is enabled only where one of CKEditor’s transformations has exactly that pair. By language:
  - Both keys, “…” for `"` and ‘…’ for `'`: `en-US`, `en-GB`, `da`, `es`, `pt`, and `nl` set to `double-outer`.
  - `'` only, as ‘…’: `nb` (its primary «…» is not one of CKEditor’s) and `nl` by default (‘…’ on both levels; CKEditor’s `"` gives “…”).
  - `"` only, as „…”: `pl` (its nested «…» is not one of CKEditor’s).
  - Neither, left straight as typed: `de` (German is book style `»…«` by default, which CKEditor cannot produce; so is its `low-high` variant `„…“`), `de-AT`, `de-DE`, `de-CH`, `cs`, `fr`, `fr-CH`, `it`, `fi`, `sv`, and the variants `single-outer`, `guillemets` and `guillemets-outer`.

  CKEditor’s config cannot carry a pair it does not know by name, so marks left straight are set by the page pass or fix on save later. CKEditor’s `en-GB` pairs put ‘…’ on `'`, which is not what `single-outer` does (it puts ‘…’ on `"`), so Sorts does not use them. On Lite the editor is always English, with both keys.

- CKEditor keeps no memory of nesting: typing `"` inside an open “…” gives “…” again, where Sorts gives a straight `"` typed inside a quotation the other level. Sorts leaves the curly marks CKEditor wrote as they are.

- Typed and pasted text still differ in two cases. `rock 'n' roll` typed gives `rock ‘n’ roll`, because CKEditor pairs the two marks as a quotation, where pasted or saved text gives `rock ’n’ roll`. A quotation of the same kind typed straight inside another, `"She said "no" today"`, gives `“She said ”no" today"` in the editor, because CKEditor closes the quotation at the second `"`; Sorts keeps the curly marks CKEditor wrote, so the page shows `“She said ”no” today”`, where pasted text gives `“She said ‘no’ today”`. Type the inner quotation with `'`, or correct these by hand.

- With *Normalize quotes to house style* on, only `"` is converted while typing: a typed `'` may be an outermost quotation, which depth sets in the primary marks, and CKEditor cannot know that. So `nb` and `nl` (by default) convert nothing while typing.

- Dashes, ellipsis and symbols follow the rules that are on. `--` becomes an en dash only for languages where Sorts’ `--` is a spaced en dash (US English with the `spaced-en-dash` variant included, where `---` is left for Sorts too, since CKEditor would make it an em dash). With *Fine typography* on, CKEditor’s fractions are off, for it would turn the `1/2` of `8 1/2` into `½` and leave the space.

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

**The audit** (CP → Utilities → Sorts) scans entries a hundred at a time, lists those with something to fix with a before and after, and fixes the ones you tick through the queue. It also lists, without a tick box, what Sorts will not change but you may want to: a number with an apostrophe before the s (`1930’s`), under *Flagged, not changed*. The scan only reads. The fix is saved the way Craft’s own resave command saves, so it creates no revision and leaves each entry’s Date Updated alone, unless you tick *Create a revision for each entry and update its Date Updated*. The edition is checked when the job is queued, not when it runs, so a job already queued finishes after a downgrade; it carries the language and rules each site was to be fixed with, so a French entry is still fixed in French. The job also carries the user who queued it and skips any entry that user may not save on that site (Craft’s own `canSave()` check).

## What Sorts assumes

HTML pages rendered by Craft templates. Not handled: JSON and XML responses, emails, PDFs, text that JavaScript inserts after the page loads, hyphenation, and CJK punctuation.

**Hyphenation** is the browser’s job. Use CSS `hyphens: auto` with a correct `lang` attribute (Craft sets one if your layout prints `{{ currentSite.language }}`).

## Known limits

- **An outermost single-quoted quotation gets the secondary marks.** Because `'` is always the secondary pair at the outermost level, `'No,' she said.` becomes `‘No,’ she said.` in en-US, and `“No,” she said.` in en-GB `single-outer`. Switch on *Normalize quotes to house style* to have the nesting depth decide instead.
- **A second pass with apostrophes or primes switched off.** With the apostrophes rule (or the primes rule) off, a straight mark Sorts reads as an apostrophe (or a prime) stays straight, and a second pass reads it beside the curly marks the first pass wrote, so it can take it for a quotation mark. This can happen in any language. In one generated sweep of 1,500 mixed inputs per language, 4 to 15 inputs per language and mode changed on a second pass with apostrophes off (38 to 43 in French and Swiss French), and at most one with primes off; with the default rules, none did. The inputs found are machine-made, most with a straight `'` right beside a curly ’ or ”.
- **A lone mark at the start of a word inside a quotation.** The word-initial apostrophe rule applies only where no quotation is open, so a lone `'` opening a word inside a quotation (`"He's from the 'hood," she said`) is still read as a quotation that was never closed, and gets an opening mark (‘hood). Type ’ (U+2019) for it, or wrap the text in `data-sorts="off"`.
- **A word-initial apostrophe that opens a paragraph.** A `'` at the very start of a block (a paragraph, a list item, a heading, or after a line break) is an opening quotation mark, because a quotation that runs over several paragraphs opens each one and closes only the last. A Navajo-style word that opens a paragraph (`'Áshįįh 'Asdzáán` as its first words) is read as a quotation that was never closed: it becomes `‘Áshįįh “Asdzáán`. Type ’ (U+2019) or the modifier letter ʼ (U+02BC) for it, or wrap the text in `data-sorts="off"`.
- **A numerical plural keeps its apostrophe.** `1930's` becomes `1930’s`, and Sorts does not take the apostrophe out: it cannot tell a plural (the 1930s) from a possessive (1930’s best film). The audit lists them under *Flagged, not changed*, and the engine reports them (see [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md)). Bringhurst §5.4.3 asks for `1930s`.
- **A spaced hyphen between numbers is a break in the dash variant.** With *US English* set to `spaced-en-dash`, `5 - 3` becomes `5 – 3`; only an equation (`5 - 3 = 2`) is recognised as a minus. Wrap other arithmetic in `data-sorts="off"`.
- **A whole number in another element does not join its fraction.** `<b>8</b> 1/2` gives `8 ½`; the fraction joins only a number in the same run of text.
- **A straight mark closing a curly quotation of another shape.** A straight mark that closes a quotation opened with a curly mark of another shape can get the wrong glyph: Italian `«Disse “sì" e partì»` becomes `«Disse “sì» e partì»`. Type both marks the same way. A foot mark or a plural possessive is not taken for such a closer: in en-GB `single-outer`, `“He said ‘I am 6' tall,”` gives `6′` and `‘the dogs' bowls` gives `dogs’`.
- **A plural possessive inside inline markup, ahead of a foot mark.** Inside a quotation opened with a curly ‘, a straight `'` after a digit ends the quotation unless its closing ’ is still to come, and a later plural possessive (`the players’ bus`) is not counted as that closer. Sorts reads that possessive past a tag or an entity after it (`the players’ <a href="…">bus</a>`, `the players’&nbsp;bus`), but not inside a later element: in `‘The final score was 3' and <em>the players’</em> bus left early.` the ’ in `<em>` is counted, so `3'` becomes a foot mark (`3′`). Type ’ (U+2019) for the closer, or move the tag off the possessive.

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
