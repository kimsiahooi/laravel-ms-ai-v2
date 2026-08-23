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
| Backend concerns | ✅ | `RendersResourceIndex`, `ResolvesPerPage`, `SortsResourceQuery`, `RespondsWithToast`. The rest arrive with the modules that need them. |
| `spatie/laravel-data` + TS types | ✅ | `#[TypeScript]` DTOs in `app/Data` → `generated.d.ts`; gated by `check:generated-types`. |
| zod `primitives.ts` (i18n-aware) | ✅ | Both layers read `lang/{locale}/validation.php`. `check:i18n` now fails on an untranslated rule. |
| Shell redesign | ✅ | `TenantLayout` + `TenantSidebar` + `tenant-nav.ts`. Auth and settings screens took their `t()` pass here. |
| ⌘K palette | ⬜ | Needs `cmdk` + `shadcn add command`. Deferred until the nav has more than two entries to filter. |

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
surface on the screen.

The behavioural alternative — drop `disabled`, let the click land, and answer it with what
is missing — was built and then reverted at the owner's call. It measured well (4.73:1
light, 6.25:1 dark, because a button at full opacity is just a button) but a vivid, fully
live "Delete permanently" beside an empty confirmation field reads as ready to fire. The
dimming is doing real work: it is the signal that the phrase has not been typed yet. WCAG
exempts disabled controls precisely because they are not offering themselves, so the
low number here is the honest one. Kept as `disabled`.

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

**Found while looking at the screenshots: "1 days ago".** `TimeAgo` resolved the count with
`t()`, a plain lookup, so the singular never rendered. Now `tChoice()`, with `days_ago`
carrying both forms in `en` and a single segment in `ms`/`zh_Hans` — which is the reason it
has to be the locale's choice rather than a ternary at the call site: two of the three
languages have no plural inflection at all.

### create-workspace-sheet, split three ways

251 lines, so the structure gate warned on every commit. It was doing four jobs; now each
has a file:

- `lib/slug.ts` — `toSlug()`. Pure, ASCII-only, capped at 50 so `<db prefix><slug>` fits
  MySQL's 64-character database-name limit.
- `_components/workspace-identity-fields.tsx` — name and address, and the mirroring rule
  that is the only real behaviour on the form: the slug tracks the name until it is edited
  by hand, then stops for good.
- `_components/workspace-admin-fields.tsx` — the administrator fieldset. Entirely
  uncontrolled; nothing reads those values back before submit.
- `create-workspace-sheet.tsx` — the sheet, the `<Form>`, and the submission. 92 lines.

The `reset()` function disappeared rather than moving. Radix unmounts the sheet's content
when it closes, so the field groups take their state with them and reopen empty — which is
worth stating out loud in the file rather than leaving as an accident, and was checked in
the browser: fill all five fields, Escape, reopen, all five empty.

### The confirm prompt moved to `common`

`ConfirmDialog` lives in `components/feedback/`, but its one string sat under `console`.
Every module from Phase 3 on deletes something through that dialog, so the key moved to
`common.confirm.type_to_confirm` before there were 21 callers rather than after. Nothing
else changed — the same sentence, in the same three locales.

### RendersResourceIndex — and the three concerns deliberately not ported

Two listings, one contract. `filters` is an agreement with `ResourceFilters` in
`types/ui.ts` and with `DataTable`, which re-sends every key on every request; assembled
by hand in each controller it drifts one key at a time, and a missing `sortable` is a
header that looks clickable and does nothing. `resourceList()` builds it once.

Three deliberate departures from v1's version:

- **It takes a `Builder`, not a `class-string`.** v1 called `$model::query()`, which
  cannot express `onlyTrashed()` — the archive listing could not use the helper at all
  and duplicated it instead, which is exactly the duplication v2 had. A Builder also
  makes eager loading plain `->with()` at the call site rather than another parameter.
- **It returns props; it does not render.** v1 rendered, so it needed `$view`, `$key`
  and an `$extraProps` array whose values are eagerly evaluated — a trap v1 documents
  itself, because a partial reload asking only for the list still paid for every extra
  prop. Handing the props back means the controller writes an ordinary
  `Inertia::render`, where deferring and closures work as documented. The trait name
  kept the plan's wording; the method is `resourceList()`, because it does not render.
- **Search is a closure.** The trait resolves and trims the term and skips the closure
  when it is empty — so an empty box can never become `LIKE '%%'` on every column.

`Searchable` came with it, as a model concern: the columns describe the data, not the
request. It uses an abstract `searchableColumns()` rather than v1's `$searchable`
property, and that is not taste — **PHP fatals when a class redeclares a trait property
with a different default**, which is why v1's trait cannot declare one and reads
`$this->searchable ?? []` behind a PHPStan suppression. Checked rather than assumed:

    Fatal error: A and T define the same property ($searchable) in the composition of A.

**Not ported: `ResolvesDateRange`, `StreamsMedia`, `BuildsStockPickers`.** In v1 they
serve reports/dashboard/export, medialibrary, and eight order/stock controllers. None of
those exist here yet, medialibrary is not installed, and there are no stock tables — so
they would be written against imagined callers with nothing to drive in a browser. They
land with the modules that need them, in Phases 3 to 6.

**Driven after the refactor**, since it changes every listing query: search by name and
by slug (`zztest-golf` matches the address only); the same search on the archive, which
returns only archived rows — proof the OR group stays inside `onlyTrashed()`; sort by
name and by `created_at` in both directions; `defaultSort: 'deleted_at'` on the archive;
`per_page`; and `?sort=locale&direction=asc` — a real column that is not whitelisted —
falling back to `created_at desc` with the direction ignored too, which is the injection
guard intact.

**Found while building the fixtures: `created_at` and `updated_at` are stored twice.**
`Tenant::getCustomColumns()` lists `id`, `name`, `locale` and `deleted_at`, so the
timestamps overflow into the `data` JSON column *as well as* occupying their real
columns. `ORDER BY created_at` reads the real column while the row's displayed timestamp
comes back from `data`. They agree on any tenant created normally — both are written from
the same moment — so nothing is visibly wrong today, and it is Phase 1 code rather than
anything this change introduced. Left alone pending a decision: adding both to
`getCustomColumns()` is two lines, but it changes how every tenant row is read.

### The data layer: DTOs, and generated types with a gate

`spatie/laravel-data ^4.23` (require) and `spatie/laravel-typescript-transformer ^3.3`
(require-dev) — the same split and the same versions v1 pins, which happen to be the
newest of each, so there was no upgrade to bridge.

**No `config/data.php`.** v1 publishes it, but a `diff` against the vendor copy shows
only import aliases and whitespace — not one value differs. A published config that
matches the default is a copy of upstream's decisions frozen at today's date; leaving it
unpublished means the package's defaults stay the package's to improve.

