import type { ReactNode } from 'react';
import { useTimeZone } from '@/hooks/use-time-zone';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format';
import { ExpectedDate } from '@/pages/purchase-orders/_components/expected-date';
import type { TranslationKey } from '@/types/lang';

type Order = App.Data.PurchaseOrderData;

/**
 * Everything about the order that is not a line: who it is with, what it is priced in,
 * when it is wanted, and — once it has arrived — who took it in and where.
 *
 * **The receiving half appears only once there is one.** An empty "Received by —" on
 * every pending order is a row that says nothing on the screens where it is shown most,
 * and its absence is itself the answer to "has this arrived".
 *
 * The exchange rate is shown only when it is not 1, for the same reason the form hides
 * the box: an order in the workspace's own money has a rate that carries no information,
 * and printing "1.000000" invites the question of what it is doing there.
 */
export function OrderSummary({ order }: { order: Order }) {
    const { t } = useTranslation();
    const timeZone = useTimeZone();

    // A parse for a *display* decision, never for arithmetic — which is why it is here
    // and not in `lib/money.ts`. `1`, `1.0` and `1.000000` are one rate written three
    // ways, and comparing the strings would show the box for two of them.
    const converted = Number(order.exchange_rate) !== 1;

    return (
        <dl className="grid max-w-3xl gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            <Row label="purchase-orders.summary.supplier">
                {/* Null once the supplier has been force-deleted. i18n-allow */}
                {order.supplier ?? '—'}
            </Row>
            <Row label="purchase-orders.summary.currency">
                <span className="tabular-nums">
                    {order.currency}
                    {converted && (
                        <span className="ml-2 text-muted-foreground">
                            {t('purchase-orders.summary.rate', {
                                rate: order.exchange_rate,
                            })}
                        </span>
                    )}
                </span>
            </Row>

            <Row label="purchase-orders.summary.expected">
                {order.expected_date === null ? (
                    // i18n-allow
                    '—'
                ) : (
                    <ExpectedDate date={order.expected_date} />
                )}
            </Row>
            <Row label="purchase-orders.summary.raised_by">
                {/* Null once the person has been removed. i18n-allow */}
                {order.created_by ?? '—'}
            </Row>

            {order.received_at !== null && (
                <>
                    <Row label="purchase-orders.summary.received_by">
                        {/* i18n-allow */}
                        {order.received_by ?? '—'}
                    </Row>
                    <Row label="purchase-orders.summary.received_at">
                        <time
                            dateTime={order.received_at}
                            className="tabular-nums"
                        >
                            {formatDateTime(order.received_at, timeZone)}
                        </time>
                    </Row>
                    <Row label="purchase-orders.summary.received_into">
                        {/* Null once the warehouse has been removed; the ledger rows it
                            wrote are still there. i18n-allow */}
                        {order.received_warehouse ?? '—'}
                    </Row>
                </>
            )}

            {order.notes !== null && (
                <div className="sm:col-span-2">
                    <dt className="text-muted-foreground text-xs">
                        {t('purchase-orders.summary.notes')}
                    </dt>
                    {/* The buyer's own words, so their line breaks are theirs to keep. */}
                    <dd className="whitespace-pre-line">{order.notes}</dd>
                </div>
            )}
        </dl>
    );
}

/** One label-over-value pair. A `div` inside the list keeps the two together. */
function Row({
    label,
    children,
}: {
    label: TranslationKey;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div>
            <dt className="text-muted-foreground text-xs">{t(label)}</dt>
            <dd>{children}</dd>
        </div>
    );
}
