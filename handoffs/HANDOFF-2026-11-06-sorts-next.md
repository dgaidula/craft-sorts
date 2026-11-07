# Handoff: Sorts next build, icon, G32 tooling (2026-11-06)

- **From:** Claude Opus 5.5 (1M ctx), fable-mode orchestrator; session started 2026-11-05 (`handoff pick up` of `HANDOFF-2026-11-05-sorts-build.md`); Claude Code CLI. Written at about 15 % context left, at Dan’s request.
- **To:** `orchestrate` (`clorchestrate` in `~/sw/github-public/craft-sorts`). No model change. **Two background agents were running when this was written** (§2): their reports return to the old session only. A new session reads the repos, not the agents.

## 1. Where the work stands (verified this session)

- **Sorts is hardened and pushed:** README `**Hardened:** 2026-11-06 · 0bb7844 · gate CLOSED WITH FOLLOW-UPS` (`gates/2026-11-06-sorts-r4-1-recheck.json`); `origin/main` at `d6bb835`. That covers D818–D824 (quote type, nesting, variants) after a FAIL, three fix rounds and four re-checks (all receipts in `gates/`). Local commit after it: `6b077dd` (Craft 6 note), and whatever the running build commits.
- **Decisions this session** (claude-ops `DECISIONS-OPEN.md`, Decided): D816–D824, D829, D843, D844 (German book style default), D845–D849 (fine typography is house standard G32), D850, D854 (house preset: ranges and multiplication on; “correctness wins”), D855–D859 (Bringhurst ch. 5), D817 (Bringhurst preset in Sorts, cite the book).
- **G32 house standard:** rules for models in chezmoi `dot_claude/skills/gaidula-taste/references/fine-typography.md` (applied, pushed); proposal and README row in `~/sw/github-private/gaidula-standards`; house preset `gaidula-standards/presets/typeset-house.json` (ranges and multiplication on, `de` → `book`); Bringhurst 3e ch. 5 rule table `gaidula-standards/research/bringhurst-3e-ch5-2026-11-06.md` (39 rules, Dan’s photos in `~/Downloads/bringhurst-chapter5/`).
- **prose-typeset** (`~/sw/github-private/prose-typeset`, private, no remote yet): the CLI that runs the Sorts engine over HTML and text (D846). Working-mode gate FIX FIRST → fix round → re-check NOT CLOSED on R2-1 → orchestrator fixed R2-1 and R2-2 (`c42d1d1`); 32/32 tests. Not on PATH. First real run: thesis v4 at its build (Dan: v3 is a sketch), then `gaidula-www`.
- **Testbed** `~/sw/github-private/craft5-plugin-testbed`: Craft 5.12.0.1, PHP 8.4, MySQL 8.4 (pushed `91877d5`, `7adc763`); `bash scripts/php-version.sh 8.5|default|status` switches PHP without dirtying git.
- **Craft 6 trial:** done; per-plugin notes `docs/craft6-trial-2026-11-06.md` in all five plugin repos (`craft-downtoll`, `craft-lithocinch`, `craft-sesame`, `craft-sorts`, `craft-videocinch`), full report `monetization-ops/research/craft6-trial-2026-11-06.md`. Recommendation: port at beta.
- **Published pages (private artifacts):** G32 specimen https://claude.ai/artifact/GXCWe2FLyqSyHz5U4WUdhr (source `…/scratchpad/specimen/g32-specimen.html`, built by `…/scratchpad/uibuilder-t7k2/build.mjs`); icon directions https://claude.ai/artifact/H8AsL12mAda9dhiQkcutCi. The scratchpad is `/private/tmp/claude-502/-Users-dgaidula-sw-github-public-craft-sorts/00a5e988-4909-468c-bf8c-c01c1c5d14d6/scratchpad/` and is cleared at reboot.

## 2. In flight

| Item | State | Basis |
|---|---|---|
| Sorts build: D844 German default, D855 en-US spaced-en-dash variant, D858 `Dr Smith` nbsp, D859 flag numerical plurals (findings API), Bringhurst gaps (Navajo apostrophe, `8½ × 11`, `8½` join, clock-time ranges), FU1/FU3 from the R4-1 re-check, **plus the D817 Bringhurst preset** | Working (dgbuilder, release mode) when written; commits land on `main` | its brief, this session |
| Icon: Dan picked **C “Four bodies”, archive red `#dc0035`**, made responsive: four bodies at ≥ about 40 px, two at about 32, one pilcrow body at ≤ 24, in one `src/icon.svg` | Working (uibuilder): first reads how Craft 5.12 renders plugin icons (inline vs `<img>`, svg-sanitize and `<style>`) | Dan, 2026-11-06 |

