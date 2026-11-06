# Sorts — build spec

Status: **approved for build, 2026-11-05.** Every decision is settled (§9).

## 1. The problem

Editors type and paste straight quotes (`"`, `'`). Craft 5 does nothing about them:

- CKEditor 5 converts quotes only as they are **typed**, and only when its `TextTransformation` plugin is
  loaded, which `craftcms/ckeditor` leaves off by default. Pasted text keeps whatever it came with.
- Titles and plain-text fields get no conversion at all.
- Twig escapes `"` as `&quot;` and `'` as `&#039;`, so the straight characters reach the page as entities.

Some typefaces make this worse than ugly. Palanquin (Google Fonts, v1.0.4, checked 2026-11-04) draws `"` with
the **identical outline** to `”` and `'` identical to `’`. On such a font a straight quote looks right at the
end of a quotation and **backward at the start**, and editors fix the opening ones by hand, one at a time.

The free plugins (Typogrify, ~5.7k installs; Wordsmith, ~5.2k; both Craft 5) are **Twig filters**: someone
has to add them to every template that prints text, and nothing fixes the content itself. No Craft 5 plugin
applies typography site-wide on its own (Store API and web search, 2026-11-05).

**Sorts’ pitch:** install it and every page is typeset correctly — titles, every field type, hard-coded
template text — with no template edits; and in the control panel editors get the right characters as they type.

## 2. Editions (decided, Dan 2026-11-05)

Ascending ladder, one edition per install (`Plugin::editions()`); every gate goes through `Plugin::isPro()`.
Lite holds only what the market already prices at zero.

| | Lite (free) | Pro (paid) |
|---|---|---|
| Twig filter `\|sorts` and `{% filter sorts %}` (English quotes, apostrophes, dashes, ellipsis) | ✓ | ✓ |
| CKEditor as-you-type quotes (load `TextTransformation`) | ✓ | ✓ |
| **Automatic whole-page pass** (no template edits) | | ✓ |
| Every rule in §3 and every locale in §4 | | ✓ |
| Exclusions (§5.4) | | ✓ |
| Fix-on-save and the audit/fix utility (§6) | | ✓ |
| GraphQL directive `@sorts` | | ✓ |

Edition gates control features, never safety: a downgrade to Lite stops the automatic pass; it never leaves
content half-converted.

## 3. Rules (Pro covers all of them; Dan: “PRO covers all advanced typography problems”)

Each rule has an on/off setting; the default column is the out-of-box state.

| Rule | Example | Default |
|---|---|---|
| Double and single quotes, nested | `"She said 'no'"` → `“She said ‘no’”` | on |
| Apostrophes, including leading | `don't`, `'90s`, `rock 'n' roll` → `don’t`, `’90s`, `rock ’n’ roll` | on |
| Quotes next to dashes, brackets, inline tags | `—"Yes"`, `("no")`, `<i>"x"</i>` | on |
| Primes for feet/inches and minutes/seconds | `5'10"` → `5′10″`; `45°30'` → `45°30′` | on |
| Em and en dashes | `--` → `—` (or spaced `–`, by locale); `---` → `—` | on |
| Number ranges | `1-5`, `pages 10-12` → `1–5` | off (phone numbers, ISBNs, SKUs) |
| Ellipsis | `...` → `…` | on |
| Symbols | `(c)` `(r)` `(tm)` → `©` `®` `™` | on |
| Multiplication | `3x4`, `1920 x 1080` → `3×4`, `1920 × 1080` | off |
| Fractions | `1/2` → `½` (only the Unicode set; never dates or URLs) | off |
| Ordinals | `1st` → `1<sup>st</sup>` | off |
| Widows | non-breaking space between the last two words of headings and paragraphs (minimum word count setting) | on for headings |
| Non-breaking spaces | number + unit (`10 kg`), initials, honorifics (`Dr. Smith`), `§ 5` | on |
| Locale spacing | French narrow no-break space before `; : ! ?` and inside `« »` | per locale |
| Styling hooks | wrap `&` in `<span class="amp">`, runs of caps in `<span class="caps">`, an opening quote in `<span class="dquo">`/`<span class="quo">` for hanging punctuation | off |

**Not in scope:** hyphenation (use CSS `hyphens: auto` with a correct `lang`; the README says so), CJK
punctuation, number formatting.

## 4. Locales

