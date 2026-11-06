#!/usr/bin/env node
// cldr-locales - generate the Sorts engine's locale files from Unicode CLDR.
//
// Reads the quotation marks (delimiters.json) from an extracted copy of the npm
// package cldr-misc-full and writes one PHP data file per locale into the
// engine's locales folder. The package ships resolved locale data; where a key
// is still missing, this tool walks the locale's parent chain (de-CH to de to
// root) itself and says so. Dash style and spacing are not in CLDR: they come
// from the style authorities named in the LOCALES table below and are written
// into each file with their source.
//
// Usage:
//   cldr-locales inspect  <package-dir>
//   cldr-locales generate <package-dir> [--out <dir>] [--go]
//   cldr-locales --help | --version | --cheat
//
// <package-dir> is the extracted npm package (the folder holding main/):
//   npm pack cldr-misc-full@49.0.0 && mkdir cldr && tar xzf cldr-misc-full-49.0.0.tgz -C cldr
//   cldr-locales generate cldr/package
//
// Commands:
//   inspect    Read-only. Reports each locale's resolved quote pairs and where
//              they came from.
//   generate   Mutating. A DRY RUN by default (reports which files would be
//              created or change); --go writes them. Files already identical
//              are skipped.
//
// Flags:
//   --out <dir>   where the locale files go (default: ../src/engine/locales
//                 beside this script).
//   --go          generate: write the files.
//   --brief       one line per locale, then a summary line.
//   --json        machine-readable output.
//   --quiet       summary line only.
//   --help, -h    print this header.
//   --version     print "cldr-locales <version>".
//   --cheat       print a short card of common invocations.
//
// To change a locale's quote pairs for the product (as opposed to following
// CLDR), edit PAIR_OVERRIDES below, with the reason, and regenerate; do not edit
// the PHP files.
// CLDR is the default everywhere but one place: German, which defaults to book style
// by Dan's taste (D844), with a reason recorded in PAIR_OVERRIDES. Where a style
// authority disagrees, the other convention is a named variant in the LOCALES table
// (`variants`), written into the locale file beside its source, and chosen by the caller.
//
// Exit codes: 0 all ok; 1 a locale warned or failed; 2 usage error; 3 could not
// run (package folder missing or not CLDR 49.0.0).

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { parseArgs } from 'node:util';
import { fileURLToPath } from 'node:url';

const CHEAT = `# cldr-locales

> Generate the Sorts engine locale files from Unicode CLDR.
> More information: cldr-locales --help.

- Report the quote pairs CLDR gives each locale:

\`cldr-locales inspect {{path/to/cldr/package}}\`

- Preview which locale files would change (dry run):

\`cldr-locales generate {{path/to/cldr/package}}\`

- Write the locale files:

\`cldr-locales generate {{path/to/cldr/package}} --go\``;

const CLDR_VERSION = '49.0.0';
const NNBSP = '\\u{202F}';
const NBSP = '\\u{00A0}';

// Where a locale differs from CLDR on purpose, list it here: id -> { quotes, nested, reason }.
// The reason is written into the locale file. CLDR 49.0.0 is the default everywhere
// except here (D816); the CLDR pair then becomes a named variant of that locale.
const PAIR_OVERRIDES = {
  de: {
    quotes: ['\u00BB', '\u00AB'], nested: ['\u203A', '\u2039'],
    reason: 'Dan Gaidula\u2019s taste (decision D844, 2026-11-06): German book style \u00BB\u2026\u00AB \u203A\u2026\u2039 is the default; CLDR 49.0.0 gives German \u201E\u2026\u201C \u201A\u2026\u2018, which stays available as the variant low-high. The only place Sorts departs from CLDR by default.',
  },
};

