# UI / UX Guidelines

**The bar is product-grade UI, not plain forms.** Every screen should look considered, use
the design system, work in light + dark, and give real feedback.

Stack: **React 19 + Inertia v3 + Tailwind v4 + shadcn/ui + lucide-react**, toasts via
**sonner**, tables via **@tanstack/react-table**. Formatting/lint is **Biome**.

## 1. Principles

1. **Not just a plain form.** Cards, sections, icons, spacing, states — never a bare stack
   of `<input>`s.
2. **Never black-and-white only.** Use the brand accent for primary actions, links, focus
   and brand marks; keep large surfaces neutral so the colour reads as an accent.
3. **Always give feedback.** Loading, success, error and empty are designed states.
4. **Accessible + responsive by default.** Labels, focus rings, keyboard reach, mobile-first.
5. **Consistent.** Mirror the established patterns instead of inventing new ones.
6. **Plain language.** Written for the person running the business, not the developer. No
   dev jargon (on-hand ledger, morph, snapshot). Keep the domain terms users actually
   use — SKU, warehouse, BOM.
7. **Translatable.** No user-facing literal in a component — every string goes through
   `t()`. Interpolate rather than concatenate, and don't assume English label widths:
   Malay runs longer and Chinese denser. See [`LOCALIZATION.md`](LOCALIZATION.md).

## 2. Colour & theme (design tokens only)

**Use semantic tokens — never hard-code hex, `zinc-*`, `black`, or `white` for surfaces.**
Tokens live in `resources/css/app.css` and adapt to light/dark automatically.
`scripts/ui-guard.sh` rejects raw `#hex` / `rgb()` / `hsl()` in authored TSX.

| Use | Token classes |
|---|---|
| Primary action / brand | `bg-primary text-primary-foreground` |
| Links / accents | `text-primary` |
| Focus ring | `ring-ring` / `focus-visible:ring-ring` |
| Page / card surfaces | `bg-background`, `bg-card`, `bg-popover` |
| Muted surfaces / hover | `bg-muted`, `bg-muted/50`, `bg-secondary`, `bg-accent` |
| Secondary text | `text-muted-foreground` |
| Borders / inputs | `border-border`, `border-input` |
| Destructive | `text-destructive`, `<Button variant="destructive">` |

**Dark mode is mandatory.** Because everything uses tokens, both themes work by default.
If you must add a custom colour, add a `dark:` variant or a token.

## 3. Use shadcn/ui — don't hand-roll

Reach for `resources/js/components/ui/` before writing raw HTML.

- **Buttons** — use `variant` + `size`. Icon-only buttons need `aria-label` and usually a
  `Tooltip`. Any button inside a form that doesn't submit needs `type="button"`.
- **Inputs** — `<Input>` + `<Label htmlFor>`, a leading lucide icon for context, a
  placeholder, `truncate` on long values.
- **Forms** — Inertia `<Form>` with the render-prop `({ processing, errors })`, plus the
  zod gate (§6). Prefer `disableWhileProcessing`.
- **Overlays** — a right-hand **`Sheet`** for multi-field create/edit; a **`Dialog`** for
  focused confirmations. Guard against dismissing mid-submit.
- **Tables** — reach for the shared **`DataTable`** wrapper. For a one-off, compose the
  `ui/table` primitives — never a raw `<table>`. Hide non-essential columns below `sm`/`md`,
  and keep the table in an `overflow-x-auto` container.
- **Icons** — `lucide-react` only, `size-4`/`size-5`, coloured via `currentColor`.

## 4. UX patterns (required)

- **Loading** — disable the submit, show a spinner and a "…" label. Never leave a click
  with no response.
- **Empty states** — icon tile + heading + one-line explanation + a primary CTA. Not a bare
  "No items."
- **Validation errors** — render under each field with `<InputError>`, paired with
  `aria-invalid` + `aria-describedby` (+ `role="alert"`). Global errors use an `Alert`.
- **Success / failure** — fire a sonner `toast`, close the overlay, reflect the change.
- **Destructive actions** — confirm first, style as `destructive`.
- **Lists** — search/filter with a live count, and a "no results" state distinct from empty.
- **Timestamps** — relative ("2h ago") with the absolute time on hover.
- **Responsive** — mobile-first; the body must **never** scroll horizontally. Sidebar is a
  drawer below `lg`, pinned at `lg`+. Verify at 375 / 768 / 1024.
- **Keyboard & a11y** — labels bound to inputs, visible `focus-visible` rings, icon buttons
  labelled, and respect reduced-motion. Never use a **positive** `tabIndex`; DOM order is
  the tab order.
- **SSR safety** — anything derived from `Date`/`window`/timezone is computed post-mount.
  See CLAUDE.md; this is a hard rule because nothing else catches a hydration mismatch.
- **Honesty** — only show data that actually exists. No invented metrics, no fake charts,
  no actions without a backing endpoint.

## 5. Layout & shell

- Authenticated areas use the shadcn **inset sidebar** shell: sticky top bar (trigger +
  breadcrumbs + theme toggle), sidebar (brand tile, nav with active state, footer user
  menu), padded `max-w` content area.
- Auth pages use the **split-screen** pattern: a branded panel beside a form `Card`.
- Pages own their `<Head title>` and pass `breadcrumbs` to the layout.

## 6. Validation is two-layer

Every form has a Laravel FormRequest (the source of truth) **and** a zod schema that
refuses the same thing in the browser. Wire it with `useZodGate(schema)` spread onto the
Inertia `<Form>` (with `noValidate`). zod's issue paths match Inertia's error-bag keys, so
one `<InputError>` per field renders whichever source failed.

`bun run check:validation` fails the build if a server-validated field has no zod
counterpart. Adding a FormRequest without a schema is a build failure, by design.

## 7. Before finalizing a UI change

1. `bun run check` — **0 errors**.
2. `bun run types:check` passes.
3. Eyeball it in **both** light and dark.
4. Check it at 375px (no horizontal scroll) and tab through it.