Rules are **data**: one rule set per locale (quote pairs, nested pairs, dash style, spacing). The page pass
picks the set from the site’s language and from any `lang` attribute on an element (nearest ancestor wins).
First release: `en-US`, `en-GB` (single quotes outermost), `de` (`»…« ›…‹`, book style, by D844; CLDR’s `„…“ ‚…‘` is the variant `low-high`), `de-CH`/`fr-CH` (`«…» ‹…›`), `fr`
(`« … »` with narrow no-break spaces), `es`, `it`, `pt`, `nl`, `sv`/`fi` (`”…”`), `da`/`no`, `pl`
(`„…”`), `cs`. The examples above are a guide only: take each locale’s quote pairs from Unicode CLDR’s
`delimiters` data and its spacing from CLDR or the locale’s style authority, and record the CLDR version in
the README. Unknown languages fall back to the site’s default set, then `en-US`.

Every locale ships in Pro (D812, Dan 2026-11-05). Build them as rule-set data behind one interface anyway, so a
locale is a file, not code.

**Where CLDR and a style authority disagree** (D816, Dan 2026-11-05: “This is also how we should treat all
inconsistencies”), CLDR is the default and the other convention ships as a **named variant** of that locale,
chosen by a setting. A variant overrides only the keys it changes and cites its authority. First variant:
`en-GB` `single-outer` (`‘…’` outside, `“…”` nested; Oxford/New Hart’s Rules book style); CLDR 49’s `“…” ‘…’`
stays the default. Every other divergence found becomes a candidate variant, listed for Dan before it is built.

## 5. Architecture

### 5.1 Engine (`src/engine/`, no Craft dependency)

- Pure PHP 8.2+, unit-testable without Craft. Input: a string of HTML (or plain text) plus a rule set;
  output: the same string with only text runs changed.
- **Write our own engine.** `mundschenk-at/php-typography` is GPL-2.0-or-later and cannot ship inside a
  Craft-License plugin. `michelf/php-smartypants` (BSD-3, last release 2016) may be read as a reference, with
  attribution in `NOTICE` if any logic is borrowed; it misses leading apostrophes and quotes after dashes and
  brackets, which our corpus must cover.
- **HTML handling:** a streaming tokenizer that splits markup from text and edits text runs only. Never
  re-serialize through `DOMDocument` (it rewrites HTML5, entities and whitespace). A page with nothing to
  convert must come out **byte-identical** (fuzz-tested).
- **Entities:** treat `&quot;`, `&#34;`, `&#x22;`, `&#039;`, `&#39;`, `&#x27;`, `&apos;` in text as straight
  quotes; leave every other entity alone. Output real characters (UTF-8), not entities.
- **Context across tags:** inline elements (`a`, `i`, `em`, `b`, `strong`, `span`, `sup`, `sub`, `small`,
  `mark`, `abbr`, `cite`, `q`, `u`, `s`) are transparent when deciding a quote’s direction; block elements and
  `<br>` are boundaries.
- **Never touched:** attributes; the contents of `script`, `style`, `pre`, `code`, `kbd`, `samp`, `var`,
  `textarea`, `math`, `svg`, `template`, `noscript`; HTML comments; anything inside an excluded element (§5.4).
- Performance budget: ≤ 5 ms for a 100 KB page on PHP 8.2 (measure and record in the README).

### 5.2 Automatic page pass (Pro)

- `craft\web\View::EVENT_AFTER_RENDER_PAGE_TEMPLATE`; site requests only (never CP, console or action
  requests); only when the response format is HTML.
- Runs on preview and token requests too, so a preview matches the live page.
- Template caches (`{% cache %}`) and static-cache plugins store the converted output, because the pass runs
  on the rendered page; say so in the README.
- `<title>` text and `meta` `content` attributes: off by default, one setting turns them on (they show in
  browser tabs and link previews).

### 5.3 Lite pieces

- Twig filter `|sorts` (HTML-aware; a plain-text mode for attribute and JSON contexts: `|sorts('text')`).
- CKEditor: `CkeditorConfig::registerFirstPartyPackage(['TextTransformation'])` when `craftcms/ckeditor` is
  installed; no-op otherwise. Verify against the installed `craftcms/ckeditor` version and note it.

### 5.4 Exclusions (Pro)

- `data-sorts="off"` on any element skips it and its subtree; `data-sorts-lang="…"` overrides the locale.
- Settings: extra tags or class names to skip; URI patterns to skip (e.g. `/api/*`).
- The Pro part of this section is the settings. `data-sorts="off"` and `data-sorts-lang` are safety controls and
  work in every edition (gates control features, never safety; orchestrator decision, fix round 1, 2026-11-05).

### 5.5 Extension API (Pro, first release; D813, Dan 2026-11-05)

Sites have their own domain conversions, for example a recipe site that turns scaled decimal quantities into
Unicode fractions (`0.875` → `⅞`, `1.5` → `1½`) except before metric units and ounces. Those belong in the
site’s own code, but they should run through Sorts rather than beside it:

- **Register a rule:** `Sorts::EVENT_REGISTER_RULES` lets a module or plugin add a class implementing
  `RuleInterface` (`handle()`, `apply()` over a text run with its context, `priority()`, `defaultEnabled()`,
  locale-aware). Built-in rules (§3) use the same interface, so the API is exercised from day one.
