# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The Plugin Store reads
only `## X.Y.Z - YYYY-MM-DD` headings; rename `Unreleased` on release day.

## Unreleased

### Added
- Initial release.
- Typography engine (no Craft dependency): a lossless HTML tokenizer; smart quotes with
  nesting, apostrophes (including leading ones), primes, dashes, ellipsis, symbols, number
  ranges, fractions, multiplication signs, ordinals, widows, no-break spaces, French spacing
  and styling hooks, each a rule behind one interface; sixteen locales generated from CLDR 49.0.0.
- Lite: the `|sorts` Twig filter (HTML and `'text'` modes, `{% apply sorts %}`; English quotes,
  apostrophes, primes, dashes and ellipsis), and CKEditor as-you-type quotes through CKEditor’s
  `TextTransformation` plugin, set to the site language where CKEditor can do that.
- Pro: the automatic page pass (site page requests that answer with HTML, previews and token
  requests included; never the control panel, console, action requests or a non-HTML response; a
  failure sends the page as rendered), with an optional pass over `<title>` and prose `<meta>`
  descriptions.
- Pro: settings for rule switches, the widow minimum, quotation-style variants (`en-GB`
  `single-outer`), extra tags, classes and request paths to skip, all validated and overridable
  in `config/sorts.php`; `data-sorts`, `data-sorts-lang` and `data-sorts-rules` in templates.
- Pro: the extension API (`Plugin::EVENT_REGISTER_RULES`, `RegisterRulesEvent` and the `engine`
  component), with a rule runnable everywhere, inside `data-sorts-rules`, or by name from Twig and PHP.
- Pro: the `@sorts` GraphQL directive.
- Pro: fix on save for titles, Plain Text and CKEditor fields (off by default), and the audit
  utility (CP → Utilities → Sorts) that scans entries, previews each change and fixes the ticked
  ones through the queue without a new revision or a changed Date Updated, unless asked.
- Pro: `maxPageBytes` (1,000,000 bytes by default): the page pass sends a larger page, or one it
  might not have the memory for under `memory_limit`, as rendered, with a warning in the log. A
  number in `config/sorts.php` or project config that does not validate (`'2MB'`, a negative
  value) falls back to its default, with a warning, rather than switching the pass off.
- Pro: on sites in more than one language, fix-on-save and the audit leave as typed a value that
  reaches a site in another language (a new entry, a field shared by every site); the page pass
  sets it on each page in that page’s language.
- Quotation marks keep the quote type the author typed: a straight `"` pair becomes the language’s
  primary marks and a straight `'` pair its secondary marks at any depth, as CKEditor does while
  typing, in every edition (the engine option `quoteNesting`, `typed` by default). Pro setting
  `normalizeQuotes` (off by default) sets every quotation by nesting depth instead, through the page
  pass, the filter, `@sorts`, fix on save and the audit. CKEditor’s as-you-type quotes follow the same
  rule.
- Quotation-style variants `de-CH` `guillemets` (`«…»` `‹…›`, Schweizerische Bundeskanzlei,
  *Schreibweisungen*) and `es` `guillemets-outer` (`«…»` `“…”`, RAE *Ortografía*), beside `en-GB`
  `single-outer`.
- After updating Sorts, run `php craft up` or clear compiled templates (README, Known limits).
