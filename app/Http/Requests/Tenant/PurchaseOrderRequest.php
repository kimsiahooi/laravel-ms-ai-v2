<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Actions\OpenPurchaseOrder;
use App\Enums\DiscountType;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Models\BusinessSetting;
use App\Models\RawMaterial;
use App\Support\Money;
use App\Support\OrderTotals;
use App\Support\StockItem;
use App\Support\TimeZones;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Raising a purchase order, or rewriting a pending one. The same fields either way — an
 * edit replaces what was agreed, so it carries the whole agreement.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/purchase-order.ts, which
 * exports `purchaseOrderSchema(currencies)` because this one's currency rule is built from
 * the workspace's own list rather than a constant. `bun run check:validation` builds it
 * with the arguments in that script's FACTORY_ARGS and fails if the two stop covering the
 * same fields.
 *
 * **No total is a field here, and none ever will be.** {@see OrderTotals} computes the
 * four order figures and every line's own from the lines below, and
 * {@see OpenPurchaseOrder} stores what it decided. A total that arrived in a
 * request would be a number the client chose for a document the business is going to pay
 * against.
 *
 * **`number` is not a field either.** v1 had one — optional, typed into a box, unique only
 * by a rule that read the table before writing it — and its own generator went unused.
 * The number is allocated under a row lock when the order is created and is not something
 * an edit may touch.
 *
 * `max:200` on the lines is a ceiling on the request, not a business rule. A purchase
 * order of two hundred materials is not one anybody typed.
 */
