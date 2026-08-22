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

## Phase 2 — Shared UI kit ⬜

## Phases 3–8 — Modules ⬜

| Phase | Modules | Status |
|---|---|---|
| 3 · Catalog | categories, suppliers, customers, raw materials, products (+BOM, image) | ⬜ |
| 4 · Stock | locations, warehouses, StockService, movements, transfers, reorder levels, stock takes | ⬜ |
| 5 · Orders | purchase orders, purchase returns, sales orders, sales returns, production orders | ⬜ |
| 6 · Insights | reports, activity log | ⬜ |
| 7 · Team & settings | users, roles/RBAC, business settings, document numbering, e-invoice | ⬜ |
| 8 · Cross-cutting | exports, barcode/QR scanning, tenant dashboard, admin dashboard | ⬜ |
