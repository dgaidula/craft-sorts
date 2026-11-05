# craft-sorts — agent guide

Sorts: Typographer-grade punctuation for Craft CMS: smart quotes, apostrophes, primes, dashes and more, fixed site-wide with no template edits. Craft 5 plugin, stamped from craft-plugin-base.

- Check: `composer check` (validate, lint, cs, PHPStan), run under the PHP the plugin
  targets (a bare `php` in a script can be an old MAMP 7.4; on the author’s MacBook
  use `/opt/homebrew/bin/php`).
- Store readiness, by hand until a checker exists: changelog headings are
  `## Unreleased` or `## X.Y.Z - YYYY-MM-DD` (the Store ignores any other `##`
  heading), no `version` in `composer.json`, `icon.svg` in `src/`, and complete
  `extra` / `support` URLs that resolve.
- Tests: `node tests/run-all.mjs` against your own testbed — never a client site.
- Craft API claims: read `vendor/craftcms/cms/src` of the testbed and write the
  version you verified against here ("verified against Craft 5.x.y, <date>").
- Before writing code that touches element saves, caching, dates, settings or
  migrations, read the starter’s `docs/PATTERNS.md` §§11–17 (in the
  craft-plugin-base repo) and `docs/DEVELOPMENT.md`.