**v3 of the transformer has no config file at all** — the configuration is a service
provider class. This matters because every tutorial still describes a
`config/typescript-transformer.php` that v3 deleted, and because
`spatie/laravel-data` *still ships* a `DataTypeScriptTransformer` extending a
`DtoTransformer` that v3 removed: registering it, as those guides instruct, is a fatal
rather than a degradation. `AttributedClassTransformer` + `#[TypeScript]` is the working
path, and opting in by attribute is also what keeps a DTO with no business on the wire
off it.

The provider is hand-written rather than published by `typescript:install`, whose stub
gets three things wrong here: it registers Prettier (Biome replaced it), scans all of
`app/` (so BetterReflection walks Models, Http and Tenancy every run), and registers
itself unguarded. It is registered behind `class_exists()` in `bootstrap/providers.php`
because it extends a **dev** dependency — unguarded, `composer install --no-dev` boots
into a class-not-found fatal.

**Two DTOs, not one.** `TenantData` carries `created_at`, `ArchivedTenantData` carries
`deleted_at`. Merging them means both nullable — an always-null `deleted_at` shipped to
the live list, an unread `created_at` to the archive, and the column accessor's type
widened from `string` to `string | null` on both screens to describe a case that cannot
happen.

`slug` is a wire-only name and the reason both classes have a named factory: a
workspace's primary key IS its slug, so a bare `::from($tenant)` emits `id` — the wrong
word for something that appears in every URL, and a rename that would have rippled
through eleven field accesses across the two pages.

**The boundary.** The generated types replace exactly two local aliases — the row shapes.
`Paginated<T>` stays hand-written: it describes Laravel's paginator envelope, has no PHP
class behind it, and is a deliberately curated subset (it omits `links`, `path` and the
four `*_page_url` keys Laravel actually sends). v1 is the proof — it has both packages
installed and still hand-writes its paginator type. `ResourceFilters` stays hand-written
too: it is assembled across two controller traits and typed only by a PHPStan array
shape, so there is nothing to read. Generating it would need a new class *and* a backed
enum for `'asc'|'desc'`, and would push `sortable` — the `ORDER BY` allow-list this repo
calls a security control — through a DTO. Explicit non-goal.

**ui-guard lost a rule, and gained a better one.** `generated.d.ts` was listed as
read-only, blocking modifications. Every *other* path in that list is gitignored, so it
can never appear as MODIFIED in a commit — the rule could only ever have fired on the one
file that is supposed to change in commits, and the only way past would have been to skip
the hook. It is now guarded the way its twin `lang.d.ts` is:
`bun run check:generated-types` transforms into a temp directory and compares, which
catches the stale file *and* the hand-edit while letting regeneration through.

The temp directory is not incidental. A staleness check that writes where it is checking
leaves a modified tree behind when it fails, and the next run then reports staleness for
a reason unrelated to the change under test — the mistake the i18n gate already made
once. `config/typescript-transformer.php` exists solely to make the output directory
overridable; PHPStan rejected reading it from `env()` in the provider, correctly.

**Proved rather than assumed.** The gate was broken deliberately both ways — a property
added to a Data class without regenerating, and a field hand-patched into
`generated.d.ts` — and it failed on each, leaving the tree unmodified. The wire shape was
checked against the array it replaced and is byte-identical:

    TenantData:      {"slug":"demo","name":"Demo Manufacturing","created_at":"2026-08-22T07:33:12+00:00"}
    old hand-rolled: {"slug":"demo","name":"Demo Manufacturing","created_at":"2026-08-22T07:33:12+00:00"}

No wrapping, no reordering, no date-format drift. Then driven: both listings, and a full
create → archive → view archive → permanently delete cycle, which is what exercises
`ArchivedTenantData` and the row actions that read `slug` and `name` off the generated
type. SSR confirmed, zero console errors.

### Bug: `<html lang>` went stale after a language switch

Found while building the workspace shell, but it is older than that and the console had
it too. The blade template gets the attribute right on a full page load; a language
switch is an Inertia visit, and the `<html>` element outlives one. So the page re-renders
in Malay while the document still claims English, and a screen reader pronounces it with
English phonetics until the next hard refresh.

Two attempts failed before the third worked, and both failures were already written down
in `use-translation.tsx`'s own docblock, which explains why that hook has no context
provider:

- A component rendered in `withApp` throws — `withApp` wraps Inertia's `<App>` from the
  outside, where `usePage()` is not available.
- Passing the locale down from `withApp` instead compiles and does nothing useful:
  `withApp` runs only for the first page, so the value is frozen at whatever the app
  booted with — which is precisely the value the blade had already set correctly.

A `router.on('navigate')` subscription was no better: it reports the page being *left*,
so the attribute sat exactly one visit behind. That one is worth recording because it
looks right in code review and only fails on the second switch — the browser check
caught it, nothing else would have.

`useDocumentLocale()` reads `usePage()` inside the tree like everything else does, in an
effect because SSR has no `document`, and the layouts that carry a language switcher call
it. Verified by switching en → zh_Hans → en and reading the attribute at each step.

### The workspace shell

The tenant side was running 100% untouched starter kit — a "Platform" nav group with
one Dashboard entry and GitHub/Laravel-docs footer links, none of it translated. It is
now a real shell, modelled on the console shell that was already built and reviewed:
sidebar with the workspace's name and address, permission-filtered nav, breadcrumbs,
user menu, language and theme controls.

**The nav is one entry, and that is its honest size.** `config/tenant-nav.ts` ships the
*mechanism* — groups, `TranslationKey` titles, real Lucide components, Wayfinder hrefs,
and per-entry permissions — with Dashboard in it, because Dashboard and the three
account-settings pages are the only navigable tenant routes that exist. A Wayfinder
helper for a route that has not been written is not a dead link, it is a compile error.
Each module adds its line as it lands; groups (Catalog, Stock, Orders…) arrive with the
modules that fill them, because an empty group renders as a heading with nothing under
it. Both the sidebar and — later — the ⌘K palette read that one function, so the two
cannot come to disagree about what exists.

Account settings sit in the user menu rather than the sidebar: they belong to the
person, not to the workspace.

**Deleted, not adapted** — nine starter-kit files with no remaining importer:
`app-layout`, `app/app-sidebar-layout`, `app/app-header-layout`, `app-sidebar`,
`app-header`, `nav-main`, `nav-footer`, `nav-user`, `app-logo`, `app-sidebar-header`,
`user-menu-content`, `auth/auth-card-layout`, `auth/auth-split-layout`. Keeping
scaffolding you do not use is how a shell ends up with two ways to do everything.

