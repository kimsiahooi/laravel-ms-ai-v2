<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Actions\SaveSalesReturn;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Support\Decimals;
use App\Support\ReturnedQuantities;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Taking a customer's goods back, and changing the note that records it.
 *
 * The mirror of {@see PurchaseReturnRequest}, and the same four rules carry it.
 *
 * **Four fields and a grid, and only one number on the grid is a person's.** The quantity is
 * typed; the price, the discount and the tax all come off the order line the row names, so this
 * request never accepts money. That is what makes a credit note reconcile with the invoice it
 * credits — see {@see SaveSalesReturn}.
 *
 * **The order must be one that was actually fulfilled**, and the rule says so rather than
 * leaving it to the controller. Goods cannot come back before they go out, and there is a
 * sharper reason besides: `OpenSalesOrder::revise()` hard-deletes an order's lines on every edit,
 * so a return pointing at a *pending* order's line would make the sales-order edit screen fail
 * against this module's foreign key. "Fulfilled only" is what keeps those ids worth pointing at.
 *
 * **Each line must belong to this return's own order.** Without the scope, a crafted payload
 * could put order B's line on a return against order A, and the Action would copy the price from
 * a document the return does not credit — a corruption no index can see.
 *
 * The browser mirror is `lib/validation/schemas/sales-return.ts`, and it checks the same ceiling
 * against the same numbers, because both read what the server sent the form.
 */
