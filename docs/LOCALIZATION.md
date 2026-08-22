# Localization

**Scope: UI strings only.** Labels, buttons, validation messages, emails, empty states —
the app's own chrome. Tenant *data* (product names, customer names) is stored as typed and
is not translated.

**Locales:** `en` (base + fallback), `ms` (Bahasa Malaysia), `zh_Hans` (简体中文).
Laravel directory names use underscores (`zh_Hans`); the HTML `lang` attribute uses the
BCP-47 form (`zh-Hans`).

**No per-tenant terminology overrides.** One set of translations for everyone. The `t()`
lookup is written so an override layer could slot in later without touching call sites.

## Why not react-i18next

react-i18next's SSR design assumes a Node web server and uses `i18next-http-middleware` to
clone a **per-request** i18n instance — precisely because a shared singleton leaks one
user's language into another's render. Inertia SSR is a long-lived Node process rendering
concurrent requests handed over by Laravel, with no request context and no middleware hook.
Making it safe means hand-rolling per-render cloned instances; getting it wrong is a
cross-request bug that only appears under load.

Laravel already knows the locale. Handing the bundle to a pure lookup makes render a
function of its inputs, with no global mutable state to leak. That is the whole argument.

## How it works

**Laravel `lang/` is the single source of truth** — it already has to serve validation
messages, emails and exports, so the UI reads from the same place. Files are grouped by
domain to match the module structure:

```
lang/en/common.php      lang/ms/common.php      lang/zh_Hans/common.php
lang/en/products.php    lang/ms/products.php    …
lang/en/stock.php       …
lang/en/validation.php  …
```

**Export to a static asset, not a page prop.** A 21-module ERP has on the order of a
thousand strings. Shipping the whole bundle as an Inertia prop would re-send it on *every*
visit. Instead an artisan command flattens `lang/{locale}/*.php` into
`resources/js/lang/{locale}.json`, which Vite code-splits into one lazily-loaded, browser-
cached chunk per locale. Inertia shares only the locale **string**.

This deliberately mirrors the existing `bun run types:generate` pattern: a generated
artifact, reproducible from the PHP source, wired into the build.

**Locale resolution** (first match wins): the signed-in user's preference → the tenant's
default → `config('app.locale')`. A middleware calls `App::setLocale()`, and
`HandleInertiaRequests` shares `locale` so the client picks the same bundle the server
rendered with.

## The two rules that keep SSR working

1. **Locale comes from the server prop — never `navigator.language`.** The app pins
   `NUMBER_LOCALE` today specifically to stop `toLocaleString` from producing a React #418
   hydration mismatch. Once the locale is dynamic, that pin becomes "use the locale the
   server sent". Reading the browser's locale during render reintroduces the exact bug.

2. **No global mutable locale state.** The active locale is a value threaded through props
   and context, not a module-level variable someone calls `setLocale()` on. That is what
   makes concurrent SSR renders safe.

## Validation messages — both layers

Validation is two-layer (a Laravel FormRequest and a matching zod schema), and both produce
user-facing text that must agree. The schemas must not carry English literals.

zod v4 supports customising errors **at parse time**, so the schema itself stays static and
locale-free: the primitives emit a translation **key** plus params, and `runGate()` maps
them through `t()` when it validates. One bundle feeds both layers, the schema stays a pure
value, and nothing is locale-aware at module scope — which keeps SSR safe for the same
reason as above.

## Enforcement

`bun run check:i18n` joins the gates and fails when:

- a non-base locale is **missing a key** that `en` has (an untranslated string would fall
  back silently and ship as English);
- a locale has a key `en` does not (a stale leftover after a rename);
- a `t()` call references a key that does not exist in the base locale.

The export also generates a TypeScript union of valid keys, so `t('products.titl')` is a
`tsc` error rather than a blank string at runtime. With no test suite, the type system and
these gates are what stand in for coverage.

## Writing translatable UI

- **No user-facing literal in a component.** Every label, placeholder, empty state, toast
  and aria-label goes through `t()`.
- **Keys are namespaced by domain**, mirroring the lang files: `products.create_title`,
  `common.actions.save`.
- **Interpolate, never concatenate.** `t('stock.on_hand', { qty })`, not
  `t('stock.on_hand') + qty` — word order differs between English, Malay and Chinese.
- **Pluralise through the helper**, not an `if`. Malay has no plural inflection and Chinese
  has no plural form, so hand-written `n === 1 ? … : …` produces wrong output.
- **Keep domain terms untranslated where users expect them** — SKU, BOM. See the copy
  guidance in `docs/UI-UX-GUIDELINES.md`.
- **Chinese text is denser and Malay longer than English.** Layouts must not assume label
  width; check the sidebar and table headers in all three.
