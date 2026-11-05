# craft-sorts — agent guide

Sorts: Typographer-grade punctuation for Craft CMS: smart quotes, apostrophes, primes, dashes and more, fixed site-wide with no template edits. Craft 5 plugin, stamped from craft-plugin-base.

- Check: `composer check` (validate, lint, cs, PHPStan), run under the PHP the plugin
  targets (a bare `php` in a script can be an old MAMP 7.4; on the author’s MacBook
  use `/opt/homebrew/bin/php`).
- Store readiness, by hand until a checker exists: changelog headings are
  `## Unreleased` or `## X.Y.Z - YYYY-MM-DD` (the Store ignores any other `##`
  heading), no `version` in `composer.json`, `icon.svg` in `src/`, and complete
  `extra` / `support` URLs that resolve.
- Tests: `node tests/run-all.mjs` against your own testbed — never a client site. `composer test-engine`
  runs the engine alone. Each Craft-layer suite switches Lite and Pro itself; load `tests/support.php`
  (the container path `/var/www/html/plugins/craft-sorts/tests/support.php`) and avoid `use` statements
  in a suite (the shell rejects some names as already in use).
- Craft API claims: read `vendor/craftcms/cms/src` of the testbed and write the
  version you verified against here ("verified against Craft 5.x.y, <date>").
  **Verified against Craft 5.12.0.1 and craftcms/ckeditor 5.9.0, 2026-11-05**: `View::EVENT_AFTER_RENDER_PAGE_TEMPLATE`
  (`web/View.php`, `TemplateEvent::$output` is written back), `TemplateResponseFormatter`, `Request`
  (`getIsCpRequest`, `getIsActionRequest` is false whenever a token is present, `getFullPath`),
  `Elements::EVENT_BEFORE_SAVE_ELEMENT` and the save (`resaving` keeps `dateUpdated` and, in
  `Entry::_shouldSaveRevision`, the revision), `Gql::EVENT_REGISTER_GQL_DIRECTIVES`,
  `Utilities::EVENT_REGISTER_UTILITIES`, `Plugins` settings (packed associative arrays), and in
  `craftcms/ckeditor` `CkeditorConfig::registerFirstPartyPackage()`, `Field::EVENT_MODIFY_CONFIG` and
  the bundled `ckeditor5.js` (`TextTransformation`, its default groups and its quote transformations).
  Twig 3.30 (`pre_escape`, constants are safe, node visitors).
- Settings arrive as arrays, `''` or strings; `Settings::setAttributes()` coerces them. Project config
  stores a nested map in a packed form (`__assoc__`): unpack it before reading it raw
  (`ProjectConfigHelper::unpackAssociativeArrays`), as `settingsHtml()` and `tests/support.php` do.
- Twig: a macro returns a `Markup` object, and an empty one is truthy. Never return a flag from a macro.
- Before writing code that touches element saves, caching, dates, settings or
  migrations, read the starter’s `docs/PATTERNS.md` §§11–17 (in the
  craft-plugin-base repo) and `docs/DEVELOPMENT.md`.
