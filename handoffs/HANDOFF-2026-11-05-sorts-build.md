# Handoff: Sorts first build, 2026-11-05

- **From:** Claude Opus 5.5 (1M ctx), fable-mode orchestrator; session started 2026-11-04 in a client project (its details stay there); Claude Code CLI.
- **To:** `orchestrate` — `clorchestrate` started in this folder. No model change; nothing in flight.

## 1. Where the work stands

- Stamped 2026-11-05 from `craft-plugin-base` `309f8f4` with `bin/new-plugin.mjs --vendor iceboxind --handle sorts --name Sorts --no-config-screen`. The stamp is unchanged apart from `SPEC.md`, this handoff and `HANDOFFS.md`.
- `SPEC.md` is the build spec. Settled by Dan on 2026-11-05: the name (Sorts, `iceboxind/craft-sorts`), the Lite/Pro line (§2), plugin first, and Pro covering every rule in §3.
- Local repo only: no GitHub remote, nothing pushed, not on Packagist.

## 2. In flight

Nothing.

| Item | State | Basis |
|---|---|---|
| `SPEC.md` | Waiting on Dan (D812–D815; none blocks the engine) | `SPEC.md` §9 |
| First build | Idle | this handoff |

## 3. The next three steps

1. **Read `SPEC.md`, this repo’s `CLAUDE.md`, `craft-plugin-base/docs/PATTERNS.md` §§11–17 and `docs/DEVELOPMENT.md`; check the testbed** (`~/sw/github-private/craft5-plugin-testbed`, its `CLAUDE.md`). Acceptance: the testbed boots under ddev and `composer check` runs here under `/opt/homebrew/bin/php`.
2. **Build the engine first** (§5.1, §3, §4, §8 corpus and fuzz): cast `dgbuilder`, mode **release**. Locales as rule-set data behind one interface, so D812 moves files, not code. Acceptance: the corpus (≥ 400 cases) and the byte-identical fuzz pass, idempotence holds, and the performance number is recorded.
3. **Then the Craft layer** (§5.2–§5.5, §6), smoke-tested in the testbed under Lite and Pro, then a `gate` at `xhigh` with mutants. Acceptance: gate closed, and the README carries the `**Hardened:**` line.

## 4. Decisions still Dan’s

1. **D813:** build the extension API (SPEC §5.5: register, scope and order custom rules) in the first release, in Pro? Recommended: yes. Doesn’t block the engine; build the rule interface either way.
2. **D812:** ship all locales in Pro (recommended), or English in Pro plus paid per-locale add-on plugins? Doesn’t block the build.
3. **D814:** Pro at $39 + $19/yr (recommended)? Needed only at Store submission.
4. **D815:** create the GitHub repo `dgaidula/craft-sorts` (public, like the sibling plugins) and push? Outward-facing; not done.

## 5. Unverified beliefs and known gaps

- The CLDR quote pairs in `SPEC.md` §4 are from memory; the spec says to take them from CLDR data.
- The ≤ 5 ms per 100 KB budget is a target, not a measurement.
- Whether Pixel & Tonic accepts paid add-on plugins for your own plugin (relevant only if D812 picks add-ons) is unverified; it belongs with the edition questions in `monetization-ops/plans/craft-plugins.md`.

## 6. Traps

- Never test against a client site; the testbed only.
- Twig escapes quotes as `&quot;` / `&#039;`, so the engine must read entity forms (§5.1).
- Don’t re-serialize pages through `DOMDocument`.
- `php-typography` is GPL: don’t add it as a dependency.
- Sonnet never takes a fix pass over verified findings (`casting` skill).
