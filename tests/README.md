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