- **Scope a rule:** a registered rule runs (a) everywhere in the page pass, (b) only inside elements marked
  `data-sorts-rules="<handle> …"`, or (c) only when called by name — `|sorts(rules: ['<handle>'])` in Twig,
  or `Sorts::getInstance()->engine->apply($text, ['<handle>'])` from PHP, so non-page output (CSV, XLSX, JSON)
  gets the same result as the page.
- **Order and protection:** rules run by priority; a rule can mark the characters it produced as settled so
  later rules leave them alone (a range-dash rule must not touch `1-1/2` once a fraction rule has handled it).
- **Edition:** Pro to start (one-way door: it can move to Lite later, never back).
- **Acceptance:** a fixture rule registered from a test module passes all three scopes, and its output is
  identical between the page pass and the PHP call.

## 6. Fixing stored content (Pro)

The page pass fixes what visitors see; feeds, APIs, search and exports read stored content. Two tools:

- **Fix-on-save** (off by default): on element save, run the engine over the title and over PlainText and
  CKEditor field values (text runs only; never slugs, URIs, handles, URLs, emails or code fields). Per field
  type toggles. Must not fire during resaves, propagation or migrations unless asked.
- **Audit utility** (CP → Utilities → Sorts): scan entries for convertible characters, list them with a
  before/after preview (dry run), and fix the selected ones through a queue job. The fix must not create
  revisions or change `dateUpdated` unless the user ticks a box. Read `craft-plugin-base/docs/PATTERNS.md`
  §§11–17 before writing this.

## 7. Assumes (README)

HTML pages rendered by Craft templates. Not handled: JSON/XML responses (RSS opt-in is a later release),
emails, PDFs, text inserted by JavaScript after load, hyphenation, CJK.

## 8. Tests and build mode

- **Engine corpus:** at least 400 cases across the §3 rules and every §4 locale, including the hard ones:
  leading apostrophes, nested quotes, quotes beside inline tags and after em dashes, primes vs closing quotes,
  entity forms, already-curly text left alone (idempotence: running twice equals running once).
- **HTML fuzz:** random real-world pages through the tokenizer with all rules off → byte-identical output.
- **Craft smoke:** Lite and Pro in your own testbed (never a client site): the page pass on, off and excluded;
  preview requests; the CKEditor package registered; fix-on-save and the audit utility on fixture entries.
- **Build mode: release.** Sorts will run on production sites, so `dgbuilder` builds, then a `gate` at
  `xhigh` with mutants, fix rounds per the casting rules, and the README’s `**Hardened:**` line.

## 9. Decisions (fleet ledger, claude-ops `DECISIONS-OPEN.md`)

- **D812 — locales: decided 2026-11-05 (Dan: y).** Every locale ships in Pro; no per-language add-ons.
- **D813 — extension API (§5.5): decided 2026-11-05 (Dan: y).** In the first release, in Pro.
- **D815 — public repo: done 2026-11-05 (Dan: y).** `github.com/dgaidula/craft-sorts`.
- **D816 — CLDR divergences: decided 2026-11-05 (Dan).** CLDR is the default; a style authority’s convention is a
  named, switchable locale variant (§4). First one: `en-GB` `single-outer`.
- **D817 — a Bringhurst preset (Pro): open, proposed 2026-11-05.** One Pro setting, “Fine typography (Bringhurst)”,
  that switches the §3 rules to the choices in Robert Bringhurst’s *The Elements of Typographic Style* (for
  example spaced en dashes instead of close-set em dashes). Each choice cites the section number of the **3rd
  edition** (Dan’s copy; he does not own the 4th), from photos of his pages; nothing is built from memory.
- **D818 — quote type: decided 2026-11-05 (Dan: a).** Keep the quote type the author typed: a straight `"` becomes
  the locale’s primary pair and `'` its secondary (nested) pair, as CKEditor does while typing, so text typed in
  the control panel and text pasted in agree. Normalising every quotation to house style by nesting depth (the
  first build’s behaviour) becomes a Pro setting, off by default.