// The first-release locales (SPEC.md section 4). `cldr` is the folder under
// main/ that holds the data; `no` is served by nb, so nb carries it as an alias.
// `dash` and `spacing` are style choices, each with its authority in `source`.
const EM = { mark: '—', spaced: false };
const EM_SPACED = { mark: '—', spaced: true };
const EN_SPACED = { mark: '–', spaced: true };
const FRENCH_SPACING = {
  before: { ';': NNBSP, '!': NNBSP, '?': NNBSP, ':': NBSP },
  guillemets: NNBSP,
};
const LOCALES = [
  { id: 'en-US', cldr: 'en', name: 'English (United States)', aliases: ['en'], dash: EM,
    // The default stays Chicago's closed em dash; a spaced en dash for breaks is the house style (D855).
    variants: {
      'spaced-en-dash': {
        dash: { ...EN_SPACED, allBreaks: true },
        source: 'Robert Bringhurst, The Elements of Typographic Style, 3rd ed., section 5.2.1 (p. 80): set off a phrase with a spaced en dash, a normal word space either side, not a close-set em dash or a spaced hyphen (Dan Gaidula, house style, decision D855, 2026-11-06). Every way of typing a break takes it: `--`, `---` and a hyphen with a space each side. The default stays The Chicago Manual of Style, 17th ed., closed em dash.',
      },
    },
    source: 'Dash: The Chicago Manual of Style, 17th ed., em dash closed up (unspaced).' },
  { id: 'en-GB', cldr: 'en-GB', name: 'English (United Kingdom)', dash: EN_SPACED,
    // CLDR is the default; a style authority that disagrees is a named variant a caller can choose.
    variants: {
      'single-outer': {
        quotes: ['\u2018', '\u2019'], nested: ['\u201C', '\u201D'],
        source: 'New Hart\u2019s Rules (Oxford University Press), 2nd ed.: single quotation marks outermost, double inside.',
      },
    },
    source: 'Dash: New Hart’s Rules, 2nd ed., spaced en rule.' },
  { id: 'de', cldr: 'de', name: 'German', dash: EN_SPACED,
    // The default is German book style (PAIR_OVERRIDES, D844; first built as the variant book, D823).
    // The variant is CLDR's own pair, read from the package, named for its shape: the low opening
    // mark and the high closing mark, as guillemets names the glyph and single-outer the level.
    variants: {
      'low-high': {
        cldr: true,
        source: 'Unicode CLDR 49.0.0, main/de/delimiters.json: the low-high marks „…“ for a quotation and ‚…‘ for one inside it (Gänsefüßchen), as CLDR gives German. Not the default since D844: the default is the book style »…« ›…‹ (Dan Gaidula, typographer; Duden Band 9, Richtiges und gutes Deutsch, 5th ed. 2001, via a secondary source), which sets no space inside either mark.',
      },
    },
    source: 'Dash: Duden, Gedankenstrich as a spaced Halbgeviertstrich.' },
  { id: 'de-CH', cldr: 'de-CH', name: 'German (Switzerland)', dash: EN_SPACED,
    variants: {
      guillemets: {
        quotes: ['\u00AB', '\u00BB'], nested: ['\u2039', '\u203A'],
        source: 'Schweizerische Bundeskanzlei, Schreibweisungen, 2nd ed. 2013 (corrected 2015), section 2.1.1 (rules 202 and 203): guillemets « » for a quotation and half guillemets ‹ › for one inside it, a space outside each mark and none inside (read in the copy at https://kr-geschaefte.zug.ch/dokumente/11187/3468-2-17179_Beilage2-Schreibweisungen-Bund.pdf). The rule is for federal texts; CLDR 49.0.0 gives de-CH the German marks „…“ ‚…‘.',
      },
    },
    source: 'Dash: as de (Duden); no separate Swiss-German authority was consulted.' },
  { id: 'fr-CH', cldr: 'fr-CH', name: 'French (Switzerland)', dash: EN_SPACED, spacing: FRENCH_SPACING,
    source: 'Dash and spacing: as fr (Imprimerie nationale, Lexique des règles typographiques); no separate Swiss authority was consulted.' },
  { id: 'fr', cldr: 'fr', name: 'French', dash: EN_SPACED, spacing: FRENCH_SPACING,
    source: 'Dash: Imprimerie nationale, Lexique des règles typographiques, spaced demi-cadratin. Spacing: the same, a narrow no-break space before ; ! ? and inside guillemets, a no-break space before the colon (the OECD French style guide also asks for a non-breaking space there).' },
  { id: 'es', cldr: 'es', name: 'Spanish', dash: EM,
    variants: {
      'guillemets-outer': {
        quotes: ['\u00AB', '\u00BB'], nested: ['\u201C', '\u201D'], third: ['\u2018', '\u2019'],
        source: 'Real Academia Española, Ortografía de la lengua española (2010), chapter III, “Las comillas” (https://www.rae.es/ortografía/las-comillas): in print, the angular marks « » first, then the English marks “ ”, then the single marks ‘ ’ for a quotation inside a quotation. Not read at rae.es (it refused automated fetches): the order is as the Universidad de Piura’s Castellano actual quotes it from the RAE Libro de estilo de la lengua española (2018), p. 120.',
      },
    },
    source: 'Dash: Real Academia Española, Ortografía de la lengua española (2010), raya closed up to the words it encloses.' },
  { id: 'it', cldr: 'it', name: 'Italian', dash: EN_SPACED,
    source: 'Dash: Treccani, lineetta with spaces.' },
  { id: 'pt', cldr: 'pt', name: 'Portuguese', dash: EM_SPACED,
    source: 'Dash: travessão with spaces (Brazilian and European editorial practice).' },
  { id: 'nl', cldr: 'nl', name: 'Dutch', dash: EN_SPACED,
    variants: {
      'double-outer': {
        quotes: ['\u201C', '\u201D'], nested: ['\u2018', '\u2019'],
        source: 'Taalunie, taaladvies.net, “Dubbele of enkele aanhalingstekens bij een citaat” (12 May 2021, https://taaladvies.net/dubbele-of-enkele-aanhalingstekens-bij-een-citaat/): there is no fixed rule, but a traditional preference for double marks around a literal quotation, single marks inside it. CLDR 49.0.0 gives Dutch the single marks ‘…’ at both levels, which stay the default.',
      },
    },
    source: 'Dash: Taalunie, Woordenlijst and Schrijfwijzer, spaced en dash.' },
  { id: 'sv', cldr: 'sv', name: 'Swedish', dash: EN_SPACED,
    source: 'Dash: Språkrådet, tankstreck as a spaced en dash.' },
  { id: 'fi', cldr: 'fi', name: 'Finnish', dash: EN_SPACED,
    source: 'Dash: Kotimaisten kielten keskus, ajatusviiva as a spaced en dash.' },
  { id: 'da', cldr: 'da', name: 'Danish', dash: EN_SPACED,
    source: 'Dash: Dansk Sprognævn, tankestreg as a spaced en dash.' },
  { id: 'nb', cldr: 'nb', name: 'Norwegian Bokmål', aliases: ['no'], dash: EN_SPACED,
    source: 'Dash: Språkrådet, tankestrek as a spaced en dash.' },
  { id: 'pl', cldr: 'pl', name: 'Polish', dash: EM_SPACED,
    source: 'Dash: Polish editorial practice, pauza as a spaced em dash.' },
  { id: 'cs', cldr: 'cs', name: 'Czech', dash: EN_SPACED,
    source: 'Dash: Ústav pro jazyk český, Internetová jazyková příručka, spaced pomlčka (en dash).' },
];

// ---- facts -> verdict ------------------------------------------------------

function verdict(item) {
  if (item.error) return 'fail';
  if (item.kind === 'unchanged') return 'skip';
  if (item.walked) return 'warn';
  return 'ok';
}

function checkEnvironment() {
  return { ok: true };
}

// Read main/<dir>/delimiters.json and return its four marks, walking up the
// parent chain for any that are missing.
function resolveDelimiters(pkg, dir) {
  const want = ['quotationStart', 'quotationEnd', 'alternateQuotationStart', 'alternateQuotationEnd'];
  const chain = [];
  const found = {};
  for (let d = dir; d; d = d.includes('-') ? d.slice(0, d.lastIndexOf('-')) : null) {
    const file = join(pkg, 'main', d, 'delimiters.json');
    if (!existsSync(file)) continue;
    const data = JSON.parse(readFileSync(file, 'utf8')).main;
    const marks = Object.values(data)[0].delimiters ?? {};
    chain.push(d);
    for (const k of want) if (found[k] === undefined && marks[k] !== undefined) found[k] = { value: marks[k], from: d };
  }
  const missing = want.filter((k) => found[k] === undefined);
  const walked = [...new Set(want.filter((k) => found[k] && found[k].from !== dir).map((k) => found[k].from))];
  return { found, missing, walked, chain };
}