final class PurchaseOrderRequest extends TenantFormRequest
{
    /**
     * An order raised in the base currency converts at one, and is not asked.
     *
     * The form renders no rate box for a base-currency order, because there is nothing to
     * decide — so the field arrives empty and would fail the `required` rule that exists
     * to catch the case that *does* matter. Filling it in here rather than relaxing the
     * rule keeps both halves: a blank rate on a foreign-currency order is still refused,
     * which is precisely the hole v1 documented and left open.
     *
     * The rate is **overwritten**, not defaulted. A base-currency order at 4.42 is not a
     * preference somebody expressed, it is a figure that would misstate the order's own
     * value in its own books; no screen can send one, and a payload that does is not
     * describing anything real.
     *
     * `input()` rather than `string()`, which fatals on `currency[]=x`: an array reaches
     * `Str::of()` and raises a TypeError, so a hand-edited payload would be a 500 before
     * a single rule had run. The same hazard {@see ReadsQueryValues}
     * guards on the query string.
     */
    protected function prepareForValidation(): void
    {
        $currency = $this->input('currency');

        if (is_string($currency) && $currency === BusinessSetting::current()->base_currency) {
            $this->merge(['exchange_rate' => '1']);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Resolved before the array rather than inline in it, for the reason
        // StockMovementRequest gives: `check:i18n` reads this file as text to find the
        // rules in use, and a quoted string inside the rules literal is indistinguishable
        // to it from a rule name.
        $currencies = BusinessSetting::current()->allowedCurrencies();

        return [
            'supplier_id' => ['required', ...$this->foreignKey('suppliers')],
            // The workspace's own list, which always includes the base currency — see
            // BusinessSetting::allowedCurrencies(). Not a global ISO list: an order in a
            // currency the books cannot express is a figure nobody can roll up.
            'currency' => ['required', 'string', Rule::in($currencies)],
            // Base-currency units per one unit of the order currency. **Required**, where
            // v1 left it nullable and documented the hole it left: a direct POST that
            // omitted it on a foreign-currency order was stored at rate 1, silently
            // valuing the order wrong. It is required here and filled in for the one case
            // where the answer is not a question — see prepareForValidation() — so a blank
            // rate on a foreign-currency order is refused rather than assumed.
            //
            // `max` is the column's real ceiling: decimal(15,6) spends six of its fifteen
            // digits after the point, leaving nine, and MySQL in strict mode *errors* on
            // more — so without this an over-large rate is a 500 rather than a sentence.
            'exchange_rate' => ['required', 'numeric', 'decimal:0,6', 'gt:0', 'max:999999999'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // `required` already refuses an empty array, so there is no `min:1` — and it
            // is the rule that says an order with nothing on it is not an order.
            'items' => ['required', 'array', 'max:200'],
            // One field naming one material, not a type and an id — see StockItem. No
            // `distinct`: unlike a bill of materials, the same material may legitimately
            // appear twice at two prices, which the migration says more about.
            'items.*.item' => ['required', 'string', $this->materialExists()],
            'items.*.quantity' => ['required', ...$this->decimalRules()],
            // `gte:0`, not `gt:0`: a free line is real — a sample, a replacement, a
            // goodwill item shipped at no charge — and refusing it would force somebody to
            // invent a price.
            'items.*.unit_cost' => ['required', ...$this->decimalRules('gte:0')],
            'items.*.discount_type' => ['required', Rule::enum(DiscountType::class)],
            // Nullable, because a blank discount box is not a missing answer — it is no
            // discount, and {@see self::lines()} reads it as zero. Requiring it would make
            // clearing the box an error on a line nobody meant to discount.
            'items.*.discount_value' => ['nullable', ...$this->decimalRules('gte:0')],
            'items.*.taxable' => ['required', 'boolean'],
        ];
    }

    /**
     * The order's own fields — everything that is not a line — in the types the columns
     * want.
     *
     * Not `header()`, which is what this wants to be called: `Illuminate\Http\Request`
     * already has one, and an override that took no arguments would break every caller
     * asking this request for an HTTP header.
     *
     * The conversion belongs here rather than in the Action, for the reason
     * {@see BomRequest::lines()} gives: a form submits strings, and the class that
     * declared the rules is the one that knows which is which.
     *
     * `exchange_rate` stays a **string**. It multiplies every figure on the order when it
     * is rolled up into the base currency, and a rate that has been through a float is a
     * rate that may no longer be what was quoted.
     *
     * The currency is passed through rather than upper-cased: the `in` rule has already
     * pinned it to one of the workspace's own codes, which are stored upper-case, so
     * anything else never reaches here.
     *
     * An empty notes box becomes null rather than an empty string — "nothing was written
     * down" is an absence, and a column full of empty strings is a column nothing can ask
     * a useful question of.
     *
     * @return array{supplier_id: int, currency: string, exchange_rate: string, expected_date: CarbonImmutable|null, notes: string|null}
     */
    public function orderHeader(): array
    {
        $notes = $this->input('notes');

        return [
            'supplier_id' => $this->integer('supplier_id'),
            'currency' => (string) $this->string('currency'),
            'exchange_rate' => (string) $this->string('exchange_rate'),
            'expected_date' => $this->expectedInstant(),
            'notes' => is_string($notes) && $notes !== '' ? $notes : null,
        ];
    }

    /**
     * The validated lines, resolved and typed.
     *
     * Each line's material is decoded back into a row rather than trusted, which is the
     * same re-resolution the stock screens do and is what turns a picker value into the
     * foreign key the column stores. It costs one primary-key lookup per line, bounded by
     * the `max:200` above; the alternative is splitting the encoded value here, and the
     * shape of that string lives in {@see StockItem} and nowhere else.
     *
     * A line whose material no longer resolves is dropped rather than raised. The rule
     * above has just proved every one of them, so this is the analyser being told the
     * shape rather than a case that can occur — and if the catalogue changed underneath in
     * the milliseconds between, a silently shorter order is a far better outcome than a
     * 500 or a line pointing at nothing.
     *
     * Quantities and costs stay **strings**, for the reason {@see BomRequest::lines()}
     * gives and {@see Money} gives again: the value that passed
     * `decimal:0,4` is already exact, and handing it to Eloquent unchanged is what keeps
     * it so.
     *
     * @return list<array{raw_material_id: int, quantity: string, unit_cost: string, discount_type: DiscountType, discount_value: string, taxable: bool}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->array('items') as $line) {
            // `array()` is untyped by nature; the rules have already refused anything
            // that is not a row of scalars under an integer key.
            if (! is_array($line)) {
                continue;
            }

            $material = StockItem::decode((string) $line['item']);

            if (! $material instanceof RawMaterial) {
                continue;
            }

            $discount = $line['discount_value'] ?? null;

            $lines[] = [
                'raw_material_id' => $material->id,
                'quantity' => (string) $line['quantity'],
                'unit_cost' => (string) $line['unit_cost'],
                // Safe to `from` rather than `tryFrom`: Rule::enum has just refused
                // anything this could not resolve.
                'discount_type' => DiscountType::from((string) $line['discount_type']),
                // A blank box is no discount. Checked with `is_numeric` rather than a
                // `??`, because the box can arrive empty as well as absent and both mean
                // the same thing.
                'discount_value' => is_numeric($discount) ? (string) $discount : '0',
                // The checkbox posts `'1'` or `'0'`; a JSON payload sends a real boolean.
                // `filter_var` reads both, where a cast would make the string `'0'` true.
                'taxable' => filter_var($line['taxable'], FILTER_VALIDATE_BOOL),
            ];
        }

        return $lines;
    }

    /**
     * A picker value naming one live raw material.
     *
     * Not {@see TenantFormRequest::itemExists()}, which accepts a product too. A purchase
     * order buys what the workspace consumes; letting a finished product through here
     * would put "buy your own output back from a supplier" one crafted payload away, and
     * the column it would be written to only points at `raw_materials` anyway.
     */
    private function materialExists(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! StockItem::decode($value) instanceof RawMaterial) {
                $fail('validation.exists')->translate();
            }
        };
    }

    /**
     * The picked day as the instant it starts, in UTC.
     *
     * **Anchored to the zone the person picking it is in**, which is the whole reason this
     * is not a one-line format call. `<input type="date">` sends a bare `Y-m-d` with no
     * zone at all; reading that as UTC would store a day that begins at 08:00 local for a
     * Malaysian buyer, and reading it as the server's zone would depend on where the
     * server happens to be. Neither reads back as the day they chose.
     *
     * {@see TimeZones::resolve()} is the same answer the rest of the app renders on — the
     * zone the browser reported — so the round trip closes: pick the 15th, store the
     * instant the 15th began where you are, read the 15th.
     *
     * A reader in another zone can still see the day before. That is inherent to holding a
     * calendar day as an instant, and it is the trade the migration names.
     */
    private function expectedInstant(): ?CarbonImmutable
    {
        $picked = $this->date('expected_date');

        if ($picked === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat(
            'Y-m-d',
            $picked->format('Y-m-d'),
            TimeZones::resolve($this),
        )->startOfDay()->utc();
    }
}
