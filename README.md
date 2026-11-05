# Sorts

**Typographer-grade punctuation for Craft CMS: smart quotes, apostrophes, primes, dashes and more, fixed site-wide with no template edits.**

A Craft 5 plugin by Danniel T. Gaidula.

## Requirements

**Craft CMS 5.0+ · PHP 8.2+.** Sorts is a Craft 5 plugin and does not run
on Craft 4.

## What it does

- Exposes `craft.sorts.*` to templates.
- Ships CP settings (General / Advanced sections) — dev-owned, project config.

Replace this section with what the plugin actually does once it's built.

## Editions

Sorts ships two editions.

| | Lite (free) | Pro |
| --- | :---: | :---: |
| Example Pro-only capability | — | ✅ |

Fill in the real capability split as the plugin takes shape, and settle it before
the first Store submission: a feature can move from Pro to Lite later, never back.
Keep Lite thin — what the market already gives away — and Pro the first thing every
client asks for next.

### Rename it in the control panel

Sorts can present under any name you like — the sidebar nav, the
Plugins screen, and the settings breadcrumb all follow **Settings → General →
Plugin name** (blank = "Sorts"). Set it in `config/sorts.php` so it
holds with `allowAdminChanges` off:

```php
// config/sorts.php
return [
    '*' => [
        'pluginName' => 'Something Else',
    ],
];
```

## Template usage

`craft.sorts` is registered but has no methods yet. Add them to
`src/web/twig/PluginVariable.php` and document each one here:

```twig
{{ craft.sorts.yourMethod() }}
```

## Locales

Sorts sets quotation marks, nested quotation marks, dashes and spacing by language: `en-US`,
`en-GB`, `de`, `de-CH`, `fr`, `fr-CH`, `es`, `it`, `pt`, `nl`, `sv`, `fi`, `da`, `nb` (also
`no`), `pl` and `cs`. The quotation marks come from Unicode CLDR **49.0.0**; dash style and
spacing come from each language’s style authority, named in its locale file. A
`lang` attribute on any element switches the language for everything inside it, and an
unknown language falls back to the site’s language, then to `en-US`.

CLDR is the default for every language. Where a style authority writes it differently, that
convention is a named variant a caller can choose per language. The one built so far is
`single-outer` for `en-GB` (‘single’ quotation marks outermost, “double” inside, as in
New Hart’s Rules).

## Performance

The engine is measured on a generated, realistic page of about 100 KB (a news article with
navigation, a sidebar and a footer; `php tests/engine/perf.php`), with the default rules
on, opcache on and the JIT off. Median of 300 runs after a warm-up:

| PHP | Where | Typical article | Dense stress page |
|---|---|---|---|
| 8.5.12 | MacBook Pro (Apple M1 Max, 32 GB), native | 3.3 ms | 4.7 ms |
| 8.4.27 | `php:8.4-cli` in Docker Desktop on the same Mac | 3.7 ms | 5.1 ms |
| 8.2.35 | `php:8.2-cli` in Docker Desktop on the same Mac | 5.5 ms | 6.2 ms |

About three sentences in ten on the typical article need typesetting; every sentence on the
stress page does. The budget is 5 ms for a 100 KB page. It is met for the typical page on PHP
8.4 and 8.5 and for both pages on PHP 8.5. It is **not met on PHP 8.2** (the oldest version
Sorts supports): the same code takes about 1.5 times as long there as on PHP 8.4 in the same
container. Speeding it up for 8.2 is open work.

## Development

See [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) for architecture and the local
dev loop.