function phpString(s) {
  return `'${s.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

// A dash as PHP: allBreaks, when set, makes `---` and a spaced hyphen take the same dash as `--`.
function dashPhp(d) {
  return `['mark' => ${phpString(d.mark)}, 'spaced' => ${d.spaced}${d.allBreaks ? ', \'allBreaks\' => true' : ''}]`;
}

function renderPhp(loc, r, pairs, cldrPairs) {
  const sp = loc.spacing;
  const spacing = !sp ? '[]' : [
    '[',
    "        'before' => [",
    ...Object.entries(sp.before).map(([ch, sep]) => `            ${phpString(ch)} => "${sep}",`),
    '        ],',
    `        'guillemets' => "${sp.guillemets}",`,
    '    ]',
  ].join('\n');
  const aliases = (loc.aliases ?? []).map(phpString).join(', ');
  const variantEntries = Object.entries(loc.variants ?? {});
  const variants = !variantEntries.length ? '[]' : [
    '[',
    ...variantEntries.flatMap(([name, listed]) => {
      // A variant marked cldr is CLDR's own pair, as read from the package.
      const v = listed.cldr ? { ...listed, ...cldrPairs } : listed;
      return [
        `        // ${v.source}`,
        `        ${phpString(name)} => [`,
        ...(v.quotes ? [`            'quotes' => [${phpString(v.quotes[0])}, ${phpString(v.quotes[1])}],`] : []),
        ...(v.nested ? [`            'nested' => [${phpString(v.nested[0])}, ${phpString(v.nested[1])}],`] : []),
        // A third pair, when the authority has one: three deep, then it alternates with the nested pair.
        ...(v.third ? [`            'third' => [${phpString(v.third[0])}, ${phpString(v.third[1])}],`] : []),
        ...(v.dash ? [`            'dash' => ${dashPhp(v.dash)},`] : []),
        '        ],',
      ];
    }),
    '    ]',
  ].join('\n');
  return `<?php

/**
 * ${loc.id} — ${loc.name}.
 *
 * Generated from CLDR ${CLDR_VERSION} (cldr-misc-full) by bin/cldr-locales.mjs.
 * Do not hand-edit: change the generator and run it again.
 *
 * Quote pairs: main/${loc.cldr}/delimiters.json${r.walked.length ? `, completed from ${r.walked.join(', ')}` : ''}.
${PAIR_OVERRIDES[loc.id] ? ` * Departs from CLDR: ${PAIR_OVERRIDES[loc.id].reason}\n` : ''} * ${loc.source}
 */

return [
    'id' => ${phpString(loc.id)},
    'aliases' => [${aliases}],
    // Quote pairs, [opening, closing]. The first pair is used outermost, the
    // second inside it, then they alternate. They live only here; to depart from
    // CLDR on purpose, set PAIR_OVERRIDES in the generator and run it again.
    'quotes' => [${phpString(pairs.quotes[0])}, ${phpString(pairs.quotes[1])}],
    'nested' => [${phpString(pairs.nested[0])}, ${phpString(pairs.nested[1])}],
    'apostrophe' => '’',
    // What \`--\` becomes: the mark, and whether it takes a space on each side.
    'dash' => ${dashPhp(loc.dash)},
    'spacing' => ${spacing},
    // Named alternatives to the ${PAIR_OVERRIDES[loc.id] ? 'default above (which departs from CLDR, see the header)' : 'CLDR defaults above'}. A variant lists only the keys it changes.
    'variants' => ${variants},
];
`;
}

// What CLDR itself gives the locale, whatever the product does with it.
function cldrPairsFor(r) {
  const f = r.found;
  return {
    quotes: [f.quotationStart.value, f.quotationEnd.value],
    nested: [f.alternateQuotationStart.value, f.alternateQuotationEnd.value],
  };
}

function pairsFor(loc, r) {
  const o = PAIR_OVERRIDES[loc.id] ?? {};
  const c = cldrPairsFor(r);
  return { quotes: o.quotes ?? c.quotes, nested: o.nested ?? c.nested };
}

function inspectOne(loc, { pkg }) {
  const item = { command: 'inspect', path: loc.id, name: loc.id, reason: '', changed: false, data: null };
  try {
    const r = resolveDelimiters(pkg, loc.cldr);
    if (r.missing.length) {
      item.error = `no value for ${r.missing.join(', ')} in ${r.chain.join(' > ') || 'any locale'}`;
      item.summary = 'incomplete CLDR data';
      item.reason = item.error;
    } else {
      const p = pairsFor(loc, r);
      item.summary = `${p.quotes.join('')} ${p.nested.join('')}`;
      item.walked = r.walked.length > 0;
      if (item.walked) item.reason = `completed from the parent chain: ${r.walked.join(', ')}`;
      const o = PAIR_OVERRIDES[loc.id];
      item.data = { quotes: p.quotes, nested: p.nested, from: r.chain, override: Boolean(o), cldr: o ? cldrPairsFor(r) : undefined, reason: o?.reason };
      item.lines = [`quotes: ${p.quotes.join(' ')}`, `nested: ${p.nested.join(' ')}`, `read: main/${r.chain.join(', main/')}`];
      if (o) item.lines.push(`departs from CLDR (${cldrPairsFor(r).quotes.join(' ')} ${cldrPairsFor(r).nested.join(' ')}): ${o.reason}`);
      item.render = renderPhp(loc, r, p, cldrPairsFor(r));
    }
  } catch (e) {
    item.error = e.message;
    item.summary = 'cannot read';
    item.reason = e.message;
  }
  item.verdict = verdict(item);
  return item;
}

