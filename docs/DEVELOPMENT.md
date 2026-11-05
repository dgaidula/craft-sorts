# Sorts — Development Guide

This guide is for developers who want to customize Sorts, build an
integration against it, or contribute to it. For install-and-use documentation,
start with the [README](../README.md).

## Table of contents

- [Overview](#overview)
- [Architecture](#architecture)
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
├── migrations/
│   └── Install.php                # Install migration
├── models/
│   └── Settings.php                # Global settings (project config)
├── templates/
│   └── settings.twig              # CP: settings (headed sections, no tabs)
└── web/
    └── twig/PluginVariable.php    # craft.sorts.*
```
