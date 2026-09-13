import type { ReactNode } from 'react';
import { InlineLink } from '@/components/inline-link';
import { useDateNames } from '@/hooks/use-date-names';
import { useTimeZone } from '@/hooks/use-time-zone';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format';
import { show as showOrder } from '@/routes/purchase-orders';
import type { TranslationKey } from '@/types/lang';

type Return = App.Data.PurchaseReturnData;

/**
 * Everything about the return that is not a line: what it credits, who it is with, why, and
 * what it is priced in.
 *
 * **The order is a link, and it is the first row.** A return is only meaningful against the
 * delivery it credits, so "which one" is the first question and the answer is one click.
 *
 * The exchange rate shows only when it is not 1, for the reason the order's own summary gives:
 * a document in the workspace's own money has a rate that carries no information.
 *
 * **The completion half appears only once there is one**, the shape the order's own summary
 * uses for its receipt: three rows of dashes on every pending return would say nothing on the
 * screens it appears on most. Where the goods left from is part of it, because that is the one
 * fact about a completed return that the lines themselves cannot tell you.
 */
export function ReturnSummary({ row }: { row: Return }) {
    const { t } = useTranslation();
    const timeZone = useTimeZone();
    const names = useDateNames();

    // `Number`, not a string comparison: the column reads back `1.000000` and a form may have
    // sent `1`, so comparing the strings would show the rate for one of them.
    const converted = Number(row.exchange_rate) !== 1;

    return (
        <dl className="grid max-w-3xl gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            <Row label="purchase-returns.summary.order">
                <InlineLink
                    href={showOrder({ purchaseOrder: row.purchase_order_id })}
                >
                    {row.purchase_order_number}
                </InlineLink>
            </Row>
            <Row label="purchase-returns.summary.supplier">
                {/* Null once the supplier has been force-deleted. i18n-allow */}
                {row.supplier ?? '—'}
            </Row>

            <Row label="purchase-returns.summary.reason">
                {t(`returns.reason.${row.reason}` as const)}
            </Row>
            <Row label="purchase-returns.summary.currency">
                <span className="tabular-nums">
                    {row.currency}
                    {converted && (
                        <span className="ml-2 text-muted-foreground">
                            {t('purchase-returns.summary.rate', {
                                rate: row.exchange_rate,
                            })}
                        </span>
                    )}
                </span>
            </Row>

            <Row label="purchase-returns.summary.raised_by">
                {/* Null once the person has been removed. i18n-allow */}
                {row.created_by ?? '—'}
            </Row>

            {row.completed_at !== null && (
                <>
                    <Row label="purchase-returns.summary.completed_by">
                        {/* i18n-allow */}
                        {row.completed_by ?? '—'}
                    </Row>
                    <Row label="purchase-returns.summary.completed_at">
                        <time
                            dateTime={row.completed_at}
                            className="tabular-nums"
                        >
                            {formatDateTime(row.completed_at, timeZone, names)}
                        </time>
                    </Row>
                    <Row label="purchase-returns.summary.warehouse">
                        {/* Null once the warehouse has been removed; the ledger rows it
                            wrote are still there. i18n-allow */}
                        {row.completed_warehouse ?? '—'}
                    </Row>
                </>
            )}

            {row.notes !== null && (
                <div className="sm:col-span-2">
                    <dt className="text-muted-foreground text-xs">
                        {t('purchase-returns.summary.notes')}
                    </dt>
                    {/* Somebody's own words, so their line breaks are theirs to keep. */}
                    <dd className="whitespace-pre-line">{row.notes}</dd>
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