function generateOne(loc, { pkg, out, go }) {
  const item = inspectOne(loc, { pkg });
  item.command = 'generate';
  if (item.error) return item;
  const file = join(out, `${loc.id}.php`);
  item.path = file;
  const before = existsSync(file) ? readFileSync(file, 'utf8') : null;
  if (before === item.render) {
    item.kind = 'unchanged';
    item.summary = `unchanged ${item.summary}`;
  } else if (!go) {
    item.summary = `would ${before === null ? 'create' : 'update'} ${loc.id}.php ${item.summary}`;
  } else {
    try {
      mkdirSync(out, { recursive: true });
      writeFileSync(file, item.render);
      item.changed = true;
      item.summary = `${before === null ? 'created' : 'updated'} ${loc.id}.php ${item.summary}`;
    } catch (e) {
      item.error = e.message;
      item.summary = 'write failed';
      item.reason = e.message;
    }
  }
  item.verdict = verdict(item);
  return item;
}

// ---- contract hooks ---------------------------------------------------------

const MUTATING_COMMANDS = new Set(['generate']);
const AUTO_RUN_COMMANDS = new Set();
const EXTRA_OVERRIDE_FLAGS = [];
const GO_ALIASES = [];

function nextAction(run) {
  return defaultNextAction(run);
}

function requireValue(flag, value, { multiline = false } = {}) {
  if (typeof value !== 'string') usageError(`${flag} needs a value`);
  if (!value.trim()) usageError(`${flag} must not be blank`);
  if (!multiline && /[\r\n\u2028\u2029\u0085]/.test(value)) usageError(`${flag} must be one line, with no line break`);
  return value.trim();
}

// ---- CLI --------------------------------------------------------------------

async function main() {
  let parsed;
  try {
    parsed = parseArgs({
      args: process.argv.slice(2),
      allowPositionals: true,
      options: {
        help: { type: 'boolean', short: 'h' },
        version: { type: 'boolean' },
        cheat: { type: 'boolean' },
        brief: { type: 'boolean' },
        json: { type: 'boolean' },
        quiet: { type: 'boolean' },
        go: { type: 'boolean' },
        out: { type: 'string' },
      },
    });
  } catch (e) {
    usageError(e.message);
  }
  const { values, positionals } = parsed;

  if (values.help) {
    console.log(helpText());
    return;
  }
  if (values.version) {
    console.log(`${TOOL_NAME} ${readVersion()}`);
    return;
  }
  if (values.cheat) {
    for (const m of ['brief', 'json', 'quiet']) {
      if (values[m]) usageError(`--cheat and --${m} cannot be combined`);
    }
    console.log(CHEAT);
    return;
  }

  const mode = outputMode(values);
  const [command, pkgArg, ...extra] = positionals;
  if (!command) usageError('no command (expected `inspect` or `generate`)');
  if (command !== 'inspect' && command !== 'generate') {
    usageError(`unknown command \`${command}\` (expected \`inspect\` or \`generate\`)`);
  }
  if (!pkgArg || extra.length) usageError(`\`${command}\` takes exactly one <package-dir>`);
  const here = dirname(fileURLToPath(import.meta.url));
  const out = values.out === undefined ? resolve(here, '..', 'src', 'engine', 'locales') : resolve(requireValue('--out', values.out));
  const pkg = resolve(pkgArg);

  const env = checkEnvironment();
  if (!env.ok) environmentError(env.message);
  try {
    const meta = JSON.parse(readFileSync(join(pkg, 'package.json'), 'utf8'));
    if (meta.name !== 'cldr-misc-full' || meta.version !== CLDR_VERSION) {
      environmentError(`${pkg} is ${meta.name}@${meta.version}; this generator is written for cldr-misc-full@${CLDR_VERSION}`);
    }
  } catch (e) {
    environmentError(`cannot read ${join(pkg, 'package.json')}: ${e.message}`);
  }
  if (command === 'inspect' && values.go) console.error('note: --go has no effect on `inspect` (read-only)');

  const items = command === 'inspect'
    ? LOCALES.map((l) => inspectOne(l, { pkg }))
    : LOCALES.map((l) => generateOne(l, { pkg, out, go: !!values.go }));

  const effect = effectFor(command, !!values.go);
  const argv = process.argv.slice(2);
  const next = nextAction({ command, items, effect, state: null, argv });
  report({ command, effect, items, next, mode, argv });
}

export {
  verdict, inspectOne, generateOne, nextAction, requireValue,
  MUTATING_COMMANDS, AUTO_RUN_COMMANDS, EXTRA_OVERRIDE_FLAGS, GO_ALIASES,
  glyph, briefLine, jsonItem, summarize, exitCodeFor, effectFor,
  nextStep, rerunArgv, defaultNextAction, assertSafeNext, nextLine, report,
};

// ---- mjs-tool runtime v3 (do not edit; replace wholesale) ----
// The shared machinery of contract mjs-tool/3, byte-identical in every tool
// built from mjs-tool-skeleton. It carries its own imports (namespaced, so they
// never collide with the tool's); its other top-level names are unprefixed, so
// a tool keeps no copies of them. The tool supplies, outside this block:
// MUTATING_COMMANDS, AUTO_RUN_COMMANDS, EXTRA_OVERRIDE_FLAGS, GO_ALIASES,
// main(), and a nextAction() policy whose result it hands to report(); the
// block checks their shapes as it loads. To move a tool to a later contract,
// swap this whole block.

import * as runtimeFs from 'node:fs';
import * as runtimePath from 'node:path';
import * as runtimeUrl from 'node:url';

const CONTRACT = 'mjs-tool/3';

// The realpath of this script, so a symlinked (npm-linked) invocation still
// reports the right name/version and finds its own header for --help.
const SCRIPT_PATH = (() => {
  const self = runtimeUrl.fileURLToPath(import.meta.url);
  try { return runtimeFs.realpathSync(self); } catch { return self; }
})();
const TOOL_NAME = runtimePath.basename(SCRIPT_PATH).replace(/\.mjs$/, '');

// The directory the run started in (next.cwd), read once as the module loads,
// so a run that removes its own working directory still reports. If it was
// gone before the start, the shell's $PWD stands in (or / without one).
const START_CWD = (() => {
  try { return process.cwd(); } catch {
    const pwd = process.env.PWD;
    return pwd && runtimePath.isAbsolute(pwd) ? pwd : '/';
  }
})();

