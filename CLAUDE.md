# CLAUDE.md — Project instructions

Durable project rules for AI agents. Put lasting guidance here or in `docs/`.

This app is a **multi-tenant manufacturing / inventory ERP** being rebuilt from
`../laravel-ms-ai` (v1) module by module. v1 stays untouched as the working reference —
read it freely, but **do not paste its JSX**; every screen is redesigned here.
Progress: [`docs/MIGRATION-STATUS.md`](docs/MIGRATION-STATUS.md).

## Never commit unprompted

Finish the work, get the gates green, and **leave the changes in the working tree**.
The user reviews the diff, then asks for a commit. Staging is fine; committing is not.

## There is no test suite — this is deliberate

No Pest, no Vitest, no Playwright, no PHPUnit. Do **not** add one, and do not suggest
"let me write a test for that". The safety net is:

1. **Static gates** (below) — they must be green before any handover.
2. **`bun run check:validation`** — reads the PHP FormRequests and checks every
   server-validated field has a matching zod key. This is the *only* thing standing
   between the two validation layers and silent drift. Never delete or weaken it.
3. **Driving the real app in a browser** — the verification checklist in
   [`docs/CODING-STANDARDS.md`](docs/CODING-STANDARDS.md).

Because nothing runs the UI in CI, **SSR determinism is a hard rule, not a style
preference**: no `Date.now()`, `Math.random()`, or unpinned `Intl`/`toLocaleString` in
render output. Compute in `useEffect` or pin the locale. A nondeterministic render is a
React #418 hydration mismatch that nothing but your own eyes will catch.

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
3. `bun run check:validation` (any form/FormRequest touched)
4. `bun run build` before a release
5. Drive the change in a browser — light **and** dark, 375 / 768 / 1024
