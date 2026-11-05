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
- Suites that mutate state reseed first. Each suite also puts the testbed back as it found it when it
  calls `sortsDone()`: the plugin's project config (edition and settings) in the database and in the
  YAML, and the queue (jobs it pushed are deleted); the last check of every suite says whether that
  worked. `run-all.mjs` then checks the testbed's git tree is clean, `project-config/diff` has
  nothing pending and the queue did not grow, and fails if not. (`craft shell` writes project
  config to the database only, never the YAML, and runs no shutdown functions.)
- `run-all.mjs` will not start on a testbed that is already dirty: it would take that state as the one
  to put back. A suite killed by a PHP fatal after a web request wrote the YAML leaves it that way;
  `node tests/run-all.mjs --reset` puts back the committed `config/project` YAML and applies it, and
  touches nothing else. No run discards changes by itself.
- The suites share one control panel session (the cookies live in the testbed’s `storage/runtime`;
  `run-all.mjs` deletes them when it starts and ends): Craft 5.12.0.1 answers more than 10 login posts
  from one IP a minute with a 429 (`defaultRateLimit`), and logging in afresh for every client used to
  fail a second run started straight after the first.
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
The speed guards in `unit.php` compare two pages timed in the same process (4× the input in under 8×
the time, which a linear pass meets and a quadratic one does not), never a fixed number of
milliseconds, so a slow machine or PHP 8.2 in Docker does not fail them.
