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