// -- verdicts: symbols, counts, exit code

const GLYPH = { ok: '✓', warn: '⚠', skip: '•', refuse: '⊘', fail: '✗' };
function glyph(v) { return GLYPH[v] || '?'; }

function summarize(items) {
  const s = { ok: 0, warn: 0, skip: 0, refuse: 0, fail: 0 };
  for (const it of items) if (it.verdict in s) s[it.verdict] += 1;
  return s;
}

// 0 all ok (skips are not failures), 1 partial. The other two codes exit
// through usageError() (2), environmentError() and the entry guard (3).
function exitCodeFor(items) {
  const s = summarize(items);
  return s.warn || s.refuse || s.fail ? 1 : 0;
}

// -- effect: what this run was allowed to do to the world. An applied run may
// still change nothing (every item refused); the items' `changed` says what did.

function effectFor(command, go) {
  if (!MUTATING_COMMANDS.has(command)) return 'read-only';
  return go ? 'applied' : 'dry-run';
}

// -- next: the advice on the next step

// Who takes each action, derived and never chosen: run, wait and done are the
// agent's; ask and stop hand the run to a person.
const NEXT_WHO = { done: 'agent', run: 'agent', wait: 'agent', ask: 'human', stop: 'human' };
const NEXT_ACTIONS = Object.keys(NEXT_WHO);
const NEXT_KEYS = ['action', 'who', 'argv', 'afterSeconds', 'why', 'cwd'];
// Rule 4: the advice never contradicts the exit code, which stays authoritative.
// Exit 0 allows ask: a clean run can still end at a person's decision.
const ACTIONS_FOR_EXIT = { 0: ['done', 'run', 'wait', 'ask'], 1: NEXT_ACTIONS, 2: ['stop'], 3: ['stop'] };
// Rule 2: a flag that confirms or overrides a gate never appears in next.argv.
// Long flags match case-insensitively: the built-in classes by prefix (so
// --yes-really and --forced), --allow and the tool's EXTRA_OVERRIDE_FLAGS
// exactly, with or without =value. An EXTRA_OVERRIDE_FLAGS entry may also be a
// bare word, which blocks that verb. --no-yes and --iamdan are not listed:
// parseArgs rejects them as unknown options, so no run could carry them.
const OVERRIDE_FLAGS = ['--allow'];
const OVERRIDE_PREFIXES = ['--yes', '--force', '--assume-yes', '--allow-', '--i-am-'];

function isOverrideFlag(token) {
  const t = String(token);
  const lower = t.toLowerCase();
  const isFlag = (f) => lower === f || lower.startsWith(`${f}=`);
  if (OVERRIDE_FLAGS.some(isFlag) || OVERRIDE_PREFIXES.some((p) => lower.startsWith(p))) return true;
  if (/^-[A-Za-z]*[yY][A-Za-z]*$/.test(t)) return true; // -y or -Y (the short --yes), alone or grouped
  return EXTRA_OVERRIDE_FLAGS.some((f) => (/^-[A-Za-z]$/.test(f)
    ? new RegExp(`^-[A-Za-z]*${f[1]}[A-Za-z]*$`).test(t) // a declared short flag, alone or grouped
    : isFlag(String(f).toLowerCase())));
}

// A --go alias the tool declares in GO_ALIASES, matched as a declared override
// flag is: a long one in any case, with or without =value; a short one alone or
// grouped. Aliases are input only: advice spells --go, which the checks read.
function isGoAlias(token) {
  const t = String(token);
  const low = t.toLowerCase();
  return GO_ALIASES.some((f) => (/^-[A-Za-z]$/.test(f)
    ? new RegExp(`^-[A-Za-z]*${f[1]}[A-Za-z]*$`).test(t)
    : low === f.toLowerCase() || low.startsWith(`${f.toLowerCase()}=`)));
}

// A next object with the fixed field set. `who` follows from the action and
// `cwd` is START_CWD, so neither is an option. `why` is escaped (a filename in
// it cannot break the line) and clipped, here rather than refused by the
// guard, which runs after the work is done.
function nextStep(action, why, options = {}) {
  const { argv = null, afterSeconds = null, ...rest } = options;
  if (Object.keys(rest).length) {
    throw new Error(`nextStep() takes argv and afterSeconds only, not ${Object.keys(rest).join(', ')}: who and cwd are derived`);
  }
  const line = typeof why === 'string' ? clipText(printable(why), 200) : why;
  return { action, who: NEXT_WHO[action] ?? null, argv, afterSeconds, why: line, cwd: START_CWD };
}

// The head of every re-run argv: this node, then this script as invoked, made
// absolute but not realpath'd (an npm-linked bin stays its bin path). Running
// the script through node needs no executable bit and no PATH lookup.
function selfArgv() {
  return [process.execPath, __entry === SCRIPT_PATH ? runtimePath.resolve(process.argv[1]) : SCRIPT_PATH];
}

// This tool's own command line again, as an argv array, with flags inserted
// before any `--` (after it they would be positionals).
function rerunArgv(argv, ...extra) {
  const end = argv.indexOf('--');
  return end < 0 ? [...selfArgv(), ...argv, ...extra] : [...selfArgv(), ...argv.slice(0, end), ...extra, ...argv.slice(end)];
}

function count(n, noun) {
  return `${n} ${noun}${n === 1 ? '' : 's'}`;
}

// At most `max` characters of text, then … if any were cut.
function clipText(text, max) {
  const chars = [...text];
  return chars.length > max ? `${chars.slice(0, max).join('')}…` : text;
}

// Names for `why` (which nextStep() escapes): each clipped to 80 characters,
// at most `max` of them within about 120, then a count of the rest, so the
// count survives the clip on the whole.
function listNames(items, max = 3) {
  const names = [];
  let length = 0;
  for (const it of items) {
    const name = clipText(String(it.name), 80);
    if (names.length === max || (names.length && length + name.length > 120)) break;
    names.push(name);
    length += name.length + 2;
  }
  const rest = items.length - names.length;
  return rest ? `${names.join(', ')} and ${rest} more` : names.join(', ');
}