final class SalesReturnRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Resolved above the array rather than written into it. `bun run check:i18n` reads a
        // rule array as text and strips `Class::method(…)` before doing so, which leaves a
        // chained builder's arguments looking like rules with no translated message.
        $orderId = $this->parentOrderId();

        $fulfilledOrder = Rule::exists('sales_orders', 'id')
            ->whereNull('deleted_at')
            ->where('status', SalesOrderStatus::Fulfilled->value);

        $ownOrderLine = Rule::exists('sales_order_items', 'id')
            ->where('sales_order_id', $orderId);

        return [
            'sales_order_id' => ['required', 'integer', $fulfilledOrder],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            // No `min:1`: `required` already refuses an empty array, and it is the rule that
            // says a return with nothing on it is not a return.
            'items' => ['required', 'array', 'max:200'],
            // `distinct` is not optional. The table's unique index turns a repeated line into a
            // QueryException — a 500 rather than a sentence — and the form cannot produce one,
            // so the only thing that ever will is a crafted payload.
            'items.*.sales_order_item_id' => ['required', 'integer', 'distinct', $ownOrderLine],
            // `gt:0` from the default bound: taking none of something back is not a line, and
            // leaving the box blank keeps the line off the return entirely.
            'items.*.quantity' => ['required', ...$this->decimalRules()],
        ];
    }

    /**
     * The parent order is immutable once the return exists, so it is overwritten rather than
     * defaulted — the treatment {@see PurchaseReturnRequest} gives the same field.
     *
     * Re-parenting is not a harmless edit: every line still names the old order's lines, so the
     * money would be copied from a document the return no longer credits and the ceiling would
     * be measured against a despatch it has nothing to do with. Neither the unique index nor the
     * foreign keys can see that.
     */
    protected function prepareForValidation(): void
    {
        $return = $this->editing();

        if ($return !== null) {
            $this->merge(['sales_order_id' => $return->sales_order_id]);
        }
    }

    /**
     * The ceiling: no line may bring back more than the despatch sent out.
     *
     * **An after-hook rather than a closure rule on the quantity**, for the three reasons
     * {@see PurchaseReturnRequest::withValidator()} sets out in order of weight: it reads the
     * returned map and the sold map **once for the document**; it can stand down when the order
     * or the list already failed, which a closure rule cannot see; and summing what a document
     * asks for is a question about the list, not about a row.
     *
     * **Pending returns count here**, which is the whole reason {@see ReturnStatus::consuming()}
     * exists. {@see CompleteSalesReturn} asks the narrower question — only what has actually
     * moved — under a lock.
     *
     * **The message key is invisible to `check:i18n`.** The gate reads rule arrays and never
     * looks inside `after()`, so `sales-returns.validation.over_return` had to be added to all
     * three locales by hand. What still catches a mistake: the bundles are compared
     * key-for-key, so an `en`-only key fails, and the browser's copy is a `TranslationKey`.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // The order or the list itself is wrong, so every ceiling below it would be
            // measured against nothing.
            if ($validator->errors()->hasAny(['sales_order_id', 'items'])) {
                return;
            }

            $rows = $this->rowsToCheck($validator);

            if ($rows === []) {
                return;
            }

            $ids = array_values(array_unique(array_column($rows, 'sales_order_item_id')));
            $returned = ReturnedQuantities::forSalesOrderItems($ids, $this->editing()?->id);
            $sold = SalesOrderItem::query()->whereKey($ids)->get(['id', 'quantity'])->keyBy('id');

            foreach ($rows as $index => $row) {
                $line = $sold->get($row['sales_order_item_id']);

                if (! $line instanceof SalesOrderItem) {
                    continue;
                }

                $left = ReturnedQuantities::remaining(
                    $line->quantity,
                    $returned[$row['sales_order_item_id']] ?? '0',
                );

                if (bccomp($row['quantity'], $left, self::DECIMAL_SCALE) <= 0) {
                    continue;
                }

                $validator->errors()->add("items.{$index}.quantity", __(
                    'sales-returns.validation.over_return',
                    ['remaining' => Decimals::trim($left)],
                ));
            }
        });
    }

    /**
     * The header, in the shape {@see SaveSalesReturn} declares.
     *
     * **Exactly the three real field names, and that is a constraint rather than a coincidence.**
     * `scripts/check-validation-parity.ts` reads `^\s{12}'key' =>` from `public function rules`
     * to the *end of the file* — there is no closing bound — so any twelve-space array key in a
     * later method is counted as a field the browser must also check. These three are already
     * fields, so the gate stays honest; a fourth key here would silently demand a zod key for
     * something nobody validates.
     *
     * @return array{sales_order_id: int, reason: ReturnReason, notes: string|null}
     */
    public function returnFields(): array
    {
        $notes = $this->validated('notes');

        return [
            'sales_order_id' => (int) $this->validated('sales_order_id'),
            'reason' => ReturnReason::from((string) $this->validated('reason')),
            'notes' => is_string($notes) && $notes !== '' ? $notes : null,
        ];
    }

    /**
     * The rows, as ids and quantities. Nothing else — the money is the order's.
     *
     * Quantities stay strings all the way to the column, for the reason every quantity in this
     * app does: a float here is a rounding error in a credit note.
     *
     * @return list<array{sales_order_item_id: int, quantity: string}>
     */
    public function lines(): array
    {
        $items = $this->validated('items');
        $lines = [];

        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines[] = [
                'sales_order_item_id' => (int) $row['sales_order_item_id'],
                'quantity' => (string) $row['quantity'],
            ];
        }

        return $lines;
    }

    /**
     * The rows worth measuring: those whose own two fields passed.
     *
     * **Both guards are deliberate.** `after()` callbacks run even when other rules have failed,
     * so a quantity of `"abc"` would otherwise reach `bccomp`, and bcmath in PHP 8 throws a
     * `ValueError` naming its own argument — a 500 that mentions nothing anybody typed. The
     * per-field error check is the correct guard; `is_numeric` is what stops a later refactor
     * turning this into an outage.
     *
     * Keyed by the row's own index, because that is what the error message has to be filed under
     * for the browser to find the box.
     *
     * @return array<int, array{sales_order_item_id: int, quantity: numeric-string}>
     */
    private function rowsToCheck(Validator $validator): array
    {
        $items = $this->input('items');
        $rows = [];

        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $index => $row) {
            if (! is_int($index) || ! is_array($row)) {
                continue;
            }

            $failed = $validator->errors()->hasAny([
                "items.{$index}.sales_order_item_id",
                "items.{$index}.quantity",
            ]);

            $id = $row['sales_order_item_id'] ?? null;
            $quantity = $row['quantity'] ?? null;

            if ($failed || ! is_numeric($id) || ! is_numeric($quantity)) {
                continue;
            }

            $rows[$index] = [
                'sales_order_item_id' => (int) $id,
                'quantity' => (string) $quantity,
            ];
        }

        return $rows;
    }

    /** The return being edited, or null while raising one. */
    private function editing(): ?SalesReturn
    {
        $return = $this->route('salesReturn');

        return $return instanceof SalesReturn ? $return : null;
    }

    /**
     * The order the lines must belong to, or 0.
     *
     * Zero rather than null so the scoped `exists` matches nothing and every line is refused —
     * leaving `sales_order_id`'s own rule to report the real problem rather than this one
     * reporting a symptom of it.
     */
    private function parentOrderId(): int
    {
        $value = $this->input('sales_order_id');

        return is_numeric($value) ? (int) $value : 0;
    }
}
