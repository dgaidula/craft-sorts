# Sorts — Development Guide

This guide is for developers who want to customize Sorts, build an
integration against it, or contribute to it. For install-and-use documentation,
start with the [README](../README.md).

## Table of contents

- [Overview](#overview)
- [Architecture](#architecture)
- [The typography engine](#the-typography-engine)
- [The editions model](#the-editions-model)
- [The config layer](#the-config-layer)
- [Developing against a host site](#developing-against-a-host-site)
- [Coding standards and layout](#coding-standards-and-layout)

---

## Overview

> **Requirements:** Craft CMS 5.0+ and PHP 8.2+. Sorts is a Craft 5
> plugin and does not run on Craft 4.

Describe what the plugin does and its primary usage pattern here.

## Architecture

### The `Plugin` bootstrap

`src/Plugin.php` is the whole wiring diagram.
`Plugin::init()` registers, via Yii events:

- **Twig variable** — `craft.sorts` → `web\twig\PluginVariable`.
- **Template roots** — `src/templates/` is registered as the `sorts` root
  for the **CP** only. No site template root is registered by default: it would
  make every CP template routable on the front end. If the front end needs
  plugin templates, register a narrow folder (`templates/_site`) only.
- **Permissions** — `sorts:manageConfig`.

`init()` also applies the optional **plugin rename**:
`Settings::$pluginName` relabels the plugin everywhere in the CP (sidebar,
Plugins screen, settings breadcrumb). Blank keeps "Sorts".

### The pieces, walked

- **`models/Settings.php`** — global settings, stored in **project config**.
  Credential-shaped fields accept a literal or a `$ENV_VAR` reference, resolved
  server-side with `craft\helpers\App::parseEnv()` — no secrets are ever
  bundled or shipped to the browser.
- **`web/twig/PluginVariable.php`** — exposes `craft.sorts.*`.


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

1. **Tokenize** (`Engine::TOKENS`). One pattern splits the string into text, tags,
   comments, CDATA, doctypes and raw-text elements (`script style textarea title
   xmp iframe noembed noframes`), following the HTML5 rules for where a tag
   ends (an attribute value may hold `>`; a stray `</` opens a bogus comment).
   The string is never parsed into a tree or re-serialized, so what the engine
   does not change comes out byte for byte. If nothing changes, the input string
   comes back. Every byte must belong to a token, or the engine returns the input.
2. **Walk.** A stack of `Frame`s tracks the open elements: the locale (nearest
   `data-sorts-lang`, then `lang`), whether the subtree is left alone
   (`data-sorts="off"`, the skip tags and classes, `pre code kbd samp var
   textarea math svg template noscript`) and the rules named by
   `data-sorts-rules`. Implied end tags (`<p>` closed by a block, `<li>` by
   `<li>`) are handled; other malformed nesting is repaired by popping to the
   matching name.
3. **Flows.** Text between two boundaries (a block element’s start or end, `<br>`)
   is one `Flow`, with inline tags (`Html::TRANSPARENT`) looked through. Code
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
is open). Quote depth, not the typed character, picks the pair: the outermost
level uses the locale’s primary pair, the next its nested pair, then they
alternate.

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
CLDR default and never an error.

### Tests and speed

```bash
composer test-engine                  # corpus, unit tests, HTML fuzz; no Craft needed
php tests/engine/run.php corpus      # or: unit, fuzz
php tests/engine/perf.php            # timing on a generated ~100 KB page
```

The corpus (`tests/engine/corpus/*.txt`) records each case’s input, locale, mode,
rules and expected output, and every case is also run a second time on its own
output. See the header of `tests/engine/corpus.php` for the format.

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

Every Pro gate should funnel through `Plugin::isPro()`, server-side, at the
narrowest choke point — so there is exactly one question asked in exactly one
way. Keep a table here (once real gates exist) mapping each Pro capability to
its gate location and Lite fallback behavior.

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

- **House rules worth knowing before a PR:**
  - Pro gates go through `Plugin::isPro()`, server-side, at the narrowest
    choke point.
  - Secrets are never stored or rendered; credential settings accept `$ENV_VAR`
    references resolved server-side with `App::parseEnv()`.

### `src/` directory map

```
src/
├── Plugin.php                     # Bootstrap: components, events, routes, permissions
├── icon.svg
├── engine/                        # Typography engine: pure PHP, no Craft (see above)
│   ├── Engine.php                 # Tokenize, walk, run rules; register(), apply()
│   ├── Options.php                # Locale, mode, rule switches and settings, skips
│   ├── RuleInterface.php          # The one interface for built-in and site rules
│   ├── Run.php                    # Editable text plus settled marks
│   ├── Context.php                # What a rule sees around its run
│   ├── Flow.php, Frame.php, Pass.php, Html.php   # Walk internals and element tables
│   ├── LocaleInterface.php, DataLocale.php, LocaleRegistry.php
│   ├── locales/                   # One generated data file per locale (CLDR 49.0.0)
│   └── rules/                     # The built-in rules and QuoteScanner
├── migrations/
│   └── Install.php                # Install migration
├── models/
│   └── Settings.php                # Global settings (project config)
├── templates/
│   └── settings.twig              # CP: settings (headed sections, no tabs)
└── web/
    └── twig/PluginVariable.php    # craft.sorts.*

bin/cldr-locales.mjs               # Generates src/engine/locales from CLDR
tests/engine/                      # Engine tests: run.php, corpus/, unit.php, fuzz.php, perf.php
```