// The generic policy (rule 3). A tool's nextAction() starts here and adds what
// only it knows. Pure: reads the command, the items, the effect and the argv.
function defaultNextAction({ command, items, effect, argv }) {
  const s = summarize(items);
  const findings = s.warn + s.refuse + s.fail;
  if (s.fail) {
    return nextStep('stop', `failed: ${listNames(items.filter((it) => it.verdict === 'fail'))}`);
  }
  if (effect === 'read-only') {
    return nextStep('done', findings ? `read-only: report the ${count(findings, 'finding')}` : 'read-only: nothing to apply');
  }
  if (effect === 'dry-run') {
    if (argv.some(isOverrideFlag)) {
      return nextStep('ask', 'the command carried an override flag, which next never repeats; a person decides');
    }
    if (s.warn || s.refuse) {
      return nextStep('ask', `dry run: review the ${count(s.warn + s.refuse, 'finding')} before --go`, { argv: rerunArgv(argv, '--go') });
    }
    if (!s.ok) return nextStep('done', 'dry run: nothing would change');
    // A clean dry run goes on to --go unattended only for a command the tool
    // has pre-approved in AUTO_RUN_COMMANDS; for any other it is a person's call.
    const action = AUTO_RUN_COMMANDS.has(command) ? 'run' : 'ask';
    return nextStep(action, `dry run clean: ${count(s.ok, 'item')} would change`, { argv: rerunArgv(argv, '--go') });
  }
  const changed = items.filter((it) => it.changed).length;
  return nextStep('done', findings
    ? `applied: ${changed} changed; report the ${count(findings, 'finding')}`
    : `applied: ${changed} changed`);
}

// Rules 2 and 4 (and the fixed shape), enforced on every `next` before it is
// rendered. A violation throws, and the run exits 3: a tool cannot ship advice
// that walks an agent past a gate, and its tests meet the violation first.
// `current` is the run the advice follows: its effect, and the argv it handed
// nextAction(). report() passes it, so a run goes to --go only on what that
// run just previewed; without it, no run may carry --go.
function assertSafeNext(next, exit, current = {}) {
  const unsafe = (why) => { throw new Error(`unsafe next: ${why}`); };
  if (next === null) return;
  if (!next || typeof next !== 'object' || Array.isArray(next)) unsafe('nextAction() must return a next object or null');
  const keys = Object.keys(next);
  if (keys.length !== NEXT_KEYS.length || !NEXT_KEYS.every((k) => keys.includes(k))) {
    unsafe(`the fields are exactly ${NEXT_KEYS.join(', ')}`);
  }
  const { action, who, argv, afterSeconds, why, cwd } = next;
  if (!NEXT_ACTIONS.includes(action)) unsafe(`unknown action ${printable(JSON.stringify(action))}`);
  if (who !== NEXT_WHO[action]) unsafe(`who is ${NEXT_WHO[action]} for ${action}, never chosen`);
  if (argv !== null && !(Array.isArray(argv) && argv.length && argv.every((a) => typeof a === 'string'))) {
    unsafe('argv is a non-empty array of strings, or null');
  }
  if ((action === 'run' || action === 'wait') && argv === null) unsafe(`${action} needs an argv`);
  if ((action === 'done' || action === 'stop') && argv !== null) unsafe(`${action} carries no argv`);
  // run, wait and any ask argv re-enter this tool and its own gates; a handoff
  // to another tool is done, with the next step in the runbook.
  const head = selfArgv();
  if (argv !== null && (argv[0] !== head[0] || argv[1] !== head[1])) {
    unsafe(`${action} re-invokes this tool: argv starts with ${printable(head.join(' '))}`);
  }
  const flag = (argv || []).find(isOverrideFlag);
  if (flag !== undefined) unsafe(`argv carries the override flag ${printable(flag)}; a step that needs one is ask`);
  const alias = (argv || []).find(isGoAlias);
  if (alias !== undefined) unsafe(`argv carries ${printable(alias)}, a --go alias in GO_ALIASES; advice spells --go`);
  if (action === 'run' && argv.slice(2).some((a) => a === '--go' || a.startsWith('--go='))) {
    // Advice goes straight to --go only on what this run just previewed: the
    // dry run's own argv with --go added, as rerunArgv(argv, '--go') builds it.
    // A step into another command, or after a run that was no dry run, is a
    // read-only or dry-run step, with no --go.
    if (current.effect !== 'dry-run') {
      unsafe(`run carries --go only straight after a dry run of the same command; this run was ${printable(current.effect ?? 'not given')}`);
    }
    const expected = Array.isArray(current.argv) ? rerunArgv(current.argv, '--go') : [];
    if (argv.length !== expected.length || argv.some((a, i) => a !== expected[i])) {
      unsafe("run carries --go only on the command just previewed: that dry run's argv with --go added, as rerunArgv(argv, '--go') builds it; a step into another command carries no --go");
    }
  }
  if (action === 'wait') {
    // wait re-polls, so it never mutates: no --go, and no mutating command,
    // matched as the same words in a row (command names may be phrases).
    const rest = argv.slice(2);
    if (rest.some((a) => a === '--go' || a.startsWith('--go='))) unsafe('wait never carries --go');
    const has = (words) => rest.some((_, i) => words.every((w, j) => rest[i + j] === w));
    const mutating = [...MUTATING_COMMANDS].find((c) => has(String(c).split(' ')));
    if (mutating !== undefined) unsafe(`wait never runs the mutating command ${printable(mutating)}`);
  }
  if (action === 'wait' ? !(Number.isInteger(afterSeconds) && afterSeconds > 0) : afterSeconds !== null) {
    unsafe('afterSeconds is a positive integer for wait, and null otherwise');
  }
  if (typeof why !== 'string' || !why.trim() || CONTROL_CHARS.test(why)) {
    unsafe('why is one non-empty line, with no control characters');
  }
  if (cwd !== START_CWD) unsafe('cwd is the working directory the run started in');
  if (!(ACTIONS_FOR_EXIT[exit] || []).includes(action)) unsafe(`action ${action} contradicts exit ${exit}`);
}

