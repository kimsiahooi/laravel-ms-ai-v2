/**
 * Money arithmetic in the browser, exact, and agreeing with the server.
 *
 * **This is a mirror of `App\Support\Money`, and the server is the authority.** The order
 * form shows a running total as somebody types, which is worth having and is not worth
 * trusting: what gets stored is what the Action computes. The two are kept honest by using
 * the same scale, the same rounding rule and the same order of operations, and by the
 * screen never sending a total — only the lines it was computed from.
 *
 * **Scaled integers, not doubles.** v1 did this in floats on both sides
 * (`Math.round(base * rate) / 100`), which is how a preview and a stored figure end up a
 * cent apart on exactly the invoices somebody checks by hand. `decimal(15,4)` at its
 * maximum is fifteen significant digits, which is the edge of what a double holds exactly,
 * so the margin is nil. `BigInt` has no such edge.
 *
 * Every function takes and returns a decimal STRING, so nothing outside this file has to
 * know about the scaling.
 */

/** `decimal(15,4)`, the same working scale as `App\Support\Money::SCALE`. */
const SCALE = 4;
const UNIT = 10n ** BigInt(SCALE);

/**
 * A decimal string as an integer count of ten-thousandths, or null if it is not a number.
 *
 * Null rather than zero: a half-typed `1.` and a genuine `0` are different answers, and a
 * running total that reads a blank box as zero is how v1's count sheet painted a variance
 * for a field somebody had merely cleared.
 */
function parse(value: string): bigint | null {
    const trimmed = value.trim();

    if (
        trimmed === '' ||
        !/^[+-]?\d*\.?\d*$/.test(trimmed) ||
        !/\d/.test(trimmed)
    ) {
        return null;
    }

    const negative = trimmed.startsWith('-');
    const bare = trimmed.replace(/^[+-]/, '');
    const [whole, fraction = ''] = bare.split('.');
    // Pad or truncate to the working scale. Truncation matches bcmath, which discards
    // beyond its scale rather than rounding — and the field refuses more than four places
    // anyway, so this only bites on a value no form would have accepted.
    const scaled = `${whole || '0'}${fraction.padEnd(SCALE, '0').slice(0, SCALE)}`;
    const magnitude = BigInt(scaled);

    return negative ? -magnitude : magnitude;
}

/**
 * A scaled integer back to a decimal string, at `places` decimals.
 *
 * `places` defaults to the working scale, which is what a line amount is stored at. Order
 * totals pass the currency's scale instead, so the string this produces is the same string
 * `App\Support\Money::roundTo()` produces on the server — byte for byte, not merely the
 * same number. Anything less and every consumer would have to normalise before comparing.
 */
function render(scaled: bigint, places: number = SCALE): string {
    const negative = scaled < 0n;
    const magnitude = negative ? -scaled : scaled;
    const whole = magnitude / UNIT;
    const fraction = (magnitude % UNIT)
        .toString()
        .padStart(SCALE, '0')
        .slice(0, places);

    return `${negative ? '-' : ''}${whole}${places > 0 ? `.${fraction}` : ''}`;
}

function multiply(a: bigint, b: bigint): bigint {
    // Both operands are already scaled, so the product carries the scale twice.
    return (a * b) / UNIT;
}

/**
 * Round half away from zero, to `places`.
 *
 * The same rule as `Money::round()`, and the same reason it is spelled out: truncation
 * would shave a unit off roughly half of all tax lines, and rounding a negative the wrong
 * way makes a credit note short.
 */
function roundScaled(scaled: bigint, places: number): bigint {
    const step = 10n ** BigInt(SCALE - places);

    if (step <= 1n) {
        return scaled;
    }

    const half = step / 2n;
    const negative = scaled < 0n;
    const magnitude = negative ? -scaled : scaled;
    const rounded = ((magnitude + half) / step) * step;

    return negative ? -rounded : rounded;
}

/** How a single line's discount is expressed. Mirrors `App\Enums\DiscountType`. */
export type DiscountType = 'none' | 'percent' | 'amount';

/** One line, as the form holds it — every field a string, because every field is an input. */
export type MoneyLine = {
    quantity: string;
    unitPrice: string;
    discountType: DiscountType;
    discountValue: string;
    taxable: boolean;
};

export type LineAmounts = {
    /** quantity × unit price, before any discount. */
    gross: string;
    discount: string;
    /** What the line actually comes to. */
    net: string;
};

export type OrderTotals = {
    subtotal: string;
    discountTotal: string;
    taxTotal: string;
    total: string;
};

/**
 * What one line comes to.
 *
 * A line whose quantity or price is missing contributes nothing rather than guessing at
 * zero — an unfinished row should not drag the total down while somebody is still typing
 * in it.
 */
export function lineAmounts(line: MoneyLine): LineAmounts {
    const quantity = parse(line.quantity);
    const unitPrice = parse(line.unitPrice);

    if (quantity === null || unitPrice === null) {
        return { gross: '0.0000', discount: '0.0000', net: '0.0000' };
    }

    const gross = multiply(quantity, unitPrice);
    const value = parse(line.discountValue) ?? 0n;

    let discount = 0n;

    if (line.discountType === 'percent') {
        discount = multiply(gross, value) / 100n;
    } else if (line.discountType === 'amount') {
        discount = value;
    }

    // A discount never exceeds the line: a negative amount is not a thing anybody meant,
    // and the server refuses it too rather than storing one.
    if (discount > gross) {
        discount = gross;
    }

    return {
        gross: render(gross),
        discount: render(discount),
        net: render(gross - discount),
    };
}

/**
 * What the order comes to, rounded to what the currency can express.
 *
 * **Tax is charged on the taxable lines only**, and is rounded once at the order level
 * rather than per line. Rounding each line and summing gives a different answer — usually
 * by a cent, always on a document somebody reconciles — and the order-level figure is the
 * one that gets paid.
 *
 * `places` comes from the caller because the currency's scale is the server's knowledge:
 * see `App\Support\Money::scaleFor()`. Two unless told otherwise.
 */
export function orderTotals(
    lines: MoneyLine[],
    taxRate: string,
    places = 2,
): OrderTotals {
    const rate = parse(taxRate) ?? 0n;

    let subtotal = 0n;
    let discountTotal = 0n;
    let taxableBase = 0n;

    for (const line of lines) {
        const amounts = lineAmounts(line);
        const net = parse(amounts.net) ?? 0n;

        subtotal += net;
        discountTotal += parse(amounts.discount) ?? 0n;

        if (line.taxable) {
            taxableBase += net;
        }
    }

    const tax = roundScaled(multiply(taxableBase, rate) / 100n, places);
    const rounded = roundScaled(subtotal, places);

    return {
        subtotal: render(rounded, places),
        discountTotal: render(roundScaled(discountTotal, places), places),
        taxTotal: render(tax, places),
        total: render(rounded + tax, places),
    };
}
