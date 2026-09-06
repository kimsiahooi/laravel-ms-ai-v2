import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';
import type { OrderLine } from '@/components/form/order-lines-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTimeZone } from '@/hooks/use-time-zone';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateInput } from '@/lib/format';
import { runGate } from '@/lib/validation/gate';
import { purchaseOrderSchema } from '@/lib/validation/schemas/purchase-order';
import {
    baseCurrency,
    OrderHeaderFields,
} from '@/pages/purchase-orders/_components/order-header-fields';
import {
    OrderLinesCard,
    seedLines,
    toPayloadLines,
} from '@/pages/purchase-orders/_components/order-lines-card';
import {
    create,
    edit,
    index,
    show,
    store,
    update,
} from '@/routes/purchase-orders';

type Order = App.Data.PurchaseOrderData;

type Props = {
    /** The order being edited, or null while raising a new one. */
    order: Order | null;
    /** Its lines, in the order they were entered. Empty for a new order. */
    items: App.Data.PurchaseOrderItemData[];
    suppliers: App.Data.OptionData[];
    materials: App.Data.StockItemOptionData[];
    currencies: string[];
    /** A percentage: `'6'`, not `'0.06'`. The lines quote it back in their tax row. */
    taxRate: string;
};

/**
 * Raising an order, and amending one that has not arrived yet.
 *
 * **A page, where every form before this was a dialog.** An order is a document: a
 * header, then as many lines as the delivery has, each with its own price and discount.
 * A dialog would have to scroll to reach its own submit button by the third line, and it
 * would put the running total somewhere nobody can see while typing into the row above
 * it. `ResourceFormDialog` is still right for a category and wrong for this.
 *
 * **Create and edit are one screen, told apart by `order` being null.** They validate
 * identically and post to the same controller, so the only real differences are a URL
 * and four strings — and keeping them together is what stops one growing a field the
 * other forgets.
 *
 * **What the page holds, and what it does not.** The header fields are uncontrolled, the
 * way every form in this app is: the DOM keeps what was typed and hands it over on
 * submit. Two things are exceptions and both earn it — the lines, because the totals
 * redraw as somebody types and a running figure cannot be read back out of the DOM; and
 * the currency, because whether there is an exchange-rate field at all depends on it.
 *
 * `useForm` is the envelope rather than the state: the error bag, the in-flight flag,
 * and `transform`, which is where the payload is assembled. The same shape `CountInput`
 * uses, and for the same reason — what gets sent is built here rather than scraped off
 * the form, so the wire is something this file states outright. It has to be: the shared
 * line editor names its money column `unit_price` and a purchase order records a
 * `unit_cost`.
 *
 * **No total is ever sent.** The lines are the whole of it; `App\Support\OrderTotals`
 * computes the figures again under the same rules `lib/money.ts` previews them with.
 */
export default function PurchaseOrderForm({
    order,
    items,
    suppliers,
    materials,
    currencies,
    taxRate,
}: Props) {
    const { t } = useTranslation();
    const timeZone = useTimeZone();

    const [currency, setCurrency] = useState(
        order?.currency ?? baseCurrency(currencies),
    );
    const [lines, setLines] = useState<OrderLine[]>(() => seedLines(items));

    // Seeded from the order so the first render is honest; from there the DOM holds the
    // header and `transform` below assembles what is actually sent.
    const form = useForm({
        supplier_id:
            order?.supplier_id == null ? '' : String(order.supplier_id),
        currency,
        exchange_rate: order?.exchange_rate ?? '',
        // The stored instant back onto this browser's clock, which is the inverse of how
        // the server anchored it — so the box offers the day that was picked, not the day
        // that instant happens to fall on in UTC.
        expected_date:
            order?.expected_date == null
                ? ''
                : formatDateInput(order.expected_date, timeZone),
        notes: order?.notes ?? '',
        items: toPayloadLines(lines),
    });

    // Inertia keys a nested failure by dot path — `items.2.quantity` — which its own
    // per-field typing cannot express. The bag is that shape at runtime.
    const errors = form.errors as Record<string, string>;

    const schema = useMemo(() => purchaseOrderSchema(currencies), [currencies]);

    const foreign = currency !== '' && currency !== baseCurrency(currencies);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const fields = new FormData(event.currentTarget);
        const read = (name: string): string => {
            const value = fields.get(name);

            return typeof value === 'string' ? value.trim() : '';
        };

        const payload = {
            supplier_id: read('supplier_id'),
            currency: read('currency'),
            // A base-currency order has no rate box to read: one unit of the order's
            // money IS one unit of the workspace's, and the form stopped asking rather
            // than pose a question with a single legal answer. See OrderHeaderFields.
            exchange_rate: foreign ? read('exchange_rate') : '1',
            expected_date: read('expected_date'),
            notes: read('notes'),
            items: toPayloadLines(lines),
        };

        form.transform(() => payload);

        const options = {
            preserveScroll: true,
            // Checked before it is sent, so a quantity the column would silently round
            // is refused here rather than stored as a different number.
            onBefore: () => runGate(schema, payload, form, t),
        };

        if (order === null) {
            form.post(store().url, options);
        } else {
            // PATCH, not PUT: the document's number, status and receipt columns are
            // untouchable from this form however complete it looks. See routes/tenant.php.
            form.patch(update({ purchaseOrder: order.id }).url, options);
        }
    };

    const title =
        order === null
            ? t('purchase-orders.create.title')
            : t('purchase-orders.edit.title', { number: order.number });

    setLayoutProps({
        breadcrumbs: [
            { title: t('purchase-orders.title'), href: index() },
            ...(order === null
                ? [{ title: t('purchase-orders.create.crumb'), href: create() }]
                : [
                      {
                          title: order.number,
                          href: show({ purchaseOrder: order.id }),
                      },
                      {
                          title: t('purchase-orders.edit.crumb'),
                          href: edit({ purchaseOrder: order.id }),
                      },
                  ]),
        ],
    });

    return (
        <>
            <Head title={title} />

            <div className="max-w-2xl space-y-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {title}
                </h1>
                <p className="text-muted-foreground text-sm">
                    {t('purchase-orders.create.subtitle')}
                </p>
            </div>

            {/* `noValidate`, or the browser's own bubble fires on the first `required`
                field and the zod gate never runs — the same reason every dialog form in
                this app carries it. */}
            <form onSubmit={submit} noValidate className="space-y-6">
                <Card>
                    <CardContent>
                        <OrderHeaderFields
                            order={order}
                            suppliers={suppliers}
                            currencies={currencies}
                            currency={currency}
                            onCurrencyChange={setCurrency}
                            errors={errors}
                        />
                    </CardContent>
                </Card>

                <OrderLinesCard
                    lines={lines}
                    onChange={setLines}
                    materials={materials}
                    errors={errors}
                    currency={currency}
                    taxRate={taxRate}
                />

                <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                order === null
                                    ? index()
                                    : show({ purchaseOrder: order.id })
                            }
                        >
                            {t('common.actions.cancel')}
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {t(
                            form.processing
                                ? 'purchase-orders.create.submitting'
                                : 'purchase-orders.create.submit',
                        )}
                    </Button>
                </div>
            </form>
        </>
    );
}
