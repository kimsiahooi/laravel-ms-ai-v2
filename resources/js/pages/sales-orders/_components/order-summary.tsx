import type { ReactNode } from 'react';
import { ExpectedDate } from '@/components/data/expected-date';
import { useDateNames } from '@/hooks/use-date-names';
import { useTimeZone } from '@/hooks/use-time-zone';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format';
import type { TranslationKey } from '@/types/lang';

type Order = App.Data.SalesOrderData;

/**
 * Everything about the order that is not a line: who it is for, what it is priced in, when
 * it was promised, and — once it has shipped — who sent it and from where.
 *
 * **The shipping half appears only once there is one.** An empty "Shipped by —" on every
 * pending order is a row that says nothing on the screens where it is shown most, and its
 * absence is itself the answer to "has this gone out".
 *
 * The exchange rate is shown only when it is not 1, for the same reason the form hides the
 * box: an order in the workspace's own money has a rate that carries no information, and
 * printing "1.000000" invites the question of what it is doing there.
 *
 * **Two kinds of date, treated oppositely, and the difference is the point.** `fulfilled_at`
 * is an instant — a moment that happened — so it is converted to the workspace clock.
 * `expected_date` is a day somebody agreed, so nothing converts it at all; see
 * {@see ExpectedDate}.
 */
export function OrderSummary({ order }: { order: Order }) {
    const { t } = useTranslation();
    const timeZone = useTimeZone();
    const names = useDateNames();

    // A parse for a *display* decision, never for arithmetic — which is why it is here and
    // not in `lib/money.ts`. `1`, `1.0` and `1.000000` are one rate written three ways, and
    // comparing the strings would show the box for two of them.
    const converted = Number(order.exchange_rate) !== 1;

    return (
        <dl className="grid max-w-3xl gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            <Row label="sales-orders.summary.customer">
                {/* Null once the customer has been force-deleted. i18n-allow */}
                {order.customer ?? '—'}
            </Row>
            <Row label="sales-orders.summary.currency">
                <span className="tabular-nums">
                    {order.currency}
                    {converted && (
                        <span className="ml-2 text-muted-foreground">
                            {t('sales-orders.summary.rate', {
                                rate: order.exchange_rate,
                            })}
                        </span>
                    )}
                </span>
            </Row>

            <Row label="sales-orders.summary.expected">
                {order.expected_date === null ? (
                    // i18n-allow
                    '—'
                ) : (
                    <ExpectedDate date={order.expected_date} />
                )}
            </Row>
            <Row label="sales-orders.summary.raised_by">
                {/* Null once the person has been removed. i18n-allow */}
                {order.created_by ?? '—'}
            </Row>

            {order.fulfilled_at !== null && (
                <>
                    <Row label="sales-orders.summary.fulfilled_by">
                        {/* i18n-allow */}
                        {order.fulfilled_by ?? '—'}
                    </Row>
                    <Row label="sales-orders.summary.fulfilled_at">
                        <time
                            dateTime={order.fulfilled_at}
                            className="tabular-nums"
                        >
                            {formatDateTime(
                                order.fulfilled_at,
                                timeZone,
                                names,
                            )}
                        </time>
                    </Row>
                    <Row label="sales-orders.summary.fulfilled_from">
                        {/* Null once the warehouse has been removed; the ledger rows it
                            wrote are still there. i18n-allow */}
                        {order.fulfilled_warehouse ?? '—'}
                    </Row>
                </>
            )}

            {order.notes !== null && (
                <div className="sm:col-span-2">
                    <dt className="text-muted-foreground text-xs">
                        {t('sales-orders.summary.notes')}
                    </dt>
                    {/* The seller's own words, so their line breaks are theirs to keep. */}
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