## 3. The next three steps

1. **Read the build’s commits on `main` after `6b077dd`** (`git log`), run `composer test-engine`, `composer check`, `node tests/run-all.mjs`. Then a **release gate at `xhigh`** (cast `gate`) over the delta since `0bb7844`, fix rounds per the casting rules, then renew the Hardened line and push (pushing hardened Sorts is covered by D850). Acceptance: gate closed, README line renewed, `origin/main` level.
2. **Done 2026-11-06 (`76de41b`):** the responsive icon is `src/icon.svg`; its preview and build script are kept in `docs/icon/`. Still to check by eye in the testbed CP (Settings → Plugins at 44 px, the Settings grid at 40 px). Original step: **Install the icon:** the responsive SVG is at `…/scratchpad/icon/sorts-icon-c-responsive.svg` if the designer finished (else its `sorts-icon-c-archive.svg`); copy to `src/icon.svg`, check it renders in the testbed CP (Settings → Plugins), commit. If the scratchpad is gone, re-brief from §2. Acceptance: Store readiness complete (CLAUDE.md list).
3. **G32 phase 4:** `web-standards-check typography` (D847) and the one-line G29g allowance for `hanging-punctuation` and `text-wrap: pretty` (D849), in `~/sw/github-private/web-standards-check`; link `prose-typeset` onto PATH. Acceptance: working-mode gate closed.

## Update, later on 2026-11-06

- **The build landed:** `7b362d6`, `230fbbc`, `4153b5e`, `4b04db1`, `abc79de`, `b007985`, effort row `cb57d6a`. The orchestrator re-ran `composer test-engine`: 5131/0. German now defaults to `»…«` (CLDR’s `„…“` is variant `low-high`; `book` is retired); en-US variant `spaced-en-dash`; the Bringhurst preset is setting `fineTypography`, engine preset `bringhurst`, Pro only.
- **A release gate at `xhigh` was launched** over `0bb7844..cb57d6a`; its receipt will be `gates/2026-11-06-sorts-d844-d859-gate.json`. Step 1 above becomes: read that receipt, run the fix rounds, then renew the Hardened line and push.
- **The house preset needs one edit after the gate:** `gaidula-standards/presets/typeset-house.json` sets `de` → `book`, which is now retired. Remove that line (book style is the default), and add `en-US` → `spaced-en-dash` (D855).

## Overnight run sheet (Dan, 2026-11-06: “we have a lot of capacity running into tomorrow's 16:00 reset”)

Dan answered §4 below as **D863–D867** (claude-ops `38545845`): 1 y, 2 y (tonight), 3 y, 4 y, 5 fix overnight if it can be fixed. Run these in order in a fresh `clorchestrate` session in this repo, with fable-mode, release mode throughout:

