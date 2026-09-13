<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Actions\SavePurchaseReturn;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Support\Decimals;
use App\Support\ReturnedQuantities;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Raising a return against a delivery, and changing one.
 *
 * **Four fields and a grid, and only one number on the grid is a person's.** The quantity is
 * typed; the price, the discount and the tax all come off the order line the row names, so this
 * request never accepts money. That is what makes a return reconcile with the invoice it
 * credits — see {@see SavePurchaseReturn}.
 *
 * **The order must be one that was actually received**, and the rule says so rather than leaving
 * it to the controller. Goods cannot go back before they arrive, and there is a sharper reason
 * besides: `OpenPurchaseOrder::revise()` hard-deletes an order's lines on every edit, so a
 * return pointing at a *pending* order's line would make the purchase-order edit screen fail
 * against this module's foreign key. "Received only" is what keeps those ids worth pointing at.
 *
 * **Each line must belong to this return's own order.** Without the scope, a crafted payload
 * could put order B's line on a return against order A, and the Action would copy the price
 * from a document the return does not credit — a corruption no index can see.
 *
 * The browser mirror is `lib/validation/schemas/purchase-return.ts`, and it checks the same
 * ceiling against the same numbers, because both read what the server sent the form.
 */
final class PurchaseReturnRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Resolved above the array rather than written into it. `bun run check:i18n` reads a
        // rule array as text and strips `Class::method(…)` before doing so, which leaves a
        // chained builder's arguments looking like rules with no translated message — the trap
        // `TenantFormRequest::roleKey()` exists for. Nothing above the first key is scanned.
        $orderId = $this->parentOrderId();

        $receivedOrder = Rule::exists('purchase_orders', 'id')
            ->whereNull('deleted_at')
            ->where('status', PurchaseOrderStatus::Received->value);

        $ownOrderLine = Rule::exists('purchase_order_items', 'id')
            ->where('purchase_order_id', $orderId);

        return [
            'purchase_order_id' => ['required', 'integer', $receivedOrder],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            // No `min:1`: `required` already refuses an empty array, and it is the rule that
            // says a return with nothing on it is not a return.
            'items' => ['required', 'array', 'max:200'],
            // `distinct` is not optional. The table's unique index turns a repeated line into
            // a QueryException — a 500 rather than a sentence — and the form cannot produce
            // one, so the only thing that ever will is a crafted payload.
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', $ownOrderLine],
            // `gt:0` from the default bound: returning none of something is not a line, and
            // leaving the box blank keeps the line off the return entirely.
            'items.*.quantity' => ['required', ...$this->decimalRules()],
        ];
    }

    /**
     * The parent order is immutable once the return exists, so it is overwritten rather than
     * defaulted — the same treatment `PurchaseOrderRequest` gives a base-currency exchange rate.
     *
     * Re-parenting is not a harmless edit: every line still names the old order's lines, so the
     * money would be copied from a document the return no longer credits and the ceiling would
     * be measured against a delivery it has nothing to do with. Neither the unique index nor
     * the foreign keys can see that.
     */
    protected function prepareForValidation(): void
    {
        $return = $this->editing();

        if ($return !== null) {
            $this->merge(['purchase_order_id' => $return->purchase_order_id]);
        }
    }

    /**
     * The ceiling: no line may send back more than the delivery has left.
     *
     * **An after-hook rather than a closure rule on the quantity**, for three reasons in order
     * of weight. It reads the returned map and the ordered map **once for the document**, where
     * a per-row closure would repeat both queries per line. It can stand down when the order or
     * the list already failed, which a closure rule cannot see — a ceiling measured against an
     * order that is not an order is a second message about a mistake already named correctly.
     * And summing what a document asks for is a question about the list, not about a row.
     *
     * **Pending returns count here**, which is the whole reason
     * {@see ReturnStatus::consuming()} exists: a second claim on goods another
     * pending return already claims is not a sensible document to raise, and while somebody is
     * typing is the earliest honest moment to say so. The completion Action asks the narrower
     * question — only what has actually moved — under a lock.
     *
     * **The message key is invisible to `check:i18n`.** The gate reads rule arrays and never
     * looks inside `after()`, so `purchase-returns.validation.over_return` had to be added to
     * all three locales by hand. What still catches a mistake: the bundles are compared
     * key-for-key, so an `en`-only key fails, and the browser's copy is a `TranslationKey`.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // The order or the list itself is wrong, so every ceiling below it would be
            // measured against nothing.
            if ($validator->errors()->hasAny(['purchase_order_id', 'items'])) {
                return;
            }

            $rows = $this->rowsToCheck($validator);

            if ($rows === []) {
                return;
            }

            $ids = array_values(array_unique(array_column($rows, 'purchase_order_item_id')));
            $returned = ReturnedQuantities::forOrderItems($ids, $this->editing()?->id);
            $ordered = PurchaseOrderItem::query()->whereKey($ids)->get(['id', 'quantity'])->keyBy('id');

            foreach ($rows as $index => $row) {
                $line = $ordered->get($row['purchase_order_item_id']);

                if (! $line instanceof PurchaseOrderItem) {
                    continue;
                }

                $left = ReturnedQuantities::remaining(
                    $line->quantity,
                    $returned[$row['purchase_order_item_id']] ?? '0',
                );

                if (bccomp($row['quantity'], $left, self::DECIMAL_SCALE) <= 0) {
                    continue;
                }

                $validator->errors()->add("items.{$index}.quantity", __(
                    'purchase-returns.validation.over_return',
                    ['remaining' => Decimals::trim($left)],
                ));
            }
        });
    }

    /**
     * The header, in the shape {@see SavePurchaseReturn} declares.
     *
     * **Exactly the three real field names, and that is a constraint rather than a coincidence.**
     * `scripts/check-validation-parity.ts` reads `^\s{12}'key' =>` from `public function rules`
     * to the *end of the file* — there is no closing bound — so any twelve-space array key in a
     * later method is counted as a field the browser must also check. These three are already
     * fields, so the gate stays honest; a fourth key here would silently demand a zod key for
     * something nobody validates.
     *
     * @return array{purchase_order_id: int, reason: ReturnReason, notes: string|null}
     */
    public function returnFields(): array
    {
        $notes = $this->validated('notes');

        return [
            'purchase_order_id' => (int) $this->validated('purchase_order_id'),
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
     * @return list<array{purchase_order_item_id: int, quantity: string}>
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
                'purchase_order_item_id' => (int) $row['purchase_order_item_id'],
                'quantity' => (string) $row['quantity'],
            ];
        }

        return $lines;
    }

    /**
     * The rows worth measuring: those whose own two fields passed.
     *
     * **Both guards are deliberate.** `after()` callbacks run even when other rules have
     * failed, so a quantity of `"abc"` would otherwise reach `bccomp`, and bcmath in PHP 8
     * throws a `ValueError` naming its own argument — a 500 that mentions nothing anybody
     * typed. The per-field error check is the correct guard; `is_numeric` is what stops a
     * later refactor turning this into an outage.
     *
     * Keyed by the row's own index, because that is what the error message has to be filed
     * under for the browser to find the box.
     *
     * @return array<int, array{purchase_order_item_id: int, quantity: numeric-string}>
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
                "items.{$index}.purchase_order_item_id",
                "items.{$index}.quantity",
            ]);

            $id = $row['purchase_order_item_id'] ?? null;
            $quantity = $row['quantity'] ?? null;

            if ($failed || ! is_numeric($id) || ! is_numeric($quantity)) {
                continue;
            }

            $rows[$index] = [
                'purchase_order_item_id' => (int) $id,
                'quantity' => (string) $quantity,
            ];
        }

        return $rows;
    }

    /** The return being edited, or null while raising one. */
    private function editing(): ?PurchaseReturn
    {
        $return = $this->route('purchaseReturn');

        return $return instanceof PurchaseReturn ? $return : null;
    }

    /**
     * The order the lines must belong to, or 0.
     *
     * Zero rather than null so the scoped `exists` matches nothing and every line is refused —
     * leaving `purchase_order_id`'s own rule to report the real problem rather than this one
     * reporting a symptom of it.
     */
    private function parentOrderId(): int
    {
        $value = $this->input('purchase_order_id');

        return is_numeric($value) ? (int) $value : 0;
    }
}
