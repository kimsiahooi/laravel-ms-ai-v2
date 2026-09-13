import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { OrderActions } from '@/pages/sales-orders/_components/order-actions';
import { OrderLinesTable } from '@/pages/sales-orders/_components/order-lines-table';
import { OrderStatusBadge } from '@/pages/sales-orders/_components/order-status-badge';
import { OrderSummary } from '@/pages/sales-orders/_components/order-summary';
import { edit, index, show } from '@/routes/sales-orders';

type Props = {
    order: App.Data.SalesOrderData;
    /** Every line, unpaginated — see {@see OrderLinesTable} on why. */
    items: App.Data.SalesOrderItemData[];
};

/**
 * The order itself — the screen the module exists for.
 *
 * **A document, not a form.** Nothing on this page is editable, including while the order is
 * still pending: amending it means going back to the form, where the lines can be priced
 * against a running total. What lives here instead are the decisions that can only be taken
 * once, and the figures somebody needs in order to take them.
 *
 * **Once it is over, it is over.** A fulfilled or cancelled order renders with no footer and
 * no way to edit; {@see OrderActions} decides that for itself rather than being told, so the
 * rule has one home. What is left is a record: what was sold, what it came to, and — for a
 * fulfilled order — who shipped it and from which building.
 *
 * **The status sits beside the number rather than in the summary.** Whether this order can
 * still be acted on is the first thing to know about it, and it is the answer to why the
 * footer has gone.
 */
export default function SalesOrderShow({ order, items }: Props) {
    const { t } = useTranslation();
    const { can } = usePermissions();

    // Editable for exactly as long as it is unresolved. The server draws the same line — an
    // update against a fulfilled order is refused there, not merely hidden here.
    const editable = order.status === 'pending' && can('sales-orders.update');

    setLayoutProps({
        breadcrumbs: [
            { title: t('sales-orders.title'), href: index() },
            { title: order.number, href: show({ salesOrder: order.id }) },
        ],
    });

    return (
        <>
            <Head title={order.number} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="font-semibold text-2xl tracking-tight">
                            {order.number}
                        </h1>
                        <OrderStatusBadge status={order.status} />
                    </div>
                    <p className="text-muted-foreground text-sm">
                        {/* Null once the customer has been force-deleted. i18n-allow */}
                        {order.customer ?? '—'}
                    </p>
                </div>

                {editable && (
                    <Button variant="outline" asChild>
                        <Link href={edit({ salesOrder: order.id })}>
                            <Pencil className="size-4" />
                            {t('sales-orders.action.edit')}
                        </Link>
                    </Button>
                )}
            </div>

            <OrderSummary order={order} />

            <OrderLinesTable order={order} items={items} />

            <OrderActions order={order} />
        </>
    );
}
