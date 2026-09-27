import { Head, setLayoutProps } from '@inertiajs/react';
import { ReturnStatusBadge } from '@/components/feedback/return-status-badge';
import { useTranslation } from '@/hooks/use-translation';
import { CompletionCard } from '@/pages/sales-returns/_components/completion-card';
import { ReturnActions } from '@/pages/sales-returns/_components/return-actions';
import { ReturnLinesTable } from '@/pages/sales-returns/_components/return-lines-table';
import { ReturnSummary } from '@/pages/sales-returns/_components/return-summary';
import { index, show } from '@/routes/sales-returns';

type Props = {
    return: App.Data.SalesReturnData;
    /** Every line, unpaginated — see {@see ReturnLinesTable} on why. */
    items: App.Data.SalesReturnItemData[];
    /** Where the goods may be received into. Empty once the return has ended. */
    warehouses: App.Data.WarehouseOptionData[];
    /** The despatch's own warehouse, which the picker opens on. `''` once it has ended. */
    chosenWarehouse: string;
};

/**
 * The return itself — a credit note against one despatch.
 *
 * **A document, not a form.** Nothing here is editable, including while the return is still
 * pending: amending it means going back to the form, where each line can be weighed against what
 * the despatch has left. What lives here is the record — what came back, what it credits, and
 * why.
 *
 * **The two endings sit in a card of their own, below the document rather than beside its
 * heading.** {@see ReturnActions} in the header is about changing the paperwork — edit, delete —
 * and {@see CompletionCard} is about the goods. Putting stock back needs a warehouse chosen
 * before it can be confirmed at all, which is a card's worth of thinking and not a button;
 * putting it under the lines also means the last thing read before confirming is what is
 * actually coming back.
 *
 * Both components decide for themselves whether to render, so "may this still be changed" has
 * one home each rather than being asked again here.
 */
export default function SalesReturnShow({
    return: row,
    items,
    warehouses,
    chosenWarehouse,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('sales-returns.title'), href: index() },
            { title: row.number, href: show({ salesReturn: row.id }) },
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
                        {/* Null once the customer has been force-deleted. i18n-allow */}
                        {row.customer ?? '—'}
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
            />
        </>
    );
}