**Found by putting the language switcher in the tenant header: it 419s.** The switcher
had only ever run on `/admin`, where the central session is the right one. The session
driver is `database` and `DatabaseTenancyBootstrapper` switches the connection, so a
workspace's session row lives in its own database — posting to the central `/locale`
route from inside a workspace looks the cookie up in `central.sessions`, finds nothing,
starts a fresh session, and CSRF rejects it. Verified rather than guessed: both
databases were confirmed to hold a `sessions` table. Fixed by registering the same
controller inside the tenant group as `tenant.locale.update`, outside the auth group —
the sign-in screen offers the switcher too, and someone who cannot read the form yet is
exactly who needs it.

**Driven**: signed into `/demo`, both shells side by side, settings still rendering under
the new layout, all three locales with `<html lang>` following each switch, 375 px with
the sidebar off-canvas and no horizontal body scroll, SSR confirmed, zero console errors.

**Still starter-kit and untouched:** `pages/dashboard.tsx` is three grey placeholder
boxes (Phase 8 builds the real one) and every string under `pages/auth/**` and
`pages/settings/**` is hard-coded English — ~85 literals across nine files, plus missing
`auth.php`/`passwords.php` in all three locales, which is why Fortify's messages render
English in Malay and Chinese. `check:i18n` passes green through all of it: it verifies
that referenced keys exist, and cannot report a namespace nobody created. That sweep is
the next unit.

### The auth screens: translated, and redesigned to match the console

Six screens, plus the two strings that were hiding in `PasskeyVerify`'s `??` defaults
rather than in any page. `lang/{en,ms,zh_Hans}/auth.php` now carries both Laravel's three
failure keys — quoted verbatim, so English output is byte-identical to the framework — and
this app's screen copy, grouped by screen. `passwords.php` joins it for the broker's five.

**The server-side half was the invisible one.** Without `auth.php` and `passwords.php` in
`ms`/`zh_Hans`, Laravel silently falls back to English, so a fully Malay sign-in screen
refused a password in English and nothing reported it. Now:
`Butiran ini tidak sepadan dengan rekod kami.`

**The blocker was the layout contract, not the strings.** Every auth page declares
`Page.layout = { title, description }` at module scope — evaluated at import time, outside
React, where `t()` cannot run because it reads the current page's locale. A find-and-replace
over JSX would have silently skipped all twelve of those. The fix keeps the object static
and makes its *values* translation keys, resolved by the layout during render; because
`TranslationKey` is a generated union, a typo there is now a tsc error rather than a blank
heading. `two-factor-challenge.tsx` is the one screen whose heading changes at runtime, so
it keeps `setLayoutProps` — and that was a live bug for a moment, since it was still
passing resolved sentences into a layout that had started calling `t()` on them.

**One string was deleted rather than translated.** Registration is off
(`config/fortify.php`), so login's "Don't have an account?" had no link after it —
translating it would have shipped the same orphan into three languages.

**Left alone deliberately:** the `status` strings rendered raw on four screens are
password-broker and verification messages that Laravel has *already* resolved. Wrapping
them in `t()` would be wrong; `passwords.php` is where they get translated, and
`'verification-link-sent'` is a sentinel to compare against, never text.

**The auth screens are now split-screen**, matching the console's own sign-in: a branded
panel beside the form. `docs/UI-UX-GUIDELINES.md:101` mandated this and it was true only of
the console; the starter kit's split layout sat unused with zero importers and was deleted
rather than adopted. The panel is `aria-hidden` — it repeats nothing the form does not say,
so announcing it only delays reaching the fields — and it disappears below `lg`, where the
form is the whole job.

**The language switcher now lives on the auth screens**, which is what finally justifies
registering `tenant.locale.update` outside the auth group. The previous commit's message
claimed the sign-in screen already offered one; it did not, and that was wrong when
written.

**Driven** in all three locales: the split panel with its `:workspace` interpolation, a
deliberately failed sign-in showing Fortify's message in Malay, forgot-password, 375 px
with the panel dropped and no horizontal scroll, `<html lang>` correct on every switch,
zero console errors.

**Still to sweep:** `pages/settings/**` and the nine components those pages pull in
(`delete-user`, `manage-two-factor`, `manage-passkeys`, `two-factor-setup-modal` at 361
lines, `two-factor-recovery-codes`, `passkey-register`, `passkey-item`, `appearance-tabs`,
`password-input`), plus `layouts/settings/layout.tsx`. And `check:i18n` still cannot see a
hard-coded literal — it verifies that *referenced* keys resolve, so a file with no `t()` at
all is invisible to it. That gap is what let nine files sit untranslated behind a green
tick.

### The settings sweep

`pages/settings/**`, the settings layout, and the nine components those pages pull in —
86 hard-coded literals across 14 files, now translated through `settings.php` and
`welcome.php` in three locales. The inventory came from the coverage gate below, built
first precisely so it could enumerate the work rather than have it enumerated by eye; it
caught one string the auth sweep had missed (`two-factor-challenge.tsx` still said
"Continue") and a whole category a JSX pass cannot see —
`{isLoading ? 'Registering...' : 'Register passkey'}`.

**A hydration mismatch fell out of it.** The settings nav is built at module scope, where
route helpers run at import time — before `setUrlDefaults` has a tenant. The server
rendered `href="/$tenant/settings/profile"` against the client's
`/demo/settings/profile`. It predates this work and survived every gate, and the reason
is worth keeping: module scope has no locale **and** no tenant, and only the locale half
had been thought about. The routes are now functions resolved during render.

How it surfaced matters as much as the fix. Earlier visits to settings went through the
user menu — a client-side Inertia visit, no hydration step, no mismatch. Only a hard page
load exposes it, which is an argument for navigating to a URL rather than clicking to it
when checking SSR.

### `check:i18n` can finally see a hard-coded string

The gate had five checks and none of them could fail on an untranslated literal. All five
verify that keys which *are* referenced resolve — so by construction, a file with no `t()`
at all was invisible. That is what let nine auth and settings files sit untranslated
behind a green tick, and it would have done the same to the next nine.

Check 6 parses rather than greps, and that is not fastidiousness. The regex prototype
reported `Promise` out of `() => Promise<void>` and flagged every translation key already
being passed as a `label`. **A gate that cries wolf gets switched off**, so it walks the
TSX with the TypeScript compiler and asks three narrower questions: is this JSX text; is
this a string given to a prop a person reads (not `className`/`variant`/`href`); is this a
literal inside a *rendered* expression. Every one of those narrowings came from a false
positive the previous version produced.

The subtlest is the last: in `status === 'verification-link-sent' && <p/>` the literal is
an operand of a comparison, never text, so only the branches that actually render are
walked. `i18n-allow` on a line opts out a genuine non-string — a product name, an example
slug.

