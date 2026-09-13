import { Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { ReturnActions } from '@/pages/purchase-returns/_components/return-actions';
import { ReturnLinesTable } from '@/pages/purchase-returns/_components/return-lines-table';
import { ReturnStatusBadge } from '@/pages/purchase-returns/_components/return-status-badge';
import { ReturnSummary } from '@/pages/purchase-returns/_components/return-summary';
import { index, show } from '@/routes/purchase-returns';

type Props = {
    return: App.Data.PurchaseReturnData;
    /** Every line, unpaginated — see {@see ReturnLinesTable} on why. */
    items: App.Data.PurchaseReturnItemData[];
};

/**
 * The return itself — a credit note against one delivery.
 *
 * **A document, not a form.** Nothing here is editable, including while the return is still
 * pending: amending it means going back to the form, where each line can be weighed against
 * what the delivery has left. What lives here is the record — what is going back, what it
 * credits, and why.
 *
 * **There is no completion on this screen yet, deliberately.** This slice builds the document
 * and nothing in it writes a stock movement; the transition that moves the goods arrives next,
 * and it brings a warehouse picker and an availability panel with it.
 *
 * {@see ReturnActions} decides for itself whether to render, so "may this still be changed"
 * has one home rather than being asked again here.
 */
export default function PurchaseReturnShow({ return: row, items }: Props) {
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
        </>
    );
}
