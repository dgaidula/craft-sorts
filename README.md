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

## Development

See [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) for architecture and the local
dev loop.