Proved by breaking it both ways, plain JSX text and a literal inside a ternary, and by
confirming a sentinel comparison does not trip it.

`PasswordInput`'s reveal toggle moved from `settings.password.*` to
`common.password.*` straight after. The component is reached from the sign-in screen
long before anyone opens account settings, so the settings namespace was simply the
wrong home — it sat there because the sweep that found the strings happened to be the
settings one. It now sits beside `common.confirm.*`, which is the same case: shared
chrome, its own group.

**Known limitation:** it skips `components/ui/**`, so the vendored sidebar's "Toggle
Sidebar" is untranslated and unreported. That tree is read-only by policy, so the fix is a
wrapper rather than an edit — deliberately left.

## Phase 2 — remaining ⬜

## Phase 3 — Catalog 🚧

| Module | Status | Notes |
|---|---|---|
| categories | ✅ | Full CRUD, translated, permission-gated. First use of `TenantFormRequest` + `RecordsCreator`. |
| suppliers | ✅ | Seven fields, a two-column form. Where the shared form kit came from. |
| customers | ✅ | Thirteen fields in three groups. First enum, first select. |
| raw materials | ✅ | Four fields, three required. `App\Enums\Unit` replaces v1's free-text unit — dimensions, factors, conversion. |
| products (+ BOM, image) | 🚧 | **A · core ✅** — table, category + supplier pickers, list + form. B · image and C · BOM to come. |

### Categories — what landed

One screen: a server-paginated list, a dialog over it for create and edit, and a
confirmation for delete. Every write returns `back()`, so the table refreshes in place
and nobody loses their search or their page.

New shared pieces this module needed, and where they went:

| Piece | Home | Why there |
|---|---|---|
| `TenantFormRequest` | `app/Http/Requests/Tenant/` | The base every tenant form request extends. Deliberately minimal — v1's decimal helpers arrive with the stock modules that use them. |
| `RecordsCreator` | `app/Models/Concerns/` | Stamps `created_by`, exposes `creator`. Both nullable: a seeded row has no author. |
| `optionalText()` | `lib/validation/primitives.ts` | `['nullable','string','max:N']`. No `min(1)` — an untouched field submits `''`, which is what `nullable` accepts. |
| `common.actions.edit` / `.delete` / `common.field.optional` | `lang/*/common.php` | Shared chrome, same argument as `cancel`. Every module from here deletes something. |
| `ui/textarea.tsx` | vendored | `shadcn add textarea`. A 1000-character description in a single-line input is not a field, it is a punishment. |

Kept **inside** `pages/categories/_components/` rather than promoted: the form dialog,
the row-action menu and the new-category button. All three are on their first consumer,
and the rule of three says a supplier form is what will reveal the right shared shape —
not a guess made while writing the only one that exists.

**Row actions own their own dialogs.** Columns are built once at module scope, so a cell
cannot close over page state. Rather than route around that with a context, each row
renders its own edit dialog and its own confirm — which is what `WorkspaceActions`
already does in the console, and it keeps the column array a true constant.

### The bug the built-asset pass caught

Every tenant page shipped **completely unstyled in production**, and had since the shell
landed. `config/tenancy.php` had `asset_helper_tenancy => true`, which rewrites every
`asset()` call inside a workspace to `/tenancy/assets/…`. Laravel's Vite helper uses
`asset()`, so the compiled bundle went through it too — and that route resolves the
tenant *by domain*, calling `Tenant::domains()`. This app identifies tenants **by path**,
so there is no such relation: `BadMethodCallException`, a 500 on every script, stylesheet
and font, and a workspace rendering as bare HTML.

Now `false`, with the reasoning in the config file. Tenant-specific files (product
images, avatars) will be served by their own controller when medialibrary lands, not by
`asset()`.

**Why it stayed hidden:** `bun run dev` serves assets from Vite's own origin and never
consults `asset()`. Nothing in dev mode can see this. It is exactly the failure the
per-phase checklist's "one pass against built assets" exists to catch, and it is the
first time that pass has earned its keep.

### Verified in a real browser (Playwright, SSR on)

`data-server-rendered="true"` confirmed on every load; console read after each.

- Empty state → create → the row appears, toast fires, list updates in place.
- Submitted empty: **no request left the browser** (network log confirms), zod reported
  "The name field is required." under the field and refocused it.
- Submitted a duplicate name: the server's `unique` rule came back through the *same*
  `<InputError>`, in the translated wording.
- Edit pre-fills from the row, saves unchanged (proving `Rule::unique(...)->ignore()`),
  and renames — toast and list both follow.
- Delete: confirm dialog, no type-to-confirm (a soft delete does not warrant one), row
  gone, total 12 → 11.
- Search matches the name **and** the description (`pallet` → Packaging); no match shows
  the module's own copy plus a Clear search button; the term round-trips in the URL.
- Sortable headers are exactly the controller's allow-list — `name` and `created_at` are
  buttons, `description` and `creator` are not.
- **Permission gating, both halves.** A user holding only `categories.view` sees the
  sidebar entry and the rows, but no New category button and no row menu — and
  `POST /demo/categories` and `DELETE /demo/categories/2` both return **403** from
  `AuthorizeTenantRoute`. The UI hiding is convenience; the server is the boundary.
- en / ms / zh_Hans, light and dark, 375 / 768 / 1024. No horizontal body scroll; at
  375 the description rides under the name and the table drops to Name + Actions.
- One pass over **built assets** with `inertia:start-ssr` — the pass that found the
  tenancy bug above, and clean afterwards.

Two things that looked like bugs and were not: pagination and the sign-in button both
appeared dead under Playwright's `.click()`, and both worked when driven through the DOM.
Tooling artifact, not application behaviour — worth recording so the next session does
not re-chase them.

### Open, and deliberately not fixed here

- **Dates are not localized.** `formatDate` returns `23 Aug 2026` in every locale — the
  month abbreviations are an English array in `lib/format.ts`. Fixing it properly means
  either 12 month keys × 3 locales, or a pinned `Intl.DateTimeFormat`, which the SSR rule
  currently forbids outright. Worth a decision before more modules render dates.
- **A category's name stays reserved after deletion.** The unique index counts trashed
  rows, so re-creating a deleted category's name is refused with "already been taken"
  while nothing by that name is visible. Matching the index is the correct behaviour —
  excluding trashed rows in validation would pass and then fail the INSERT as a 500 — but
  the message is confusing. Same tradeoff the tenant `users` table already documents.
- **`viewer@demo.test` / `password`** and a `Catalog Viewer` role now exist in the demo
  tenant, created to prove the permission gating above. Kept so the gating can be seen
  first-hand; delete them whenever.

