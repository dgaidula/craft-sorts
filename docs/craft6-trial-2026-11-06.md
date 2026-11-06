# Craft 6 compatibility trial: Sorts (2026-11-05/06)

A throwaway Craft **6.0.0-alpha.20** site (`~/sw/craft6-trial`, DDEV `craft6-trial`, PHP 8.5, MySQL 8.4) ran all five of Dan’s plugins through `craftcms/yii2-adapter`. This note keeps Sorts’s part so it is not lost; the full report, with versions, setup gotchas and every citation, is `monetization-ops/research/craft6-trial-2026-11-06.md` (a copy of `~/sw/craft6-trial/REPORT.md`). Craft 6 is alpha: this is a snapshot, to be re-run at beta. Recommendation (2026-11-06): no port work until Craft 6 reaches beta, then one port pass per plugin from this note.

| Plugin | Verdict | Top blocker | Porting |
|---|---|---|---|
| Sorts (`2949cfa`) | Partly works | CP settings save drops settings; `@sorts` GraphQL fatal; page pass rewrites `{% header %}` JSON | small–medium |

**Finding #1, every plugin:** `composer.json` requires `craftcms/cms ^5` (or `^4.5.0 || ^5.0.0`), which blocks install on Craft 6; Craft 6 and the adapter need PHP ^8.5.

## Sorts — small to medium
Tested: install, settings; the page pass (Pro) on `/sorts-lab` with `data-sorts="off"`, rule scopes and `lang="fr"`;
`processHead`; entry-route pages. `View::EVENT_AFTER_RENDER_PAGE_TEMPLATE` fires through the adapter.
Suites, run one by one through the tinker shim (trial copies patched for host and the queue table):

| Suite | Unmodified | After the three trial fixes below |
|---|---|---|
| audit | 21 / 11 | — |
| ckeditor | 21 / 16 | — |
| engine-service | 35 / 5 | 39 / 1 |
| extension-api | 15 / 1 | 15 / 1 |
| fix-on-save | 40 / 24 | 57 / 7 |
| graphql | 1 / 10 | 15 / 8 |
| page-pass | 52 / 24 | 64 / 12 |
| settings | 58 / 13 | — |
| smoke | 2 / 0 | — |
| twig | 72 / 5 | 73 / 4 |

**Broke:**
1. `src/models/Settings.php:118` — `setAttributes($values, $safeOnly = true)`. Craft 5.12.0.1 calls
   `setAttributes($settings, false)`; Craft 6 calls `setAttributes($settings)` (`Plugin/Plugins.php:724`), so only
   attributes with validation rules are assigned. A CP save reports “saved” but drops `rules`, `normalizeQuotes`,
   `fixOnSave`, `fixTitles`, `pagePass`, `processHead` and others. Silent.
2. `src/gql/directives/Sorts.php:44`, `:50` — `new FieldArgument([...])`: the class does not exist in
   webonyx/graphql-php 15.33.1 (which Craft 6 requires); Craft 6’s own directives use plain arrays
   (`Gql/Directives/Markdown.php:32-40`). Every `@sorts` query fatals.
3. `src/services/PagePass.php:146-152` — `isHtmlResponse()` reads the Yii response’s `content-type`, which a template’s
   `{% header "Content-Type: application/json" %}` no longer sets in Craft 6, so the pass rewrites JSON text. A safety
   regression. (Related core difference: a `.json.twig` template is served as `text/html`.)
4. The deprecations above.
Not plugin bugs: `craftcms/ckeditor` (5.9.0 and its 6.x branch) requires `craftcms/cms ^5.10.0`, so CKEditor could not be
tested; Craft 6’s queue is Laravel’s `jobs` table while `Table::QUEUE` still names `{{%queue}}`; the CP is Inertia (HTML
scraping fails); `/fr/` over http 404’d; preview-token URLs 404 (`Tokens` → `RouteTokens`, CHANGELOG line 3127); Craft 6’s
`|md` output differs; a tinker check threw `en is not valid BCP 47` (`Site.php:102`, `APP_LOCALE=en`); some isolation
checks failed because fixtures reached the YAML late.

## Deprecations naming plugin code
From the `deprecationerrors` table and `storage/logs` (`scratch/deprecations.mjs`):

| Plugin file:line | Deprecated call | Craft 6 change |
|---|---|---|
| sorts `src/Plugin.php:212` | `View::registerTwigExtension()` → `Twig::registerExtension()` | CHANGELOG 6.0.0-alpha.1, Twig section (line 3136) |
| sorts `src/Plugin.php:164`, downtoll `src/web/twig/DowntollVariable.php:36`, videocinch `src/Plugin.php:226` | `View::renderTemplate()` → `TemplateManager::renderTemplate()` / `template()` | same section (line 3137) |
| sorts `src/services/EngineService.php:168`, `ContentFixer.php:225` | `Craft::$app->sites` → `app(Sites::class)` | line 3095 |
| sorts `src/services/ContentFixer.php:167` | `Craft::$app->getIsMultiSite()` → `Sites::isMultiSite()` | line 3095 |
| sorts `src/services/AuditScanner.php:42` | `EntryQuery::orderBy()` with an array | `ElementQuery.php:870`; CHANGELOG lines 1071, 1080 |

## Shared blockers and patterns
1. Composer: all five hit `craftcms/cms ^5`; Craft 6 needs PHP ^8.5.
2. The adapter needs `-W` on today’s starter.
3. Yii-era API use per plugin (downtoll/lithocinch/sesame/sorts/videocinch): `Craft::$app->` 43/16/79/27/13;
   `use yii\` 10/5/12/6/3; `use craft\` 53/33/72/52/25; `Event::on` 9/6/11/8/5. All of it loaded.
4. **Where the adapter does not bridge** (each seen here): a typed-property change (`TranslationMethod`); a changed core
   call convention (`setAttributes`); a removed library class (`FieldArgument`); the response/header model
   (`{% header %}`); DI model defaults (`Craft::$container->set(ImageTransform::class…)`); route-rule parameter binding;
   exception-to-status mapping (403 → 500). A grep for these across the other plugins is a cheap first step.
5. Deprecations `View::renderTemplate`, `Craft::$app->sites`, `getIsMultiSite`, `registerTwigExtension` appear in 3 of 5.
6. The shared test harness (Sorts, Sesame) relies on `ddev craft shell` and Yii REPL semantics, a `queue` table, Craft 5
   CSRF and login, CP HTML scraping, `config/project` (Craft 6: `config/craft/project`), and hard-coded hosts and paths.
7. `config/craft/<handle>.php` plugin config files are picked up; multi-environment config files are deprecated.