// Characters that could break, rewrite, reorder or hide part of a line of text
// output: C0 controls but TAB, DEL, C1 controls, the Unicode line and paragraph
// separators, and the bidi embedding, override and isolate controls. Item text
// is data, and styling is the renderer's: a tool puts no ANSI colour in it.
const CONTROL_CHARS = /[\u0000-\u0008\u000a-\u001f\u007f-\u009f\u2028\u2029\u202a-\u202e\u2066-\u2069]/;

function escapeControl(c) {
  const code = c.charCodeAt(0);
  return { '\n': '\\n', '\r': '\\r' }[c]
    ?? (code < 0x80 ? `\\x${code.toString(16).padStart(2, '0')}` : `\\u${code.toString(16).padStart(4, '0')}`);
}

// A control character inside $'...': one past ASCII as its UTF-8 bytes (\xHH
// each), not \uXXXX, which bash 3.2 (macOS's /bin/sh and /bin/bash) does not
// decode; bash 4+ and zsh read \xHH too.
function escapeShell(c) {
  return c.charCodeAt(0) < 0x80 ? escapeControl(c)
    : [...Buffer.from(c, 'utf8')].map((b) => `\\x${b.toString(16)}`).join('');
}

// Text for a text-mode line: control characters shown as escapes, so an item
// is always one line and nothing in it can pose as another line. TAB passes.
function printable(text) {
  return String(text).replace(new RegExp(CONTROL_CHARS, 'g'), escapeControl);
}

// POSIX single-quoting, only where a token needs it; a token holding a control
// character is ANSI-C quoted ($'...') instead, so the line stays one line.
function shellQuote(token) {
  if (CONTROL_CHARS.test(token)) {
    return `$'${token.replace(/[\\']/g, (c) => `\\${c}`).replace(new RegExp(CONTROL_CHARS, 'g'), escapeShell)}'`;
  }
  return /^[A-Za-z0-9_\/.,:@%+-][A-Za-z0-9_\/.,:=@%+-]*$/.test(token) ? token : `'${token.split("'").join("'\\''")}'`;
}

// The text form of `next`, the last line of every text mode, and always exactly
// one line. A command is printed only where its reader may take it: run and
// wait always, led by `cd <cwd> &&` so the line runs as pasted from any
// directory; ask only for a person, in the default human mode, which says
// "your call" (--brief and --quiet print a bare ask, so an agent is never
// handed a command at a halt); done and stop never. argv is quoted for
// reading; --json carries the exact array, with cwd beside it.
function nextLine(next, mode) {
  const command = next.argv ? ` ${next.argv.map(shellQuote).join(' ')}` : '';
  const why = `  (${printable(next.why)})`;
  const here = `cd ${shellQuote(next.cwd)} &&`;
  if (next.action === 'run') return `next: run ${here}${command}${why}`;
  if (next.action === 'wait') return `next: wait ${next.afterSeconds}s ${here}${command}${why}`;
  if (next.action === 'ask' && mode === 'human') return `next: your call${command && `:${command}`}${why}`;
  return `next: ${next.action}${why}`;
}

// -- rendering (one item shape -> every mode)

const REASON_CUT = 160; // --brief cuts a reason after this many of its own characters (before escaping)

function summaryLine(s, effect) {
  const base = `summary: ok=${s.ok} warn=${s.warn} skip=${s.skip} refuse=${s.refuse} fail=${s.fail}`;
  return effect === 'dry-run' ? `${base}  (dry run: pass --go to apply)` : base;
}

// A reason for --brief: the whole of it, or its first REASON_CUT characters
// and a count of the rest, which --json carries in full. Characters are the
// reason's own code points, counted before escaping: a surrogate pair is never
// split, the cut never falls inside an escape (a control character is cut
// whole, then shown as its escape), and N is the code points --json holds past
// the cut. The cut applies only where it shortens the reason (a reason a few
// characters over the limit is shorter whole than with the note), so --brief
// is never longer than the human output.
function briefReason(reason) {
  const shown = printable(reason);
  const chars = Array.from(String(reason));
  if (chars.length <= REASON_CUT) return shown;
  const cut = `${printable(chars.slice(0, REASON_CUT).join(''))}… (+${chars.length - REASON_CUT} chars; --json for all)`;
  return Buffer.byteLength(cut) < Buffer.byteLength(shown) ? cut : shown;
}

// One line per item: the verdict word, the summary, then the name, with no
// padding and no glyph. The parenthesised reason appears only for a non-ok
// verdict.
function briefLine(item) {
  const showReason = item.verdict !== 'ok' && item.reason;
  return `${item.verdict} ${printable(item.summary)}  ${printable(item.name)}`
    + (showReason ? `  (${briefReason(item.reason)})` : '');
}

// The stable per-item JSON shape (top-level keys fixed; tool-specific facts go
// under `data`).
function jsonItem(item) {
  return {
    path: item.path,
    name: item.name,
    verdict: item.verdict,
    summary: item.summary,
    reason: item.reason || '',
    changed: !!item.changed,
    data: item.data || null,
  };
}

// The exit code of the report once written: a late error keeps it.
let reportedExit = null;

