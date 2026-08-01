# AGENTS.md — chatter

Laravel 13 (PHP 8.3) + Inertia 3 + Svelte 5 starter-based chat app.
Keep it a small app. Complexity must be proportional to the task: a 5-minute task gets a
5-minute change. Over-engineering (speculative abstraction, validation "gates", extra
conditions) is a defect, not a bonus.

## Hard rules for every change

1. **Plan first (non-trivial only).** Before touching code, output a 3-bullet plan:
   what changes, which files, where it stops. Reject any plan that introduces a
   framework, gate, helper, or abstraction without naming a current failure it prevents.
2. **Budgets.** Default budget: ≤3 files, no new dependencies, preserve existing
   structure. If a change needs more, say why before continuing. For small scripts and
   one-offs, verification is one happy-path run plus tests only for regressions the
   change could realistically create.
3. **No speculative gates.** Do not add validation gates, guards, or early returns that
   no current failure justifies. If a gate already exists and a type or earlier check in
   the chain guards the same path, remove the redundant gate instead of adding another.
   Do not enter validation loops: verify once, then move on.
4. **State the structure you want.** When a task involves new UI or modules, the requester
   states the target structure (components, files, architecture). Implement exactly that
   structure, barebones. Do not invent additional layers.
5. **Verification is bounded.** One happy-path run + focused tests only. Do not loop on
   edge cases "just in case". A change is done when its named behavior works and the
   applicable checks below pass — not when it is architecturally "complete".
6. **Review filter.** For every review finding ask: "Does fixing this change the outcome
   of the main process?" If no, it is out of scope. Ignore test-coverage gaps,
   falsification gaps, generic hardening, and security/concurrency observations that are
   merely hardening.
7. **Reuse over invention.** Use stdlib, framework natives (Laravel/Inertia/Svelte
   conventions), and existing helpers first. Delete dead code when you touch it.
   YAGNI + KISS + DRY + Occam's razor: when a plan is proposed, say what survives the
   filter before implementing.

## Verification commands

| Check | Command |
|---|---|
| Backend style | `composer lint:check` (Pint) |
| Static analysis | `composer types:check` (PHPStan) |
| Tests | `composer test` — focused: `php artisan test --filter=<Name>` |
| Frontend lint / format / types | `npm run lint:check` · `npm run format:check` · `npm run types:check` |
| Build | `npm run build` |

Green before done. If a check is irrelevant to the change (e.g. frontend types for a pure
backend tweak), say so and skip it — do not "fix" unrelated failures unless they block the
change.

## Working style

- **Planning/architecture:** high-effort reasoning (Sol / max). **Implementation:** lower
  effort (Luna / medium or below). Never implement with the highest reasoning model; that
  is how over-engineering happens.
- Small, self-contained tasks in separate sessions/chats. Each task is planned, briefed,
  then executed.
- Learned lessons belong in this file as short rules, not in prompt boilerplate.