### Suppliers — and the promotions it triggered

Categories left three components in `pages/categories/_components/` on the grounds that
one consumer is not a pattern. Suppliers is the second, and it settled the question:
the dialog, the menu and the field wiring were identical, so all three moved out.

| Promoted to | What it owns | Why it earned it |
|---|---|---|
| `components/form/resource-form-dialog.tsx` | the shell, the submission, the zod gate, the footer | Non-trivial and identical: dual-mode routing, close-on-success, `preserveScroll`, `noValidate`. |
| `components/form/text-field.tsx` | label + control + error, and the aria wiring between them | `aria-invalid`, `aria-describedby` and the message id have to agree, are invisible when they do not, and are what gets forgotten on the twentieth form. |
| `components/data/row-actions.tsx` | the edit/delete menu | Identical markup, permission-gated. Reports what was chosen; the module decides what it means. |

Categories was rewritten onto all three in the same change, because an abstraction with
one user is a guess. Its form dialog went 120 → 77 lines and its actions 86 → 65.

**Not promoted:** each module still owns a thin `XActions`, `XFormDialog` and
`NewXButton`. What is left in them is genuinely per-resource — the routes, the copy, the
fields — and a shared component taking all of that as props would just be the same code
with worse names. The delete wiring (confirm state, `router.delete`, processing) is
repeated verbatim in both and is the obvious next promotion; a third repeat is what
should trigger it, not this one.

### Two bugs found by driving it

**A seven-field form did not fit a phone.** `ui/dialog.tsx` centres its content with no
max-height and no overflow, so the Malay create form at 375×667 rendered 836px tall in a
760px viewport — clipped at the top, Cancel unreachable at the bottom, and unscrollable
because the dialog is `position: fixed`. Fixed by composition rather than by editing the
vendored primitive: `ResourceFormDialog` caps the height, lays the content out as a
column, and gives the fields their own scroll region between a pinned header and footer.
Categories benefits for free.

**The optional marker was welded to its label** — `Alamat(pilihan)`. JSX drops whitespace
between lines, so `{t(label)}` followed by a `<span>` on the next line produces no space.
A margin fixes it and does not depend on how JSX treats newlines.

### `check:i18n` learned about keys in expressions

The gate flagged `title={editing ? 'suppliers.edit.title' : 'suppliers.create.title'}` as
hard-coded text. It was already exempting key-shaped literals — but only as plain
attribute values, never inside an expression, and a key chosen between two branches is
still a key. `KEY_SHAPE` now applies in both positions.

Verified the narrowing did not open a hole: a sentence in either branch of the same
conditional is still reported, as is bare JSX text.

### Verified in a real browser (Playwright, SSR on)

- Empty form: only `name` is refused, the other six accept blank. Every error is wired to
  its field through `aria-describedby`.
- `not-an-address` in the email field is refused in the browser; a duplicate address is
  refused by the server, through the same `<InputError>`, in the translated wording.
- Edit pre-fills all seven fields from the row — the DTO carries the whole record, so
  opening the form costs no round trip.
- Search reaches `notes` (`cartons` → Zenith Packaging) and `contact_person` (`Ravi` →
  Titan Bearings). `tax_id` and `phone` are deliberately not searchable: both are looked
  up by exact value, and a LIKE would match a fragment of one number inside another.
- Delete is a soft delete — confirmed in the database, not just on screen.
- Categories re-verified end to end after being rewritten onto the shared kit.
- en / ms / zh_Hans, light and dark, 375 / 667 / 1280.

### Customers — the first enum, and the first picker

Thirteen fields, because an invoice has to be addressed to a legal entity rather than to
a name. MyInvois (MY) and InvoiceNow (SG) both want the buyer's tax identity and a
broken-out address as separate fields, and a single free-text address cannot be split
back into them afterwards.

All of it is optional but the name. A customer is usually added mid-conversation, long
before anyone knows their TIN, and a form that insisted would just be filled with
rubbish. The three groups — who they are, tax identity, billing address — are the design
that makes abandoning the last two an informed choice rather than fatigue.

**`App\Enums\Country` is the first enum in the project**, and it earns the transformer
that was configured for it three phases ago:

- `#[TypeScript]` emits `App.Enums.Country = 'MY' | 'SG'`, so `country_code` on the wire
  is that union rather than `string`. A typo in the browser is a tsc error.
- The model casts the column to it, `Rule::enum` validates against it, and
  `Country::codes()` feeds the picker. One list, four consumers, no drift.
- The transformer now scans `app/Enums` alongside `app/Data`. The provider's comment
  already predicted this — "inert until the first enum lands".
- The NAMES are deliberately not in the enum. A country's name is a user-facing string
  and lives in `lang/{locale}/countries.php`, keyed by code. The controller sends codes
  only; sending English labels would put one language's words inside the data.

`components/form/select-field.tsx` joins TextField. Two things about it are worth
knowing:

- **It submits through a hidden input, not through the Select.** Radix does bubble a
  native select when it detects a form, but what reaches the server then depends on that
  detection and on the "not set" sentinel not leaking out of it. A hidden input makes the
  wire value something the component states outright.
- **Radix reserves `''` on the root for "nothing selected" but forbids it on an item**, so
  clearing needs a sentinel entry. First cut mapped `''` to that sentinel, which made an
  untouched field read "Not set" and meant the placeholder never appeared at all. Passing
  `''` straight through fixes it: unset shows "Choose a country", and "Not set" is there
  to undo a choice.

### `check:i18n` caught the rule name

`Rule::enum` emits Laravel's `enum` message, not `in` — the two have identical English,
so a zod schema pointing at `validation.in` would have looked right until someone
translated one of them and the two layers started saying different things in Malay. The
gate refused the commit until `enum` existed, and the primitive now names the same key
the server does.

### Verified in a real browser (Playwright, SSR on)

- The picker: placeholder when unset, "Malaysia" and `MY` in the hidden input once
  chosen, back to placeholder and `''` when cleared — and the database stores NULL, not
  an empty string.
- All twelve text fields plus the country pre-fill from the row on edit.
- A forged `country_code` of `ZZ` **and** a lowercase `my` are both refused 422 by
  `Rule::enum`, in the translated wording. The picker can only offer two values; this is
  what happens when someone goes around it.
- Search reaches TIN and registration number — the two identifiers someone pastes off a
  purchase order — and deliberately not city, which would turn a name search into a
  region search.
- **The dialog scroll fix under real pressure:** 1329px of content in a 389px scroll
  region on a 375x667 phone, in Chinese. Dialog 635px in a 667px viewport, title and
  submit both reachable, no horizontal scroll. The country popover opens correctly inside
  the scrolling container, which is the classic place for this to come apart.
