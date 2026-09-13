import { InlineLink } from '@/components/inline-link';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format';
import { show as showOrder } from '@/routes/purchase-orders';
import { show } from '@/routes/purchase-returns';

type Return = App.Data.PurchaseReturnData;

/**
 * The return's number, and the way in to the document.
 *
 * The number is the link rather than a row-menu entry, for the reason the orders list gives:
 * a document reached only through a menu is one most people never find. The order it credits
 * rides underneath below `sm`, where its own column has been given up for width — a return
 * that does not say what it credits is not a row anybody can act on.
 */
export function NumberCell({ row }: { row: Return }) {
    return (
        <div className="min-w-0">
            <InlineLink
                href={show({ purchaseReturn: row.id })}
                className="block truncate font-medium"
            >
                {row.number}
            </InlineLink>
            <span className="block truncate text-muted-foreground text-xs sm:hidden">
                {row.purchase_order_number}
            </span>
        </div>
    );
}

/**
 * The delivery being credited, as a link to it.
 *
 * A second link in the row, which the orders list does not have — and it earns its place:
 * "which delivery was this" is the first question anybody asks of a return, and the answer is
 * one click rather than a search on the other screen.
 */
export function OrderCell({ row }: { row: Return }) {
    return (
        <InlineLink
            href={showOrder({ purchaseOrder: row.purchase_order_id })}
            className="block truncate"
        >
            {row.purchase_order_number}
        </InlineLink>
    );
}

/**
 * Who the goods are going back to.
 *
 * Read through the order rather than stored — null only once the supplier has been
 * force-deleted, and the return is still true without it.
 */
export function SupplierCell({ supplier }: { supplier: string | null }) {
    // i18n-allow
    return <span className="block truncate">{supplier ?? '—'}</span>;
}

/** Why the goods are going back. A code on the wire; the word is composed here. */
export function ReasonCell({ reason }: { reason: App.Enums.ReturnReason }) {
    const { t } = useTranslation();

    return (
        <span className="block truncate text-muted-foreground">
            {t(`returns.reason.${reason}` as const)}
        </span>
    );
}

/**
 * What the return credits, in the currency the delivery was charged in.
 *
 * The currency travels with every figure rather than being stated once in a heading, because
 * this list mixes them — see the orders list on why a column of bare numbers invites a
 * comparison nobody should make.
 */
export function CreditCell({ row }: { row: Return }) {
    return (
        <span className="whitespace-nowrap font-medium tabular-nums">
            {formatMoney(row.total, row.currency)}
        </span>
    );
}
