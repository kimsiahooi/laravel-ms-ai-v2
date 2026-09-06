import { InlineLink } from '@/components/inline-link';
import { formatMoney } from '@/lib/format';
import { ExpectedDate } from '@/pages/purchase-orders/_components/expected-date';
import { show } from '@/routes/purchase-orders';

type Order = App.Data.PurchaseOrderData;

/**
 * The order number, and the way in to the document.
 *
 * The number is the link, not a row-menu entry: an order reached only through a menu is
 * one most people never find, and everybody on this list already has
 * `purchase-orders.view`. Bold *and* underlined, the same trade the warehouse list makes
 * — the weight makes it the row's name, the link styling makes it followable. It carries
 * no `aria-label`: the number already names where it goes, and twenty-five rows repeating
 * "Open purchase order" would replace the only thing telling them apart.
 *
 * The supplier rides underneath below `sm`, where its own column has been given up for
 * width. An order that does not say who it is with is not a row anyone can act on.
 */
export function NumberCell({ order }: { order: Order }) {
    return (
        <div className="min-w-0">
            <InlineLink
                href={show({ purchaseOrder: order.id })}
                className="block truncate font-medium"
            >
                {order.number}
            </InlineLink>
            <span className="block truncate text-muted-foreground text-xs sm:hidden">
                {/* Null once the supplier has been force-deleted. i18n-allow */}
                {order.supplier ?? '—'}
            </span>
        </div>
    );
}

/**
 * Who the order is with.
 *
 * Null once the supplier has been force-deleted — the order outlives it, and the number
 * beside this is still true, so a dash rather than a broken row.
 */
export function SupplierCell({ supplier }: { supplier: string | null }) {
    // i18n-allow
    return <span className="block truncate">{supplier ?? '—'}</span>;
}

/**
 * What the order comes to, in the currency it was raised in.
 *
 * The currency travels with every figure rather than being stated once in a column
 * heading, because this list mixes them: a workspace may raise one order in MYR and the
 * next in CNY, and a column of bare numbers would invite them to be compared. See
 * `formatMoney` on why the code and not the symbol.
 */
export function TotalCell({ order }: { order: Order }) {
    return (
        <span className="whitespace-nowrap font-medium tabular-nums">
            {formatMoney(order.total, order.currency)}
        </span>
    );
}

/** The delivery date somebody is waiting on, or a dash while nobody has named one. */
export function ExpectedCell({ date }: { date: string | null }) {
    return (
        <span className="text-muted-foreground">
            {/* i18n-allow */}
            {date === null ? '—' : <ExpectedDate date={date} />}
        </span>
    );
}
