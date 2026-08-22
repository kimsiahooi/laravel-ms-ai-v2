# Package policy — buy vs build

**Suggest a package whenever a stable, popular one would remove real code or real risk.**
Don't hand-roll something the ecosystem has already standardised on, and don't add a
dependency for something the platform already does well.

This is a live catalog. Update it as decisions land.

## The bar

A package has to clear all five:

1. **Stable** — a 1.0+ release line, no churn in its public API between minors.
2. **Popular** — the thing the Laravel/React ecosystem actually reaches for. If picking it
   needs a justification paragraph, it is probably not the default.
3. **Maintained** — commits and releases in the last ~6 months; issues get answered.
4. **Compatible** — confirm it supports **Laravel 13 / React 19 / Tailwind v4** *before*
   proposing it. Several popular packages still lag on these.
5. **Easy to manage** — installs and configures without patching, doesn't fight Inertia or
   SSR, and can be removed again without unpicking the whole app.

Frontend packages carry one extra test: **bundle cost**. This app is SSR + Inertia, so a
heavy client library shows up in every page load. Prefer one that tree-shakes or lazy-loads
(v1 loads `@zxing/browser` only when the native barcode API is missing — a good pattern).

## How to propose one

When a feature would benefit, say so **before** writing the hand-rolled version:

> "This needs currency-safe money math. `brick/money` is the ecosystem default, actively
> maintained, Laravel-13 compatible, and replaces ~200 lines of rounding logic we'd
> otherwise own. Add it, or hand-roll?"

Name the package, why it beats hand-rolling, roughly what it removes, and any risk. Then
let the user decide. Never silently add a dependency.

## Already decided

**In the stack:** `stancl/tenancy` (multi-DB tenancy) · `spatie/laravel-permission` (RBAC)
· `spatie/laravel-data` + `spatie/laravel-typescript-transformer` (DTOs → generated TS)
· `spatie/laravel-medialibrary` (product images, logos) · `spatie/laravel-activitylog`
(audit trail) · `openspout/openspout` (streaming CSV/XLSX) · `laravel/fortify` (+ passkeys,
2FA) · `laravel/wayfinder` (typed routes) · `@tanstack/react-table` · `zod` · `cmdk` ·
`sonner` · `recharts` · `radix-ui`.

**`@tanstack/react-table` — v9, decided 2026-08-22.** Worth recording because the evidence
argued the other way and the call was the user's. v1 pays for this dependency and registers
only `getCoreRowModel`: across all 20 of its list pages it uses no sorting, filtering,
pagination, selection or visibility model, because every one of those happens in SQL. So the
library was earning a `ColumnDef` type and `flexRender`, and hand-rolling a column
descriptor was the cheaper, lighter option. It was adopted anyway, on v9.1.2 rather than the
v8.21.3 every shadcn data-table snippet targets, so the feature set is there when row
selection or virtualization arrives rather than needing the column API rewritten then.

Two things to know when working on it: v9 renamed `useReactTable` to `useTable` and nothing
exists until its feature is registered in `tableFeatures({…})`, so a missing API usually
means a missing feature rather than a removed one; and `manualPagination` / `manualSorting`
only *bypass* the client stages — they never fetch and never slice, so `data` must already
be the page the database returned.

**Deliberately not added — the platform already does it:**

| Need | Use instead |
|---|---|
| Relative/absolute date formatting | native `Intl.RelativeTimeFormat` / `DateTimeFormat` in `lib/format.ts` — pin the locale for SSR |
| Class merging | `clsx` + `tailwind-merge` (`cn`) |
| Client form state | Inertia `<Form>` + the zod gate — **not** react-hook-form |
| Status enums with labels | native PHP `enum` |
| UI translation | Laravel `lang/` + a thin `t()` — **not** `i18next`/`react-i18next` or `@lingui/react`. See below. |

v1 proved these: it shipped `react-hook-form` and `@hookform/resolvers` that nothing ever
imported, and 14 `@radix-ui/*` packages with zero direct imports.

**On i18n specifically** — `react-i18next` is genuinely the React default, and would have
been the obvious pick. It was rejected on a concrete technical ground, not on size:
its SSR safety relies on `i18next-http-middleware` cloning a **per-request** instance,
because a shared singleton leaks one user's language into another's render. Inertia SSR is a
long-lived Node process with no request context and no middleware hook, so that mechanism
does not exist here. `@lingui/react` was rejected because its Babel macro has to coexist
with the React Compiler plugin already configured in `vite.config.ts`. Full reasoning:
[`docs/LOCALIZATION.md`](LOCALIZATION.md).

## Likely upcoming decisions

Flagged now so they get considered at the right moment, not retrofitted:

| Phase | Need | Candidate | Note |
|---|---|---|---|
| 5 · Orders | Money & totals | `brick/money` | Currency-safe integer math + rounding. v1 stores `decimal(15,4)` and does the math by hand — the #1 place a rounding bug becomes a wrong invoice. |
| 5 · Orders | Printable invoice / DO / PO | `spatie/laravel-pdf` (Browsershot) or `barryvdh/laravel-dompdf` | v1 print-styles an Inertia page; PDF is the natural next step. Browsershot needs a headless Chrome — weigh the ops cost. |
| 7 · Settings | Typed app settings | `spatie/laravel-settings` | v1 hand-rolled a `Setting` model + registry + casting. Evaluate whether the package is simpler before porting the hand-rolled one. |
| 8 · Reports | Heavy exports | already covered by `openspout` | Streams rather than buffering. v1's roadmap said `maatwebsite/excel`, but openspout is the lighter pick and is what v1 actually shipped. |
| later · Costing | Weighted-average cost / COGS | **none — build it** | Domain logic, no package fits. Depends on `brick/money`. |
| later | Multi-UOM conversions | **none — build it** | Belongs in the core item model. |

## Removing packages counts too

A dependency nothing imports is worse than no dependency: it carries install time, security
surface, and a false signal about how the app works. When a module lands, check whether it
made anything unused — and say so.
