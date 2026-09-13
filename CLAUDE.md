# CLAUDE.md — Project instructions

Durable project rules for AI agents. Put lasting guidance here or in `docs/`.

This app is a **multi-tenant manufacturing / inventory ERP** being rebuilt from
`../laravel-ms-ai` (v1) module by module. v1 stays untouched as the working reference —
read it freely, but **do not paste its JSX**; every screen is redesigned here.
Progress: [`docs/MIGRATION-STATUS.md`](docs/MIGRATION-STATUS.md).

## Never commit unprompted

Finish the work, get the gates green, and **leave the changes in the working tree**.
The user reviews the diff, then asks for a commit. Staging is fine; committing is not.

## Ask when you are not sure

If a decision is genuinely open — two defensible approaches, an ambiguous requirement, a
trade-off the user has a stake in — **ask, with options and a recommendation.** Use
`AskUserQuestion`, name the trade-off inside each option rather than only the label, and mark
the one you would pick "(Recommended)". **This holds in plan mode and in auto mode too**:
neither is a reason to guess and carry on.

It is not licence to ask about everything. A conventional default, something the codebase
already settles, or a detail the user has no stake in is yours to decide — make the call, say
you made it, and keep going. The bar is whether a different answer would change the work.

## There is no test suite — this is deliberate

No Pest, no Vitest, no PHPUnit, and **no Playwright test suite**. Do **not** add one, and
do not suggest "let me write a test for that". (Playwright *is* used — to drive the app by
hand after each phase, per point 3. Driving it is not the same as a suite: nothing is
asserted, recorded or run in CI.) The safety net is:

1. **Static gates** (below) — they must be green before any handover.
2. **`bun run check:validation`** — reads the PHP FormRequests and checks every
   server-validated field has a matching zod key. This is the *only* thing standing
   between the two validation layers and silent drift. Never delete or weaken it.
   Its sibling `bun run check:i18n` fails on a validation rule with no translated
   message, because Laravel *falls back* to English rather than failing — an
   untranslated rule renders, and nothing reports it.
3. **Driving the real app in a browser with Playwright** — mandatory after every phase, and
   it is *my* job, not the user's: they review the code, I prove the screens work. Confirm
   the page is actually server-rendered (`data-server-rendered="true"` in view-source)
   before trusting the pass, watch the browser console, and report what was observed.
   **The sweep covers every feature, not only the phase's own screens** — a shared
   component, a new server prop or a locale key breaks pages nobody touched, and a React
   #418 hydration warning in the console is what that looks like. **The user watches the
   browser, so narrate each step as an overlay injected into the page itself — not in the
   chat, which they are not reading at the time — and keep moving without waiting.**
   Checklist, the sweep, the `window.__qc` overlay helper to paste in, and the four silent
   client-fallback causes: [`docs/CODING-STANDARDS.md`](docs/CODING-STANDARDS.md).

Because nothing runs the UI in CI, **SSR determinism is a hard rule, not a style
preference**: no `Date.now()`, `Math.random()`, or unpinned `Intl`/`toLocaleString` in
render output. Compute in `useEffect` or pin the locale. A nondeterministic render is a
React #418 hydration mismatch that nothing but your own eyes will catch.

**Timestamps are stored and sent in UTC and displayed on the workspace's clock.** The zone
is a server prop — `useTimeZone()`, resolved by `App\Support\TimeZones::resolve()` from the
business-settings row, falling back to a browser-reported cookie on `/admin` where there is
no workspace, then to UTC. Never `Intl.DateTimeFormat().resolvedOptions()` during a render,
for the same reason the locale is never `navigator.language`. `lib/format.ts` asks `Intl`
only for numbers, with both locale and zone pinned, and composes the text itself: ICU month
names differ between the SSR runtime and the browser.

**That setting is display-only, and the rule is stricter than it sounds.** It never
influences what is written to a column. Only *instants* — `created_at`, `received_at` — are
converted, and only for rendering. A date a person **chose**, such as an expected delivery,
is stored and shown verbatim with no zone anywhere near it (see
`PurchaseOrderRequest::expectedInstant()`), so changing the workspace clock can never move
an agreed day.

## Code review is the user's to run

Do **not** invoke a code-review skill, and do not dispatch a reviewer subagent, at phase end
or anywhere else. The user runs the review themselves, with their own command and on their
own schedule. Finish the phase, get the gates green, sweep it in the browser, report what
changed — and stop there.

## Code standards (enforced)

