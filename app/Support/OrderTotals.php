<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DiscountType;

/**
 * What an order's lines come to — the only place that decides.
 *
 * **The authority, mirrored in the browser rather than duplicated from it.** The order form
 * shows a running total as somebody types, so the arithmetic exists twice; that is not
 * avoidable if the figure is to update without a round trip. What is avoidable is the two
 * disagreeing, so `resources/js/lib/money.ts` follows this file's scale, rounding rule and
 * order of operations, and the form sends only the lines. A total is never accepted from a
 * request — it is computed here and stored.
 *
 * v1 did neither half of that: it computed totals as PHP floats inside its Data classes, on
 * every read, and stored none of them, so an order's total was re-derived rather than
 * recorded and could not be reconciled against anything.
 *
 * **Tax is rounded once, at the order.** Rounding each line and summing gives a different
 * answer from summing and rounding once — usually by a cent, always on the document
 * somebody checks. The order-level figure is the one that gets paid, so that is the one
 * that is rounded.
 */
final class OrderTotals
{
    /**
     * One line: what it grosses, what comes off, and what it comes to.
     *
     * A discount is capped at the line it discounts. A line that came to less than nothing
     * is not something anybody meant, and letting one through would make an order's
     * subtotal smaller than its own smallest line.
     *
     * @return array{gross: string, discount: string, net: string}
     */
    public static function line(
        string $quantity,
        string $unitPrice,
        DiscountType $discountType,
        string $discountValue,
    ): array {
        $gross = Money::multiply($quantity, $unitPrice);

        $discount = match ($discountType) {
            DiscountType::None => Money::zero(),
            DiscountType::Percent => Money::percent($gross, $discountValue),
            DiscountType::Amount => $discountValue,
        };

        if (Money::compare($discount, $gross) > 0) {
            $discount = $gross;
        }

        return [
            'gross' => $gross,
            'discount' => $discount,
            'net' => Money::subtract($gross, $discount),
        ];
    }

    /**
     * The four figures an order stores.
     *
     * @param  list<array{quantity: string, unit_price: string, discount_type: DiscountType, discount_value: string, taxable: bool}>  $lines
     * @return array{subtotal: string, discount_total: string, tax_total: string, total: string}
     */
    public static function forOrder(array $lines, string $taxRate, string $currency): array
    {
        $subtotal = Money::zero();
        $discountTotal = Money::zero();
        $taxableBase = Money::zero();

        foreach ($lines as $line) {
            $amounts = self::line(
                $line['quantity'],
                $line['unit_price'],
                $line['discount_type'],
                $line['discount_value'],
            );

            $subtotal = Money::add($subtotal, $amounts['net']);
            $discountTotal = Money::add($discountTotal, $amounts['discount']);

            if ($line['taxable']) {
                $taxableBase = Money::add($taxableBase, $amounts['net']);
            }
        }

        $subtotal = Money::roundTo($subtotal, $currency);
        $tax = Money::roundTo(Money::percent($taxableBase, $taxRate), $currency);

        return [
            'subtotal' => $subtotal,
            'discount_total' => Money::roundTo($discountTotal, $currency),
            'tax_total' => $tax,
            // Rounded like the rest, though both operands already are: it is the scale
            // that makes the string, and a total rendered at four places when its parts
            // are at two is the kind of difference that shows up in a diff against the
            // browser's mirror and in nothing else.
            'total' => Money::roundTo(Money::add($subtotal, $tax), $currency),
        ];
    }
}