- en / ms / zh_Hans, light and dark, 375 / 667 / 1280.

### Raw materials — the first module that requires something

Four fields against customers' thirteen, and the harder module of the two, because this
is the first one where a required field is required for a *reason downstream* rather than
because the record would look empty without it.

`sku` and `unit` are both NOT NULL. Every quantity that a purchase order line, a stock
movement or a BOM row will ever record is a number *of* this material's unit, referred to
by its SKU. A material saved without them is a row the stock engine cannot use — so the
form asks, and both fields carry a hint saying what they are for.

**What did not arrive, and why.** v1's `RawMaterialData` also carries a
`purchase_history` collection — which received purchase orders this material came in on,
rendered as an expandable row. Purchase orders are phase 5. It returns with them rather
than being stubbed out now.

**No decimals here after all.** The plan and the previous handover both said raw
materials would be the module that forces `TenantFormRequest`'s deferred decimal helpers.
Reading v1's schema settles it: `raw_materials` is `name`, `sku`, `barcode`, `unit` — no
cost, no price, no quantity. Nothing in the catalog holds a number. The decimal rules and
their zod mirrors arrive with the stock and order modules that actually store amounts,
which is where the `decimal:0,4` guard against MySQL's silent rounding starts mattering.

**`unit` is an enum, not free text — and it carries the physics.** This came out of a
direct question ("unit can be presets? coz in future i want to convert, like gram to kg")
and it changed the module. v1's `varchar(20)` meant "kg" and "KG" were two different units
to a stock engine that would later add their quantities together; fixing that after the
fact means reconciling every row somebody has already typed. See the next section.

**The barcode is searchable; a supplier's tax ID deliberately is not.** The difference is
how the value gets into the box. A barcode is scanned, whole, and a scan that matched
nothing is a scan that failed. A tax ID is typed from memory, where a LIKE matching a
fragment of some other number is a wrong answer dressed as a right one. `barcode` is also
indexed, which v1's is not: phase 8's scanner resolves a scanned value by exact match.

### `App\Enums\Unit` — presets that can actually convert

Fourteen units, chosen as the manufacturing default: mass (g, kg, t), volume (ml, L),
length (mm, cm, m) and count (pcs, box, roll, sheet, pair, set).

A bare list of allowed strings would have been the easy reading of "presets" and would
not have delivered what was asked for. Converting g → kg needs two things per unit that a
list does not carry:

- **a `Dimension`** — mass, volume, length, count. Conversion only happens inside one.
  `g → kg` is arithmetic; `kg → L` is a question with no answer, and answering it anyway
  is how a wrong number becomes somebody's stock figure.
- **an integer factor to that dimension's base** — gram, millilitre, millimetre. Integers
  and exact, because a decimal factor puts a rounding error inside every quantity that
  passes through it, which is the whole class of bug the enum exists to prevent.

So `Unit::convert()` is `qty × from.factor() ÷ to.factor()`, guarded by
`isConvertibleTo()`. Verified before wiring anything to it: 1500 g → 1.5 kg, 2.5 kg →
2500 g, 1 t → 1000 kg, 250 cm → 2.5 m, 1.5 L → 1500 ml, and `kg → L` → null.

**Count units never convert into each other.** A box, a roll and a sheet are each "one of
something", but there is no universal number of pieces in a box — a box of screws and a
box of paper hold different amounts. `Dimension::Count` is therefore explicitly
non-convertible, and `box → pcs` returns null rather than 1.

**That number, when it is wanted, is a pack size on the material — not a unit.** "One box
= 24 pieces" is a fact about *this* material. Modelling it on the enum would make every box
in the workspace the same size. It is a separate small feature, named here rather than
quietly folded in.

