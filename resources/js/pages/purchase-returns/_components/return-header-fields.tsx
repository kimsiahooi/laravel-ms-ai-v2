import { SelectField } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { InlineLink } from '@/components/inline-link';
import { useDateNames } from '@/hooks/use-date-names';
import { useTimeZone } from '@/hooks/use-time-zone';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format';
import { show as showOrder } from '@/routes/purchase-orders';
import type { TranslationKey } from '@/types/lang';

type Order = App.Data.PurchaseOrderData;

/**
 * Mirrors App\Enums\ReturnReason. A `Record` over the enum, so a case added server-side is a
 * compile error here rather than a reason nobody can pick.
 */
const REASON_LABEL: Record<App.Enums.ReturnReason, TranslationKey> = {
    damaged: 'returns.reason.damaged',
    wrong_item: 'returns.reason.wrong_item',
    quality: 'returns.reason.quality',
    surplus: 'returns.reason.surplus',
    other: 'returns.reason.other',
};

const REASON_OPTIONS = Object.entries(REASON_LABEL).map(([value, label]) => ({
    value,
    label,
}));

/**
 * What the return credits, and why.
 *
 * **The delivery is shown, not chosen.** A return credits one order and copies its prices, so
 * changing it would invalidate every line — which is the same work as raising a new return.
 * The sentence under it says so rather than leaving somebody hunting for a control that is not
 * there. The server refuses a re-parent regardless: the request pins the field.
 *
 * The reason is the one thing on this block a person decides, and the notes carry what a code
 * cannot.
 */
export function ReturnHeaderFields({
    order,
    reason,
    notes,
    errors,
}: {
    order: Order;
    /** Seeded on an edit; empty while raising one. */
    reason: string | null;
    notes: string | null;
    errors: Record<string, string>;
}) {
    const { t } = useTranslation();
    const timeZone = useTimeZone();
    const names = useDateNames();

    return (
        <div className="space-y-4">
            <div className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-3">
                <div>
                    <p className="text-muted-foreground text-xs">
                        {t('purchase-returns.order.number')}
                    </p>
                    <InlineLink
                        href={showOrder({ purchaseOrder: order.id })}
                        className="font-medium"
                    >
                        {order.number}
                    </InlineLink>
                </div>
                <div>
                    <p className="text-muted-foreground text-xs">
                        {t('purchase-returns.order.supplier')}
                    </p>
                    {/* Null once the supplier has been force-deleted. i18n-allow */}
                    <p>{order.supplier ?? '—'}</p>
                </div>
                <div>
                    <p className="text-muted-foreground text-xs">
                        {t('purchase-returns.order.received')}
                    </p>
                    <p className="tabular-nums">
                        {order.received_at === null
                            ? // i18n-allow
                              '—'
                            : formatDateTime(
                                  order.received_at,
                                  timeZone,
                                  names,
                              )}
                    </p>
                </div>
            </div>

            <p className="max-w-2xl text-muted-foreground text-xs">
                {t('purchase-returns.order.locked')}
            </p>

            {/* The server reads this; the picker above is display only. `InputError` has
                nowhere else to land, so a refused parent reports here. */}
            <input type="hidden" name="purchase_order_id" value={order.id} />

            <div className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                <SelectField
                    name="reason"
                    label="purchase-returns.field.reason"
                    options={REASON_OPTIONS}
                    defaultValue={reason}
                    placeholder="purchase-returns.field.reason_placeholder"
                    error={errors.reason ?? errors.purchase_order_id}
                />
                <TextField
                    name="notes"
                    label="purchase-returns.field.notes"
                    placeholder="purchase-returns.field.notes_placeholder"
                    defaultValue={notes ?? ''}
                    error={errors.notes}
                    optional
                    rows={3}
                />
            </div>
        </div>
    );
}