- **Frontend (JS/TS/TSX/JSON): Biome.** `bun run check` before finalizing any frontend
  change; verify with `bun run check:ci` (**0 errors**). Biome replaces ESLint + Prettier —
  do not reintroduce them.
- **PHP: Laravel Pint.** `vendor/bin/pint --dirty`.
- **Types:** `bun run types:check` (TS) and `composer types:check` (PHPStan **level 7,
  no baseline**) must pass. Fix findings; never add a baseline.
- **Vendored shadcn/ui is READ-ONLY.** Never hand-edit `resources/js/components/ui/**`
  or the generated trees (`resources/js/{routes,actions,wayfinder}`, `bootstrap/ssr`,
  `resources/js/types/generated.d.ts`). Pass props, **wrap** the primitive in your own
  `components/` file, or compose `radix-ui` directly. `scripts/ui-guard.sh` enforces this.
- **UI/UX: shadcn + design tokens, never a bare form.** See
  [`docs/UI-UX-GUIDELINES.md`](docs/UI-UX-GUIDELINES.md).
- **Package manager / JS runtime: Bun** (`bun install`, `bun run …`; lockfile `bun.lock`).
  Not npm / pnpm / yarn.

## Code organisation

**Read [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) before adding a file.** It defines
where each kind of code lives and the four rules that keep it maintainable: size as a
signal (~250-line pages, ~30-line controller methods), one-way dependencies
(`pages → components → ui`, `lib/` imports neither), rule-of-three before promoting a
component out of its module, and business logic never in a page or a controller.
`scripts/check-structure.sh` enforces the mechanical parts.

## Localization

**No user-facing literal strings in components — or in validation schemas.** Every label,
placeholder, empty state, toast and aria-label goes through `t()`; every zod check is built
from `lib/validation/primitives.ts`, which carries a translation key rather than a sentence
so the browser refuses a value with the words the server would have used. Laravel `lang/`
is the single source of truth;
locales are `en` (base), `ms`, `zh_Hans`. The active locale comes from a **server prop** —
never `navigator.language`, which would reintroduce hydration mismatches. Interpolate
rather than concatenate, and pluralise through the helper (Malay and Chinese have no
plural inflection). Details: [`docs/LOCALIZATION.md`](docs/LOCALIZATION.md).

## Packages — buy vs build

**Before hand-rolling anything non-trivial, check whether a stable, popular package does
it.** Never add one for something the platform already does well.

**Standing permission (2026-09-13): a package that clears the bar may be installed without
asking first.** The bar is all five criteria in
[`docs/PACKAGE-POLICY.md`](docs/PACKAGE-POLICY.md) — stable 1.0+, the ecosystem default,
maintained, verified against **Laravel 13 / React 19 / Tailwind v4**, removable — plus
bundle cost for a frontend one. Anything that does not clear all of them is still proposed
rather than installed, and so is anything that changes the architecture or replaces a
decision already in the catalog.

**Read the docs through the context7 MCP plugin before adding or upgrading anything** —
`resolve-library-id`, then `query-docs`. Compatibility is the criterion training data gets
wrong, because it is exactly the fact that changes after a model ships. Check it, don't
recall it. Then say in the handover what was installed and why, and add it to the catalog.

## Architecture

- Laravel 13 + Inertia v3 + React 19 + TypeScript + Tailwind v4, **SSR on**.
- `stancl/tenancy` in **multi-database** mode, tenants identified by **URL slug**
  (`/{slug}/…`).
- One central (landlord) DB (super-admins + tenants) + one DB per tenant. The central
  connection is named **`central`** — **never** define a connection named `tenant`.
- Central migrations in `database/migrations/`; per-tenant in `database/migrations/tenant/`.
- Validation is **two-layer**: a Laravel FormRequest (the source of truth) and a matching
  zod schema that refuses the same thing in the browser before the request is sent.

## Pre-finalize checklist

A `lefthook` gate runs the fast checks on commit and the rest on push (`bun run prepare`
installs it). Run them by hand too:

1. `bun run check` (frontend) · `vendor/bin/pint --dirty` (PHP)
2. `bun run types:check` · `composer types:check`
3. `bun run check:structure` · `bun run check:validation` (any form/FormRequest touched)
   · `bun run check:generated-types` (any `app/Data` class touched — then
   `bun run types:generate` and commit the result)
4. `bun run build` before a release
5. Drive the change in a browser — light **and** dark. Features at one ordinary desktop
   viewport (**1440 × 900, set explicitly**); responsive as its own pass at **375 / 768 /
   1024**. At the end of a phase, sweep **every** migrated module, not only the one that
   changed.
