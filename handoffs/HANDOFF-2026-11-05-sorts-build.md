# Handoff: Sorts first build, 2026-11-05

- **From:** Claude Opus 5.5 (1M ctx), fable-mode orchestrator; session started 2026-11-04 in a client project (its details stay there); Claude Code CLI.
- **To:** `orchestrate` — `clorchestrate` started in this folder. No model change; nothing in flight.

## 1. Where the work stands

- Stamped 2026-11-05 from `craft-plugin-base` `309f8f4` with `bin/new-plugin.mjs --vendor iceboxind --handle sorts --name Sorts --no-config-screen`. The stamp is unchanged apart from `SPEC.md`, this handoff and `HANDOFFS.md`.
- `SPEC.md` is the build spec. Settled by Dan on 2026-11-05: the name (Sorts, `iceboxind/craft-sorts`), the Lite/Pro line (§2), plugin first, and Pro covering every rule in §3.
- Public repo `github.com/dgaidula/craft-sorts` (D815, 2026-11-05); `main` pushed. Not on Packagist or the Store.
- Decided 2026-11-05: every locale in Pro (D812); the extension API in the first release, in Pro (D813).

## 2. In flight

Nothing.

| Item | State | Basis |
|---|---|---|
| `SPEC.md` | Approved for build; D814 (editions and price) in discussion, doesn’t block | `SPEC.md` §9 |
| First build | Idle | this handoff |

## 3. The next three steps

1. **Read `SPEC.md`, this repo’s `CLAUDE.md`, `craft-plugin-base/docs/PATTERNS.md` §§11–17 and `docs/DEVELOPMENT.md`; check the testbed** (`~/sw/github-private/craft5-plugin-testbed`, its `CLAUDE.md`). Acceptance: the testbed boots under ddev and `composer check` runs here under `/opt/homebrew/bin/php`.
2. **Build the engine first** (§5.1, §3, §4, §8 corpus and fuzz): cast `dgbuilder`, mode **release**. Locales as rule-set data behind one interface, so a locale is a file, not code. Acceptance: the corpus (≥ 400 cases) and the byte-identical fuzz pass, idempotence holds, and the performance number is recorded.
3. **Then the Craft layer** (§5.2–§5.5, §6), smoke-tested in the testbed under Lite and Pro, then a `gate` at `xhigh` with mutants. Acceptance: gate closed, and the README carries the `**Hardened:**` line.

## 4. Decisions still Dan’s

1. **D814** 💬 — editions and price. Dan asked on 2026-11-05 whether Lite must be free. Recommendation: Lite free, Pro $39 + $19/yr; fallback, one paid edition (no Lite). Needed only at Store submission; `Plugin::isPro()` gates either way.

## 5. Unverified beliefs and known gaps

- The CLDR quote pairs in `SPEC.md` §4 are from memory; the spec says to take them from CLDR data.
- The ≤ 5 ms per 100 KB budget is a target, not a measurement.

## 6. Traps

- Never test against a client site; the testbed only.
- Twig escapes quotes as `&quot;` / `&#039;`, so the engine must read entity forms (§5.1).
- Don’t re-serialize pages through `DOMDocument`.
- `php-typography` is GPL: don’t add it as a dependency.
- Sonnet never takes a fix pass over verified findings (`casting` skill).
