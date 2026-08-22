# Migration status — v1 → v2

Rebuilding `../laravel-ms-ai` (v1) into this project, module by module. v1 stays untouched
as the working reference. Plan: `~/.claude/plans/can-help-to-analyze-jiggly-rain.md`.

**Legend:** ✅ done · 🚧 in progress · ⬜ not started

## Phase 0 — Repo and toolchain

| Step | Status | Notes |
|---|---|---|
| `git init` + `.gitignore` | ✅ | On `main`. Nothing committed yet — awaiting review. |
| ESLint + Prettier → Biome | ✅ | `biome.json`; a11y/suspicious rules at **error**, not v1's `warn`. |
| Bun as package manager | ✅ | `composer.json` `setup` script switched from npm. |
| Apply shadcn preset | ✅ | Blue primary (#1447E6), red destructive, `--radius: 0`, greyscale charts. |
| PHPStan level 7, no baseline | ✅ | Scaffold's config was already correct. |
| `lefthook.yml` (no test hooks) | ✅ | Fast checks on commit, PHPStan/parity/build on push. |
| Verify SSR | ✅ | `bootstrap/ssr/app.js` builds; Inertia's `BundleDetector` finds it. |
| Docs | ✅ | `CLAUDE.md`, `docs/CODING-STANDARDS.md`, `docs/UI-UX-GUIDELINES.md`. |
| Validation-parity gate | ✅ | `scripts/check-validation-parity.ts`; auto-discovers by convention. |

### Removed from the scaffold

- `tests/`, `phpunit.xml`, `phpunit/phpunit`, `mockery/mockery`, the `Tests\` autoload entry
  — no test suite, by decision.
- `eslint*`, `prettier*`, `@stylistic/*`, `typescript-eslint`, `globals` — replaced by Biome.
- `resources/js/pages/welcome.tsx` — the starter marketing page (395 lines, 26 hard-coded
  colour literals, 4 a11y errors). Replaced by a token-based placeholder; Phase 1 turns the
  route into a redirect.

### Scaffold issues fixed to get the gates green

- 12 redundant/positive `tabIndex` attributes in `auth/login.tsx` and `auth/register.tsx`.
  Biome's `--unsafe` fixer had rewritten them to the *string* `"0"`, which fails `tsc`.
  Removed outright — inputs and links are already focusable.
- `two-factor-recovery-codes.tsx` used `role="list"`/`role="listitem"` on `<div>`s with an
  invalid `<div>` inside; rewritten as a real `<ul>`/`<li>` keyed by the code.
- Array-index React keys replaced with stable keys in `alert-error`, `breadcrumbs`,
  `app-header`, `settings/layout`, and the 2FA grid overlay.
- Documented `biome-ignore` suppressions for three legitimate cases: Fortify's
  server-rendered QR SVG, the appearance cookie (SSR reads it), and positional OTP slots.

### Design tokens

The supplied preset omits `--destructive-foreground`, which `ui/alert` and
`ui/dropdown-menu` both use for destructive *text* on a tinted surface. It is defined
equal to `--destructive` in each mode so those stay legible — the only addition to the
preset as supplied.

An earlier red-primary version of the preset put primary (`#C10007`) and destructive
(`#E7000B`) at the same hue, which would have made "Save" and "Delete" look identical in
light mode. The revised blue primary (hue 264° vs 27°) resolves it — no workaround needed.

**Open, deferred to Phase 8:** `--chart-1` … `--chart-5` are all neutral greys (chroma 0).
Fine for single-series and monochrome charts; a multi-series line chart will be hard to
read. Revisit when the dashboards are built.

### Known hazard in the vendored tree — handle in Phase 2

`resources/js/components/ui/sidebar.tsx` (`SidebarMenuSkeleton`, ~line 607) computes its
width with `Math.random()` **during render**. Under SSR that is a guaranteed React #418
hydration mismatch. It sits in the read-only vendored tree, so the fix is not to edit it:
either don't use `SidebarMenuSkeleton`, or wrap it in a component that renders the
skeleton only after mount. The nav is server-rendered and never actually pends, so not
using it is the likely answer.

### Known debt carried forward (deliberate)

- The scaffold's `ui/` components import the **scoped** `@radix-ui/*` packages; v1's newer
  vendored set uses the `radix-ui` meta package. The 14 scoped deps stay until Phase 2
  re-vendors `ui/` against the preset — no point regenerating that tree twice.
- `appearance-tabs.tsx` uses `bg-white` / `dark:bg-neutral-700` rather than tokens. Phase 2
  redesigns it.

## Phase 1 — Tenancy and auth spine ✅

| Piece | Status | Notes |
|---|---|---|
| stancl/tenancy, multi-DB, path slug | ✅ | Central connection `central`; tenant DBs `tenant_<slug>`. |
| Central / tenant migration split | ✅ | Central: users, cache, jobs, tenants. Tenant: users, sessions, cache, passkeys, 2FA, permissions. |
| `Tenant` + `CentralUser` + tenant `User` | ✅ | Two `users` tables in different databases; `CentralConnection` pins the admin one. |
| Guards `web` (tenant) / `central` (admin) | ✅ | |
| Bootstrappers | ✅ | Permission-cache and Fortify-home. |
| Fortify on the tenant guard | ✅ | All auth routes at `/{tenant}/…` via `fortify.prefix`. 2FA, passkeys, reset, verification. |
| spatie/laravel-permission | ✅ | Tables migrate per tenant; cache key scoped per tenant. |
| Tenant URL defaults | ✅ | `SetTenantUrlDefault` → Wayfinder emits `{tenant}` as OPTIONAL in TS helpers. |
| Permissions catalog + `AuthorizeTenantRoute` | ✅ | 66 permissions over 20 screens; 103 route names mapped. `usePermissions()` + `auth.permissions` / `auth.is_admin`. |
| `ProvisionTenant` + seeders | ✅ | Rolls back cleanly on either failure window — see below. |
| Central `/admin` area | ✅ | Sign-in, overview, workspaces, archive, restore, permanent delete. |

### Verified end to end

Login at `/acme/login` → dashboard 200, server-rendered, session in the tenant database
with **zero** rows in central. Cross-tenant isolation confirmed: acme's session is refused
on `/globex/`, acme's credentials fail on globex, and both tenants can be signed in at once
in one browser. Unknown slug → 404. Wrong password → back to login.

### The bug worth remembering

Signing in appeared to succeed — the POST redirected to the dashboard — and the very next
request was a guest again. Cause: `StartSession` builds the session store once, capturing
BOTH the cookie name AND the database connection at that moment. On routes registered by a
package (Fortify's), tenancy initialized *after* it, so the authenticated session was
written to the CENTRAL database while every later read came from the tenant database.

Neither middleware priority nor an explicitly ordered middleware group reordered it
reliably. The fix is `App\Http\Middleware\InitializeTenancyFromPath`, a **global**
middleware: global middleware run before routing, so the ordering cannot regress. stancl's
route-level `InitializeTenancyByPath` stays — it is what 404s an unknown slug.

**Do not move tenancy initialization back into route middleware or a bootstrapper.**

### Sharing the `tenant_` prefix with v1

v2 uses the same `tenant_<slug>` database naming as v1, on the same MySQL server, by
preference. A slug present in **both** projects therefore resolves to ONE database.

Verified this is self-guarding: provisioning a tenant whose database already exists fails
with `TenantDatabaseAlreadyExistsException` and leaves the existing data untouched (tested
against v1's `tenant_acme` — its 53 rows were unchanged). So the collision cannot silently
migrate over v1's data; it fails loudly.

**Fixed:** that failure used to leave an orphan row in the central `tenants` table, because
the row is inserted before the database is created. `ProvisionTenant` now rolls back — and
the rollback is careful about *whose* database it is:

| Failure | `tenants` row | Database |
|---|---|---|
| `TenantDatabaseAlreadyExistsException` | removed | **left alone** — it is not ours |
| Anything else (migrate or seed threw) | removed | dropped — we created it |

The row is deleted with model events suppressed, never by `forceDelete()`: force-delete
fires `TenantDeleted`, whose `DeleteDatabase` job drops the database unconditionally, which
is exactly wrong when the reason we are rolling back is that the database already belonged
to someone else.

Both paths verified against a live MySQL server: a pre-existing `tenant_collide` (with a
table in it) survived a failed provision untouched and left no row behind, and a provision
that failed mid-seed left neither a row nor a half-migrated database.

### The second bug worth remembering — route patterns are baked in at definition time

`/admin/login` returned **404**. It was being matched by Fortify's `{tenant}/login` route
and failing tenant identification, because `Route::pattern('tenant', …)` — the constraint
that excludes reserved words like `admin` — was declared in `AppServiceProvider::boot()`.

A pattern is merged into each route as that route is *defined*, so it only affects routes
declared afterwards. Fortify registers its `{tenant}/…` routes in its own `boot()`, which
runs before this app's provider — so the pattern silently missed exactly the routes that
needed it. Moving the call to `register()` (which runs before every `boot()`) fixes it.

**Declare `Route::pattern()` in `register()`, never `boot()`.**

### The third bug worth remembering — `XSRF-TOKEN` cannot be scoped by name

Archiving a workspace from the console returned **419** if you had visited a workspace
in the same browser first.

Session cookies are scoped per workspace by *name* (`…_ws_<slug>`), but Laravel hard-codes
the CSRF cookie as `XSRF-TOKEN`. Both areas were writing it at path `/`, so the workspace's
token overwrote the console's, and the console page then sent a token belonging to a
different session.

The only lever the framework exposes is the cookie's **path**, which comes from
`session.path`. `InitializeTenancyFromPath` now sets it to `/<slug>` alongside the name, so
each area gets its own `XSRF-TOKEN`. A console page sees only the `/`-path cookie; a
workspace page sees both, and the browser lists the longer path first (RFC 6265 §5.4) —
which is the one Inertia's cookie regex takes (it matches the first occurrence).

The per-tenant cookie name changed from `_tenant_` to `_ws_` at the same time, deliberately.
A browser still holding the old path-`/` copy would have *shadowed* the new scoped one:
browsers send the shorter path last and PHP keeps the last of two same-named cookies
(verified). Retiring the name makes any stale copy unrecognised instead of authoritative,
so no one has to clear cookies by hand.

Verified with a legacy cookie deliberately planted in the jar: workspace sign-in sticks,
console archive returns 302, and the console plus two workspaces stay signed in at once.

### A route action can never receive `$tenant`

`GET /{slug}` (the workspace front door, which the console's **Open workspace** links to)
returned a 500: `Too few arguments … 0 passed and exactly 1 expected`.

stancl's `PathTenantResolver` calls `$route->forgetParameter('tenant')` as soon as it has
identified the workspace — deliberately, so controllers don't all need a `$tenant` argument.
So a closure or controller method that *asks* for one gets nothing and dies. Use `tenant()`,
or just let `SetTenantUrlDefault`'s `URL::defaults` fill the slug into `route(…)`.

Worth noting how this got shipped: every test went straight to `/{slug}/login` or
`/{slug}/dashboard`. The front door — the URL a person actually clicks — was never
requested. That is the second bug in this migration with exactly that cause (the first was
the landing page linking to an unfilled `/$tenant/login`). **Drive the entry point, not just
the deep link.**

### Decisions taken here

- **Self-registration disabled** (`Features::registration()` commented out). There is no
  public signup in a B2B tenant model: a super-admin provisions a tenant and its first user;
  that admin invites the rest. `pages/auth/register.tsx` deleted with it.
- Settings routes (`profile`, `security`, `appearance`) moved under `/{tenant}/settings/…`
  — they are tenant-user settings. `routes/settings.php` folded into `routes/tenant.php`.
- `tenants.locale` and `users.locale` columns added now so Phase 2's locale resolver has
  something to read.

- The **zod gate was pulled forward from Phase 2**, because the console's create-workspace
  form is a real form and shipping it with server-only validation would have violated the
  project's own two-layer rule on day one. `lib/validation/gate.ts` and
  `hooks/use-zod-gate.ts` are ported verbatim from v1; `lib/validation/primitives.ts` and
  the i18n-aware error messages stay in Phase 2.
- `check-validation-parity.ts` now scans `Http/Requests/Central` as well as
  `Http/Requests/Tenant`. Re-verified that it still fails on a missing field.
- The console's own permission model is deliberately flat: any `CentralUser` can do
  everything at `/admin`. Per-super-admin roles would be a second RBAC system for a table
  that holds a handful of rows.

### Carried into Phase 2 from this phase

- **The admin screens are not translated.** `t()` does not exist yet, so every string in
  `pages/admin/**` and `components/layout/admin-sidebar.tsx` is an English literal. They
  need a retrofit pass when Phase 2 lands localization — the one place in the project where
  that rule is knowingly broken, and only because the infrastructure post-dates the screens.
- **Six components are waiting to be promoted.** `EmptyState`, `ListToolbar`,
  `PaginationBar`, `ConfirmDialog`, `StatCard` and `TimeAgo` live in
  `pages/admin/_components/` under the rule-of-three. Phase 2's `DataTable` and shared
  `components/feedback` + `components/data` groups should absorb them rather than grow a
  second copy.
- `lib/format.ts` was created here with the deterministic date helpers (no `Date.now()`, no
  `Intl`) that `TimeAgo` needs. Phase 2's locale work extends it rather than replacing it.

### Verified end to end (console)

Guest `/admin` → login. Wrong password → back with an error. Sign in → overview with real
counts. Create a workspace → its database is created, migrated (12 tables), seeded with 66
permissions and the Administrator role, and the first user holds that role. Sign in to the
new workspace at `/{slug}/login` and land on its dashboard with all 66 permissions shared.
Archive → the workspace 404s but its database is intact → restore → reachable again →
archive + permanent delete → row and database both gone. Server-validation messages for a
reserved slug, a duplicate slug, a bad slug shape, a bad email and a short password all
match their zod counterparts word for word.

All four console pages render through the SSR bundle with no errors and no raw colour
literals in the output.

## Phase 2 — Shared UI kit 🚧

| Piece | Status | Notes |
|---|---|---|
| Localization | ✅ | `lang/` → JSON bundles + a TS key union; `t()` / `tChoice()`; `check:i18n`. |
| Language switcher | ✅ | `PUT /locale`, session-backed, written through to `users.locale`. |
| Console translated | ✅ | 125 keys × en/ms/zh_Hans. The retrofit debt from Phase 1 is paid. |
| `DataTable` | ✅ | TanStack v9, server-driven. Absorbed `ListToolbar` + `PaginationBar`; `EmptyState` and `ConfirmDialog` moved to `components/feedback/`. |
| `scripts/check-structure.sh` | ✅ | The gate CLAUDE.md and ARCHITECTURE.md already claimed existed. |
| Backend concerns | ⬜ | `RendersResourceIndex`, `SortsResourceQuery`, `ResolvesDateRange`, … |
| `spatie/laravel-data` + TS types | ⬜ | |
| zod `primitives.ts` (i18n-aware) | ✅ | Both layers read `lang/{locale}/validation.php`. `check:i18n` now fails on an untranslated rule. |
| Shell redesign + ⌘K palette | ⬜ | Auth/settings screens get their `t()` pass here, once. |

### Localization — built first, deliberately

Ordered ahead of `DataTable` so every component from here on is born translated. The
alternative — build the kit, then retrofit `t()` — means touching every file twice, which
is exactly the cost the Phase 1 console already paid.

**Three things behaved differently from the design, and the design lost each time:**

1. **No context provider.** The plan assumed one. A provider has to be installed in
   `withApp`, which wraps Inertia's `<App>` from the *outside*, where `usePage()` does not
   exist — SSR died with *"usePage must be used within the Inertia component"*. And
   `withApp` runs only for the first client page, so a locale captured there would go
   stale on the first language switch. `useTranslation()` reads the page props itself.

2. **A hand-written Inertia `resolve`.** `@inertiajs/vite` generates one, but skips
   injection when the app defines `resolve` or `pages`. Taking it over is what lets the
   locale bundle be awaited alongside the page component — `resolve` is the only hook
   Inertia awaits on *both* the server render and every client visit, which is what makes
   `t()` synchronous everywhere else. Verified: pages still code-split (72 chunks) and each
   locale gets its own (~8 kB).

3. **Session-first locale resolution.** The plan's chain started at the user's preference,
   but the console has no user preference to read — `CentralUser` has no locale column — so
   the switcher would have had nowhere to write and the feature would have been
   unverifiable. The session now comes first, and a tenant user also gets it written
   through to their profile.

**Biome and the exporter had to be separated.** Biome reformatted the generated
`lang.d.ts`, the exporter wrote it back, and `check:i18n` reported permanent staleness.
Both artifacts are now in Biome's ignore list, next to `generated.d.ts`.

**The gate was rewritten after its own negative tests failed it.** The first version
regenerated the artifacts in place, so a failing run left the tree modified and the *next*
run reported staleness for an unrelated reason — and the staleness line masked the real
missing-key and bad-key problems underneath. It now exports to a temp directory and reads
the fresh copy, so each of the four failure modes reports the actual cause. All four were
re-tested by breaking them on purpose.

### Verified in a real browser (Playwright, SSR on)

Signed in, walked the console, switched all three languages, toggled dark, and checked
375 px — **zero console warnings throughout**, so no hydration mismatch. Four things were
wrong and only showed up by looking:

| Found | Fix |
|---|---|
| Breadcrumb root read "Console" while the sidebar read "Konsol" / "控制台" | a hard-coded literal in `admin-layout.tsx`; now `t('console.name')` |
| Timestamps read "1 h ago" | reworded to `:count hr ago` / `jam` / `小时前` |
| Chart axis read "Jul 24 / Aug 22" in a Chinese UI | `format()` → `translatedFormat()`; Carbon follows `App::setLocale` |
| **zod messages were English inside a translated form** | fixed — see below |

### Validation messages — and a correction

This was first written up as "zod is English while Laravel's equivalents are translated".
That was wrong, and checking it turned up something worse. Laravel ships `validation.php` in
English only; nothing had published a `ms` or `zh_Hans` copy, so **both** layers were
English. And because `:attribute` *was* translated while the sentence around it was not, the
server produced half-translated output:

```
The e-mel pentadbir field must be a valid email address.
```

A missing rule message does not fail — Laravel falls back to English and renders — so this
sat in front of every Malay and Chinese user without a single log line.

Fixed on both sides at once, from one source:

- `lang/{locale}/validation.php` — the rules this app can emit, in all three languages.
  A deliberate subset: Laravel merges it over the framework's, so anything omitted keeps the
  framework's wording, and the `en` copy is quoted verbatim so English output is unchanged.
- `attributes` moved there too, which **deleted** `StoreTenantRequest::attributes()` —
  Laravel reads that key without being asked, and the zod schema reads the same one.
- `lib/validation/primitives.ts` + `message.ts` — a check carries a translation key and its
  params, encoded into zod's message slot, and `runGate()` resolves it at parse time with
  the current render's translator. The schema stays a static, locale-free value, so nothing
  locale-aware exists at module scope and concurrent SSR renders cannot cross.
- `useZodGate()` supplies the translator itself, so no form component is wired for this.

`store-tenant.ts` is now a line-for-line transcription of its FormRequest:

```ts
name: text({ attribute: 'validation.attributes.name', max: 255 }),   // ['required','string','max:255']
```

**A fifth check joined `check:i18n`:** a rule used by a FormRequest with no translated
message is now a failure. Without it the next module to introduce `numeric` or `exists`
would ship English into a Malay screen exactly as this did — silently. Both the failing and
the overridden-message paths were confirmed by breaking them on purpose.

Driven in the browser, SSR on, zero console errors. Submitted empty and with bad values in
each language, and both layers were read back from the live page:

| | browser (zod, request never sent) | server (`demo` already taken) |
|---|---|---|
| en | The administrator password field must be at least 8 characters. | A workspace with that slug already exists… |
| ms | Ruangan kata laluan pentadbir mestilah sekurang-kurangnya 8 aksara. | Ruang kerja dengan alamat itu sudah wujud… |
| zh_Hans | 管理员密码至少需要 8 个字符。 | 已存在使用该地址的工作区… |

The browser's sentences match the server's word for word — checked against the same rules
run through the validator directly. English is unchanged from before the fix, which is the
point of quoting the framework verbatim.

Also verified through the **SSR bundle** directly, with zero SSR errors: headings, table columns, pagination counts (`Showing 1–1 of 1` / `Memaparkan 1–1
daripada 1` / `显示第 1–1 项，共 1 项`). `<html lang>` emits BCP-47 (`zh-Hans`, not
`zh_Hans`). An unsupported locale is refused and leaves the language unchanged.

### Translations want a native-speaker pass

The `ms` and `zh_Hans` strings are mine, not a translator's. They are consistent and
idiomatic as far as I can judge, but "arkib"/"ruang kerja" and the Chinese phrasing for
archive-versus-delete are the kind of thing worth a second pair of eyes before customers
see them.

### DataTable

Built on `@tanstack/react-table` v9 (see `docs/PACKAGE-POLICY.md` for why v9 and not v8 —
and for the evidence that argued against the dependency at all).

The shape it settled into, and the reasons:

- **`data` is the answer, never the input.** `manualSorting` and `manualPagination` skip
  TanStack's own stages; the rows handed in are already the page SQL returned. Registering
  `createSortedRowModel` here would re-order the 25 rows on screen and present that as a
  sort of the whole table — worse than no sorting.
- **The table owns every request the list makes.** Search, sort, page size and paging all go
  through one `visit()`. That is what keeps "a new search must not carry `page` forward" in
  one place instead of in each control, which is how someone lands on an empty page 7 of a
  fresh result set. Search replaces the history entry; a sort or a page change does not,
  because those are deliberate and Back should undo them.
- **Which headers are clickable comes from the server.** `filters.sortable` is the same
  allow-list that guards `ORDER BY` against injection, so there is no second copy to
  disagree with it. A page declares no sortability at all. (v1 opted in per column via
  `meta.sortKey`, and duly forgot it: its activity list has a `created_at` column that 13
  other pages sort and that one cannot.)
- **Columns name their intent, not their Tailwind.** `hideBelow`, `align`, `numeric`,
  `width` instead of free-text `meta.className`. v1 has two columns with identical intent
  hiding at different breakpoints and no way to tell which is right.
- **Four list states, each with its own copy**: rows; nothing yet; nothing matched the
  search; and *nothing on this page* — a real state, not a theoretical one, since deleting
  the last row of the last page redirects back to a `?page=N` that no longer exists. v1
  showed `No results match ""` there, quoting a search nobody had made. It now offers a way
  back to the first page.
- The empty state renders **inside** the card. v1 early-returned the bare empty state and
  discarded the whole shell, which is why 18 of its 19 pages write their create button
  twice — once for the toolbar, once for the empty state.

Converted both console lists as the proving ground; `TenantController` gained
`SortsResourceQuery`, which is v2's first backend concern beyond `ResolvesPerPage`. Two
deliberate differences from v1's version are noted in its docblock: `direction` is only
honoured alongside a recognised `sort` (v1 read them independently, so `?direction=asc`
could reorder a column the header UI had no way to indicate), and the tiebreaker follows the
primary direction instead of always being `id desc`.

### The structure gate now exists

`CLAUDE.md` and `docs/ARCHITECTURE.md` both said `scripts/check-structure.sh` enforced the
one-way dependency rule. It did not exist. Since `components/data/` is exactly the boundary
that rule protects, it was written here: `lib/` may not import React, `components/` may not
import from `pages/`, one module may not reach into another's `_components/`, and a
controller may not run a raw query — plus page size caps as warnings. All four failures were
confirmed by breaking them on purpose. Wired into lefthook's pre-commit and `bun run
check:structure`.

### Verified in a real browser (Playwright, SSR on)

Against the **production** bundle (`build:ssr` + `inertia:start-ssr`), because that is what
production runs: `data-server-rendered="true"`, rows and `aria-sort` present in the source,
and the Malay row count rendered server-side. **Zero console errors and zero React
warnings** — TanStack v9 hydrates cleanly, which was the open question in adopting a
three-week-old major.

Exercised with 15 seeded workspaces, since a one-row table proves nothing about sorting:
sort by each column (URL updates, `aria-sort` moves, `page` drops); page 2 (`Showing 11–15
of 15`, sort preserved); search (resets to page 1, keeps the sort); a search matching
nothing; `?page=99` and the recovery button; page size 10 → 25 (pager disappears at a single
page); archiving through the row-actions dropdown (15 → 14, row gone). All three languages,
light and dark, and 375 px — where only the primary and actions columns remain, the address
moves under the name, and neither body nor document scrolls horizontally. Seed data removed
afterwards; only `demo` remains.

**One finding, and it is in the tooling rather than the code:** `bun run build` deletes
`public/hot`. Run it while `bun run dev` is up and Laravel switches to production mode,
finds no SSR bundle (`build` does not make one — `build:ssr` does), and silently
client-renders while the dev server still looks healthy. That is a fourth silent fallback
and it is now in the table in `docs/CODING-STANDARDS.md`. It is also why that document says
to confirm the marker rather than assume it: this was caught by checking, not by looking at
the page.

### Bug: a list could show data from before your last change

Reported after DataTable landed, but not caused by it — the sidebar has been marked
`prefetch` since Phase 1. Inertia fetches a nav target on hover and serves that copy when
the link is clicked; nothing invalidates it, so a write in between is invisible:

1. hover **Archive** — Inertia caches the archive page, currently empty;
2. archive a workspace;
3. click **Archive** — the cached, still-empty page renders.

A manual refresh "fixes" it, which is the tell: the server was right the whole time.

Fixed with `lib/prefetch-cache.ts` — one listener, registered from the client entry
alongside `initializeTheme()`, that drops every prefetched page when a non-GET visit
finishes. What changed is not knowable from there, so nothing is guessed at. Inertia can
flush by cache tag instead, but that means tagging every link correctly and forever, and a
missed tag brings this back silently.

Proven both ways: with the listener disabled the same sequence renders "No workspaces yet"
seconds after restoring a workspace; with it enabled the row is there. Worth recording that
the *first* attempt to verify the fix passed for the wrong reason — the browser was running
a stale HMR module in which the call existed but its import did not, and a `ReferenceError`
in the console was the only sign. Read the console before believing a fix.

### Bug: the destructive button failed contrast in dark mode

Reported as "the delete-permanently button is hard to read". Measured rather than eyeballed,
and it was two problems, the second worse than the reported one:

| | before | after |
|---|---|---|
| Confirm button, armed — **dark** | 2.89:1 ✗ | **6.25:1** ✓ |
| Confirm button, armed — light | 4.77:1 ✓ | 4.77:1 ✓ |
| Row trash icon — dark | 4.77:1 | **6.21:1** |
| Confirm button, locked — dark | 2.19:1 | 3.36:1 |
| Confirm button, locked — light | 2.57:1 | 2.57:1 |

It was never a hard-coded red: `variant="destructive"` resolves to `bg-destructive
text-white`, and the token is shadcn's own. **The default is what fails.** Upstream handles
it in the button rather than the palette — its recipe carries `dark:bg-destructive/60`, so
the solid button is the light red knocked back over the surface. Our vendored
`ui/button.tsx` was copied without that class; v1's has it.

`ui/**` is read-only and `scripts/ui-guard.sh` blocks editing it, so the surface is
corrected in the palette instead: dark `--destructive` is now `oklch(0.505 0.126 21.5)`,
which is the colour `destructive/60` actually composites to — the same pixels stock shadcn
renders, reached from CSS. `--destructive-foreground` keeps the light red, because
destructive *text* needs to be lighter than a dark background rather than darker; the two
`text-destructive` usages moved to it. Light mode is untouched.

**What the palette cannot fix:** the locked state. Until the workspace slug is typed the
button is `disabled`, and shadcn dims disabled buttons with `opacity-50`, which fades the
red fill and the white text together — no token reaches it. It improved as a side effect
(2.19 → 3.36 in dark) and WCAG exempts disabled controls, but it is still the weakest
surface on the screen. The lever left is behavioural: stop disabling the button and refuse
the click instead.

### DataTable, revamped

Reworked into three bands with a clear job each, after the first pass put the page-size
control in the wrong one.

- **Toolbar** — search and per-resource filters only. Narrowing the result set.
- **Table** — a muted header row in `text-xs`, taller cells, and gutters that line up
  with the bands above and below. No uppercase: Chinese has no case and Malay reads as
  shouting.
- **Footer** — the count, the page size and the pager, together. How many rows there are
  and how many to show at once are the same question as which page you are on; splitting
  them across two ends of the card made the list feel like two components.

The pager gained numbered pages (`lib/pagination.ts`). Prev/next alone makes "jump to the
end" impossible, and rendering every page is unusable by page 40. The window keeps the
first and last page reachable plus a run around the current one — and holds a **constant
slot count** once gaps appear, so the footer does not change width while paging and the
buttons stay under the cursor. Below `sm` the numbers collapse to "Page 5 of 9".

Also removed a redundant `overflow-x-auto` wrapper: `ui/table` already brings its own.

Driven with 81 seeded workspaces, because a two-page list never exercises the gaps: first
page (`1 2 3 4 5 … 9`), middle (`1 … 4 5 6 … 9`), last, page size 10 → 100, search, sort,
all three languages, light and dark, and 375 px — where only the primary and actions
columns remain, the address moves under the name, the footer stacks, and neither body nor
document scrolls sideways. Zero console errors.

## Phase 2 — remaining ⬜

## Phases 3–8 — Modules ⬜

| Phase | Modules | Status |
|---|---|---|
| 3 · Catalog | categories, suppliers, customers, raw materials, products (+BOM, image) | ⬜ |
| 4 · Stock | locations, warehouses, StockService, movements, transfers, reorder levels, stock takes | ⬜ |
| 5 · Orders | purchase orders, purchase returns, sales orders, sales returns, production orders | ⬜ |
| 6 · Insights | reports, activity log | ⬜ |
| 7 · Team & settings | users, roles/RBAC, business settings, document numbering, e-invoice | ⬜ |
| 8 · Cross-cutting | exports, barcode/QR scanning, tenant dashboard, admin dashboard | ⬜ |
