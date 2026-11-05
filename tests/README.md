# Sorts integration tests

Real Craft, real DB, no mocks: the bugs this family has shipped (binary ciphertext
in a utf8 column, a draft autosave that dropped a value) only show against a live
install. Targets YOUR OWN testbed (default `~/sw/github-private/craft5-plugin-testbed`,
override with `TESTBED=`), **never a client site**.

- `*-test.php` run through `ddev craft shell` and print one line:
  `RESULT: <n> passed, <m> failed`. `node tests/run-all.mjs` runs them all.
- Pull plugin edits into the testbed with `ddev composer reinstall iceboxind/craft-sorts`
  (a path repo does not re-mirror on `composer update`).
- Switch edition from a suite with an explicit flush (the shell skips it):
  `$pc = Craft::$app->getProjectConfig(); $pc->set('plugins.sorts.edition', 'pro'); $pc->saveModifiedConfigData();`
- Suites that mutate state reseed first; they are not isolated from each other.
- `tests/support.php` holds the shared helpers (`sortsConfigure('pro', [...])` sets edition and settings
  through project config, `sortsHttp()` and `sortsCpClient()` make requests to the site and the control
  panel from inside the container, `sortsEntry()` seeds a `sortsTest` entry). Each suite starts from
  `sortsConfigure` and ends with `sortsDefaults()` (Lite, default settings).
- The suites need the testbed pieces listed in `docs/DEVELOPMENT.md` (the French site, the `sortsTest`
  section, the `sortstest` module and templates).
- Suites: `engine-service` (what each edition applies), `twig` (the filter and its escaping), `page-pass`
  (HTTP: on, off, excluded, CP, action, JSON, token and preview, fail-safe), `settings` (coercion, validation, the
  settings page, saving through the form, file overrides), `extension-api` (the fixture rule’s three scopes),
  `fix-on-save`, `audit`, `graphql`, `ckeditor`, and the stamped `smoke`.

## Engine tests (no Craft, no testbed)

`composer test-engine` runs `tests/engine/run.php`: the typography corpus, unit tests and a
seeded HTML fuzz, in plain PHP. `php tests/engine/run.php corpus|unit|fuzz` runs one part.
`php tests/engine/perf.php` times the engine. `tests/run-all.mjs` does not run these.
