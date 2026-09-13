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

## Installing one

**Standing permission, granted 2026-09-13:** a package that clears all five criteria above
may be installed without asking first. Install it, then report it in the handover — what it
is, what it removes, what it risks — and add it to the catalog below so the next decision
has the context.

**Verify the docs through the context7 MCP plugin first, every time.**
`resolve-library-id` to find the library, then `query-docs` for the API about to be used.
Criterion 4 is the one that goes stale: whether a package supports Laravel 13, React 19 or
Tailwind v4 is precisely the kind of fact that moved after the model was trained, and it is
the criterion most likely to be confidently wrong. Check it against the live docs rather
than recalling it. Doing this also means the code written against the package matches its
current API instead of a remembered one.

## Still worth asking about

The standing permission covers the routine case. It is not cover for a call the user would
want to make themselves. Propose rather than install when the package:

- does not clear all five criteria, or fails the bundle-cost test;
- changes the architecture, or replaces something already decided in the catalog below;
- brings a heavy runtime, a build-step change, or a native dependency;
- overlaps something the platform already does well — the table at the end of this document
  exists because that is the most common wrong reason to add one.

The shape of a proposal, when one is needed:

> "This needs currency-safe money math. `brick/money` is the ecosystem default, actively
> maintained, Laravel-13 compatible, and replaces ~200 lines of rounding logic we'd
> otherwise own. Add it, or hand-roll?"

Name the package, why it beats hand-rolling, roughly what it removes, and any risk.

## Already decided

**In the stack:** `stancl/tenancy` (multi-DB tenancy) · `spatie/laravel-permission` (RBAC)
· `spatie/laravel-data` + `spatie/laravel-typescript-transformer` (DTOs → generated TS)
· `spatie/laravel-medialibrary` (product images, logos) · `spatie/laravel-activitylog`
(audit trail) · `openspout/openspout` (streaming CSV/XLSX) · `laravel/fortify` (+ passkeys,
2FA) · `laravel/wayfinder` (typed routes) · `@tanstack/react-table` · `zod` · `cmdk` ·
`sonner` · `recharts` · `radix-ui`.

**Dev-only:** `fruitcake/laravel-debugbar`.

**`fruitcake/laravel-debugbar` — v4.4.3, added 2026-09-13.** The first package installed
under the standing permission rather than proposed. It cleared the bar on evidence rather
than reputation: `composer show -a` gives `illuminate/routing ^11|^12|^13.0` and `php ^8.2`,
checked against this project's Laravel 13.26.1 / PHP 8.5.8. That check is the point of the
context7 step — Laravel 13 is new enough that plenty of popular packages have not caught up,
and "debugbar obviously supports Laravel" is exactly the kind of thing that is true right up
until it isn't.

Note the package name moved: the docs and the namespace are now `fruitcake/...`
(`Fruitcake\LaravelDebugbar\`), while `barryvdh/laravel-debugbar` still resolves to the same
repo and commit. Install the `fruitcake` name.

Nothing to configure. Auto-discovery registers the provider, `enabled` follows `APP_DEBUG`,
and no config file is published, so there is none to keep in sync. `--dev` keeps it out of
production entirely, which matters more than usual here: it exposes queries, bindings and
session contents.

**Two things were confirmed by driving it, not assumed**, both specific to this app being
Inertia + SSR:

- It injects its markup as **siblings of `#app`** under `<body>`, never inside the React
  root. `data-server-rendered="true"` survives, and a fresh load of a tenant list showed no
  React #418 — the risk worth checking before trusting any package that rewrites responses.
- It tracks Inertia's XHR visits live through its Ajax tab, so client-side navigation still
  reports its queries instead of freezing on the last full page load.

`storage/debugbar` is gitignored — it holds one JSON file per request.

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
| Relative/absolute date *formatting* | native `Intl.RelativeTimeFormat` / `DateTimeFormat` in `lib/format.ts` — pin the locale for SSR. Still true; *picking* a date is a separate question, answered below |
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

**Drag and drop — surveyed and declined (2026-09-06).**

The Columns panel reorders columns, and the obvious move was a sortable-list package. The
survey found no candidate clearing all five bars:

| Package | Downloads/wk | Verdict |
|---|---|---|
| `@dnd-kit/core` 6.3.1 | 24.9M | **Fails "maintained"** — last published 2024-12-05, 21 months stale despite being the ecosystem default |
| `@dnd-kit/react` 0.5.0 | 1.2M | **Fails "stable 1.0+"** — the rewrite, still pre-1.0 after two years. Nicest API, explicit React 19 peer |
| `@atlaskit/pragmatic-drag-and-drop` 3.1.0 | 1.3M | Clears every bar (Atlassian-maintained, ~5KB gzip) but is *low-level*: it gives drag events, not a sortable list, so the reorder logic gets written either way — and it is native-HTML5 underneath, so touch needs a separate adapter |

Built instead: native HTML5 drag on the row, plus up/down buttons. ~40 lines in
`components/data/column-row.tsx`, no bundle cost. The buttons are not a consolation —
native drag events never fire on touch, so they are the only thing that works on a phone,
and they carry the keyboard path too.

**Worth re-opening for the Phase 5 line-items editor**, where reordering order lines is a
bigger job than seven rows in a popover. If `@dnd-kit/react` has reached 1.0 by then it
becomes the obvious pick.


**Date picker — added `react-day-picker` (2026-09-08).**

The only date entry in the app was `<input type="date">`, and it was the one control that
could not be made to look like the rest of the app: the popup belongs to the browser,
cannot be styled, and renders `mm/dd/yyyy` or `dd/mm/yyyy` by the reader's OS while every
other date on the page reads `15 Oct 2026`.

`bun x shadcn@latest add calendar` pulls `react-day-picker` (which depends on `date-fns`).
Weighed against hand-rolling a month grid on the `Popover` and `Button` primitives already
vendored — about 200 lines, no bundle cost, and total control over locale. The package won
on the part that is genuinely hard to get right and easy to get subtly wrong: the roving
focus, `role="grid"` semantics, `PageUp`/`PageDown`/`Home`/`End`, and the announcements a
screen reader needs. That is precisely the "non-trivial thing a stable package does" this
policy exists to catch.

Note it does **not** displace `lib/format.ts`. The calendar's month names, weekday names
and day numbers are all overridden through DayPicker's `formatters` so they come from this
app's own static table — date-fns's locale data would have been a second source of those
words. And `today` is a server prop rather than `new Date()`, because reading the clock in
render is a commit-blocking error here. See `components/form/date-field.tsx`.

**One caveat for whoever runs `shadcn add` next:** the registry emitted
`import { cn } from "cn"` in `calendar.tsx`, which resolves to nothing — all 26 sibling
`ui/` files use `@/lib/utils`. Check that import on any newly added component.

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
