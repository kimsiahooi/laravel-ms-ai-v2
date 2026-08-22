# Coding Standards

| Language | Tool | Config | Command |
|---|---|---|---|
| JS / TS / JSX / TSX / JSON | **Biome** 2.x | `biome.json` | `bun run check` |
| PHP | **Laravel Pint** | `pint.json` | `vendor/bin/pint --dirty` |
| TS types | `tsc` | `tsconfig.json` | `bun run types:check` |
| PHP types | **PHPStan** (larastan) level 7 | `phpstan.neon` | `composer types:check` |

**Package manager / JS runtime: [Bun](https://bun.sh).** `bun install`, `bun run …`,
lockfile `bun.lock`. Not npm / pnpm / yarn.

## Biome

Replaces ESLint + Prettier. One tool for formatting, linting, and import organization.

- `bun run check` → format + organize imports + safe lint fixes. Run before finalizing.
- `bun run check:ci` → verify only. A passing run is **0 errors**.
- `bunx biome check --write --unsafe <files>` → applies unsafe fixes (e.g. Tailwind class
  sorting). **Review what it changes** — its `noPositiveTabindex` fix once rewrote
  `tabIndex={1}` to the string `tabIndex="0"`, which type-checks as an error. Scope it to
  the files you're touching and re-run `types:check` after.

Style: 4-space indent · single quotes (JS) / double (JSX attrs) · semicolons · width 80 ·
`import type` enforced · Tailwind classes sorted in `class`/`className`/`clsx`/`cn`/`cva`.

**Excluded** (`biome.json` → `files.includes`): `public`, `resources/css` (Biome's CSS
parser doesn't handle Tailwind v4 at-rules), `resources/js/components/ui` (vendored),
`resources/js/types/generated.d.ts`, `composer.json`.

## No baseline, anywhere

PHPStan runs at level 7 with **no baseline file**, and Biome rules are `error`, not `warn`.
Ported code gets cleaned as it lands. This is the main reason v2 will stay more
maintainable than v1 — do not reintroduce a baseline to make a gate pass.

## Vendored components (shadcn/ui) — do NOT modify

`resources/js/components/ui/**` is vendored shadcn/ui + Radix. Treat it as read-only
third-party code. Editing a primitive to fix one screen reliably breaks another (v1 has a
recorded regression from exactly this), and it makes future shadcn updates conflict.

When you need different behaviour:

1. **Props / className first** — most primitives already expose the knob.
2. **Wrap it** — compose in a component under `resources/js/components/`. This is the default.
3. **Compose Radix directly** — import from `radix-ui` in your own component.
4. **Re-generate** — `bunx shadcn@latest add <name>` rather than hand-editing.

The same applies to generated trees: `resources/js/{routes,actions,wayfinder}`,
`bootstrap/ssr`, `resources/js/types/generated.d.ts`. `scripts/ui-guard.sh` blocks commits
that touch any of them.

`resources/css/app.css` is **not** read-only — it holds the authored design tokens. It's
excluded from Biome only because of the Tailwind v4 parser limitation.

## Routing traps (Laravel + a package that registers routes)

- **`Route::pattern()` goes in a provider's `register()`, not `boot()`.** A pattern is
  merged into a route as that route is *defined*, so it only constrains routes declared
  after the call. Fortify defines its `{tenant}/…` routes in its own `boot()`, which runs
  before this app's providers — declaring the `{tenant}` pattern in `boot()` silently
  misses them, and `/admin/login` 404s as an unknown workspace. This has already cost one
  debugging session; see `docs/MIGRATION-STATUS.md`.
- **Reserved first-path segments live in exactly one list**, `App\Support\ReservedSlugs`.
  It builds the `{tenant}` route pattern, the global tenancy middleware's skip list, and
  the slug validation rule. Adding a central area means adding it there — nowhere else.

## Browser verification with Playwright — after every phase

The user has the Claude Code Playwright plugin installed, and the agreement is: **they read
the code, I drive the browser.** No phase is finished until its screens have been exercised
in a real browser and the result reported — screenshots or a console transcript, never
"should work".

**`bun run dev` server-renders too** — Inertia v3 POSTs the page to the *Vite dev server*
at `/__inertia_ssr`, not to port 13714. `php artisan inertia:start-ssr` is only for built
assets. So a browser pass under `bun run dev` is a real SSR pass and does surface hydration
mismatches.

**But confirm it before trusting the result.** View source and look for
`<div data-server-rendered="true" id="app">`. An empty `<div id="app"></div>` means you are
looking at a client-rendered page, and three things cause that — all silent, all HTTP 200:

| Cause | Tell |
|---|---|
| The first ~2 s after `bun run dev` | the dev server logs `SSR skipped, module graph is still warming up…`. Wait for `Inertia SSR module graph warmed up`. |
| A stale `public/hot` from a dev server that died uncleanly | `public/hot` exists with no vite running. Laravel POSTs SSR to a dead port and falls back with **nothing in `laravel.log`**. Delete the file. |
| You are looking at a redirect | e.g. `/admin/login` while signed in is a 302 — view-source shows Laravel's redirect page, not the app. |

Do at least one pass per phase against **built** assets as well, since that is what
production runs:

```bash
rm -f public/hot          # stop Laravel dispatching to the dev server
bun run build:ssr
php artisan inertia:start-ssr &
# … drive with Playwright …
```

Per screen: create → edit → delete with the toast and list update; empty form and bad value
(errors under the fields, from the zod gate *and* from Laravel); empty state; "no results"
after a search; light **and** dark; 375 / 768 / 1024 with no horizontal body scroll; all
three locales. **Read the browser console every time** — a React #418 warning is a hydration
mismatch.

## Verification — there is no test suite

The gates above catch mechanical errors. Everything behavioural is verified by driving the
app. Per change:

- Create → edit → delete; confirm the toast fires and the list updates.
- Submit the form empty and with a bad value. Errors must render **under the fields** —
  from the zod gate before the request leaves, and identically from Laravel when bypassed.
- Empty state, "no results" after a search, loading state, error path.
- Light **and** dark, at 375 / 768 / 1024, with no horizontal body scroll.
- Reload with SSR on and watch the console. A React #418 warning is a hydration mismatch.

For stock-touching changes: post a movement, a transfer, and a stock take, then confirm
`stock_movements` and `warehouse_stocks` agree and that an over-issue is refused.