**`convert()` and `isConvertibleTo()` have no caller yet**; the stock engine in phase 4 is
their consumer. That is a deliberate exception to the rule that has deferred everything
else (the decimal primitives, `RendersResourceIndex`'s missing helpers): those would have
meant guessing at a shape, and these are physical constants. `Unit::codes()` and
`Dimension::keys()` were written and then deleted in the same change — `Rule::enum` and
`grouped()` already cover both, and an unused helper is a guess about a future caller.

**The column is still `varchar(20)`, deliberately.** Not a database enum: adding a unit
stays a code change rather than an ALTER on every tenant database, and a per-workspace
units table later needs no data migration, because the column already holds the code such
a table would key on.

**`SelectField` learned to group.** Fourteen units in a flat list is a wall; the same
fourteen under Mass / Volume / Length / Count is a menu. Grouping is per-option
(`group?: TranslationKey`) rather than a second prop, so the country picker — which groups
nothing — renders exactly as it did before, verified. Options are collected into *runs*
rather than buckets, so the server's order survives.

`primitives.ts` gained `oneOf`, the required sibling of `optionalOneOf`. The difference
that matters: an untouched picker reports **"is required"**, not "is invalid". Somebody who
has not chosen has not chosen *wrongly*, and the two sentences send them to different
places.

### The delete wiring, promoted on its fourth copy

`hooks/use-resource-delete.ts`. Categories, suppliers and customers each spelled out the
same four decisions — `preserveScroll`, `onStart`/`onFinish` rather than `onSuccess`,
closing the dialog in `onFinish` too, and never removing the row locally — and all three
have been rewritten onto it in the same change. An abstraction with one user is a guess;
this one had three before it existed.

`RowActions` stays as it is. The menu is identical everywhere, the wording and the
consequences never are, and that split is the reason a module still owns its own
`XActions`.

### `check:i18n` learned that a module can have a hyphen in its name

The gate's key pattern was `[a-z0-9_]`, so `raw-materials.title` failed it twice over: it
was reported as a hard-coded sentence, and — the quieter half — the `t()`-call scanner
stopped matching it, so nothing was checking that those keys existed at all. Both patterns
now allow a hyphen, verified by planting a bad dashed key and watching the gate refuse it.

Worth fixing once here rather than ten times later: `purchase-orders`, `sales-orders`,
`stock-movements`, `stock-takes` and four more are all spelled this way.

### Bug: every optional field said "Barcodeoptional" to a screen reader

The `(optional)` marker sits in a `<span>` beside the label. When the visual gap was
missing in Malay (`Alamat(pilihan)`), that was fixed with `ml-1` and a comment saying the
space was presentation anyway. It is not: the accessible name is built by concatenating
text nodes, and nothing is inserted between two inline elements — so the margin fixed the
screen and left the announcement as "Alamatpilihan".

Now an explicit `{' '}`, which JSX preserves (it drops whitespace between lines, not an
expression), in both `TextField` and `SelectField`. Confirmed in the accessibility tree:
`Barcode (optional)`, `Negara (pilihan)`, across all four catalog modules at once.

### `TextField` grew a `hint`

A line of muted text under the control, wired into `aria-describedby` alongside the error
— both ids when both exist, because taking the explanation away exactly when a field
errors is the wrong trade.

Plain text rather than v1's `InfoHint` tooltip. A tooltip has nowhere to go on a phone,
and hiding the explanation behind a hover hides it from the people most likely to need
it. This is the first module with a field nobody can guess the answer to ("Unit" — type
what?), which is what earned it.

### Verified in a real browser (Playwright, SSR on)

- Create, edit, delete, all through the row menu — the delete going through the newly
  promoted hook, list re-rendered from the server rather than patched locally.
- The zod gate refuses an empty form in Chinese with the new attributes translated:
  `名称不能为空。` `SKU不能为空。` `单位不能为空。` The hints stay visible beside the errors.
- The unit picker: grouped and translated (`Jisim` / `Isi padu` / `Panjang` / `Bilangan`),
  the trigger showing `Helai` while the hidden input carries `sheet`, and an edit
  re-seeding both from the stored code.
- **The case-sensitivity v1 would have accepted:** posting past the picker with `KG`,
  `kilogram` and `zz` is refused 422 by `Rule::enum` in the translated wording; `kg` is
  accepted. v1's `string|max:20` would have stored all four as distinct units.
- A duplicate SKU is refused by the server, in the translated wording, with the typed
  values still in the form — then accepted once changed.
- Barcode search finds a material by a fragment of its scanned code; a nonsense term hits
  the no-match state and clears from it.
- Newest first, without the controller asking for it — the house default.
- 375px: the SKU, date and creator columns drop away and the unit under the name is what
  survives, which is the reason it is not a column of its own. No horizontal body scroll
  (`scrollWidth` 375 = `clientWidth` 375).
- The dialog at 375 in Malay — the longest of the three — scrolls its body with the footer
  pinned, all four hints intact.
- **Built assets, with the SSR node process actually running** (`bun run build:ssr` +
  `inertia:start-ssr`, `public/hot` moved aside): `data-server-rendered="true"`, the
  Malay heading present in the server HTML, CSS 200, no React #418, and a material created
  end-to-end in production mode.
- Categories, suppliers and customers re-driven after the refactor — confirm dialog,
  cancel, and every optional label now spaced.
- en / ms / zh_Hans, light and dark, 375 / 1280.

### Open, carried forward

- **`formatDate` renders English month names in every locale** — `23 Aug 2026` under a
  Malay and a Chinese UI. Now visible on four screens. Fixing it needs either 12 month
  keys × 3 locales or a pinned `Intl.DateTimeFormat`, which the current SSR rule forbids.
- **A deleted SKU stays reserved.** The unique index counts trashed rows, so re-creating a
  deleted material reports "already been taken" with nothing on screen to explain it.
  Correct behaviour, confusing message — same as categories' and customers'. Reword, or
  add a restore path.

### Products A — the core, and the first searchable picker

Products is three modules wearing one name, so it lands in three parts rather than one
~3,000-line drop: **A · core** (here), **B · image** (medialibrary), **C · BOM** (the
repeating-row editor). Each leaves the app working.

Seven fields in two groups: what the product *is*, and how it is *filed*. The split is
the design — filing is a decision about the catalog rather than about the product, and
saying so in the group's own line makes leaving it empty a choice instead of an
oversight. Both filing fields are nullable, because a product is usually created before
anyone has decided where it belongs, and refusing to save until they have just produces
a catalog of placeholder categories.

**A correction to two earlier handovers.** They said the deferred decimal helpers would
arrive with raw materials, then with phase 4. They arrive in **part C**: `bom_items.quantity`
is `decimal(15,4)`, the first real number in the schema.

**`App\Support\ActiveExists`** is ported for the two foreign keys, and it earns its place
immediately. `Rule::exists()` queries the table directly and so bypasses the SoftDeletes
scope — a deleted category still satisfies it. Proven rather than asserted: a category
created, soft-deleted, then submitted by id is refused 422, while the row is demonstrably
still in the table (`withTrashed()->exists()` → true) so a plain `exists` would have taken
it.

The model's `category()` and `supplier()` relations use `withTrashed()` on purpose, which
is the other half of the same fact. `nullOnDelete` fires on a force-delete, not on the soft
delete the screens actually perform, so a product keeps pointing at a trashed category —
and the listing should still be able to say which one rather than rendering a dash.

### `ComboboxField`, and why it is not `SelectField`

The difference is not the search box, it is where the words come from. A country and a
unit are a fixed list whose labels are translated in `lang/`; a supplier's name is the
workspace's own data and reads the same in every language. One component covering both
would need a label that is sometimes a `TranslationKey` and sometimes a string, which is
the kind of union that gets resolved wrongly six months later.

Filtering is in the browser over a whole list sent as a page prop — a workspace has tens
of categories and hundreds of suppliers, so one query beats a round trip per keystroke,
and it still works on a bad connection. **Substring, not cmdk's fuzzy scoring:** someone
typing "steel" expects the suppliers containing "steel", not everything whose letters
happen to appear in order.

`primitives.ts` gained `optionalId`, which is deliberately the honest half of the check.
Whether row 7 still exists and is untrashed is a fact about the database; what the browser
can check is that the value is one of the ids it was given. Its message is
`validation.exists`, matching the rule it mirrors — the same trap `Rule::enum` set earlier.

### Bug: the combobox never said "no matches"

First cut kept the "Not set" entry visible during a search, on the reading that it is an
action rather than a match. That means cmdk always has one visible item, so `CommandEmpty`
never renders — a search matching nothing showed a lone "Not set" and no word about why.
It now drops out as soon as anything is typed, and is one keystroke away again.

### The vendored tree moved forward a generation

`command.tsx` from today's registry needs `dialog.tsx`'s `showCloseButton`, which the
version scaffolded with the starter kit does not have. Taken as a decision rather than a
workaround, because the alternative — staying pinned — blocks every future `shadcn add`,
and the ⌘K palette needs the same `cmdk` dependency.

The visible cost, accepted: the dialog backdrop went from `bg-black/80` to the registry's
`bg-black/50` across all 18 dialog surfaces. It reads as noticeably lighter. If that turns
out to be too light, the fix is one rule in `app.css` on `[data-slot='dialog-overlay']` —
authored, not vendored.

`ui-guard.sh` blocks modifications to `components/ui/**`, and cannot tell a CLI update from
a hand-edit. Rather than reaching for `--no-verify` — which would also skip biome, pint,
tsc and the token checks — it gained a scoped escape: `SHADCN_UPDATE=1`, which excuses that
one rule, only for that one directory, and prints the files so the diff still gets read.
Verified in both directions: blocked without it, allowed with it.

### `check:i18n` learned about a rule builder it did not write

`ActiveExists::of('categories')` was read as a rule literally called `categories` — a false
alarm, and worse, silence about the `exists` message actually needed. The extractor now
strips any static call before reading plain-string rules, and carries a small map of the
project's own builders to the Laravel rule each produces. Verified by deleting the `exists`
message and watching the gate name it.

### The category and supplier cells link through

Clicking either goes to that screen **searched for the row** — `/categories?search=Finished+Goods`
— because neither module has a detail page to link to. A name since deleted lands on
"No categories match", naming the term, which is the answer to why the link was followed
rather than a dead end.

Permission-gated, and the branch is tested rather than assumed: `Catalog Viewer` was
temporarily granted `products.view` (it has `categories.view`, not `suppliers.view`), and
that user sees the category as a link and the supplier as plain text — while `GET /suppliers`
returns 403 and `/categories` 200. The link that is withheld is exactly the one
AuthorizeTenantRoute would have refused.

**Styled as links, on a token of their own.** They started as a badge and muted text, which
looked like the data they are and gave nobody a reason to click. Now underlined and blue —
but `text-primary` would have been the wrong reach for that blue, and measurably so:

| | light | dark |
|---|---|---|
| `--link` (used) | 6.83:1 | 8.70:1 |
| `--primary` (rejected) | — | **2.03:1** |

Primary is a *surface* colour — contrast-checked against `primary-foreground` sitting on it,
never against the page behind — and in dark mode it is deliberately *darker* than its light
counterpart (0.424 vs 0.488) because a button should recede there. As text it fails badly.
`--link` / `--link-hover` are the same hue with lightness chosen per theme against the
background they actually sit on. Measured in the browser through a canvas, not asserted.

The cost, worth naming: the category badge is gone, and with it the at-a-glance scan of how
the catalog is grouped. Clickability won.

### Verified in a real browser (Playwright, SSR on)

- Create with both pickers, edit re-seeding all three from stored ids
  (`unit=pcs`, `category_id=10`, `supplier_id=1`), delete.
- Combobox: substring search, the empty state, clearing, and the popover behaving inside
  a scrolling dialog at 375 in Chinese.
- The zod gate in Chinese: `名称不能为空。` `SKU不能为空。` `单位不能为空。`
- `ActiveExists` refusing a trashed category and a nonexistent id, accepting a live one.
- Every pre-existing dialog re-driven after the vendored update — confirm dialogs and the
  catalog form dialogs all intact.
- **Built assets with the SSR process running**: `data-server-rendered="true"`, the Chinese
  heading and the product row both in the server HTML, CSS 200, no React #418, and the
  combobox filtering correctly from the production bundle.
- en / ms / zh_Hans, light and dark, 375 / 1280.

### Search: "contact" meant the wrong thing, and phone was excluded for a bad reason

Reported from a screenshot: the suppliers placeholder read *"Search name, contact, email
or notes…"* and was taken to mean **contact number**. In Malaysian English that is the
ordinary reading of the word. The field is `contact_person`, so the placeholder was
ambiguous rather than wrong — but ambiguous is wrong for a placeholder. Now "contact
person", in all three locales, on both suppliers and customers.

Then the question underneath it: *why can't it search phone?* Because of a note left on
`Supplier::searchableColumns()` saying `tax_id` and `phone` were both excluded as exact-
value lookups where "a LIKE would match a fragment of one number inside another". That
reasoning holds for a tax number and is backwards for a phone number — **fragment
matching is the entire point there**, since the last four digits are how people recognise
one.

It was also hiding a worse problem. A plain LIKE on a phone column barely works at all:
the value is stored as somebody typed it, `+60 3 1234 5678`, and the person searching
types `0312345678`. Neither is a substring of the other, so the column would have looked
searchable and found nothing. Adding `phone` to the ordinary list would have "fixed" the
complaint and left it broken.

`Searchable` gained `searchableDigitColumns()`: the noise (` -()+.`) is stripped from the
column and from the term, and what is compared is digits. Verified against the seeded
numbers:

| typed | finds `+60 3 1234 5678` |
|---|---|
| `+60 3 1234 5678` | ✅ |
| `0312345678` | ✅ |
| `312345678` | ✅ |
| `1234 5678` | ✅ |
| `60-3-1234-5678` | ✅ |
| `5678` | ✅ |

US formatting works the same way — `9856508` finds `+1 (644) 985-6508`. The phone clause
is only added when the term contains a digit, so searching "steel" still produces exactly
the SQL it did before.

**PHPStan caught the interesting part.** `orWhereRaw()` wants a `literal-string`, and the
expression is built in a loop. The fix was not a cast: `searchableDigitColumns()` is typed
`list<literal-string>` and the builder takes and returns `literal-string`, so *the compiler*
now proves the raw SQL is assembled from source code alone. A column name that came from a
request fails to type-check rather than reaching the database.

Nested `REPLACE` rather than `REGEXP_REPLACE`, which reads better and would pin this to
MySQL 8. Neither is indexable — it is a function of the column and the LIKE leads with a
wildcard — which is fine for a table of contacts and would not be for a table of movements.

### Bug: every search box had two clear buttons

Visible in the same screenshot. Chrome renders its own `::-webkit-search-cancel-button`
inside any `input[type="search"]`, and the toolbar already draws one — so every list showed
two. The app's is the one that survives: it is keyboard-reachable, carries a translated
label, and clears the *request* rather than only the field. One rule in `app.css`, which is
authored rather than vendored.

**Still open from this exchange:** search treats the whole box as one phrase, so `Wei Lim`
finds nothing when the contact is `Lim Wei`. Splitting on whitespace and requiring each word
to match some column would fix it and would also make `acme lim` work — but it changes
search semantics on every list, so it is offered rather than done.

## Phases 3–8 — Modules ⬜

| Phase | Modules | Status |
|---|---|---|
| 3 · Catalog | **categories ✅ · suppliers ✅ · customers ✅ · raw materials ✅** · products (core ✅, +BOM, image) | 🚧 |
| 4 · Stock | locations, warehouses, StockService, movements, transfers, reorder levels, stock takes | ⬜ |
| 5 · Orders | purchase orders, purchase returns, sales orders, sales returns, production orders | ⬜ |
| 6 · Insights | reports, activity log | ⬜ |
| 7 · Team & settings | users, roles/RBAC, business settings, document numbering, e-invoice | ⬜ |
| 8 · Cross-cutting | exports, barcode/QR scanning, tenant dashboard, admin dashboard | ⬜ |
