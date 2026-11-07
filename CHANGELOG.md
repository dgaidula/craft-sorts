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
- Quotation marks keep the quote type the author typed for the outermost quotation: a straight `"`
  pair becomes the language’s primary marks and a straight `'` pair its secondary marks, as CKEditor
  does while typing, and a quotation inside another takes the other level from its parent whatever was
  typed (`"She said "no""` gives “She said ‘no’”; three deep alternates primary, secondary, primary,
  or uses a third pair where the locale has one), in every edition (the engine option `quoteNesting`,
  `typed` by default). A curly mark already in the text counts as its level, read by what it does in
  the language rather than its shape (German “ closes, the book style’s » opens), and in Swedish and
  Finnish, where ” and ’ both open and close, by what is on either side of it. Pro setting
  `normalizeQuotes` (off by default) sets every quotation by nesting depth instead, through the page
  pass, the filter, `@sorts`, fix on save and the audit. CKEditor’s as-you-type quotes follow the same
  rule.
- Quotation-style variants `de` `book` (`»…«` `›…‹`, Duden Band 9 and the author’s decision as a
  typographer) and `nl` `double-outer` (`“…”` `‘…’`, Taalunie’s taaladvies.net), CLDR’s marks staying
  the default for both; a variant for `de` or `nl` also applies to `de-AT`, `de-DE` and `nl-BE`.
- Quotation-style variants `de-CH` `guillemets` (`«…»` `‹…›`, Schweizerische Bundeskanzlei,
  *Schreibweisungen*) and `es` `guillemets-outer` (`«…»` `“…”` `‘…’`, RAE *Ortografía*), beside `en-GB`
  `single-outer`.
- After updating Sorts, run `php craft up` or clear compiled templates (README, Known limits).
- German defaults to book style `»…« ›…‹` (decision D844), the only place Sorts departs from CLDR by default; `de-AT` and `de-DE` follow it and `de-CH` keeps CLDR’s marks. CLDR’s `„…“ ‚…‘` is the `de` variant `low-high`; the variant name `book` is retired (it is the default).
- US English variant `spaced-en-dash` (D855, Bringhurst §5.2.1): `--`, `---` and a hyphen with a space each side become a spaced en dash. The default stays the closed em dash.
- A no-break space after an honorific written without its period (`Dr Smith`, `Mr`, `Mrs`, `Ms`, `St`, `Msgr`, `Fr`; D858), leaving `Main St Louis` (a street) alone.
- The engine reports, and never changes, a numerical plural with an apostrophe (`1930's`; D859): `Engine::findings()`, shown by the audit under *Flagged, not changed*.
- A word-initial apostrophe in running text with nothing to close it is an apostrophe (`'Áshįįh` gives `’Áshįįh`; a `'` that opens a paragraph stays an opening mark, D876); a precomposed fraction counts as a number before an x (`8½ x 11`); `8 1/2` joins to `8½`; clock-time ranges (`4:30-5:00`) take an en dash when ranges are on. The corpus pins each: `10 1/2` now gives `10½` (was `10 ½`), and `10:30-11:00` is a range when ranges are on.
- Pro setting *Fine typography (Bringhurst)* (`fineTypography`, D817): the spaced en dash for breaks and for typed em dashes, a dash and a thin space opening dialogue, ranges, fractions and multiplication, spaced ellipses in English, and thin spaces inside guillemets (not in German), each cited to *The Elements of Typographic Style*, 3rd ed., chapter 5. Fix on save stores a block-opening `-- ` as `— ` (an em dash and its space, D897), which the page’s dialogue dash reads. Off, output is byte for byte what it was.
- Pro: every rule, the preset-only ones (`typed-dashes`, `dialogue-dash`, `ellipsis-spacing`) included, is **on**, **off** or **follows the preset** (D864), in the settings screen and as `'on'`, `'off'` or `'follow'` in project config and `config/sorts.php`. Off vetoes a rule the preset turns on (ranges off while the rest runs); on turns on one it leaves off. Follow is the default, so a site behaves as before; a plain on or off stored by an earlier build reads as it did. *Em and en dashes* off, under the preset, takes `typed-dashes` and `dialogue-dash` off with it unless either is switched on.