- **D819 — five more locale variants: decided 2026-11-05 (Dan: a).** Per D816, each with its authority confirmed by
  a source search before it is built: `de-CH` guillemets `«…» ‹…›`; `de` book style `»…« ›…‹` (Duden); `fr` and
  `fr-CH` with `“…”` nested inside `«…»` (Imprimerie nationale); `es` `«…»` outside, `“…”` nested (RAE); `nl`
  `“…” ‘…’` (Taalunie). **Built (`81e35a2`):** `de-CH` `guillemets` (Swiss Federal Chancellery, *Schreibweisungen*
  2013/2015, rules 202–203, read at source) and `es` `guillemets-outer` (RAE *Ortografía* 2010; rae.es refused
  automated reads, so built on consistent secondary sources quoting the RAE). Following the same authority’s order
  « “ ‘, `es` `guillemets-outer` also carries a third-level pair `‘…’` (`4411307`): depth 3 uses it, and deeper
  levels alternate second and third; locale data without a third pair is unchanged. **Dropped:** `fr`/`fr-CH` nested `“…”`
  (the *Lexique* itself, 3rd ed. 1990, nests by repeating « », as CLDR does). The other two became D823 and D824.
- **D822 — nested quotes of the same kind: decided 2026-11-05 (Dan: y).** A quotation inside another takes the other
  level, whatever was typed; only the outermost keeps the typed type (D818), so `"She said "no" today"` gives
  `“She said ‘no’ today”`.
- **D823 — `de` book style: decided 2026-11-05 (Dan: y, “I prefer german book style”).** Variant `»…« ›…‹`, citing
  Dan (typographer) and *Duden Band 9* (5th ed., 2001); per D816 CLDR’s „…“ stays `de`’s default.
- **D824 — `nl` double-outer: decided 2026-11-05 (Dan: y).** Variant `“…” ‘…’`, citing taaladvies.net (Taalunie, 2021).
- **D829 — second fix round for D818–D824: decided 2026-11-05 (Dan: y); landed `c7f6856`, `94880b6`.** Two rules
  as the orchestrator ruled them after the fixer measured the literal versions:
  - **A straight closing mark** closes the innermost open quotation and takes its closing glyph **when that quotation
    was opened with a curly mark** (`«Disse “sì" …»` → `“sì”`); a quotation opened with a straight mark still closes
    by kind. Applied literally, the rule changed 1,209 generated rows in every locale and broke ordinary dialogue
    (`"He's from the 'hood," she said. "Yes," he said.` pushed every later quotation down a level).
    **Backed out under D843 (fix round 3, 2026-11-06):** a straight closing mark closes by kind again, as at
    `9822af0`. The narrowed rule brought the same failure back whenever the stray mark was curly: a word processor’s
    wrong-way apostrophe (`‘90s`, `‘til`, `‘s avonds`, `‚n`) inside straight-quoted dialogue opened a curly
    quotation that took the closing mark, and every later quotation dropped a level (re-check 2, R3-1). The mixed
    case it fixed (`«Disse “sì" e partì»` → `«Disse “sì» e partì»`) is a README Known limit instead.
  - **An inch mark, foot mark or possessive** is judged against one quotation, not every open one: the innermost,
    or past one opened straight of the other kind the nearest of the mark’s kind; checking all of them turned real
    closing marks into primes (`“He said ‘I was 6' then.”`).
- **D817 — Bringhurst preset: decided and built 2026-11-06 (Dan: y).** Pro setting `fineTypography` (engine preset `bringhurst`): the `spaced-en-dash` variant (§5.2.1), `typed-dashes` (§5.2.1), `dialogue-dash` (§5.2.3), ranges with clock times (§5.2.2), fractions with the join (§5.2.5), multiplication (§5.2.6), `ellipsis-spacing` in English (§5.2.7), thin spaces inside guillemets except German (§5.4.1), cited to *The Elements of Typographic Style*, 3rd ed. A rule switch moved off its default and a chosen variant win over it. The proposal text under D817 above is superseded.
- **D844 — German defaults to book style: decided 2026-11-06 (Dan: y), built.** `de` `»…« ›…‹`; CLDR’s `„…“ ‚…‘` is the variant `low-high`; `de-AT`, `de-DE` inherit, `de-CH` keeps CLDR’s. The one default departure from CLDR (an exception to D816); the generator records the reason.
- **D855 — spaced en dash variant: decided 2026-11-06 (Dan: a), built.** `en-US` `spaced-en-dash` for `--`, `---` and a spaced hyphen; the default stays Chicago’s closed em dash.
- **D856 — typed em dashes in the Bringhurst preset: built** as rule `typed-dashes` (preset only), except a dash opening a block, a dash run and a dash touching a quotation mark.
- **D857 — thin spaces inside guillemets in the preset: built**, German excepted (Duden, D823).
- **D858 — period-less honorific: built** in `nbsp`, in every preset, with a guard for `St` as Street.
- **D859 — numerical plurals: built** as `Engine::findings()` and the audit’s *Flagged, not changed*; never changed.
- **D814 — editions and price: decided 2026-11-05 (Dan: y).** Lite free; Pro **$39 + $19/yr** at launch (paid
  installs are flat across price bands, and freemium newcomers out-install paid-only ones; both prices can rise
  later, since Craft honours only the renewal price in the 30-day reminder).
