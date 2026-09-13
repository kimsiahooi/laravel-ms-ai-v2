import { Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { CompletionCard } from '@/pages/purchase-returns/_components/completion-card';
import { ReturnActions } from '@/pages/purchase-returns/_components/return-actions';
import { ReturnLinesTable } from '@/pages/purchase-returns/_components/return-lines-table';
import { ReturnStatusBadge } from '@/pages/purchase-returns/_components/return-status-badge';
import { ReturnSummary } from '@/pages/purchase-returns/_components/return-summary';
import { index, show } from '@/routes/purchase-returns';

type Props = {
    return: App.Data.PurchaseReturnData;
    /** Every line, unpaginated — see {@see ReturnLinesTable} on why. */
    items: App.Data.PurchaseReturnItemData[];
    /** Where the goods may be sent from. Empty once the return has ended. */
    warehouses: App.Data.WarehouseOptionData[];
    /** The warehouse `availability` was computed against, or `''`. */
    chosenWarehouse: string;
    /** What that warehouse holds, or null when there is nothing left to decide. */
    availability: App.Data.StockAvailabilityData[] | null;
};

/**
 * The return itself — a credit note against one delivery.
 *
 * **A document, not a form.** Nothing here is editable, including while the return is still
 * pending: amending it means going back to the form, where each line can be weighed against
 * what the delivery has left. What lives here is the record — what is going back, what it
 * credits, and why.
 *
 * **The two endings sit in a card of their own, below the document rather than beside its
 * heading.** {@see ReturnActions} in the header is about changing the paperwork — edit, delete —
 * and {@see CompletionCard} is about the goods. Sending stock back needs a warehouse chosen and
 * a panel read before it can be confirmed at all, which is a card's worth of thinking and not a
 * button; putting it under the lines also means the last thing read before confirming is what is
 * actually going back.
 *
 * Both components decide for themselves whether to render, so "may this still be changed" has
 * one home each rather than being asked again here.
 */
export default function PurchaseReturnShow({
    return: row,
    items,
    warehouses,
    chosenWarehouse,
    availability,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('purchase-returns.title'), href: index() },
            { title: row.number, href: show({ purchaseReturn: row.id }) },
        ],
    });

    return (
        <>
            <Head title={row.number} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="font-semibold text-2xl tracking-tight">
                            {row.number}
                        </h1>
                        <ReturnStatusBadge status={row.status} />
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {/* Null once the supplier has been force-deleted. i18n-allow */}
                        {row.supplier ?? '—'}
                    </p>
                </div>

                <ReturnActions row={row} />
            </div>

            <ReturnSummary row={row} />

            <ReturnLinesTable row={row} items={items} />

            <CompletionCard
                row={row}
                warehouses={warehouses}
                chosenWarehouse={chosenWarehouse}
                availability={availability}
            />
        </>
    );
}
