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
looking at a client-rendered page, and four things cause that — all silent, all HTTP 200:

| Cause | Tell |
|---|---|
| The first ~2 s after `bun run dev` | the dev server logs `SSR skipped, module graph is still warming up…`. Wait for `Inertia SSR module graph warmed up`. |
| A stale `public/hot` from a dev server that died uncleanly | `public/hot` exists with no vite running. Laravel POSTs SSR to a dead port and falls back with **nothing in `laravel.log`**. Delete the file. |
| **`bun run build` was run while `bun run dev` was up** | `public/hot` is **gone** but vite is still running. The build deletes the hot file, so Laravel switches to production mode and looks for an SSR bundle that `build` (unlike `build:ssr`) never produced — SSR is skipped with nothing logged, and the dev server looks healthy. Restart `bun run dev`. |
| You are looking at a redirect | e.g. `/admin/login` while signed in is a 302 — view-source shows Laravel's redirect page, not the app. Also: `curl` has no session, so any authenticated URL redirects — check with the browser, not curl. |

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

### The sweep covers every feature, not just the new one

A phase is finished when **the whole app still works**, not when the phase's own screens do.
So the pass at the end of a phase walks every module already migrated, not only the one just
built.

That is not belt-and-braces. The defects this catches are the ones that cannot show up on
the screen being worked on: a shared component gaining a prop, a new key in
`HandleInertiaRequests::share()`, a changed translation key, a renamed route, a column
layout, a `lib/format.ts` helper whose signature moved. Each is invisible where it was
edited and visible three modules away.

**The cheap version is enough for untouched modules:** open the list, open one record,
read the console. Keep the full create → edit → delete pass for the module the phase
actually changed. A sweep that is too expensive to run is a sweep that gets skipped.

**Hydration mismatches are checked throughout the sweep, not only on new screens.** A React
#418 warning is the signal and it names no component, so record which page produced it
before navigating away — it is much harder to find again than to notice. The causes this
repo has actually hit: a date, locale or zone read during render instead of arriving as a
server prop; `Date.now()` or `Math.random()` in render; an unpinned `Intl` call, whose ICU
data differs between the SSR runtime and the browser; and `bun run build` leaving a stale
SSR bundle, because only `build:ssr` builds both.

Report the sweep as what was actually visited and what the console said on each — a list of
modules and their result, not "everything works".

### Narrate it **on the screen** — somebody is watching the browser, not the transcript

The user watches the browser while it is being driven. So the narration goes **into the page
they are looking at**, as an injected overlay — not only into the chat, which they are not
reading at the time. Narrating in the transcript alone was the first attempt and it was the
wrong surface.

Then **keep going. Do not stop and wait for confirmation.** They will interrupt if they see
something wrong; pausing at every checkpoint turns a twelve-module sweep into a dozen round
trips and wastes the thing that makes watching useful.

**Install the overlay once per page load** (a full navigation wipes it — re-run after each
`browser_navigate`), then call `window.__qc(kind, title, detail)` before each step:

```js
// Injected into the live page at runtime ONLY. Never add this to the app's own code.
const KINDS = {
  action: ['#1447E6', 'DOING'],     // what is about to happen
  check:  ['#B45309', 'LOOK FOR'],  // what a pass looks like, BEFORE the screen answers
  pass:   ['#15803D', 'PASS'],      // what was actually observed
  bug:    ['#B91C1C', 'BUG'],       // what is wrong, named on the screen showing it
  note:   ['#475569', 'NOTE'],      // anything else worth pointing at
};

window.__qc = (kind, title, detail) => {
  const [colour, tag] = KINDS[kind] ?? KINDS.note;
  let box = document.getElementById('claude-qc-overlay');

  if (!box) {
    box = document.createElement('div');
    box.id = 'claude-qc-overlay';
    box.style.cssText = [
      'position:fixed', 'top:0', 'left:0', 'right:0', 'z-index:2147483647',
      'font:13px/1.45 ui-sans-serif,system-ui,sans-serif',
      'padding:10px 14px', 'color:#fff', 'display:flex', 'gap:10px',
      'align-items:flex-start', 'box-shadow:0 2px 10px rgba(0,0,0,.25)',
      'pointer-events:none',
    ].join(';');
    document.body.appendChild(box);
    document.documentElement.style.scrollPaddingTop = '52px';
  }

  box.style.background = colour;
  box.innerHTML = '';

  const badge = document.createElement('strong');
  badge.textContent = tag;
  badge.style.cssText =
    'flex:none;font-size:10px;letter-spacing:.08em;background:rgba(255,255,255,.22);' +
    'padding:3px 7px;border-radius:3px;margin-top:1px';

  const body = document.createElement('div');
  const head = document.createElement('div');
  head.textContent = title;
  head.style.fontWeight = '600';
  body.appendChild(head);

  if (detail) {
    const sub = document.createElement('div');
    sub.textContent = detail;
    sub.style.cssText = 'opacity:.92;margin-top:2px';
    body.appendChild(sub);
  }

  box.append(badge, body);
  return `${tag}: ${title}`;
};
```

`pointer-events:none` so the bar can never intercept a click meant for the app, and a top bar
rather than a bottom one because debugbar already owns the bottom edge.

**What goes in it**, and these are exactly what a watcher cannot get from the screen alone:

- **`check` — what to look for, before the screen answers.** *"The weekday headers should read
  日 一 二 三 四 五 六 and the caption `2026年9月`."* Someone who knows what a pass looks like can
  catch a wrong result the driver has already called correct. That is the most valuable thing a
  second pair of eyes does here, and it only works if the expected answer arrives **first**.
- **`action` — what is about to happen, and why it is in the sweep** — which defect it would
  catch. That is what lets them say a check is pointless, or that a more important one is
  missing.
- **`bug` — say it on the screen that shows it.** A defect named three messages later in a
  transcript is a defect they have to go looking for.
- **`pass` — what was actually observed**, not "works".

Fill in the `element` description on every Playwright call too: it is what gets named as the
action happens.

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
