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
  *(Superseded: `lib/format.ts` now uses a pinned `Intl` for the numbers, so the second
  route is open — the month names simply have not been switched. See "Dates now read on
  the viewer's clock".)*
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
  keys × 3 locales or a pinned `Intl.DateTimeFormat` — the latter is no longer forbidden,
  see "Dates now read on the viewer's clock".
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

### Products B — the photo

`spatie/laravel-medialibrary` **11.23.5**, which was already on the agreed Composer list.
It brings `spatie/image` 3.9 with it; nothing else was added.

One photo per product. `singleFile()`, so a second upload replaces the first and deletes
its file — a product cannot quietly accumulate five years of packaging revisions, and
nobody has to remember to clear the old one.

**Where the files live is the part worth reading.**

    {slug}/products/{ulid}.jpg
    {slug}/products/conversions/{ulid}-thumb.jpg

on a new private `assets` disk. Four properties, each load-bearing:

- **Private, never symlinked.** The `public` disk puts a file in the docroot, where the URL
  is the only thing between one workspace's photos and anyone who guesses it. Every read
  goes through the auth-gated media route instead. The disk also leaves `serve` off — the
  scaffold's `local` disk sets it, which registers Laravel's own unauthenticated
  `/storage/{path}` route.
- **Central and un-suffixed.** It is deliberately absent from `tenancy.filesystem.disks`,
  so stancl never repoints its root per workspace. What separates workspaces is the path,
  written by `App\Support\Media\TenantPathGenerator`, which throws rather than generate a
  path outside a workspace at all.
- **The slug prefix is not decoration.** Every workspace has its own database, so media ids
  restart at 1 in each of them. Without the prefix, workspace A's files and workspace B's
  files are the same tree.
- **The owner's folder says what the file is**, without a database round trip. `products/`
  answers "what is this and who has it", which is the question anyone looking at a disk is
  actually asking. It comes from `App\Support\Media\MediaOwners` — one registry holding
  both facts the app needs about a media-owning model, its folder and the permission that
  may read it, so a collection cannot end up storable but unservable. Unregistered models
  are refused at both ends rather than defaulted.
- **The filename is a fresh ULID, not what was uploaded.** This is what makes two uploads
  of `photo.jpg` safe now that files share a folder: uniqueness moved out of the directory
  and into the name. A ULID rather than `Str::random()` for a reason that shows up on a disk
  rather than in a threat model — it sorts, so `ls` is upload order. The uploaded name is
  not lost: medialibrary keeps it in `media.name` while `media.file_name` holds what is on
  disk. It also means the uploaded name never reaches the filesystem — no unicode, no
  300-character names, no `../`, and no `supplier-contract-Q3-margins.png` in a listing.

`storage/assets/.gitignore` follows the framework's own convention for `storage/app` —
`*` plus `!.gitignore` — so the folder exists on a fresh clone and nobody ever commits a
workspace's uploads.

**A stray `storage/tenantdemo/`, and why it was not the uploads.** medialibrary leaves
`temporary_directory_path` null and falls back to `storage_path('media-library/temp')` —
resolved *inside* the request, by which time stancl's FilesystemTenancyBootstrapper has
repointed `storage_path()` at `storage/tenant{id}`. So every workspace that ever uploaded a
photo left an empty `storage/tenant<slug>/media-library/temp` at the storage root that
nothing ever read. Pinning the value in `config/media-library.php` fixes it, because a value
written there is resolved once at boot, before any tenant exists. Measured inside a live
tenant context: `storage_path()` returns `…/storage/tenantdemo` while the configured temp
path stays `…/storage/media-library-temp`, and a real upload through the UI no longer
creates a `storage/tenant*` directory at all.

Every other `storage_path()` in the app was already in a config file for the same reason —
swept and confirmed — so this was the only one being resolved late. One shared temp root is
safe between workspaces: medialibrary appends a `Str::random(32)` segment per operation and
deletes it afterwards, so concurrent conversions never meet and nothing accumulates.

**Dropping the per-media directory was a trade, and the risk was checked before taking it.**
medialibrary's default gives every file its own `{media-id}/` folder. Removing it means one
delete now costs a directory listing — `DefaultFileRemover` calls `allFiles()` on the folder
— and that listing is over every file in `products/` rather than over one. On a local disk
that is a readdir; on S3 it would be a paginated LIST per delete, which is worth knowing
before this disk ever moves to object storage. The important half is that the remover
deletes only the entry matching the media's own `file_name`, and drops the directory only
when it is already empty — so a shared folder is safe. Proven rather than read: two products
uploaded the same `temp-test.jpg`, both landed as different ULIDs side by side, one was
removed and the other survived untouched with the folder intact.

Paths are **derived, never stored** — the media table has no path column — so changing this
layout again after go-live orphans every existing file. It would need a move script plus a
`file_name` rewrite, not just a new generator.

The `media` table is therefore a **tenant** migration, not a central one. Its rows are
`morphs('model')` — a class and an id — and both halves only mean something inside one
database. A central table would file "product 7" for every workspace at once.

**Teardown came with it.** `App\Jobs\DeleteTenantAssets` runs on the same pipeline as the
database drop when a workspace is force-deleted. Without it, reclaiming a workspace's
database silently leaves every photo anyone ever uploaded on disk, under a slug somebody
else can now register. The database goes first: if that fails the pipeline stops, and the
files are still there to go with the data that survived.

### Is the thumbnail necessary? Measured, then answered

Asked whether generating a thumbnail on every upload is worth the second file. Measured on a
4000x3000 camera photo, resized to what `max:2048` actually accepts:

| file | size |
|---|---|
| stored original (2400px) | 954 KB |
| `thumb` conversion (256px) | 16.5 KB |

The conversion is **1.7%** of the original, and it carries the entire products list: at the
default ten rows a page transfers 165 KB with it and **9.5 MB** without, because every 40px
cell would otherwise fetch the full-size photo. It earns its place several times over, and it
is the package's own work — `Spatie\MediaLibrary\Conversions\FileManipulator::performConversions()`
generates it, and this app only *declares* it through `registerMediaConversions()`. It stays.

**A 1600px cap on the stored original was tried, and then removed.** It worked — ~970 KB down
to ~424 KB per product, five times the saving deleting the thumbnail would have given — but it
was the one piece of this feature that processed an image *outside* medialibrary. It ran on the
controller's upload path, it was destructive with no untouched copy kept, and the collection
could not enforce it: a second upload path (the phase 7 logo, a seeder, an import) would have
stored full-size files unless it remembered to call it. The decision was to hand the pipeline
back to the package — **the file that is uploaded is the file that is stored**, and the only
derived file is the one medialibrary makes itself. `max:2048` is what bounds storage now.

Worth keeping from the attempt, because it will bite the next person who tries something
similar: spatie/image picks its output format from the file *extension*, and PHP's upload
temporary file has none. The first version threw `Unsupported format ''` inside its own catch,
so the upload succeeded, stored the original untouched, and only the log said otherwise — a
silent `catch` there would have shipped a measured-as-zero no-op.

### The thumbnail, and the black square it nearly shipped

The listing draws twenty-five products at 40px square. Serving the originals into those
cells is up to 50MB of photographs per page of the catalog, so there is a `thumb`
conversion at 256px — enough for the table at 3x and the dialog preview at 2x. `Fit::Max`
never upscales, so a small image is left alone rather than blown up and re-encoded.

It is generated **inline**, during the upload request, and `queue_conversions_by_default`
is set to `false` to say so. `QUEUE_CONNECTION` is `database` and nothing in this app runs
a worker, so a queued conversion is a thumbnail that never appears: the row renders a
broken image and no error is raised anywhere.

**`keepOriginalImageFormat()` is the line that matters.** medialibrary's conversions default
to JPEG — its `Conversion` constructor calls `->format('jpg')` — and JPEG has no alpha
channel. A product photographed on a transparent background, which is most catalog artwork,
comes back as the product on a solid **black** square. Measured rather than guessed: a
transparent PNG through the default pipeline gives `rgb(0,0,0)` in every corner. With the
flag, a transparent PNG stays a PNG and a JPEG stays a JPEG, verified both ways on disk.

The trade is real and worth knowing: a PNG thumbnail can be *larger* than a small PNG
original, because a 256px resample has anti-aliased edges the flat original did not. For
photographs — the case that actually matters — the conversion is about 5x smaller.

Changing a conversion definition after files exist orphans the old ones, which is what
`php artisan media-library:regenerate` is for. It bit this session mid-test and is worth
remembering before the next conversion is added.

### The media route, and a permission v1 did not check

`GET /{tenant}/media/{media}` for the original, `/{tenant}/media/{media}/{conversion}` for a
size. **Extension-less on purpose**: some nginx setups serve anything ending in `.png`
straight from the docroot with `try_files $uri =404` and never reach PHP — which for a
private disk means every image 404s in production and works perfectly in development.

The id is the version. A re-upload replaces the media row, so the new file has a new id and
therefore a new URL; a stored URL always names the file it named when it was written, and a
deleted id 404s rather than showing whatever took its place. `StreamsMedia` adds the
validators for the narrower case the id cannot cover — the same row re-processed — so the
browser revalidates and gets a 304 rather than assuming.

**The deviation from v1:** v1 left this route open to any signed-in user, so somebody with
no products permission at all could read every product photo in the workspace by counting
up from `/media/1`. A media row knows what it belongs to, so `MediaController` reads the
permission off the owner via `MediaOwners` — the same registry that decided where the file
was written. Unknown owner types are **refused, not allowed**, at both ends: a model with no
entry cannot be stored and cannot be served.

### Two new zod primitives

`optionalFile` and `optionalFlag`. The first is the more interesting.

Everything the server checks *about the file itself* is checkable before the bytes leave,
and that is the whole point: a 2MB photo the server is going to refuse costs an upload, a
wait and a re-pick, and on a phone it costs data. Verified with an 11MB file — the gate
refused it with **zero network requests**.

It takes both `mimes` and `values`, which look redundant and are not. A browser reports
what it picked as a mime type (`image/jpeg`) and knows nothing about the extension; Laravel
matches on the extension and prints that list in its message. `jpg` and `jpeg` are one mime
type and two extensions, so neither list can be generated from the other.

`isFile` is duck-typed rather than `z.instanceof(File)`. These schemas are built during
*render*, which also happens inside the Node process that server-renders the page, and
`z.instanceof` reads the global at construction. Node has had `File` since v20 and this app
runs v24, so nothing was broken — a shape check simply cannot become a version problem
later.

New validation messages in all three locales: `boolean`, `image`, `mimes`, and `max.file`.
That last one closed a quiet hole — the gate counted `max` as covered because `max.string`
existed, so a file-size failure would have fallen back to the framework's English inside a
Malay form.

### The field, and the three states a file input cannot express

No photo; a new file chosen; the stored one removed. A file input alone gives two — empty,
or holding a file — so removal travels as its own flag, rendered only while it is true, so
an untouched form sends no opinion about the photo at all and the server treats absent as
"leave it exactly as it is". A new file beats the flag: somebody who presses Remove and then
picks a replacement meant the replacement.

The preview of a newly picked file is an object URL created in the change handler, never
during render — one minted while rendering is minted again on every re-render and every one
of them leaks until the tab closes. Only image-shaped files are previewed: picking a PDF and
getting a broken-image glyph reads as "the app is broken" rather than "that is not a photo",
and the message under the field is already saying the second.

In the list, the photo rides in the **name cell** rather than a column of its own — it
identifies the same thing the name does, and a separate column would be mostly empty frames
taking width from the fields that carry information. Rows without one get the same empty
frame so the names stay on one line, and `object-contain` on a neutral ground rather than
`object-cover`, because cropping a supplier's catalog shot to fill a square is how the label
ends up outside the frame. `alt=""` there: the name is the adjacent text and a screen reader
reading the product twice per row is worse than not describing the picture.

`ImageField` and `ProductThumb` stay in `pages/products/_components/` — one consumer each.
The business logo in phase 7 is their second, and the non-trivial logic makes that the
promotion point.

### Verified in a real browser (Playwright, SSR on)

- Create with a photo → the row shows `/demo/media/1/thumb`; replace → a **new id**, so no
  stale URL is possible; Remove → media row gone, both files gone from disk, placeholder back.
- The served thumb: 256x256, `image/png` (format kept), `no-cache, private`, ETag
  `"2-thumb-…"`, and **304 on revalidation**.
- `/media/{id}` (original), `/media/{id}/thumb` → 200. `/media/{id}/bogus` and a nonexistent
  id → 404.
- **Permissions:** signed out → redirected to login, no bytes. Signed in as
  `viewer@demo.test` (Catalog Viewer, no `products.view`) → **403** on both the original and
  the thumbnail.
- The zod gate: 11MB → "must not be greater than 2048 kilobytes", **no request sent**,
  `aria-invalid` set and focus moved to the input. A `.txt` → "must be a file of type: jpg,
  jpeg, png, webp", and the stored photo stays visible instead of a broken image.
- The **server** refusing the same things with the gate bypassed (raw multipart POST): 422
  with both `image` and `mimes` messages, translated through our own lang file.
- Transparency: a transparent PNG round-trips as a 256x256 PNG with alpha 127 in the corner.
- en / ms / zh_Hans — `Gambar (pilihan)` / `图片 （选填）`, `Buang gambar` / `移除图片`, and the
  gate in Chinese: `图片不能大于 2048 KB。`
- Light and dark, 375 / 1280, no horizontal overflow. Hard reload with SSR on:
  `data-server-rendered="true"`, the thumbnail URL in the server HTML, console clean.
- After the dialog change: the close button reads `Close` / `Tutup` / `关闭`, dismisses the
  dialog, and measures 36x36. Re-driven across the form dialog, the confirm dialog, the
  delete-account dialog and the 2FA modal — the four shells that moved to the facade.
- "Remove photo" now starts on the same vertical line as the hint and the input above it
  (measured: all three at x=425), with the hover surface still surrounding the label and the
  target still 32px tall.
- The 2FA modal driven through both reachable states in Chinese — `启用两步验证` / `继续`,
  then `验证身份验证码` / `返回` / `确认`. The third (already-enabled) state was checked by
  resolving its keys in all three bundles rather than by driving it, because reaching it
  means enrolling a real TOTP on the demo account; the pending secret that opening the modal
  created was cleared afterwards, so `owner@demo.test` is as it was.
- The appearance tabs and the sidebar trigger in en / ms / zh_Hans, both in the accessibility
  tree and in the **server-rendered HTML** — so they are in the first paint, not swapped in
  after hydration.
- `bun run build` and `bun run build:ssr` both succeed.

### Found while driving: the platform refuses before the rule does

`upload_max_filesize` and `post_max_size` are both **2M** on this machine, and the rule is
`max:2048` — the same number. `post_max_size` covers the *whole* request body, so a file at
the limit plus the other fields exceeds it, and the request dies at the web server with a
**413** before Laravel's validator ever runs. Measured: 2.1MB → 413, 1.5MB → reaches Laravel.

Nobody hits this through the UI, because the zod gate refuses over-2MB files before the
request is built. But it means the app's stated limit can never be the one that fires for
anyone who bypasses the browser. The fix is a deployment setting rather than a code change:
the platform needs to allow a request body comfortably larger than the file limit (8M is a
reasonable ceiling for a 2MB rule). Left alone here because php.ini is machine
configuration, not repository content.

### Every dialog said "Close" in English

Found while checking the photo field's Malay, and it had nothing to do with the photo:
`ui/dialog.tsx` labels its dismiss button with a hard-coded `sr-only` "Close", so **every
dialog in the app** announced an English word in Malay and in Chinese. It arrived with the
vendored update two commits earlier and survived because an `sr-only` label is invisible on
screen — the exact failure mode `check:i18n` exists for, in the one directory that gate
deliberately does not read.

The primitive is not edited. It already takes `showCloseButton`, so
**`components/feedback/dialog.tsx`** turns that button off, re-exports everything else
untouched, and renders a replacement that reads its label from `lang/`. Every dialog moved
to it by changing one import line.

Two things improve on the way through, neither of them the point: the replacement is a real
`Button`, so it inherits the same focus ring and hover as every other control; and it is a
36px target rather than the primitive's 16px, which was under the 24px minimum.

**A regression that bigger button introduced, caught by measuring rather than looking.**
The close button is absolutely positioned, so a long title runs underneath it — true of the
16px original too, but a 36px button widens the band. At 375px, *"Delete Stainless steel
folding step stool, 3-tread, powder coated?"* overlapped, and its Malay translation wrapped
to three lines and overlapped twice. The facade now reserves 32px on the **title** — on top
of the header's own 24px, which clears the button's 49px — and leaves the description, which
sits below the button and never collides with it, at full width. On a phone, where the header
centres its text, centring what is left over is where the title belongs anyway.

Reserving it on the title is enforced in one place, and so is using the facade at all:
`check:structure` now fails on any import of `@/components/ui/dialog` from outside the
vendored tree. Proven by planting one. Without it, the seventh dialog is untranslated again
and nothing says so.

### `check:i18n` could not see a string one level up from the JSX

The gate read JSX text and JSX props, so a sentence assigned to an **object property** was
invisible to it. `two-factor-setup-modal.tsx` keeps its three states in a `useMemo`
returning `{ title, description, buttonText }` and renders those — which is the ordinary way
to write a component with three states, and it meant four English sentences shipped in every
locale with the gate reporting green.

It now also reads a string literal assigned to a property named like something a person
reads, using the same allow-list as a JSX prop, because it is the same question: `title` and
`label` are read, `variant` and `href` are not. The same `i18n-allow` escape and the same
`KEY_SHAPE` exemption apply, so a translation key passed through an object is still fine.

Turning it on found **two** files, not one:

- **The 2FA modal**, as expected — six strings across three states, now `settings.setup.*`
  keys resolved where they are rendered rather than sentences built inside a `useMemo` that
  has no business knowing the locale. The "Close" state reuses `common.actions.close`.
- **`appearance-tabs.tsx`**, which nobody was looking for. The appearance settings page
  spelled out "Light / Dark / System" while the theme menu two inches above it said
  "Cerah / Gelap / Sistem" from `common.theme.*` — the same three choices, one translated
  and one not. It now reads the same keys, and its descriptor moved to module scope like
  `ThemeToggle`'s.

### The shell's sidebar trigger

`SidebarTrigger` bakes "Toggle sidebar" into an `sr-only` span, so the one control present
on every screen at every size announced English in Malay and in Chinese. `aria-label` wins
over element text for the accessible name, so both layouts pass a translated one and the
vendored file is untouched. Verified in the accessibility tree, not just the DOM:
`button "Togol bar sisi"` / `button "切换侧边栏"`.

### Open, carried forward

- **The mobile drawer still says "Sidebar", and it cannot be fixed from outside.** On a
  phone, `Sidebar` renders `<Sheet {...props}><SheetHeader className="sr-only">
  <SheetTitle>Sidebar</SheetTitle><SheetDescription>Displays the mobile sidebar.
  </SheetDescription>` — the props spread reaches the Sheet **root**, and the two strings are
  literal children, so no prop and no wrapper reaches them. Confirmed live at 375px: the
  drawer's `aria-labelledby` resolves to "Sidebar" and its `aria-describedby` to "Displays
  the mobile sidebar." in every locale. Two ways out, and the choice is a structural one:
  have the app own its mobile drawer (a ~30-line wrapper rendering `Sheet`/`SheetContent`
  itself, which also means copying the private `SIDEBAR_WIDTH_MOBILE` and the `[&>button]:
  hidden` trick, and owning that branch's behaviour from then on), or edit the vendored file
  under an exception. Not taken unilaterally — the second breaks the read-only rule and the
  first buys two `sr-only` strings with a permanent fork of the sidebar's most
  behaviour-heavy branch.
- No image is shown at full size anywhere yet, so `ProductData` carries only `thumb_url`.
  A lightbox or a detail page would add `image_url` alongside it.

### Dates now read on the viewer's clock, and the database still does not care

Asked whether the date columns followed UTC. They did — `config('app.timezone')` is UTC,
the columns round-trip the literal PHP writes, the DTOs emit `toIso8601String()`, and
`formatDate` read `getUTCDate()` on purpose. Correct, and wrong for a person in Malaysia:
a row created at 02:00 local on the 6th is 18:00 UTC on the 5th, and the table said
"5 Sep". Everything between midnight and 08:00 was a day early.

**Storage is unchanged.** UTC in the columns, UTC on the wire, UTC in `<time dateTime>`.
Only the rendered text moved.

The hard part is not the conversion, it is that the server has to do it too. Under SSR the
same markup is produced twice, so a zone the browser picks for itself is a React #418
mismatch — the same trap `SetLocale` avoids by refusing `Accept-Language`. So the zone
takes the same route the locale and the theme already take:

1. an inline script in `app.blade.php`, beside the dark-mode one, writes
   `Intl.DateTimeFormat().resolvedOptions().timeZone` to a cookie **before first paint**;
2. `TimeZones::resolve()` validates it against the tzdb — an unknown or oversized value
   falls back to UTC rather than reaching `Intl` and throwing a RangeError mid-render;
3. `HandleInertiaRequests` shares the result, `useTimeZone()` reads it back, and both
   sides format against that one string.

On a browser that has never reported a zone the server has nothing to render with, so the
script reloads once — from `<head>`, before anything is painted, and only after reading
the cookie back so a browser with cookies blocked degrades to UTC instead of spinning.
Measured: setting the cookie to `America/New_York` and reloading gave
`navigationType: "reload"`, a corrected cookie and correct dates, once. It also means a
laptop that changes zone corrects itself on the next full load.

**Why `Intl` is now allowed where the rule forbade it.** The rule bans *unpinned* `Intl`,
and it was right to: ICU data differs between the SSR runtime and the browser, and CLDR 42
renamed en-GB's short September from "Sep" to "Sept" — two runtimes, two strings, same
input. So `lib/format.ts` asks Intl only for **numbers**, with both the locale and the zone
pinned, and composes the text from its own month table. Digits are digits in every ICU
version. Checked identical across Node 24 and Bun 1.3, including a DST zone
(`America/New_York` at -04:00 in June and -05:00 in December), a 45-minute one
(`Asia/Kathmandu`), and midnight, which `hour12: false` renders as "24" in some ICU
versions and `hourCycle: 'h23'` renders as "00" in all of them.

`DateCell` exists because a TanStack `cell` renderer is called as a function, not mounted,
so it cannot call a hook. Five column definitions became one line each.

The tooltip behind a relative time now reads `22 Aug 2026, 15:33 (+08:00)` instead of
`… 14:03 UTC`. The offset is derived from the conversion rather than stored, so it is
right on both sides of a DST boundary — and it is digits, so it needs no translation.

#### Verified in a real browser (Playwright, SSR on)

- The **server-rendered HTML** — no JavaScript — carries
  `<time dateTime="2026-09-05T18:35:35+00:00">6 Sep 2026</time>`. It never contains
  "5 Sep 2026", which is the whole proof: the server formatted in `Asia/Kuala_Lumpur`, so
  the client's string matches and nothing re-renders.
- Console: **0 errors**, no #418. The only warnings are Vite font-preload noise.
- Cookie resolution, by request: absent → `UTC` · `Asia/Kuala_Lumpur` → itself ·
  `Mars/Olympus` → `UTC` · a 200-character value → `UTC`.
- Products, categories and the console's workspace list all shifted correctly; morning-UTC
  rows correctly did **not** move.
- `bun run build:ssr` clean.

#### Open, carried forward

- **Auto-detected, not chosen.** There is no per-user time-zone setting, because the ask
  was "follow the PC". The `users` table already carries `locale`; a `timezone` column
  beside it would slot into `TimeZones::resolve()` above the cookie and change nothing else.
- **Month names are still English in every locale** — see below; this change did not
  address it, but it did remove the reason it was blocked.

### Products C — the bill of materials, and the first real number

The last third of products, and the first `decimal(15,4)` column in the schema. A bill is
which raw materials go into a product and how much of each it takes to make **one** —
per unit, not per batch, so a production order for 250 multiplies rather than needing its
own bill.

`bom_items` carries no soft delete and no `created_by`, unlike everything else in the
catalog. A bill is not a record of something that happened; it is the current answer to
"what goes into this". What *did* happen is the production order, which will snapshot its
own copy of these lines at creation — which is what makes it safe to replace a bill
wholesale, and why `ReplaceBom` deletes and re-inserts rather than reconciling. Nothing
points at a line's id.

The unique index on `(product_id, raw_material_id)` is the reason `distinct` is in the
rules: two lines for one material are not a bill with a duplicate, they are two answers to
one question, and without the rule the second insert would be a 500 instead of a sentence.

#### The decimals, finally

`TenantFormRequest::decimalRules()` and the `decimal()` zod primitive both land here,
because this is the first column that needs them.

**`numeric` alone is not enough, and the gap is silent.** MySQL in strict mode *errors* on
too many integer digits but *rounds* extra decimal places — `1.12345` is accepted and
stored as `1.1235`, nothing is logged, and a per-unit quantity that then multiplies every
future production order quietly stops being the one somebody typed. `decimal:0,4` refuses
it; `max:99999999999` turns the overflow case from a 500 into a sentence.

`0,4` is a range rather than `4`, so `2` is as acceptable as `2.0000` — requiring exactly
four would mean typing three zeros to enter a whole number.

Three places carry the value and none of them is a float:

- `bom_items.quantity` is `decimal(15,4)`;
- `BomRequest::lines()` hands Eloquent the **string** that passed `decimal:0,4`, so the
  exact value reaches the column;
- `BomItemData` trims it back for the form — `2.5000` → `2.5`, and the `.` guard matters:
  trimming zeros off `10` without it gives `1`.

0.1 has no exact binary representation, and this number gets multiplied by an order size.
The column is fixed-point for that reason, and casting anywhere along the way would undo it.

#### The editor

A dialog of its own, opened from the row menu, rather than a third group in the product
form: it is a list somebody grows and prunes rather than a fixed set of fields, and the
product form is already eight fields long. `RowActions` grew a `children` slot for the
menu entry — a slot rather than a described list, because what those entries do and
whether they are permitted differs every time and only their position is shared.

**The rows are uncontrolled, and `key` is what makes that work.** State holds only that a
row exists plus its seed values; the inputs hold their own values after mount. Removing
the first of two lines re-indexes the survivor's inputs from `items[1][…]` to
`items[0][…]` while React keeps the same DOM nodes — so nothing is re-seeded and nothing
typed is lost. Verified live. `items[0][quantity]` is also exactly the name
`gate.ts:dotPathToInputName` has been converting zod's `items.0.quantity` into since
Phase 2, which is what puts a line's error under that line's own field.

`BomLines` sits *inside* the dialog's content rather than beside it, so Radix unmounts it
on close and an abandoned edit cannot reappear on the next open — the same property
ordinary uncontrolled fields already rely on.

**`nullable`, not `present`, on `items`.** A bill with no lines is legitimate — it is how
one is cleared — but a form with no rows renders no `items[…]` inputs, so the key is
simply absent. There is no markup that submits an empty array. Since the endpoint's whole
contract is "here is the new bill", absent and empty mean the same thing, and demanding a
key the browser cannot send would only be a rule the form has to work around.

Below `sm` the column headers hide and each field's own label takes over, which is what
`labelHidden="sm"` on `TextField` and `ComboboxField` is for. The alternative — labels
always visible — costs about 170px per line, and a ten-material bill is then most of a
phone screen per row.

#### Verified in a real browser (Playwright, SSR on)

- Create, edit, re-index, clear. Two lines saved as `2.5000` and `0.3500`; removing the
  first and saving left one row **with a new id**, which is the delete-and-reinsert
  visible from outside; removing the last cleared the bill to zero rows.
- The zod gate refused, before any request left: an empty line ("The material field is
  required." / "The quantity field is required."), `1.12345` ("The quantity field must
  have 0-4 decimal places."), and a duplicate material — filed on the **second**
  occurrence, the one just chosen, not on both.
- Bypassing the gate with `fetch`, the server refused the same five things with the same
  sentences, word for word: too many decimals, duplicate, `0`, an unknown material id,
  and `999999999999`.
- The listing's SSR HTML — no JavaScript — already contains "3 materials", and never
  contains `12.0000`: the trim happens in the DTO, not in the browser.
- 375px: column headers hidden, both labels visible, row stacked, no horizontal overflow.
  1024px: headers visible, labels `sr-only` but still in the a11y tree, row a
  `418px 144px 36px` grid.
- en / ms / zh_Hans complete, including the validation messages
  ("Ruangan kuantiti mestilah mempunyai 0-4 tempat perpuluhan.") and the counts —
  "3 materials" / "3 bahan" / "3 项物料". The `count` key uses explicit `{0}|[1,*]`
  conditions in all three: Malay and Chinese have one plural form, so a bare two-segment
  string would always have picked "No bill".
- Console: 0 errors beyond five deliberate 422s from the bypass probes. No #418.
- `bun run build:ssr` clean.

#### Open, carried forward

- **Laravel flags both halves of a duplicate; zod flags only the second.** Two different
  readings of the same rule, and the browser's is the better one — it points at the line
  just changed rather than at two lines equally. Only reachable by bypassing the gate.
- **Stale errors survive a row being removed.** The bag is keyed by index, so removing a
  line above an errored one leaves the message under the wrong row until the next submit
  clears it. Cosmetic, and it self-corrects on the next save.
- **No bill is shown anywhere but the editor.** The listing carries a count under the
  product name; there is no read-only view of what a product is made of.
- The catalog has no seeder — the demo workspace's products, materials and this bill were
  all made by hand. A fresh tenant starts empty, which is correct, but it means the
  feature is invisible until somebody adds a material.

### A material a product is built from can no longer be deleted

Asked what happens to a bill when its material is deleted. Measured it, and the answer
was worse than it looked.

Nothing is lost: the materials screen soft-deletes, and the `ON DELETE CASCADE` on
`bom_items.raw_material_id` only fires on a real row removal, which the UI never does.
The bill line survives and restoring the material restores everything.

But the bill became **unusable and unexplained**:

| | |
|---|---|
| `bom_items` row | survived |
| Product list | still counted it — "2 materials" |
| The editor's line | showed **"Choose a material"** — indistinguishable from an empty row |
| Saving the bill | refused: "The selected material is invalid." |

Together those are worse than either alone. The line looked like nobody had filled it
in, gave no hint which material had gone, and blocked the whole bill from saving — even
an edit to a different line. The name was not even unavailable: `ProductData` already
sends it, because `BomItem::rawMaterial()` resolves `withTrashed()`. The editor renders
from the picker's options instead, and those exclude trashed rows.

This is specific to bills. A product's `category_id` and `supplier_id` are nullable and
show a dash when their row goes; `bom_items.raw_material_id` is NOT NULL and cannot.

**Chosen: refuse the delete.** The materials screen now refuses to delete a material a
product's bill calls for, and names the products. The alternative considered was to let
the delete happen and flag the stale line in the editor — easier deleting, something to
fix later. Refusing keeps bills correct by construction, which matters more once
production orders start reading them in phase 5.

Two halves, and both are needed:

- **The server is the boundary.** `RawMaterialController::destroy` checks and returns an
  error toast naming the products. Verified by bypassing the UI entirely with `fetch`:
  the material survived, its bill lines were intact, and the response carried
  `{"type":"error","message":"Serbuk kayu cannot be deleted — it is used in the bill of
  materials for Amos Brennan."}`.
- **The screen explains before the click.** `RawMaterialData` carries `bom_products`
  (capped at five names) and `bom_product_count`, so Delete opens a dialog that says why
  rather than a button that fails. `ConfirmDialog` grew a `blocked` variant for it — a
  discriminated union, so the compiler refuses a confirmable dialog with no button and a
  blocked one carrying an `onConfirm` that can never run. It also drops the corner ✕,
  because its footer already says Close and two controls with one accessible name is
  something a screen reader has to disambiguate for nothing.

`RawMaterial::products()` is the reverse of `Product::bomItems()` — `bom_items` read as
an ordinary many-to-many. **Trashed products are excluded, deliberately**: a
soft-deleted product has no restore or force-delete route (recorded below), so counting
its bill would make a material undeletable forever with nothing on any screen to explain
why. The trade is that restoring such a product — which no route currently does — could
surface a stale line.

#### Verified in a real browser (Playwright, SSR on)

- Blocked: "Cannot delete Serbuk kayu" / "It is used in the bill of materials for Amos
  Brennan. Remove it from that bill first…", one Close button, no destructive action.
- Not blocked: the ordinary destructive confirm is untouched, and deleting still works.
- Bypassed: refused server-side, as above.
- ms: "Tidak boleh memadam Serbuk kayu" — complete, including the singular branch.

#### Open, carried forward

- **`BomItemData.name` is still unused.** It is sent on every line and nothing reads it.
  It is what the discarded option would have displayed; it stays because a read-only view
  of a bill will want it.
- Materials already stranded by an earlier delete still show a blank line in the editor.
  There are none in the demo data now, and the guard stops new ones, but nothing repairs
  an old one.

### Lists can filter by unit, and the toolbar slot finally has a tenant

Asked for a unit filter on raw materials and products. The interesting part was not the
filter; it was that `ListToolbar` has had an `extra` slot since phase 2 documented for
"a status filter, a date range" and nothing had ever used it. Building the first one
decided the shape every later filter inherits, so it was built generic.

**Three pieces, none of them about units.**

- `RendersResourceIndex` gained `extra` — a bag of this resource's own filters, echoed
  back under `filters.extra`. Only echoed: the *constraint* is applied by the controller
  on its own Builder, for the same reason `searchUsing` is a closure rather than a flag.
  What a filter means belongs beside the query it narrows.
- `DataTable` re-sends `filters.extra` on every visit. Without that, sorting a filtered
  list would silently widen it back out — the failure mode this kind of feature usually
  ships with.
- The `toolbar` prop became a **render prop** taking a `FilterApi` (`values`, `set`).
  A control has to route its change through the table, because "narrowing starts again
  at page 1" already lives in `visit()` and a control calling `router.get` itself would
  be the second copy of that rule.

`SelectFilter` is the control: translated labels from `lang/`, no `<Label>`, no name on
the wire. It is to a filter what `SelectField` is to a form — and deliberately not
`ComboboxField`, whose search box would be furniture over a list of six.

**Only the units actually in use are offered.** Fourteen exist; the demo workspace uses
six for products and three for materials, and the two lists are different. Computed over
the whole table rather than the current page or search — a filter whose options moved as
you typed would be one you could not get back out of. Ordered by the enum's own cases,
so it reads mass, volume, length, count instead of interleaving them alphabetically.
Below two units the control is not rendered at all: a filter offering one choice narrows
nothing.

`Unit::tryFrom()` is the whole of the input handling. `?unit=nonsense` is no filter
rather than an error or an empty list — there is nothing to protect here beyond the
column.

#### Verified in a real browser (Playwright, SSR on)

- Products offered exactly its six units in enum order (Kilogram, Millilitre, Litre,
  Metre, Piece, Box); raw materials offered its own three (Kilogram, Millilitre, Sheet).
- `?unit=ml` narrowed six rows to one, and the trigger showed the choice.
- **It composes**: sorting kept `unit=ml` (`?direction=asc&sort=name&unit=ml`), and a
  search on top gave `?search=Aladdin&sort=name&unit=ml` with the filter still shown.
- `?unit=nonsense` fell back to "All units" and all six rows, no error.
- Changing the filter dropped `page` from the URL. The demo data is too small for a
  second page to exist, so this was observed on the URL rather than on rows.
- Setting every material to one unit made the control disappear, leaving search intact;
  restored afterwards.
- ms: "Tapis mengikut unit" / "Semua unit", with unit names already translated through
  the existing `units.name.*` keys.
- 375px: stacks under the search box, both full width, no horizontal overflow. Console
  clean.

#### Open, carried forward

- **Only one filter per list so far.** `filters.extra` is a bag and `SelectFilter` is
  generic, so a second one is a second control and another key — but nothing has yet
  proved two side by side, and the toolbar's `sm:ml-auto` row may want revisiting when
  it happens.
- `per_page` accepts only 10/25/50/100, so a deep link with anything else silently
  becomes 10. Pre-existing, noticed while testing the page reset, not changed.

### Filters moved behind one button, before there were enough to need it

Raised the point that filters will keep arriving and the toolbar row will not hold them.
Correct: the plan's later lists want warehouse, direction and a date range on stock
movements; customer, status and date on orders. Three or four controls, which a toolbar
row cannot hold on a phone — they stack, and push the table they exist to narrow off the
screen.

Built now rather than when the fourth one arrives, because the shape decides how every
later list is written. The plumbing did not change: `filters.extra` and `FilterApi` from
the previous commit are what this renders.

- **`FilterPanel`** — one button holding every filter a list has. A popover on a desk, a
  bottom sheet on a phone; the same children either way. `useIsMobile` uses
  `useSyncExternalStore` with a server snapshot, so hydration cannot disagree, and the
  panel is shut on first render where the difference is invisible anyway.
- **The count on the button is the load-bearing part.** A filter behind a button is a
  filter nobody can see; arriving from a link with rows missing and nothing to explain
  it is the failure this design invites. The badge is server-rendered — it is in the SSR
  HTML, not painted after hydration.
- `SelectFilter` gained a real `<Label>`. Stacked in a panel, "All units" says what is
  selected but not what it selects.
- `FilterApi` gained `count` and `clear`. Clearing three filters is **one** visit and one
  history entry, not three of each.

#### Two real bugs, found by driving it

Filtering to zero rows was broken, and had been since the unit filter landed — the
earlier pass never hit an empty result.

- **The toolbar disappeared.** It rendered on `page.total > 0 || searching`, so a filter
  matching nothing took away the only control that could undo it. The filter could only
  be removed by editing the URL. Now `page.total > 0 || narrowed`.
- **It claimed the list was empty.** Zero rows with a filter applied showed "No products
  yet — Add the first one…", which is false: there were six, and the filter excluded
  them. Now it shows the no-match state, with its own sentence when no search term is
  involved rather than "nothing matches ''".

The no-match state offers one button per thing that can be undone — "Clear search" and
"Clear all filters" — rather than one compound label that would have to describe every
combination of the two.

#### Verified in a real browser (Playwright, SSR on)

- Closed: `[ Filters ]`. With `?unit=ml`: `[ Filters 1 ] [ Clear ]`, badge present in the
  **server** HTML (`Filters<span data-slot="badge"…`), one matching row.
- Both clear paths work: "Clear all filters" inside the panel, and the toolbar shortcut.
  Each drops the key from the URL and restores all six rows.
- 375px: a bottom sheet, full width, anchored to the bottom, titled, with the labelled
  control inside and no horizontal overflow.
- **The multi-filter case was proven rather than assumed.** A second filter was added to
  products temporarily: the badge read 2, the panel stacked two labelled controls, and
  `?unit=kg&category=1` (zero matches) kept its toolbar and offered both escape hatches.
  Reverted afterwards — the controller and page are back to one filter.
- Search with no matches still names the term and offers only "Clear search"; no filter
  badge. Unchanged.
- Console: 0 errors.

#### Open, carried forward

- **Products still has one filter.** Category and supplier would now be about ten lines
  each, and the panel is built for them, but they were not asked for.
- The panel has no "apply" step — each control visits immediately. Fine for one or two;
  with four, four visits to set four filters may want batching into one.

### Products can be filtered by what they are built from, one material or several

Asked for a bill-of-materials filter on products: pick a material, see the products made
from it. The first filter whose question is about a *related* table rather than a column
on the row.

`whereHas('bomItems', …)`, not a join. A product with three bill lines must appear once;
a join returns it once per matching line. Verified with a product carrying two materials
— filtering by one of them listed it exactly once.

**The picker offers only materials some bill actually mentions**, via
`whereHas('products')` — the same relation the delete guard reads, so "used by a product"
means one thing in both places. A material no bill names is a choice that can only return
nothing. Trashed products do not count, for the same reason they do not count there.

A **searchable** control rather than a select, and that is the point of `ComboboxFilter`
existing beside `SelectFilter`: a unit is a fixed list of translated words and fits in a
plain select; a material is workspace data and there can be hundreds. Deliberately
parallel to `ComboboxField` rather than shared with it — this one is *controlled*
(a filter's value lives in the URL and must change when something else clears it),
submits no hidden input, and its clear entry reads "Any material" rather than "Not set".
Folding the two together would mean a prop deciding which of those it is.

An id that names nothing — one that never existed, or a material since removed from every
bill — is **no filter**, not an empty list. An empty list would read as "nothing uses it"
when the truth is "there is no such thing", and the panel would show "Any material" over
zero rows, which is the UI contradicting itself.

Each control now earns its place separately: the unit filter needs two units to be worth
showing, the material filter needs one material, and the panel appears if either does.

#### Verified in a real browser (Playwright, SSR on)

- `?material=6` (a material in one bill) → exactly that product. `?material=3` (in six)
  → all six, with the two-line product listed **once**.
- Search inside the picker: "serbuk" narrowed to one, "zzz" gave "No materials match.",
  and "Any material" correctly dropped out of the list while typing.
- Combined: `?material=3&unit=m` → badge 2, and the one product that is both.
- `?material=99999` → six rows in the server HTML and no badge: no filter, and the panel
  agrees with the result.
- 375px in Malay: bottom sheet, "Penapis 2", both controls labelled ("Unit",
  "Diperbuat daripada"), "Kosongkan semua penapis", no horizontal overflow.
- Console: 0 errors.

Then asked for several at once. **ANY, not ALL** — a product qualifies by using one of
the selected materials, so ticking another widens the result. That is the shape of the
question this answers ("these are short, what does that hit?"), and ALL would narrow
toward nothing, since few products share an exact set. The demo data makes the
difference concrete: `Serbuk kayu` is in six bills and `Francis Diaz` in one, and the
product using both is the only overlap — so ALL of the two would return 1 where ANY
returns 6.

`ComboboxFilter` became multi-select outright rather than growing a `multiple` prop. It
had exactly one consumer, and warehouse and customer filters will want the same OR
semantics, so a single-select variant would have been built for nobody.

Three details in it that are not obvious:

- **The value is a comma-separated string, not an array.** It is what travels in the
  URL, and a string keeps the effect dependencies stable — an array prop is a new object
  every render, so the debounce would restart every render and never fire.
- **Ticking is debounced by 300ms**, the same as the search box, so three boxes are one
  request and one history entry rather than three of each. Measured: two rapid ticks
  produced exactly **1** request, against a counter first proven to count by making one
  deliberate change and watching it increment.
- **The popover stays open while ticking** but closes on "Any material" — picking three
  things and reopening the list twice is not a control either, while clearing is a
  decision that is finished.

Ids are validated against the materials the picker was offered, so a stale link drops
what it cannot resolve rather than returning nothing. Measured: `3,99999` → `3`,
`3,3,6` → `3,6`, `99999` → no filter, `,,` → no filter.

#### Open, carried forward

- **Only "any", with no way to ask "all".** A per-search Any/All toggle was offered and
  not taken; if "which product uses both of these?" comes up, that is where it goes.
- The filter is on products only. The reverse — "which products use this material" from
  the raw-materials screen — is the same question from the other end, and
  `RawMaterial::products()` already exists for it.

### A hand-edited URL could 500 every list in the app

Asked what happens if a filter names a material that does not exist, or one that has been
deleted — the values arrive in the query string, so anything can be typed there. The
answer to the question asked was "nothing"; the answer to the question behind it was a
real bug, one that predates the filters entirely.

**`?search[]=x` returned a 500 on every list**: categories, suppliers, customers, raw
materials, products, and the console's workspace list. `$request->string()` hands an
array to `Str::of()`, which raises a TypeError — "Array to string conversion". The
`?unit[]=` and `?material[]=` filters had inherited the same fatal.

The galling part: the hazard had already been found. `SortsResourceQuery::requested()`
documents it in a comment and guards against it, which is why `?sort[]=name` was fine
while `?search[]=x` was not. The fix existed and had never been applied anywhere else.

Now {@see ReadsQueryValues} is the single definition, used by the search box, the sort
guard and both filters. `query()` rather than `input()`, so a request body cannot supply
a filter; anything that is not a string reads as `''`, which every caller already treats
as no filter.

#### What the deleted-material cases actually do

Both were tested against a throwaway material put into a real bill, then deleted each way:

| | |
|---|---|
| Soft-deleted, bill line still present | dropped from the filter, absent from the picker |
| Hard-deleted | the FK cascade removes the bill line first; same result |
| Mixed, `?material=7,3` | the dead id is dropped, the **live one survives** — either order |

Dropping rather than filtering is deliberate: an empty list would read as "nothing uses
it" when the truth is "there is no such thing", and the panel would show "Any material"
over zero rows — the UI contradicting its own result.

#### The adversarial pass

Sixteen shapes of hostile URL, before and after. Everything now answers 200 and logs
nothing — measured: eight hostile requests grew `laravel.log` by **0 lines**.

- Arrays and nested arrays on every parameter at once
  (`search[]=a&sort[]=b&direction[]=c&per_page[]=d&unit[]=e&material[]=f`) — was 500,
  now 200.
- `1 OR 1=1`, `1'; DROP TABLE bom_items;--`, `1) OR (1=1`, `1 UNION SELECT * FROM users`,
  `kg' OR '1'='1` — all no-ops. Two independent reasons: ids are `(int)`-cast and
  whitelisted against the materials the picker offered before they reach the query, and
  the query is parameter-bound (`in (?, ?)` with bindings `[3,6]`). Every table was
  counted afterwards and is intact.
- Negative, zero, float, scientific notation, letters, null byte, emoji — all no filter.
- `3,99999` → `3` · `3,3,6` → `3,6` · `99999` → no filter · `,,` → no filter.

Search and sort re-driven afterwards and unchanged.

#### Open, carried forward

- `ResolvesPerPage` still reads `$request->integer('per_page')`, which casts an array to
  `1` with a PHP warning rather than fatally. It lands outside the allow-list and falls
  back to 10, so the behaviour is right by accident rather than by intent. Left alone;
  worth folding into the same helper if that trait is touched.

### The multi-select filter now says what it does

Raised after using it: a multi-select does not tell you how the several combine, and
the reasonable guess is the wrong one. Ticking two materials reads as "narrow to
products with both"; it actually widens to "products with either". The control gave no
sign either way — it said "2 materials" and left the rest to be reverse-engineered from
the results.

Two changes, both wording, one component prop.

**The trigger carries the rule.** `:count materials` → `Any of :count materials`. The
panel is shut whenever someone is looking at the results, so this is the only place the
rule can be read without opening anything.

**A hint under the control, pluralised on how many are ticked.** `ComboboxFilter` gains
an optional `hint`, resolved with `tChoice(hint, picked.length)` — so the sentence
teaches while the list is short and states the reading once it is not:

| Ticked | Sentence |
|---|---|
| 0 or 1 | Tick more than one to widen the search — a product needs only one of them. |
| 2+ | Showing products that use any of these :count materials, not products that use all of them. |

The component does not know that "any" is the rule; it knows there is a sentence worth
saying and how to pluralise it. Both halves live in `products.php`, so a later filter
with different semantics writes its own.

Wired through `aria-describedby` on the trigger, matching `TextField` — the hint is
part of the control, not a paragraph that happens to sit near it.

#### Verified in a real browser (Playwright, SSR on)

- 0 ticked → teaching sentence. 1 ticked → **1 row**, still teaching. 2 ticked → **6
  rows**, and the sentence switches to the stating form. The row count going *up* is
  the proof the sentence is true.
- `aria-describedby` on the trigger resolves to the hint element, checked by id rather
  than by "a `<p>` is nearby".
- Trigger reads "Any of 2 materials" / "Mana-mana daripada 2 bahan" / "2 种物料中的任一种".
- 375: the panel is a bottom sheet there, and the hint fits inside it with no sideways
  body scroll.
- zh_Hans used 材料 in the first draft while the rest of the block says 物料; caught and
  aligned before it shipped.

#### Not done, considered

- **An Any/All toggle.** Still declined — the question "products using all of these
  materials" is a bill-of-materials query, not a list filter, and a control offering it
  would need a second server path for a question nobody has asked yet.
- **Naming the picked materials in the sentence** ("uses Serbuk kayu or Francis Diaz").
  Reads better, but joining a list with a localised "or" wants `Intl.ListFormat`, and
  unpinned `Intl` in render output is the exact hydration hazard CLAUDE.md forbids.
  The count plus the checkmarks in the list carry it.

## Phase 4 — Stock 🚧

### Sites — the first stock table, and no quantities on it

`locations` in the schema, **Sites** on screen. The word differs on purpose: the table
name is v1's and changing it would make every later join read differently from the
reference, while "location" in a warehouse app already means a bin or a rack, and using
it for a whole branch would collide with the thing it means one level down.

Nothing is stored *at* a site. A site owns warehouses and a warehouse holds the stock,
so this table carries a name, a code, an address, and no numbers at all. That is the
whole reason it lands first: everything below it is addressed through it.

**Ported nearly verbatim from v1** — the columns and rules are unchanged. What is new
is what every v2 module gets for free: translated strings, a zod schema paired with the
FormRequest, the shared `resourceList` concern, and the URL hardening from `96f9892`.

| Piece | Note |
|---|---|
| `2026_08_23_000700_create_locations_table` | `name`, `code` (nullable **and** unique), `address` text, `created_by` nullOnDelete, soft deletes |
| `Location` | `searchableColumns()` is name + code + **address** — someone looking for the Penang branch is as likely to type the town |
| `LocationData` | Same shape as `CategoryData`; `creator` flattened to a name at the boundary |
| `LocationRequest` + `location.ts` | 3 fields, parity gate green at 8 pairs |
| `LocationController` | 4 methods, none over 30 lines; `SORTABLE` is name/code/created_at |
| Nav | A new **Stock** group, `tenant.nav.stock` in all three locales |

**Nullable and unique together.** MySQL permits any number of NULLs in a unique index,
so "most sites have no code, and the ones that do must not collide" is one index rather
than a rule living somewhere else. Verified rather than assumed: two sites now hold
`code = NULL` side by side, and a third was refused for reusing `PEN`.

#### Verified in a real browser (Playwright, SSR on)

- Empty submit: "The name field is required." under the field, and **zero requests to
  `/locations`** — the zod gate stopped it before the network.
- Duplicate code: refused by the server, error under `code`, dialog still open with
  both values kept. Only the database can answer that question, which is why the
  schema does not try.
- Create ×4, edit (adding a code to a site that had none), delete — each with its toast
  and the list refreshed in place.
- Search: `Shah Alam` by name, `Lebuh Pantai` by **address alone**, `IPH` by **code
  alone**, `zzz nothing` → 0. All three columns carry.
- Hostile URLs, the class that used to 500 every list: `?search[]=`, `?sort[]=`,
  `?per_page[]=`, `?page[]=`, `?sort=name);DROP TABLE locations;--`, `?sort=address`
  (a real column, off the allow-list), and all of them at once — **eight probes, eight
  200s**, table intact at 4 rows afterwards.
- 375 / 768 / 1024: no sideways body scroll at any width; columns reveal as
  Site → +Code +Address → +Added, and at 375 the code rides under the name rather than
  vanishing.
- Light and dark: background `oklch(1 0 0)` → `oklch(0.145 0 0)`, muted foreground
  `0.556` → `0.708` — lighter in dark, which is the right direction.
- en / ms / zh_Hans: tab title Sites / Tapak / 站点, nav group Stock / Stok / 库存.
- Console clean. The four 405s in the transcript are the harness re-issuing PUT after
  the locale redirect, one per switch — not the app.

#### Open, carried forward

- **No delete guard yet.** A site that still owns warehouses must not go, but the
  `warehouses` table does not exist yet and a guard against nothing is a guard nobody
  can trust. It lands with warehouses, next.
- **Demo data is hand-made again.** Three sites were typed into the browser to have
  something to attach warehouses to. This is the fourth module in a row where that has
  happened; a seeder is still the outstanding ask.
- No filters on the list — nothing to filter by yet with five columns and no status.

### Warehouses — and the guard sites have been waiting for

A warehouse is a building that belongs to a site, and it is the level everything
stocked is addressed at: a movement moves through a warehouse, never through a site.
The table still holds no quantities — those arrive with `warehouse_stocks`.

**What was deliberately left out.** v1's `WarehouseController` has a `show` screen, and
it is a stock report: a two-leg `UNION ALL` over `products` and `raw_materials`,
left-joined to on-hand quantities and reorder levels, with `selectRaw` throughout.
Every table it reads arrives with StockService, so the screen arrives then rather than
as an empty frame now. `WarehouseData` leaves out `items_in_stock` / `low_stock` /
`out_of_stock` for the same reason — three numbers on every row that are wrong is worse
than three that are absent.

| Piece | Note |
|---|---|
| `create_warehouses_table` | `location_id` **restrictOnDelete**, name, `code` nullable+unique, address, soft deletes |
| `Warehouse` | `location()` is `withTrashed`, so a row can always name its site |
| `Location::warehouses()` | New — read by the guard *and* the list, hence eager-loaded |
| `WarehouseRequest` + `warehouse.ts` | A **factory** schema, like `productSchema`: the browser can only refuse an unknown site if told which exist |
| `WarehouseController` | Site filter (multi, ANY) reusing `ComboboxFilter` — its second consumer, and the hint's |

**The site delete guard, finally.** `warehouses.location_id` is NOT NULL and restricted,
so the database refuses a *hard* delete outright. A soft delete slips straight past
that: the row stays, the constraint is never tested, and the warehouses are left
pointing at a site the workspace believes it removed. The controller refuses first,
naming the buildings in the way, with a "View all N warehouses" link into the filtered
list — the same shape as the raw-material guard, and the second use of `ConfirmDialog`'s
blocked `children` slot.

Kept in the controller rather than a model hook, matching raw materials.

#### Verified in a real browser (Playwright, SSR on)

- **Two different nothings.** With no sites at all the empty state says "Add a site
  first" and offers a link out, with **no New warehouse button** — the form's only
  required field would have had no valid answer. Checked by soft-deleting every site,
  loading the page, and restoring exactly the four rows that were live.
- Empty submit: *both* "The site field is required" and "The name field is required",
  and **zero requests** — the gate stopped it.
- Server-only rules, each landing on the right field: duplicate code → `code`; site
  `99999` → `location_id`; a **trashed** site id → `location_id`, which is `ActiveExists`
  doing the thing plain `exists` would not.
- Site filter: `?site=1` → 2 rows, `?site=1,2` → 3 (ANY, widening). `?site=99999`,
  `?site=abc`, `?site[]=1` and `?site=3` (a real site with no warehouse, so off the
  offered list) all fall back to no filter — never an empty list, never a 500.
  `?site=1,1,1` collapses to `1`.
- **The guard, both halves.** Plural: "2 warehouses still stand on this site (Cold room,
  Main store)". Singular after deleting one: "A warehouse still stands on this site:
  Main store." Bypassing the UI with a raw `DELETE` left `deleted_at = NULL`. And
  `forceDelete()` in tinker was refused by the database — the `restrictOnDelete` backstop
  proven rather than assumed.
- 375 / 768 / 1024: no sideways scroll; columns reveal Warehouse → +Code +Site →
  +Address. At 375 the site rides under the name, and once Site has its own column at
  768 the duplicate is gone.
- en / ms / zh_Hans: tab title Warehouses / Gudang / 仓库, and the site link's aria-label
  translated in each.
- Console clean, `laravel.log` gained nothing.

#### Open, carried forward

- **No warehouse delete guard.** What makes a warehouse undeletable is stock sitting in
  it, and there is nowhere to put stock yet. It lands with `warehouse_stocks`, on the
  same reasoning that kept sites unguarded until this module.
- `check-validation-parity.ts` needed a `FACTORY_ARGS` entry for `WarehouseRequest`. That
  is the third factory schema; the list is still hand-maintained, and a missing entry
  fails loudly rather than silently, so it stays as is.
- Codes are unique workspace-wide, not per site. Deliberate — a code exists to be
  written on a transfer note, and one that only means something once you also know the
  site is not a code — but worth revisiting if anyone wants `A` in every warehouse.
- Demo data hand-made again: four warehouses typed in, one deleted. Fifth module running.

### StockService — the first code that can corrupt data

Everything before this was shape: tables with names on them. This is the first module
where a bug does not look wrong, it *is* wrong — and stays wrong. `StockService` is the
only writer of `warehouse_stocks` and `stock_movements`, and every public method locks
the (warehouse, item) row, applies a signed delta, refuses to go below zero, and appends
exactly one ledger row inside one transaction.

**`quantity` is signed; there is no direction column.** Positive is in, negative is out.
One column means a total is `SUM(quantity)` and never a `CASE`, and it makes "in" and
"out" impossible to disagree with each other.

#### Three deliberate departures from v1

**1. Decimal strings, not floats.** v1 works in `float`. It is *nearly* safe, and it is
worth being precise about why rather than waving at "floats are inexact": every value
round-trips through a `decimal(15,4)` column, which scrubs the representation error on
each write, and two equal decimals parse to two equal doubles — so the `< 0` check
cannot misfire, which I checked before claiming otherwise. What is not safe is
accumulation. On-hand is not bounded by the per-movement maximum, and past what a double
carries, PHP's `precision=14` string conversion drops the tail:

```
(string) ((float) '99999999999' + (float) '0.0001')  ===  '99999999999'
```

The ledger would record the `0.0001` and on-hand would not move — the two tables
drifting apart, which is the one thing the class exists to prevent. Unlikely, silent,
and free to remove. `bcadd`/`bccomp` at scale 4 cannot do it, and the rest of v2 already
treats decimals as strings. `ext-bcmath` is now declared in `composer.json`.

**2. Locks taken in a fixed order.** v1's `transfer()` locks the source then the
destination, so A→B and B→A running together each hold what the other needs. v2 locks
both rows up front, ordered by warehouse id. **Measured, not assumed** — with the
ordering removed, 7 of 12 processes deadlocked; with it, 0 of 12.

**3. Nothing is fillable.** Both models are fully guarded and the service uses
`forceCreate` / `forceFill`. Mass-assignment protection guards a request array reaching
a model, and there is no request array in that file — a fillable list would only make
the ledger writable by something that has no business writing it. (v1's reason enum also
carried an English `label()`; here the words live in `lang/`, which is what
`check:i18n` exists to enforce.)

### `stock:hammer` — the answer to "no tests" for the one class that needs one

Browser driving is this project's safety net and it works because a person clicks one
button at a time. That is exactly what cannot check a lock. The failures possible here
are races: two issues of the last unit both succeeding, a ledger that no longer sums to
on-hand, two transfers deadlocking. None is reachable by clicking.

So: a tool, not a suite. Nothing runs it in CI, it is invoked by hand, and it reports
what it saw. The parent sets up a scenario and spawns N real `artisan` child processes —
a loop in one process shares a connection and never contends.

| Command | What it asks | Result |
|---|---|---|
| `stock:hammer` | 20 processes each take 1 unit from a shelf of 10 | **10 took, 10 refused, 0 errored, shelf empty** |
| `stock:hammer --deadlock` | 12 processes transfer A→B and B→A at once | **12 completed, 0 deadlocked, 10000 units still there** |
| `stock:hammer --ledger` | 16 processes make mixed movements, then reconcile | **321 movements; on-hand 1000.0000 = ledger 1000.0000** |

The deadlock run was checked against a deliberately broken build first, so a pass means
something — the same discipline as breaking `DateCell` to prove the hydration detector
fires.

### A demo seeder, at last

`DemoCatalogSeeder` — a furniture maker: 3 categories, 3 suppliers, 6 materials, 4
products, 2 sites, 3 warehouses. **Not part of provisioning**; a real workspace does not
want sample products. Run it by hand:

```
php artisan tenants:seed --class=DemoCatalogSeeder
```

**Additive and idempotent — it truncates nothing.** Every row is `firstOrCreate`d on the
column already unique for it, so it is safe to run against the workspace you are
currently looking at. Verified: two consecutive runs left all seven counts identical,
and it matched the existing `PEN` site rather than duplicating it.

Fixed data, not faked: a different catalog every run makes "did that change because of
my code or the seed?" a question you have to stop and answer. Sizes are chosen for what
they exercise — a product with no bill, one with a single line (so singular strings have
something to render), one with four; a material used by nothing (so the filter must not
offer it); quantities at the column's full scale.

#### Open, carried forward

- **No UI yet.** The engine has no screen — movements are next. Nothing in the app calls
  `StockService` today except `stock:hammer`.
- `setLevel()` takes a reason so a stock take and an adjustment can be told apart in the
  ledger; only `Adjustment` and `StockTake` are reachable until phase 5.
- `StockMovement::stockable()` is a plain `morphTo()`. A movement whose product has been
  soft-deleted will not resolve it, so the movements list will need `withTrashed` per
  type when it is built.
- The reliance on REPEATABLE READ for the gap lock is documented in the migration but
  not enforced anywhere. If the isolation level is ever lowered, two concurrent
  first-movements for the same item could both insert; the unique index turns that into
  an error rather than corruption, but it would be an ugly one.
- `laravel.log` carries one stale `ERROR` from the first hammer run, before the
  mass-assignment fix. Left in place rather than truncated.

### Stock movements — the first screen that calls the engine

List and create, and no edit or delete anywhere in the module. That is not an omission:
the ledger is append-only, a mistake is corrected by recording the opposite movement,
and an editable ledger is a ledger nobody can rely on.

**One picker over two tables.** A movement is recorded against a product *or* a raw
material. Two form fields — a type and an id — would mean two error keys for one
control, and whichever failed, the picker could only underline itself once. So the
value is a single string, `product:5`, and `App\Support\StockItem` is the only place
that knows the shape. `decode()` is the validation as well as the parser: it returns
null for a wrong shape, an unknown type, a missing id **or a soft-deleted row** — that
last one being why it queries through the model rather than trusting the id, which is
what `ActiveExists` does for an ordinary foreign key and cannot do here, because the
table to look in is part of the value.

**Every field on the wire is a value, not a sentence.** v1's `StockMovementData` shipped
`"KL HQ · Main Store"`, `"Oak board · Raw material"` and an English `reason` label —
three strings assembled on a server that cannot see the screen, in a language it had to
pick. Here the site and the warehouse arrive separately, and the type and reason arrive
as enum values the browser looks up. Same for the warehouse picker: `WarehouseOptionData`
carries `name` and `site` rather than a joined label.

**The type is a segmented control.** Three options, the first decision, and two of them
opposites — a dropdown that has to be opened before it will admit it offers "out" is a
worse way to say that. It also changes what the box below it means, so the schema is
rebuilt when it changes: `in` and `out` refuse zero, because moving nothing appends a
row to an append-only ledger saying nothing happened; `set` allows it, because "the
shelf is empty" is a real correction. The server draws the identical line.

| Piece | Note |
|---|---|
| `StockItem` + `StockItemType` | The merged picker value, and the enum whose cases are the morph-map keys |
| `StockMovementData` | Structured; `quantity` a signed decimal string |
| `BuildsStockPickers` | Warehouses by site-then-name, items grouped products-then-materials |
| `StockPickerField` | Two-line rows and group headings — `ComboboxField` can express neither |
| `QuantityCell` | Explicit `+`, `destructive` only for outward, `tabular-nums` |
| `decimal()` gained `gte` | For the one case where the bound itself is a legitimate answer |

Two things the driving found and fixed. **Trailing zeros**: `decimal(15,4)` returns
`40.5000`, so every ledger row read `+40.5000` — four digits of noise saying only how
the column was declared. `Decimals::trim()` was promoted out of `BomItemData` on its
second consumer (ARCHITECTURE's rule of three allows the second when the logic is
non-trivial, and the `.` guard that stops `10` becoming `1` qualifies), and the refusal
message trims too — "Only 18 available" rather than "Only 18.0000 available". **The
warehouse vanished on a phone**: below `md` the row said what moved and how much but not
where, which is half a record. It now rides under the item name, the same trick the
other lists use.

#### Verified in a real browser (Playwright, SSR on)

- Empty submit: all three required errors, and **zero requests** — the gate held.
- The refusal path, which is the whole point of the engine: taking 99 of 18 came back on
  the **quantity field** as "Only 18 available, and this would take 99." Not a toast —
  that field is the one that is wrong.
- `out 0` refused ("must be greater than 0"), `set 0` accepted, `1.23456` refused for
  scale. The type genuinely changes the bound, on both sides.
- **The invariant, on data made entirely through the UI**: every one of the six
  `warehouse_stocks` rows equals the sum of its own ledger lines. The `set to 0` case is
  visible in the list as a `-40.5` — the service showing its working.
- Picker: two "Raw material store" entries on different sites, distinguishable because
  the rows are two lines; 19 items under Products / Raw materials headings; searching
  `RM-OAK` finds the one material by SKU.
- Search hits item name, SKU, warehouse name and notes — and **not** the reason, which
  is a stored code like `transfer_out`. Matching English against it would work in one
  locale and silently not in the other two; the reason filter is the control for that.
- Hostile URLs: `?warehouse=99999`, `?warehouse[]=6`, `?reason=nonsense` all fall back
  to no filter. `?warehouse=6,7` widens 5 → 9.
- 375 / 768 / 1024: no sideways scroll; the phone line appears below `md` and is gone
  above it rather than doubled.
- en / ms / zh_Hans: title, reason badge (Adjustment / Pelarasan / 手动调整) and item type
  (Product / Produk / 产品) all from `lang/`, in the server-rendered HTML.
- Console clean.

#### Open, carried forward

- **No on-hand shown while recording.** The dialog does not say what is currently in the
  chosen warehouse, so "take 6" is typed blind and the refusal is the first feedback.
  The lang file already carries `field.on_hand`; it needs a small endpoint or a prop,
  and it is the first thing to add.
- **`check:i18n` mis-parsed the FormRequest.** An inline `$this->input('type') === 'set'`
  inside the rules literal was read as two rules named `type` and `set`. The condition
  moved to a variable above the array — a gate that can be confused by the code it
  checks is a gate that gets ignored — but the parser is still text-based and the next
  conditional rule will need the same care.
- `StockPickerField` is module-local. Transfers and stock takes are its second and third
  consumers, and that is when it moves to `components/form/`.
- No barcode scanning into the picker; v1 has `matchStockItem()` for it. Phase 8.
- The preset has no `success` token, so only the outward direction is coloured. Worth
  revisiting if a green is ever added.

### On-hand while you type — and the bug it uncovered

Raised after using the movement form: you pick a warehouse and an item, ask to take six,
and the refusal is the first thing that tells you six was never possible. The dialog now
shows what is there, under the quantity box.

**Fetched, not shipped with the page.** Every on-hand row could travel as a prop, and for
a small workspace that is a few dozen. But the number changes whenever anybody else
records anything, and a figure baked in at page load goes quietly stale while a dialog
sits open. `GET /{tenant}/stock/on-hand?warehouse_id=&item=` returns JSON — the first
non-Inertia endpoint in the app, which is why it is its own controller rather than a
method among ones that all render or redirect.

**It never disables anything.** The lookup takes no lock, so the figure is out of date
the moment it arrives; the refusal at submit time is the guarantee. Showing "3" must not
stop somebody submitting 4 that a colleague's delivery has just made possible.

An `AbortController` per request, because choosing a second item before the first answer
lands would otherwise leave the earlier number on screen under the later item — a wrong
figure that looks exactly like a right one.

Gated on `stock-movements.view` through `ROUTE_OVERRIDES`, not left open to any signed-in
user: it returns a stock level. When transfers and stock takes call it too, that mapping
needs to become "any stock screen's view permission" rather than one of them.

#### The bug: `exists` accepts an array

Probing the new endpoint with `?warehouse_id[]=7` returned a 404 with a stack trace,
which was the thread worth pulling. Measured, not guessed:

```
exists rule with ['7']                     → PASSES
$request->integer('warehouse_id') of ['7'] → 1
```

So a request with `warehouse_id[]=7` is **validated, accepted, and applied to row 1**.
On the lookup that is a wrong answer. On `POST /stock-movements` it is a movement
recorded against a warehouse nobody named — no error, no log, and a ledger that is
simply wrong. Six FormRequests had the shape: both stock requests, `WarehouseRequest`,
`ProductRequest` (twice) and `BomRequest`.

This is the same shape as the `?search[]=x` bug that 500'd every list in `96f9892` — a
parameter arriving as an array where a scalar was assumed. That one crashed, which is
how it was found. This one corrupts, which is why it lives in one helper now rather than
six rule arrays that each have to remember:

```php
protected function foreignKey(string $table): array
{
    return ['integer', ActiveExists::of($table)];
}
```

#### And the gate that could not see it

Adding the helper made `check:i18n` report a rule called `locations` — it reads
`rules()` as text, and `$this->foreignKey('locations')` looked like a quoted rule name.
It already handled `ActiveExists::of(…)`; it had no notion of `$this->` builders at all.

Fixing that properly turned up something worse: **`decimalRules()` has expanded to
`numeric` and `decimal` since products part C, and this check has never once looked at
them.** The builder map now lists both helpers with every rule they produce, and the
argument-stripping handles instance calls as well as static ones. The first thing the
repaired gate found was that `integer` had no translated message — so until now it would
have rendered in English inside Malay and Chinese.

#### Verified in a real browser (Playwright, SSR on)

- Nothing chosen, or only a warehouse: the line is absent, not a placeholder for an
  answer nobody asked for.
- Both chosen: **"On hand now: 18 pcs"**, matching the ledger. Changing the item updates
  it; an item never stocked there reads **"On hand now: 0 pcs"**.
- The endpoint: a real pair 200s; unknown warehouse, unknown item, malformed item and
  both-missing each 422 on the right field.
- **The hole, closed on all four paths**: `?warehouse_id[]=7`, `POST` with
  `warehouse_id[]`, `location_id[]` and `category_id[]` are now *"…must be an integer"*
  — and the database confirms no product, warehouse or movement was created by any of
  them. The scalar forms still work.

#### Open, carried forward

- `lang/units.php` is read for the symbol, so the line says "18 pcs". The unit is the
  item's own, which is right for now — multi-UOM would make it a question.
- The lookup is one request per choice. Fine at this size; a workspace where somebody
  scrolls a picker of thousands would want it debounced.
- No barcode scan into the picker yet — v1 has `matchStockItem()`. Phase 8.

### Column preferences — the reader picks the columns ✅

Prompted by a question about the notes field, which turned out to name a real defect: the
stock-movements search box matches on `notes` and advertises that it does, but there was no
notes column — **so a row could match on text that was nowhere on screen.** Notes had been
left out because 1000 characters of prose beside five columns of numbers crushes them, and
that reasoning is right about the *default* and wrong as a permanent verdict.

The fix is the general one. `DataTable` now carries a **Columns** panel, so all ten lists
get it with no per-page wiring.

- **A column is configurable if and only if it has `meta.label`.** Everything else is an
  *anchor*: always rendered, fixed at its declared index. The nine `actions` columns needed
  no edits at all — a `srOnly` header and no label is exactly what pins them to the end.
  Anchors are re-inserted at their declared index rather than pushed to the back, so a
  future select-checkbox column at the *front* works without a second rule.
- **`heading()` sets the header and the label from one key.** The name used to be trapped
  inside `header: () => <ColumnHeader label="…" />` — a closure no menu can read — and the
  alternative was writing the key twice with nothing keeping the copies equal. Converting
  45 columns across ten pages was a net line reduction.
- **`hideBelow` and the reader's choice compose as AND, and the panel says so.** Responsive
  hiding is untouched; a ticked column still obeys its breakpoint, and its row reads "Also
  hidden on narrow screens". A column meant to be opt-in — notes — declares no `hideBelow`,
  so ticking it shows it at 375 too.
- **Two guards, each stating its reason** rather than leaving a checkbox that silently
  refuses: the sorted column cannot be hidden (an `ORDER BY` in force with no header and no
  arrow is not a state worth reaching), and neither can the last one standing.
- **No drag library.** Native HTML5 drag on the row plus up/down buttons — see
  `docs/PACKAGE-POLICY.md` for the survey that found `@dnd-kit/core` 21 months stale and
  its successor still pre-1.0. The buttons are the only path that works on touch.

**The bug this nearly shipped with.** Registering `columnVisibilityFeature` makes the
header row respect visibility immediately; the body kept calling `row.getAllCells()`, which
does not. Driven deliberately to see it: hiding the SKU column left 5 headers over 6 cells,
with Category showing the SKU, Supplier showing the category and Actions showing the date —
every value plausible, every value one column left of its heading, and nothing thrown. One
line, `getVisibleCells()`, and the same probe then paired every column correctly.

Also fixed: `ListToolbar` keyed its right-hand cluster off `extra` alone, so on the lists
that pass no filters at all (categories, warehouses) the Columns button would never have
rendered.

#### Open, carried forward

- ~~**Nothing is persisted.**~~ Done — see *The layout is remembered* below. Two claims
  made here were wrong and are corrected there: the seam was **not** ready (`DataTable`
  had no `defaultLayout`/`onLayoutChange` props; `defaultLayout` was an imported function
  and the state was component-owned), and `locale` is **not** a both-tables precedent —
  the central `users` table has no `locale` column at all.
- **The last-column guard cannot currently fire**, and is kept deliberately. The sorted
  column can never be hidden, so it is always the survivor — meaning `showing === 1` is
  always already caught by the sort guard. That holds only while every controller sorts by
  a column its page renders, true of all ten today but not structurally guaranteed.
- Moving a column past a *hidden* one changes the panel and not the table. The panel is the
  ordered list of every column, so the row visibly moves; it is the honest reading, but it
  is the one place the feedback is indirect.
- `data-table.tsx` is now 506 lines, well past the ~250 signal. The header-row block is the
  obvious extraction; kept out of this change so the diff stays readable.

### The layout is remembered ✅

The Columns panel landed without persistence, deliberately, until the UI was settled. It
was, so: **tick Notes, refresh, it is still there.**

Stored per user in a `table_columns` json column, on **both** `users` tables. A dedicated
column rather than a general `preferences` bag, following `locale` directly beside it —
this app's precedent for a per-user preference is one column per preference, and a bag
would be guessing at a shape nothing needs yet.

- **`App\Enums\TableKey`** is what makes it type-safe end to end. Column ids are only
  unique *within* a screen — `name`, `created_at` and `actions` each appear on several — so
  a layout has to be keyed by its list, and an open-ended key would let a signed-in user
  grow their own row without limit. The server validates with `Rule::enum`; `#[TypeScript]`
  types `DataTable`'s new `tableKey` prop, so a typo on a page is a tsc error rather than a
  preference that silently never loads. Adding the prop turned up all ten call sites at
  once, which is the point.
- **Two routes, one controller.** A workspace cannot post to the central route: the session
  driver is `database` and tenancy switches the connection, so it would find no session and
  CSRF would answer 419. `LanguageSwitcher` already carries this split. One `__invoke`
  serves both, because `Authenticate` calls `shouldUse()` on the guard that passes — inside
  `auth:central`, `$request->user()` already *is* the `CentralUser`.
- **Validated inline, no FormRequest and no zod schema** — the call `LocaleController`
  already makes, for the same reason: a background save is not a form and has no field for
  an error to land under. `required_with` is deliberately avoided: it has **no translated
  message**, and `check:i18n` only reads `app/Http/Requests`, so an inline rule that fired
  would have spoken English in all three locales with nothing reporting it.
- **`App\Support\TableColumns`** is the read guard, shaped like `TimeZones::resolve()`.
  Nothing stored reaches a prop unchecked — driven with a deliberately hostile row
  (unknown table key, an integer id, a 200-character id, `hidden` as a string) and every
  bad part was dropped while the page rendered normally.
- **No `TenantPermissions` mapping, deliberately.** An unmapped route stays open to any
  signed-in user, which is right for a preference about the reader rather than a resource.
- Saving is debounced 500 ms: six changes in a row produced **one** request, measured.
- **Saved with `fetch` against a 204, not through Inertia's router**, and a failure is
  visible. The first cut used `router.put` for the CSRF handling it gives away free, and
  the cost was that a background save joined the page lifecycle. Driven with the endpoint
  forced to 500: the column stayed on screen looking saved, no toast appeared, and what the
  reader *did* get was Inertia's full-screen error overlay — which fires for an expired
  session too, the likeliest real case, and says nothing about columns. A dropped
  connection was silent, and so was a 422. Now every failure shape lands in one `catch` and
  becomes one sentence.
- **A failed save keeps the change and says so.** Reverting is the other honest option and
  the worse one: the arrangement still works for this session, which is what was asked for,
  and taking it back over an unrelated server fault punishes the reader twice. The toast
  carries a stable id, so five failed saves in a row produced exactly **one** toast.

**The seed is a server prop, and that is the whole SSR story.** The table builds its header
row from it during render, so both sides start from the same value. Verified the hard way
rather than by inspection — re-fetching the page from inside itself and reading the raw
HTML shows `Notes` already in `<thead>`, so the column is server-rendered rather than
added a frame after hydration.

#### What happens when the columns change later

Asked directly, and worth recording. A stored layout is a list of id *strings*, reconciled
on every render against what the page currently declares, so the failure mode is always
"fall back to sensible" rather than a crash. Driven, not assumed:

| Change | Result |
|---|---|
| Column removed | Dropped from `order`; the rest keep their positions, cells stay aligned. A stale id in `hidden` is ignored and cleared on the next save. |
| Column added | Appears **between its declared neighbours**. |
| Column id renamed | Remove plus add. |
| List retired | `TableColumns::forUser` drops entries whose key is no longer a `TableKey`. |

That second row is a change made *because* of the question. `toColumnOrder()` used to
append an unknown id to the end, which meant one deploy showed a new column in two
different places — its declared position for anyone who had never opened the panel, and
last for everyone who had. Proven by putting the old behaviour back: with a customised
layout saved, a column declared between `warehouse` and `reason` landed at index 6 instead
of 4. It now inserts after the nearest earlier column the reader still has.

#### Reset every list, from Settings → Appearance ✅

The panel's own Reset only knows the list it is open on, so starting over meant visiting
ten screens. Settings → Appearance now carries a **Table columns** section that clears
them all.

- **The count is free.** `tableColumns` already rides on every page so the table can seed
  itself, and only lists somebody actually changed are ever stored — so its size *is* the
  number of customised lists. No new query, no new prop.
- It states the count, disables itself at zero (the sentence above already says why, so
  the button needs no second copy), and asks before acting: one press can undo work across
  ten screens, which is more than a settings toggle should do quietly. No typed phrase —
  nothing here is irreversible, and asking that hard would only teach people to click through.
- `DELETE {tenant}/table-columns`, beside the PUT. Unlike the save this **is** a
  navigation — somebody pressed a button and is waiting — so it redirects with a flash
  toast rather than answering 204.
- **Tenant settings only.** `/admin` has no settings area at all, and building one for a
  single button is not the trade; a super-admin has two lists and each has its own Reset.
- The page used to say *"Choose how the app looks on this device"*, which stopped being
  true the moment a per-account setting landed on it. The page description is now
  scope-neutral and each section states its own reach: the theme is written to this
  browser, a column layout follows the account everywhere.

#### Open, carried forward

- **`csrfToken()` now lives in `lib/csrf.ts`.** It had been left in the hook that uses it,
  on the note that it would move on its second consumer — which had the rule wrong.
  Rule-of-three governs *components*; `lib/` is defined by kind, "pure functions, never
  imports React". A pure function inside a React hook was the wrong home on day one, not
  on its second caller. It gained a `typeof document` guard on the way, since it now sits
  where something could reasonably call it at module scope, and the docblock records why
  `decodeURIComponent` is load-bearing: the token is base64 and routinely carries `=` and
  `+`, so sending it raw is a 419 with no explanation.
- **Existing workspaces need `php artisan tenants:migrate`.** New ones are migrated on
  provision; the demo tenant was migrated by hand.
- Per-user isolation was verified across two *different* user records — `owner@demo.test`
  and the `CentralUser` `admin@example.com`, which also exercises the second migration —
  rather than by signing in as a second tenant user, whose password is in no seeder and was
  not worth changing for a test.
- After heavy drift the reconciled order can read oddly, since each recovered column lands
  after its declared predecessor. Only reachable from a corrupted row, and every column is
  still present and aligned; Reset fixes it.
- ~~`data-table.tsx` wants its header-row block extracted.~~ Done: the header cell is now
  `components/data/column-head.tsx`, taking the file from 516 to 448 lines. It uses the
  standalone `FlexRender` export rather than the `table.FlexRender` bound to an instance,
  which is what lets it live away from the table that owns one. The three-way empty-state
  block followed into `components/data/list-empty.tsx`, taking it to 386 — and taking `t`,
  `Button`, `EmptyState` and `SearchX` out of `data-table.tsx` entirely, which is the sign
  the seam was in the right place. `ListEmpty` derives `narrowed` and `searching` from
  `search` and `filter.count` rather than being handed them, so there is no second copy of
  a reading the table also makes. Still above the ~250 signal, and now mostly docblock and
  the table options; no obvious third extraction, so leaving it.

## Phase 4 · Stock — transfers ✅

Stock moving between two warehouses. The first screen to drive `StockService::transfer()`,
whose lock ordering `stock:hammer --deadlock` proved but which had no caller until now.

**A transfer is a document, and the ledger cannot be one.** It writes two movements — one
negative at the source, one positive at the destination — and each knows only its own
warehouse. Nothing in `stock_movements` says the two belong together, so "what was moved,
and where to" is a question the ledger cannot answer. `stock_transfers` is that answer. It
is not a duplicate of the pair: they say what happened to each warehouse, it says what
somebody did.

- **`RecordStockTransfer`, an Action, not a fourth method on StockService.** The service's
  invariant is that `warehouse_stocks` and `stock_movements` never disagree, and the
  document is no part of it. Keeping it out also leaves `transfer()` byte-for-byte as the
  hammer proved it. The Action's outer transaction is what makes document and movements
  atomic — `transfer()` opens its own, which Laravel turns into a savepoint.
- **`quantity` is an unsigned magnitude here**, unlike the ledger's signed column: a
  document has no direction to encode because `from` and `to` are already columns.
- **The `different` rule needed a translation and a primitive.** `different` had no message
  in `lang/*/validation.php`, so it would have rendered in English in all three locales —
  `check:i18n` caught it, which is the gate doing exactly its job. `:other` is itself a
  field name, so `ValidationMessage` gained an `others` map: params that are translated
  before interpolation, the same reason `attribute` is separate. The browser now says "The
  destination warehouse and source warehouse must be different" in the reader's language,
  with both names translated.
- **The on-hand lookup's gate became any-of.** A note left on `stock.on-hand` said it would
  need to when a second screen called it; that happened, so `ROUTE_OVERRIDES` values may
  now be a list and `AuthorizeTenantRoute` uses `canAny`. Gating it on movements alone
  would have 403'd somebody who may read transfers but not the ledger.
- **`StockPickerField` and `OnHandLine` were promoted** from stock movements'
  `_components/` to `components/form/` — the rule of three's "second, if the logic is
  non-trivial". Stock takes will be the third.

Two things v1 got wrong that are fixed rather than ported:

- **Search now covers a warehouse's own name.** v1 matched code and site only while its
  docblock claimed otherwise, so looking for a warehouse by the name on the door quietly
  returned nothing. Driven: searching "Finished goods" finds its transfers.
- **Notes are visible.** v1 stored and searched them and displayed them nowhere — the same
  defect the ledger had. Here they are a `defaultHidden` column, one tick away in the
  Columns panel.

Added beyond v1: a warehouse filter matching **either end**, since a transfer involves a
warehouse whether stock left it or arrived at it, and the ledger already answers the
one-directional question.

#### Verified in the browser

- One transfer of 3 → **1 document, 2 ledger rows summing to zero**, on-hand 120 → 117 at
  the source and 0 → 3 at the destination.
- 9999 against 117 available → refused on the quantity with "Only 117 available at the
  source, and this would move 9999", and **nothing left behind**: still 1 document, still 2
  movements, both on-hand figures unchanged.
- Same warehouse at both ends → refused in the browser with **zero requests sent**.
- Search by warehouse name; filter by source-only and destination-only warehouses both
  return the rows, an uninvolved warehouse returns none.
- 375px: From and To drop out and the route rides under the item name with an arrow; the
  table scrolls inside its card and the body does not.
- en / ms / zh_Hans across list, dialog and validation messages. SSR on, console clean.

#### Both ends show what they hold

The first cut showed the on-hand for the **source only**, on the reasoning that it is the
only end that can refuse a transfer. Reported from use, and the reasoning was wrong about
what the reader is doing: deciding *how much* to move is a judgement about both sides —
you are usually levelling two shelves, and the destination's number is half of that.

Each line now sits under the picker that names it, so neither has to say which warehouse
it means, and the quantity box follows both. Still advisory: the numbers are read without
a lock and the real check happens under one, which is why nothing here disables anything.

The move also fixed a smell it exposed. `OnHandLine` had been promoted to
`components/form/` but still read `stock-movements.field.on_hand` — a shared component
reaching into one module's strings, which made transfers' copy depend on a file it has no
reason to open. The key is now `common.field.on_hand`, and the dead
`on_hand_unknown` beside it (no consumer since the component was written) is gone.

#### Open, carried forward

- **`stock:hammer` writes transfer movements with no transfer document.** It calls
  `StockService::transfer()` directly rather than {@see RecordStockTransfer}, which is
  correct — it is measuring the service's lock ordering, and adding a third table to the
  contention would change what is being measured. The consequence is that a workspace the
  hammer has run against has `transfer_in`/`transfer_out` rows in the ledger with nothing
  in `stock_transfers` to pair them. Fine for a dev fixture; worth knowing before reading
  the demo workspace's numbers.
- The two prerequisite empty states — fewer than two warehouses, and an empty catalogue —
  were **not driven**: the demo workspace has six warehouses and a full catalogue, and
  manufacturing the states would mean destroying data. The ordinary empty state was.
- No deep link from a warehouse into a prefilled transfer. v1 has one (`?from=<id>` opens
  the dialog); it needs a button on the warehouse screen to be worth adding.
- No barcode scan into the picker — Phase 8, along with movements.
- Notes are written to all three rows, as in v1: the document and both movements.

### The reason badges got colours, and the preset got a palette ✅

Reported from use: every reason badge was grey. It was — `variant="secondary"` for all
ten — but the cause was upstream. **The preset had no colours to give them.** Only two
hues exist in it, the brand blue at 264° and destructive red at 27°; everything else,
including `--chart-1..5`, was `oklch(… 0 0)` — zero chroma, five greys, identical in light
and dark. That is a shadcn `baseColor: neutral` default nobody customised rather than a
design decision, and it would have made Phase 6's charts unusable too.

So the tokens were filled in rather than a second palette invented beside them: five hues
anchored on the brand blue and spaced around the wheel, at one lightness and chroma so no
series shouts louder than another.

**Coloured by family, not by reason.** Ten colours is not a legend anyone learns, and the
ten are not ten unrelated things — the enum already groups them in pairs, and the pairs
are what a person thinks in: bought, sold, made, moved, or counted by hand. Five is
learnable. The two halves of a pair share a hue because the quantity column's sign already
says which direction, and colouring that twice spends a second channel on one fact.

The map is a `Record` over the whole enum, so adding a reason is a compile error until
somebody says which family it joins — rather than a badge that silently falls back to grey.

A tint with coloured text, not a solid fill: twenty-five saturated badges down a page stop
being labels and become the page. And the hue is never the only channel — the badge says
the reason in words, in the reader's language.

#### Two corrections along the way

- The manual family (adjustment, stock take) was first given a near-neutral slate, on the
  reasoning that it is the ordinary case and the ones tied to money should stand out. On
  screen it read as exactly the grey it replaced, which was half the original complaint. It
  is a real teal now.
- **Light mode failed WCAG AA at first.** Measured rather than eyeballed, by resolving each
  token through a canvas and compositing text over the 10% tint over the card: green 4.34,
  amber 4.19 and teal 4.29 against a 4.5:1 bar for 12px text. Those three came down in
  lightness until all five cleared it. Final: **light ≥ 5.38:1, dark ≥ 5.87:1**, five
  distinct hues in both, cross-checked against the real rendered badges (5.55 and 5.37).

### The reason filter takes several ✅

Reported from use: the warehouse filter beside it took several and the reason filter took
one, so "everything except transfers" was not a question the screen could be asked.

It filled the quadrant the filter kit was missing. `SelectFilter` translates its choices
and takes one; `ComboboxFilter` takes several but searches the workspace's own rows. A
movement reason is neither — ten values at most, named in `lang/`, and only the ones a
workspace has actually used are ever offered, so a search box would be furniture over four
items. **`CheckboxFilter`** is the fourth corner: several at once, over a short translated
list, no search.

|            | one            | several            |
|------------|----------------|--------------------|
| translated | `SelectFilter` | **`CheckboxFilter`** |
| workspace  | —              | `ComboboxFilter`   |

- **The state behind both multi-selects is now one hook.** `usePickedValues` holds the
  comma-string ↔ array conversion and the 300ms settle. They differ in how choices are
  shown and not at all in what ticking one means, and two copies would be two filters in
  one panel that felt subtly different.
- The server reads `reason` as a list the same way it reads `warehouse`, and **drops
  values it does not recognise rather than refusing them** — a stale bookmark should
  narrow by what it still understands, not 500.
- Labels are associated by id rather than by wrapping: Radix renders a button, which
  neither a browser nor Biome accepts as a label's implicit control.

#### Verified in the browser

- Two reasons ticked → 322 rows, exactly 18 adjustments + 304 transfer-ins, so **any-of**
  rather than all-of. One ticked → 18, and the trigger names it instead of counting it.
- Three rapid ticks → **one** request. "Any reason" clears the key out of the URL entirely.
- `?reason=not_a_reason` → 200 and no filter; `?reason=adjustment,not_a_reason` → 18, so
  the good half still narrows.
- The refactored `ComboboxFilter` still works: two warehouses → 13, and both filters
  together → 2.
- en / ms with both hint forms — the singular for none ticked, the plural naming the
  count for two. 375px, where the panel is a sheet and every checkbox is reachable.

## Phases 3–8 — Modules ⬜

| Phase | Modules | Status |
|---|---|---|
| 3 · Catalog | **categories ✅ · suppliers ✅ · customers ✅ · raw materials ✅ · products ✅** (core · image · BOM) | ✅ |
| 4 · Stock | **locations ✅ · warehouses ✅ · StockService ✅ · movements ✅ · transfers ✅** (+ notes column, column preferences) · reorder levels, stock takes | 🚧 |
| 5 · Orders | purchase orders, purchase returns, sales orders, sales returns, production orders | ⬜ |
| 6 · Insights | reports, activity log | ⬜ |
| 7 · Team & settings | users, roles/RBAC, business settings, document numbering, e-invoice | ⬜ |
| 8 · Cross-cutting | exports, barcode/QR scanning, tenant dashboard, admin dashboard | ⬜ |
