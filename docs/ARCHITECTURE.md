# Architecture & code organisation

One obvious home for each kind of code, and logic simple enough to maintain. v1 is the
cautionary example: `components/` there is a flat dump of ~60 files, and five page files
exceed 600 lines (`sales-orders/index.tsx` is 1001). We fix that structurally here, not by
willpower.

## Frontend — `resources/js/`

| Folder | Holds | Rule |
|---|---|---|
| `pages/<module>/` | Route components | Composition + data wiring. **No business logic.** |
| `pages/<module>/_components/` | Components used by exactly ONE module | Co-located. Most components start life here. |
| `components/ui/` | Vendored shadcn | Read-only. Never hand-edited. |
| `components/data/` | `DataTable`, pagination, filters, `ExportMenu` | Shared list/table concerns |
| `components/form/` | `ResourceFormDialog`, `ComboboxField`, `FieldLabel`, `InputError` | Shared input concerns |
| `components/feedback/` | `EmptyState`, `StatusBadge`, confirm dialogs, `AlertError` | Shared state/messaging |
| `components/layout/` | Sidebar, nav, breadcrumbs, header, command palette | Shared chrome |
| `hooks/` | Reusable React hooks | One hook per file |
| `lib/` | Pure functions — format, status, stock math, validation | **Never imports React** |
| `types/` | Shared + generated types | `generated.d.ts` is read-only |
| `config/` | Static descriptors (nav, resource metadata) | Data, not logic |

## Backend — `app/`

| Folder | Holds | Rule |
|---|---|---|
| `Http/Controllers/` | HTTP only: resolve → delegate → respond | **No business logic, no raw queries** |
| `Http/Controllers/Concerns/` | Shared controller traits | `RendersResourceIndex`, `ResolvesPerPage`, … |
| `Http/Requests/` | Validation rules | Paired 1:1 with a zod schema |
| `Actions/` | One state-changing operation, one public method | `ReceivePurchaseOrder`, `PostStockTake` |
| `Services/` | Multi-step domain engines with invariants | `StockService` — the only writer of stock tables |
| `Data/` | DTOs (`spatie/laravel-data`) | The single source of the wire shape |
| `Models/` + `Models/Concerns/` | Eloquent + shared model traits | Relations and scopes; no orchestration |
| `Enums/`, `Support/` | Value types and pure helpers | No framework state |

## The four rules

1. **Size is a signal, not a law.** A page over ~250 lines, or a controller method over ~30,
   means a piece wants extracting — to `_components/` or to an Action. Do it then, not "later".

2. **Dependencies point one way.** `pages → components → ui`. `lib/` imports from neither.
   Nothing in `components/` may import from `pages/`. One module's `_components/` is private
   to that module.

3. **Rule of three for promotion.** A component starts in its module's `_components/`. It
   moves into a shared `components/` group on its **third** consumer — or its second if the
   logic is non-trivial. Don't pre-abstract; don't leave a fourth copy either.

4. **Business logic has exactly one home.** Never in a page component, never in a controller.
   Frontend derivation goes to `lib/`; backend rules go to an Action or a Service.

## Why these boundaries

Each unit should answer three questions on its own: what does it do, how do you use it, and
what does it depend on. If you can't change a component's internals without breaking a
consumer, the boundary is wrong. Smaller, well-bounded files are also the ones an AI agent
edits reliably — a 1000-line page is where both humans and agents make mistakes.

## Enforcement

Structure rules decay unless something checks them, and there is no test suite here.
`scripts/check-structure.sh` runs in the lefthook gates and enforces the mechanical parts:
the `lib/` React ban, the dependency direction, module-private `_components/`, no raw
queries in controllers, and size caps (as warnings, so they prompt rather than block).

Anything a script can decide should be a script, not a review comment — the same reasoning
as `scripts/check-validation-parity.ts`.
