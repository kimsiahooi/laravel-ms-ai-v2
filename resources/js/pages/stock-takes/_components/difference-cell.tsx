import { cn } from '@/lib/utils';

type Line = App.Data.StockTakeItemData;

/** The column's scale — `decimal(15, 4)`, and bcmath at 4 on the server. */
const SCALE = 4;

/**
 * A decimal string as an integer number of ten-thousandths — `"9.8"` → `98000n`. Null
 * for anything that is not a plain decimal.
 *
 * **The difference on this screen is not `Number(a) - Number(b)`.** Counting 10.1 where
 * 9.8 was expected renders `0.30000000000000027` in IEEE-754, and a sheet that reports a
 * third of a nothing is a sheet nobody trusts. The server holds these as strings and
 * subtracts them with bcmath for that reason; the browser has no bcmath, so it does the
 * same arithmetic the same way — scaled integers, where the subtraction is exact.
 */
function scaled(value: string): bigint | null {
    const parts = /^([+-]?)(\d*)(?:\.(\d*))?$/.exec(value.trim());

    if (parts === null) {
        return null;
    }

    const [, sign, whole, fraction = ''] = parts;

    // `""` and `"."` both match the pattern and are not numbers.
    if (whole === '' && fraction === '') {
        return null;
    }

    const digits = `${whole || '0'}${fraction.padEnd(SCALE, '0').slice(0, SCALE)}`;
    const magnitude = BigInt(digits);

    return sign === '-' ? -magnitude : magnitude;
}

/**
 * Back to a decimal string with the trailing zeros gone — `98000n` → `"9.8"`. The shape
 * `Decimals::trim()` produces, so a difference worked out here and an `applied_delta`
 * worked out there read as the same kind of number instead of `-2` beside `-2.0000`.
 */
function unscaled(value: bigint): string {
    const negative = value < 0n;
    const digits = (negative ? -value : value)
        .toString()
        .padStart(SCALE + 1, '0');
    const fraction = digits.slice(-SCALE).replace(/0+$/, '');
    const point = fraction === '' ? '' : `.${fraction}`;

    return `${negative ? '-' : ''}${digits.slice(0, -SCALE)}${point}`;
}

/** Nothing to report — which is not a zero, because a zero is a finding. */
function Dash() {
    // i18n-allow
    return <span className="text-muted-foreground">—</span>;
}

/**
 * A signed quantity, coloured the way the ledger colours one: short is the direction
 * that costs money, so it is the direction that gets the alarm. A surplus is worth
 * seeing too, which is what the explicit `+` is for — a bare number beside a `-12` reads
 * as neutral.
 */
function Signed({ value }: { value: string }) {
    const direction = Math.sign(Number(value));

    return (
        <span
            className={cn(
                'font-medium tabular-nums',
                direction < 0 && 'text-destructive',
                direction > 0 && 'text-chart-3',
                direction === 0 && 'text-muted-foreground',
            )}
        >
            {/* The + is punctuation, not a word. i18n-allow */}
            {direction > 0 ? `+${value}` : value}
        </span>
    );
}

/**
 * The gap between the shelf and the system — or, once the take is closed, the gap that
 * actually moved.
 *
 * **An empty box is not a variance.** v1 recomputed from whatever was in the field, so
 * clearing one painted the row with the full negative of its expected quantity, as
 * though somebody had just declared the shelf empty. Nothing was counted, so there is
 * nothing to say, and a muted dash says it.
 *
 * After posting it reads `applied_delta` rather than subtracting again. Those are not
 * the same number and only one is true: the delta was computed under the row lock
 * against live on-hand, and a fresh subtraction from a stale snapshot would quietly
 * disagree with the ledger row this line caused.
 */
export function Difference({
    line,
    finished,
}: {
    line: Line;
    finished: boolean;
}) {
    if (finished) {
        return line.applied_delta === null ? (
            <Dash />
        ) : (
            <Signed value={line.applied_delta} />
        );
    }

    if (line.counted_quantity === null) {
        return <Dash />;
    }

    const counted = scaled(line.counted_quantity);
    const system = scaled(line.system_quantity);

    // Unreachable unless the server sends something that is not a decimal, which it
    // cannot — but a row reading "NaN" would be worse than a row reading nothing.
    return counted === null || system === null ? (
        <Dash />
    ) : (
        <Signed value={unscaled(counted - system)} />
    );
}