- **The gate finished: FAIL** (`gates/2026-11-06-sorts-d844-d859-gate.json`, commit `184baa0`; the receipt holds every repro). The blocking findings are F1–F7:
  - **F1:** second-pass instability with the preset or `spaced-en-dash`. 2,731 of 168k rig rows; in Craft, fix-on-save changes stored content again on the next save.
  - **F2:** D858's `St` check reads stale bytes and can start inside a multibyte character; `Visit St Ives` is unstable even under the default rules.
  - **F3:** the B15 fraction join skips its date and decimal guards at the start of a run.
  - **F4:** B9 shifts the URL positions.
  - **F5:** see Dan's question below.
  - **F6:** the preset plus fix-on-save loses the dialogue dash.
  - **F7:** the preset-only rules can't be switched off, and `dashes` off doesn't stop the typed-dash rule.

  Ten mutants survived; the follow-ups are FU1–FU10. W1 runs the fix rounds for these, folding in D864 (W2, which also settles F7's switches) and D867 (W3).
- **F5 answered: D876 y** (claude-ops ledger): a block-initial `'` stays an opening quote, B38 applies in running text only, and a README Known limit is logged for a Navajo-style word that opens a block. Was: B38 turns the `'` that opens each paragraph of a multi-paragraph quotation (the convention: every paragraph opens, only the last closes) into an apostrophe. The orchestrator's recommendation: a `'` at the very start of a block stays an opening quote, and B38 applies inside running text only. A Navajo-style word at the start of a block is then a hand fix, documented. Ask him, as `D868` or the next free ID, before W1 touches B38.
- **(Superseded: the gate has finished.) First, check whether the gate is still running.** The old session’s release gate (launched about 2026-11-06 evening, 1.5–2 hours) works in this repo’s working tree. Until `gates/2026-11-06-sorts-d844-d859-gate.json` exists on `main`, **don’t edit or commit anything in `craft-sorts`, and don’t start another gate**. Do W5 first (it lives in `gaidula-standards`), then wait for the receipt: use the Monitor tool with an until-loop on that file, never a sleep loop or a load test. Re-run the gate only if no receipt has appeared about 3 hours after launch and no `gate` process is working in the repo.
- **W1.** Read the gate receipt `gates/2026-11-06-sorts-d844-d859-gate.json` (launched at `cb57d6a`; if it is missing, the gate did not finish, so re-run it from the brief recorded in §2 of this update: `0bb7844..cb57d6a`, `xhigh`, mutants). Run fix rounds per the casting rules (at most two; a fix-introduced regression goes to Dan, or past the cap only under `:you`).
- **W2. D864:** the three-state per-rule setting (on, off, follow the preset), so a site can veto a preset rule. Engine `Options`, the Craft settings screen (Pro, locked in Lite), project config, the README. Cast `dgbuilder`, release mode.
- **W3. D867:** fix FU2 if it can be fixed without regressions (the R4-1 re-check receipt `gates/2026-11-06-sorts-r4-1-recheck.json` has the repro); otherwise add it to the README’s Known limits with the repro. Cast `fixer` (it is a gate follow-up).
- **W4.** One release gate at `xhigh` over W2 and W3 (or fold them into W1’s re-check if W1 is still open), with fix rounds. Then renew the Hardened line and push `main`; pushing hardened Sorts is covered by D850.
- **W5.** Edit `gaidula-standards/presets/typeset-house.json`: remove `de` → `book` (retired; book style is the default) and add `en-US` → `spaced-en-dash` (D855). Run `prose-typeset`’s tests, commit.
- Bound every load test (memory `bound-load-tests`); check `id-codes next D` before writing any ID; log an EFFORT row per pass.

## 4. Decisions still Dan’s (the builder’s questions, 2026-11-06), answered as D863–D867

1. **B38, a word-initial apostrophe:** it is narrowed to apply only where no quotation is open, so `'hood` inside a quotation stays `‘hood`. The broad rule is one condition away, but it flipped three existing correct cases. Keep the narrow rule? (Recommend yes.)
2. **The preset against per-rule switches:** a site can't veto ranges, multiplication or fractions while the preset is on, because the settings store a plain on/off where "off" is also their default. Allowing a veto needs a three-state switch (on, off, follow the preset), which is a UI change. Build it? (Recommend later.)
3. **Swiss German guillemets:** under the preset they get no inner thin space, following the Bundeskanzlei. Keep? (Recommend yes.)
4. **D856:** the preset restyles spaced em dashes (`a — b`) as well as closed ones. Keep? (Recommend yes.)
5. **FU2** (en-GB `single-outer`: a straight `'` after `s` or a digit inside `‘…’` inside an open `“…”` closes the `“`): fix it, or document it as a Known limit? (Republishing the specimen after the build, so its “engine pending” rows go live, is a courtesy, not a decision.)

## 5. Unverified beliefs and known gaps

- Open follow-ups: Sorts R4-2–R4-6 (`gates/2026-11-06-sorts-d818-d824-recheck-3.json`), FU2 of the R4-1 re-check (a straight `'` inside `‘…’` inside an open `“…”` closes the `“`, e.g. `dogs”`); prose-typeset R2-3–R2-8 (template pages with `{{` in code samples are skipped); `prose-typeset` docblocks.
- Unpushed: claude-ops ledger commits (another session had uncommitted changes there, so no pull or push), gaidula-standards, research-notes, prose-typeset (no remote), the five plugin repos’ Craft 6 notes, craft-sorts after `d6bb835`.

## 6. Traps

- **Decision IDs collide:** other sessions mint `D` numbers at the same time. Run `id-codes next D` immediately before writing one (this session wrote D851 once by mistake; `d4f6a51` corrected it).
- **Never run an unbounded load test on this MacBook** (a stress run hung for three hours: memory `bound-load-tests`).
- **Sesame’s HTTP tests hard-code `craft5-testbed`**: running them from the repo against another site writes to the Craft 5 testbed (the Craft 6 trial did; `node tests/run-all.mjs --reset` cleans the config).
- **The engine leaves curly marks already in text alone:** Word’s wrong-way `‘90s` is fixed by hand. Ranges are numbers only.
- **Subagent reports are claims:** every build here was verified by re-running its tests and probes; keep doing that.
