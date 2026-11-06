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

## 4. Decisions still Dan’s

None open from this session. (Republishing the specimen after the build, so its “engine pending” rows go live, is a courtesy, not a decision.)

## 5. Unverified beliefs and known gaps

- Open follow-ups: Sorts R4-2–R4-6 (`gates/2026-11-06-sorts-d818-d824-recheck-3.json`), FU2 of the R4-1 re-check (a straight `'` inside `‘…’` inside an open `“…”` closes the `“`, e.g. `dogs”`); prose-typeset R2-3–R2-8 (template pages with `{{` in code samples are skipped); `prose-typeset` docblocks.
- Unpushed: claude-ops ledger commits (another session had uncommitted changes there, so no pull or push), gaidula-standards, research-notes, prose-typeset (no remote), the five plugin repos’ Craft 6 notes, craft-sorts after `d6bb835`.

## 6. Traps

- **Decision IDs collide:** other sessions mint `D` numbers at the same time. Run `id-codes next D` immediately before writing one (this session wrote D851 once by mistake; `d4f6a51` corrected it).
- **Never run an unbounded load test on this MacBook** (a stress run hung for three hours: memory `bound-load-tests`).
- **Sesame’s HTTP tests hard-code `craft5-testbed`**: running them from the repo against another site writes to the Craft 5 testbed (the Craft 6 trial did; `node tests/run-all.mjs --reset` cleans the config).
- **The engine leaves curly marks already in text alone:** Word’s wrong-way `‘90s` is fixed by hand. Ranges are numbers only.
- **Subagent reports are claims:** every build here was verified by re-running its tests and probes; keep doing that.
