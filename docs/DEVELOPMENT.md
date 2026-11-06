# Sorts — Development Guide

This guide is for developers who want to customize Sorts, build an
integration against it, or contribute to it. For install-and-use documentation,
start with the [README](../README.md).

## Table of contents

- [Overview](#overview)
- [Architecture](#architecture)
- [The typography engine](#the-typography-engine)
- [The editions model](#the-editions-model)
- [Every Pro gate](#every-pro-gate)
- [Testing](#testing)
- [The config layer](#the-config-layer)
- [Developing against a host site](#developing-against-a-host-site)
- [Coding standards and layout](#coding-standards-and-layout)

---

## Overview

> **Requirements:** Craft CMS 5.0+ and PHP 8.2+. Sorts is a Craft 5
> plugin and does not run on Craft 4.

Sorts sets the typography of a Craft site: smart quotes, apostrophes, primes, dashes and
more. A pure-PHP engine (`src/engine/`) does the work; the Craft layer around it is the
automatic page pass, the `|sorts` Twig filter, CKEditor as-you-type quotes, settings,
exclusions, the register-rules extension API, the `@sorts` GraphQL directive, fix-on-save
and the audit utility. Lite has the filter and the CKEditor quotes; the rest is Pro.

## Architecture

### The `Plugin` bootstrap

`src/Plugin.php` is the whole wiring diagram.
`Plugin::config()` declares four components, `Plugin::init()` registers, via Yii events:

- **Components** — `engine` (`services\EngineService`), `pagePass`
  (`services\PagePass`), `contentFixer` (`services\ContentFixer`) and `auditScanner`
  (`services\AuditScanner`).
- **Twig** — the `sorts` filter (`web\twig\SortsExtension`, with `RawInputVisitor`).
- **Page pass** — `View::EVENT_AFTER_RENDER_PAGE_TEMPLATE`, on site requests only.
- **Fix-on-save** — `Elements::EVENT_BEFORE_SAVE_ELEMENT`.
- **GraphQL** — `Gql::EVENT_REGISTER_GQL_DIRECTIVES` → `gql\directives\Sorts`.
- **Utility** — `Utilities::EVENT_REGISTER_UTILITIES` → `utilities\AuditUtility` (Pro only).
- **CKEditor** — `ckeditor\CkeditorSupport::register()`, control panel requests only, when
  `craftcms/ckeditor` is installed.
- **Template roots** — `src/templates/` is registered as the `sorts` root
  for the **CP** only. No site template root is registered by default: it would
  make every CP template routable on the front end. If the front end needs
  plugin templates, register a narrow folder (`templates/_site`) only.
- **Permissions** — `sorts:manageConfig`.

`init()` also applies the optional **plugin rename**:
`Settings::$pluginName` relabels the plugin everywhere in the CP (sidebar,
Plugins screen, settings breadcrumb). Blank keeps "Sorts".

### The pieces, walked

- **`models/Settings.php`** — global settings, stored in **project config**. Values can
  arrive as arrays, `''` or strings from the control panel, project config or
  `config/sorts.php`, so `setAttributes()` coerces each to its type before the typed
  property is set, and `rules()` validates what is left (PATTERNS §15). Lists accept a list or
  one value per line. There are no secrets in Sorts.
- **`services/EngineService.php`** (`Plugin::getInstance()->engine`) — builds the engine
  once per request, fires `Plugin::EVENT_REGISTER_RULES` (Pro), and turns settings and
  edition into the engine’s `Options`. Lite gets a fixed option set (quotes, apostrophes,
  primes, dashes, ellipsis; `en-US` and `en-GB` only, by handing the engine a locale
  registry that holds just those), with those five as the engine’s allow-list (`Options::$allowed`),
  so no `data-sorts-rules` scope or rule named in a call can run another. Everything that sets typography goes through
  `apply()` or `applyWith()`, so the page, the filter, GraphQL, a PHP call and the stored
  content tools agree. `storedOptions()` limits the rules to `STORED_RULES` (and allows only those);
  `applyStored()` runs stored-content options on an engine with every built-in locale, so a job
  queued under Pro still fixes in the right language after a downgrade.
- **`services/PagePass.php`** — the whole-page pass: what counts as a page to fix, the
  skip-URI matching, the head and `<meta>` handling, the size and memory guards
  (`maxPageBytes`, `MEMORY_PER_BYTE` against `memory_limit`), the warning for a page the
  engine could not read (`Engine::lastError()`), and the fail-safe `catch (Throwable)`.
- **`services/ContentFixer.php`** — title and field values: `changes()` (what would change),
  `apply()` (put it on the element), and the fix-on-save handler with its skips. A value is
  converted only when every site it reaches on the save shares the saving site’s locale and
  variant (`otherSites()`, `onlySameLocale()`; the Craft source lines are cited there).
- **`services/AuditScanner.php`** — the audit’s dry run, a batch of entries at a time, and
  the before-and-after excerpts.
- **`jobs/FixEntriesJob.php`**, **`controllers/AuditController.php`**,
  **`utilities/AuditUtility.php`** and **`templates/utility.twig`** — the audit’s fix.
- **`web/twig/SortsExtension.php`** — the filter. See *The filter’s escaping* below.
- **`gql/directives/Sorts.php`** — `@sorts`.
- **`ckeditor/CkeditorSupport.php`** — registers `TextTransformation` and narrows it.
- **`events/RegisterRulesEvent.php`** — the extension API’s event.

### The filter’s escaping

The brief for the filter is that HTML mode never turns unescaped input into safe output.
`pre_escape: 'html'` with `is_safe: ['html']` does that for variables, but Twig treats
a string literal in a template as safe, so `{{ '<script>'|sorts }}` would print raw, and
the same declaration would also pre-escape and mark safe the plain-text mode that JSON and
attributes need. So the filter has neither option. At run time it trusts only a
`Twig\Markup` value (a rich-text field, an `{% apply %}` body) and escapes anything else
before the engine reads it, then returns `Markup`. Text mode returns a plain string, which
Twig escapes when it prints it. `|raw` hands the filter a plain string and only tells the
compiler it is safe, so `RawInputVisitor` (a Twig node visitor) spots a filter whose input
is a filter Twig compiles as safe for HTML (`|raw`, `|nl2br`, `|e`/`|escape` with the HTML
strategy, `|md`/`|markdown`: `is_safe` or `is_safe_callback` giving `html` or `all`) and gives
that call a declaration that reads its input as HTML. Any other filter, `|upper` included, stays
untrusted. `tests/twig-test.php` pins each of these. Twig does not notice a change to the visitor
in a compiled template (README, Known limits): clear compiled templates after changing it.


## The typography engine

`src/engine/` (namespace `iceboxind\sorts\engine`) is pure PHP with no Craft or
Yii code. The Craft layer (page pass, Twig filter, the register-rules event,
fix-on-save) is wiring over `Engine::apply()`.

```php
$engine = new Engine(new Options(locale: 'en-US'));
$engine->apply($html);                                  // HTML in, HTML out
$engine->apply($text, [], new Options(mode: 'text'));   // plain text: no tags, no markup written
$engine->apply($csvCell, ['recipe-fractions']);         // scope (c): run a rule by name
$engine->register(new MyRule());                        // a site’s own rule
```

### How a page is processed

1. **Tokenize** (`Engine::TOKENS`). One pattern, anchored with `\G`, splits the
   string into text, tags, doctypes and bogus comments, following the HTML5 rules for
   where a tag ends (an attribute value may hold `>`; a stray `</` opens a bogus
   comment). It stops at each comment, CDATA section and raw-text element (`script
   style textarea title xmp iframe noembed noframes`), whose end the walk finds with
   plain searches, so no lazy quantifier meets a megabyte-long body; a script follows
   the HTML5 script-data escape states (`<!--`, then `<script`, hold `</script>` until
   `-->`; `Engine::scriptEnd()`). The string is never parsed into a tree or
   re-serialized: changed text is spliced back in at its byte offset, so what the
   engine does not change comes out byte for byte, and if nothing changes the input
   string comes back. When a pattern fails (a PCRE limit), the input comes back and
   `Engine::lastError()` says why.
2. **Walk.** A stack of `Frame`s tracks the open elements: the locale (nearest
   `data-sorts-lang`, then `lang`), whether the subtree is left alone
   (`data-sorts="off"`, the skip tags and classes, `pre code kbd samp var
   textarea math svg template noscript`) and the rules named by
   `data-sorts-rules`; an `<option>` with no `value` is left alone, since its text
   is what the form submits, and `lang=""` means the caller’s default locale. The
   options’ `allowed` list, when set, caps every way of turning a rule on. The
   `<title>` is text only: no rule that writes markup runs on it. Implied end tags (`<p>` closed by a block, `<li>` by
   `<li>`) are handled; other malformed nesting is repaired by popping to the
   matching name.
3. **Flows.** Text between two boundaries (a block element’s start or end, `<br>`)
   is one `Flow` (its segments in four parallel lists, which keeps a page of many
   short runs cheap), with inline tags (`Html::TRANSPARENT`) looked through. Code
   and images are context: their text counts for deciding a quote’s direction
   but is never edited. Quote nesting and widows are decided on a flow.
4. **Rules.** Each editable text run gets a `Run` (the text and its settled
   marks) and a `Context` (locale, mode, ancestors, the text before and after).
   The rules for that locale and scope run in priority order.

### Rules

Every rule, built in or a site’s own, implements `RuleInterface`: `handle()`,
`priority()`, `defaultEnabled()`, `supportsLocale()`, `emitsMarkup()`,
`triggers()` and `apply(Run, Context)`. Scope follows from `defaultEnabled()`
and the options: a rule that is on runs everywhere; any rule also runs inside
`data-sorts-rules="<handle>"` and when named in the call. Markup-writing rules
(ordinals, the styling hooks) never run in text mode.

| Priority | Handle | Default | Notes |
|---|---|---|---|
| 10 | `symbols` | on | (c) (r) (tm) |
| 20 | `ellipsis` | on | |
| 30 | `dashes` | on | `--` by locale; `---` is an em dash |
| 40 | `fractions` | off | the Unicode set only |
| 50 | `ranges` | off | plain number pairs only |
| 60 | `multiplication` | off | |
| 70, 80, 90 | `primes`, `apostrophes`, `quotes` | on | one shared scan (`QuoteScanner`) |
| 100 | `nbsp` | on | units, initials, honorifics, § |
| 110 | `locale-spacing` | on | only for locales whose data has `spacing` (French) |
| 120 | `widows` | on | headings only (option `tags`, `minWords`) |
| 130 | `ordinals` | off | writes markup; English only |
| 140–142 | `hook-amp`, `hook-caps`, `hook-quotes` | off | write markup |

The quote rules are one scan because their decisions depend on each other (a
quote after a digit is a prime or a closing quote depending on whether a quotation
is open). Which pair a recognised quotation gets is `Options::$quoteNesting`
(`Context::$quoteNesting` in a rule). `typed`, the default and the only mode in
Lite, keeps the quote type typed (D818): an outermost `"` pair gets the locale’s
primary pair and an outermost `'` pair its nested pair, as CKEditor’s
`quotesPrimary` and `quotesSecondary` do. It is level, not glyph shape, so a
variant that swaps the pairs (`en-GB` `single-outer`) swaps what each key gives.
A quotation inside another takes the other level from its parent, whatever was typed
(D822): the scanner keeps each open quotation’s level beside its kind (`QuoteScanner`’s
`$levels`, in one object in the flow state that every run changes in place, so a run
copies nothing and closing a quotation costs only the quotations it closes), a closing
mark takes the level of the quotation it closes, in `typed` and `depth` alike, and a
curly mark already in the text counts as the level its glyph has in the locale and
variant. A glyph that is in both pairs (`fr`, `nl`) counts as primary. A curly mark next
to a straight one is classed by its role in the locale, not its shape (`QuoteScanner::marks()`):
the locale’s opening marks are openers and its closing marks closers, so German “ ‘ and
the `book` style’s « ‹ close; marks the locale does not use keep their English shape.
That holds across an inline tag too, where the mark before the run is the scan’s own
output. A mark that both opens and closes (`fi`, `sv` `”` and `’`) is told by its
sides, as a straight one is: after a space, an opening bracket, a dash or the start and
not before a space it opens (a `’` before `90-` or `tis` stays an apostrophe), after a
word or punctuation with a quotation of its pair open it closes, and otherwise it is
not counted. Inside a quotation opened with a curly mark, its own closing mark still to
come in the flow (more of them than of its opening marks; one that is also the apostrophe
only where no letter follows) makes a straight `"` after a digit an inch mark and a `'`
after an `s` a possessive. A closing mark with nothing open takes the level its own mark
asks for (`typed`) or its depth (`depth`).
`depth` normalises: the outermost level uses the primary pair, the next the
nested pair, then they alternate. A locale may also have a third pair (`third` in its
data; `es` `guillemets-outer` has the RAE’s ‘…’): then the third level uses it, and deeper
levels alternate the nested and third pairs, in both modes. Only the choice of glyph
differs; what is a quotation, an apostrophe or a prime is decided before it and is the
same in both. The Pro setting `normalizeQuotes` selects `depth`; `EngineService::options()`
builds it into every path, `applyWith()` forces Lite’s options on Lite (`typed`, no
variants, Lite’s rules), and an audit job carries it in its options like the variants.

### Settled characters

`Run::replace()`, `edit()` and `replaceAll()` mark what they write as settled
(pass `$settle = false` to opt out) and refuse any later edit that touches a settled
character. The marks are offsets kept beside the text, so nothing is added to
the string and nothing can leak into the output. They last for one call: a
second call over the output sees ordinary text.

### Locales

One PHP file per locale in `src/engine/locales/` (quote pairs, nested pair,
apostrophe, dash style, spacing), read by `LocaleRegistry` the first time a
locale is asked for. Adding a locale is adding a file. The files are generated:

```bash
npm pack cldr-misc-full@49.0.0 && mkdir cldr && tar xzf cldr-misc-full-49.0.0.tgz -C cldr
node bin/cldr-locales.mjs generate cldr/package          # dry run
node bin/cldr-locales.mjs generate cldr/package --go
```

Quote pairs come from CLDR 49.0.0; dash style and spacing come from the style
authority named in each file. To depart from CLDR for a locale outright, set
`PAIR_OVERRIDES` in the generator and regenerate.

**Variants.** CLDR is always the default. Where a respected style authority
disagrees, the other convention is a named variant: the locale’s `variants`
entry lists only the keys it changes (`quotes`, `nested`, `apostrophe`, `dash`,
`spacing`) and cites its authority in a comment. The generator’s `LOCALES`
table is where they are declared (`variants: { 'single-outer': … }` on `en-GB`).
A caller picks one per locale with `new Options(variants: ['en-GB' =>
'single-outer'])`; an unknown name, or a locale without that variant, means the
CLDR default and never an error. Built: `en-GB` `single-outer`, `de` `book`
(`»…«` `›…‹`), `de-CH` `guillemets`, `es` `guillemets-outer` and `nl`
`double-outer`. A variant is added only after its authority is named in the locale
file. `de` `book` rests on Dan’s decision as a typographer (D823) and on Duden Band 9,
cited via a secondary source; Duden’s online rules (D 5–D 12, §79 of the official rules)
give only „…“ ‚…‘, so CLDR’s marks stay `de`’s default. `nl` `double-outer` follows
taaladvies.net (Taalunie, 12 May 2021), which says there is no fixed rule but a traditional
preference for double marks around a literal quotation (D824). Proposed in D819 and not
built: `fr`/`fr-CH` with `“…”` nested, because the Imprimerie nationale’s *Lexique* (3rd ed.,
1990, “Citations de deuxième rang”) nests by repeating « » and uses “…” only exceptionally.
A variant set for `de` or `nl` applies to the regional forms that fall back to them
(`de-AT`, `de-DE`, `nl-BE`).

### Tests and speed

```bash
composer test-engine                  # corpus, unit tests, HTML fuzz; no Craft needed
php tests/engine/run.php corpus      # or: unit, fuzz
php tests/engine/perf.php            # timing on a generated ~100 KB page
```

The corpus (`tests/engine/corpus/*.txt`) records each case’s input, locale, mode,
rules and expected output, and every case is also run a second time on its own
output. See the header of `tests/engine/corpus.php` for the format.
`13-quote-nesting.txt` holds the quote-type cases (every locale and variant, typed and
`depth`); its `-depth` copies of earlier cases keep the expectations from before D818.
`14-same-type-nesting.txt` holds the D822 cases: same-kind nesting, three deep, and a curly
parent, for every locale and variant.

## The editions model

```php
public const EDITION_LITE = 'lite';
public const EDITION_PRO  = 'pro';

/** Single place the Pro gates ask. */
public function isPro(): bool
{
    return $this->is(self::EDITION_PRO);
}
```

Every Pro gate funnels through `Plugin::isPro()`, server-side, at the narrowest choke
point — exactly one question asked in exactly one way. Gates control features, never
safety: a downgrade stops the page pass and queues no new fixes, and never undoes or
half-finishes content.

## Every Pro gate

| Pro capability | Where the gate is | Lite fallback |
|---|---|---|
| Automatic page pass | `PagePass::shouldRun()` | The page is sent as rendered. |
| Every rule and locale, the settings | `EngineService::options()`, `EngineService::engine()`, `EngineService::applyWith()` | Fixed option set: quotes, apostrophes, primes, dashes, ellipsis; `en-US` and `en-GB` only, without their variants. The settings are stored, not read. `applyWith()` puts Lite’s options over any it is given (quotes as typed, no variants, Lite’s rules). `engine()->apply()` with `Options` a caller built itself is not an edition gate for the rules or the quote mode: the engine applies the options it is given (and any PHP can build its own `Engine`); only the locales are Lite’s there. |
| Exclusion settings (skip tags, classes, paths; head and meta) | `EngineService::options()` and `PagePass::shouldRun()` | Not read. `data-sorts="off"` and `data-sorts-lang` are safety controls (PATTERNS §1: gates control features, never safety), so they work in every edition. `data-sorts-rules`, and a rule named in a call, can run only Lite’s own rules: Lite’s options carry `allowed: LITE_RULES`, an allow-list the engine applies after every way of turning a rule on, and the Lite engine is built with it too. |
| Extension API | `EngineService::engine()` fires `EVENT_REGISTER_RULES` only in Pro; `applyWith()` | The event does not fire. A rule named in a call is skipped, not an error. |
| `@sorts` GraphQL directive | `gql\directives\Sorts::apply()` | The directive is in the schema and returns the value unchanged, so queries survive a downgrade. |
| Fix on save | `ContentFixer::onBeforeSave()` | Does nothing. |
| Audit utility | `Plugin::registerUtility()` (not listed) and `AuditController::beforeAction()` (403), at enqueue | Not listed; a post is refused. A job already queued finishes (it runs without asking the edition) with the options it was queued with, per site, on an engine with every built-in locale (`EngineService::applyStored()`), so a French entry is still fixed in French. |
| Pro settings screen | `settings.twig` (`isPro`) | Shown locked and marked Pro; stored values are carried through a Lite save in hidden inputs. |

Not gated: the `|sorts` filter and CKEditor as-you-type quotes (Lite), and anything that
removes access or deletes data (Sorts has neither).

## The config layer

- **`Settings` — PROJECT CONFIG, dev-owned.** Deploy-time values. Locked on
  production when `allowAdminChanges` is off — by design.

## Developing against a host site

The plugin repo contains no Craft installation — you develop it inside any
Craft 5 host site (a DDEV project works well). Point the host site's Composer
at your local clone with a **path repository**, which symlinks the plugin so
edits are live immediately:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../craft-sorts",
      "options": { "symlink": true }
    }
  ]
}
```

Then, from the host site:

```bash
composer require iceboxind/craft-sorts:@dev
php craft plugin/install sorts
php craft up            # applies any pending plugin migrations
```

To try the Pro edition locally:

```bash
php craft project-config/set plugins.sorts.edition pro --update-timestamp
```

### Dev-loop gotchas

- **The testbed** (`~/sw/github-private/craft5-plugin-testbed`) has the pieces the suites
  need: this repo bind-mounted at `plugins/craft-sorts`, `craftcms/ckeditor`, a French site
  (`testbedFr`), the `sortsTest` section and fields (`scripts/sorts-fixtures.php` rebuilds
  them), the `sortstest` module (the fixture rule, an action that renders a template) and the
  `sorts-*.twig` templates and routes.

- **Edition:** `php craft project-config/set plugins.sorts.edition pro --update-timestamp`,
  then restart PHP-FPM (`ddev restart`) — workers keep the old edition. From
  `craft shell`, call `saveModifiedConfigData()` yourself. There is no
  `plugin/switch-edition` command.
- **Path repo:** `composer reinstall iceboxind/craft-sorts` pulls your edits;
  `composer update` does not re-mirror. On a Mutagen DDEV site a host symlink
  arrives broken — bind-mount the repo instead.
- **After installing anything you test against** (the plugin, Blitz), run `php craft up`.
- **PHP binary:** lint and run Composer under the PHP the plugin targets; a bare
  `php` in a non-interactive shell can resolve to an old MAMP or system PHP (7.4)
  and false-fail 8.2 syntax.
- **Composer in the plugin repo itself** (for `composer check`): `composer install`
  needs `config.allow-plugins` for `craftcms/plugin-installer` and
  `yiisoft/yii2-composer` (the template sets both). With those installed,
  `composer validate` fails with “must be absolute paths” (seen with
  plugin-installer 1.6.0 on Composer 2.9.5), so the `check` script runs it with
  `--no-plugins`.

**When you change the schema:** bump `Plugin::$schemaVersion`, add a migration
under `src/migrations/` (`php craft migrate/create <name> --plugin=sorts`
scaffolds one), mirror the change in `Install.php` for fresh installs, and keep
the migration idempotent.

## Testing

```bash
composer check                 # validate, lint, cs, PHPStan (under /opt/homebrew/bin/php)
composer test-engine           # the engine: corpus, unit tests, HTML fuzz; no Craft
node tests/run-all.mjs         # the Craft layer, against the testbed (ddev must be running)
```

Each suite in `tests/*-test.php` exercises Lite and Pro by switching the edition itself
(`tests/support.php`: `sortsConfigure('pro', [...])`), so one `run-all` covers both. In the
testbed, an edition or settings change written to project config was seen by the very next web
request (checked 2026-11-05), so the suites do not restart ddev; if a worker ever serves the old
edition, `ddev restart`.

## Coding standards and layout

- **PHP** ≥ 8.2, **Craft** ^5.0 — the only runtime requirements.
- **PSR-4** — everything under `src/` maps to the `iceboxind\sorts\`
  namespace.
- **Code style** — `php-cs-fixer` (via the dev-only shim), configured in
  `.php-cs-fixer.dist.php`:

```bash
composer cs-check   # dry-run with a diff
composer cs-fix     # apply
```

- **One command before a PR** — `composer check` runs `composer validate
  --strict`, `parallel-lint` over `src/`, the cs-fixer dry run and PHPStan
  (level 5, via `craftcms/phpstan`, configured in `phpstan.neon`). Run it under
  the PHP the plugin targets (see the PHP-binary gotcha above).
- **Integration tests** — `tests/` holds a harness that runs against your own
  Craft testbed, never a client site; see [`tests/README.md`](../tests/README.md).
  `composer test-engine` runs the engine’s tests without Craft.

- **House rules worth knowing before a PR:**
  - Pro gates go through `Plugin::isPro()`, server-side, at the narrowest
    choke point.
  - Secrets are never stored or rendered; credential settings accept `$ENV_VAR`
    references resolved server-side with `App::parseEnv()`.

### `src/` directory map

```
src/
├── Plugin.php                     # Bootstrap: components, events, permissions; the Pro gate
├── icon.svg
├── engine/                        # Typography engine: pure PHP, no Craft (see above)
│   ├── Engine.php                 # Tokenize, walk, run rules; register(), apply()
│   ├── Options.php                # Locale, mode, rule switches and settings, skips, quote nesting
│   ├── RuleInterface.php          # The one interface for built-in and site rules
│   ├── Run.php                    # Editable text plus settled marks
│   ├── Context.php                # What a rule sees around its run
│   ├── Flow.php, Frame.php, Pass.php, Html.php   # Walk internals and element tables
│   ├── LocaleInterface.php, DataLocale.php, LocaleRegistry.php
│   ├── locales/                   # One generated data file per locale (CLDR 49.0.0)
│   └── rules/                     # The built-in rules and QuoteScanner
├── services/
│   ├── EngineService.php          # `Plugin::getInstance()->engine`: options, rules event, apply()
│   ├── PagePass.php               # The whole-page pass
│   ├── ContentFixer.php           # Title and field values: fix-on-save and the audit’s fix
│   └── AuditScanner.php           # The audit’s dry run
├── events/RegisterRulesEvent.php  # The extension API’s event
├── web/twig/                      # The sorts filter and its RawInputVisitor
├── gql/directives/Sorts.php       # @sorts
├── ckeditor/CkeditorSupport.php   # As-you-type quotes
├── utilities/AuditUtility.php     # CP → Utilities → Sorts
├── controllers/AuditController.php
├── jobs/FixEntriesJob.php
├── migrations/
│   └── Install.php                # Install migration
├── models/
│   └── Settings.php               # Global settings (project config)
└── templates/
    ├── settings.twig              # CP: settings (headed sections, no tabs)
    └── utility.twig               # CP: the audit

bin/cldr-locales.mjs               # Generates src/engine/locales from CLDR
tests/engine/                      # Engine tests: run.php, corpus/, unit.php, fuzz.php, perf.php
tests/*-test.php, tests/support.php   # Craft-layer suites, run by tests/run-all.mjs
```