// The only writer of stdout, and the end of the run. Renders one run in the
// chosen mode from the one item list; when the tool runs as a command, the
// process then exits with exactly the code the report carries, once stdout has
// flushed (a bare process.exit() cuts a large report off mid-pipe; a pipe
// takes 64 KiB at a time on macOS), so an open handle cannot hold the run
// open. Returns the exit code. `next` passes assertSafeNext() before a byte is
// written; `state` (a status snapshot) is optional and appears in --json only.
// `argv` is the argv the tool handed nextAction(), normalised as it normalised
// it; left out, it is this process's own.
function report({ command, effect, items, next, state = null, mode, argv = process.argv.slice(2) }) {
  const summary = summarize(items);
  const exit = exitCodeFor(items);
  assertSafeNext(next, exit, { effect, argv });

  const emit = (text) => {
    reportedExit = exit;
    process.stdout.write(text, () => { if (__isMain) process.exit(exit); });
    return exit;
  };

  if (mode === 'json') {
    const out = { tool: TOOL_NAME, version: readVersion(), contract: CONTRACT, command, effect, exit };
    if (state !== null) out.state = state;
    Object.assign(out, { items: items.map(jsonItem), summary, next });
    return emit(`${JSON.stringify(out, null, 2)}\n`);
  }

  const lines = [];
  if (mode === 'brief') {
    for (const it of items) lines.push(briefLine(it));
  } else if (mode === 'human') {
    for (const it of items) {
      lines.push(`${glyph(it.verdict)} ${printable(it.name)}`, `    ${printable(it.summary)}`);
      for (const line of it.lines || []) lines.push(`    ${printable(line)}`);
      if (it.reason) lines.push(`    ${it.verdict === 'ok' ? 'note' : it.verdict}: ${printable(it.reason)}`);
    }
    lines.push('');
  }
  lines.push(summaryLine(summary, effect));
  if (next) lines.push(nextLine(next, mode));
  return emit(`${lines.join('\n')}\n`);
}

// -- modes, errors, help, version

// The output mode from the parsed flags. --json combines with neither --brief
// nor --quiet (a usage error); --brief wins over --quiet.
function outputMode(values) {
  for (const other of ['brief', 'quiet']) {
    if (values.json && values[other]) usageError(`--${other} and --json cannot be combined`);
  }
  return values.json ? 'json' : values.brief ? 'brief' : values.quiet ? 'quiet' : 'human';
}

// Diagnostics go to stderr; stdout stays empty on both. These two may exit at
// once: they run before report(), so there is no stdout left to flush.
function usageError(message) {
  console.error(`error: ${message}`);
  console.error(`run \`${TOOL_NAME} --help\` for usage`);
  process.exit(2);
}

function environmentError(message) {
  console.error(`error: ${message}`);
  process.exit(3);
}

// Read the leading comment block of THIS file as help text (contact-sheet-cli
// pattern): skip the shebang, take the contiguous `//` lines, strip the `// `.
function helpText() {
  const lines = runtimeFs.readFileSync(SCRIPT_PATH, 'utf8').split('\n');
  const out = [];
  for (const l of lines.slice(1)) {
    if (!l.startsWith('//')) break;
    out.push(l.slice(3));
  }
  return out.join('\n');
}

function readVersion() {
  let dir = runtimePath.dirname(SCRIPT_PATH);
  for (let i = 0; i < 20; i += 1) {
    const p = runtimePath.join(dir, 'package.json');
    if (runtimeFs.existsSync(p)) {
      try { return JSON.parse(runtimeFs.readFileSync(p, 'utf8')).version || '0.0.0'; }
      catch { return '0.0.0'; }
    }
    const parent = runtimePath.dirname(dir);
    if (parent === dir) break;
    dir = parent;
  }
  return '0.0.0';
}

// Exit with `code` once stdout has flushed: process.exit() alone drops what a
// pipe has not yet taken, and waiting for the event loop to empty lets any
// open handle hold the run open.
function exitAfterFlush(code) {
  process.stdout.write('', () => process.exit(code));
}

// The hooks a tool declares above this block, checked as the module loads, so
// a missing or misshapen one fails by name (exit 3) instead of mid-run.
const HOOK_ERROR = (() => {
  const hooks = [
    ['MUTATING_COMMANDS', 'a Set of command names', () => MUTATING_COMMANDS instanceof Set],
    ['AUTO_RUN_COMMANDS', 'a Set of command names (empty for none)', () => AUTO_RUN_COMMANDS instanceof Set],
    ['EXTRA_OVERRIDE_FLAGS', 'an array of strings (empty for none)',
      () => Array.isArray(EXTRA_OVERRIDE_FLAGS) && EXTRA_OVERRIDE_FLAGS.every((f) => typeof f === 'string')],
    ['GO_ALIASES', 'an array of strings (empty for none)',
      () => Array.isArray(GO_ALIASES) && GO_ALIASES.every((f) => typeof f === 'string')],
    ['nextAction', 'a function', () => typeof nextAction === 'function'],
    ['main', 'a function', () => typeof main === 'function'],
  ];
  const wrong = hooks.filter(([, , ok]) => { try { return !ok(); } catch { return true; } });
  return wrong.length
    ? `contract ${CONTRACT}: declare ${wrong.map(([name, shape]) => `${name} as ${shape}`).join('; ')}, above the runtime block`
    : null;
})();

// Entry-point guard, the last statement of the file. Resolve symlinks first: an
// npm-linked bin is a symlink, so process.argv[1] is the link path while
// import.meta.url is the real path, and the naive guard silently skips main()
// (exit 0, no output). realpath both sides to compare. An exception escaping
// main(), or thrown from a timer, a floating promise or a stream with no
// 'error' handler before the report is written, exits 3: the run could not
// complete, which is not a partial result (1). One thrown after the report is
// still printed, but the run exits with the code the report gave, which nothing
// may contradict. A main() that returns without a report exits once stdout has
// flushed. An error while the module loads (a syntax error, a top-level use of
// a fence name above the fence) happens before this runs, and Node exits 1.
const __entry = process.argv[1]
  ? (() => { try { return runtimeFs.realpathSync(process.argv[1]); } catch { return process.argv[1]; } })()
  : null;
const __isMain = !!__entry && import.meta.url === runtimeUrl.pathToFileURL(__entry).href;
if (__isMain) {
  const fatal = (e) => {
    console.error(`error: ${e?.message ?? e}`);
    if (reportedExit === null) process.exit(3);
    exitAfterFlush(reportedExit);
  };
  process.on('uncaughtException', fatal);
  process.on('unhandledRejection', fatal);
  if (HOOK_ERROR) fatal(new Error(HOOK_ERROR));
  else Promise.resolve().then(main).then(() => { if (reportedExit === null) exitAfterFlush(process.exitCode ?? 0); }, fatal);
} else if (HOOK_ERROR) {
  throw new Error(HOOK_ERROR);
}
// ---- end mjs-tool runtime v3 ----
