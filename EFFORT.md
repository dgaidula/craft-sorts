# EFFORT — Sorts

One row per work-pass, written with `effort-log add … --go`. Agent metrics (duration, tokens, tool calls) are exact from completion records; orchestration time is an estimate, marked `(est.)`. Git author is the machine identity, not who did the work.

| # | Date | Agent / model | Role | Scope | Duration | Tokens | Tool calls | Outcome | Notes | Task | Kind |
|---|---|---|---|---|---:|---:|---:|---|---|---|---|
| 1 | 2026-11-05 | Claude Opus 5.5 (1M ctx)[high] | orchestrate | Handoff pickup (audit skipped: no model change, nothing in flight), grounding, step 1 checks, engine brief + verify probe, testbed to Craft 5.12.0.1 and all packages current (committed 66cc527), D816/D817 recorded, Craft-layer brief | ~1h 30m (est.) | n/a | ~70 (est.) | success |  | sorts-first-build | orchestrate |
