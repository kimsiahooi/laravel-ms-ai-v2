import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';
import type { OrderLine } from '@/components/form/order-lines-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { baseCurrency } from '@/config/currencies';
import { useTranslation } from '@/hooks/use-translation';
import { runGate } from '@/lib/validation/gate';
import { salesOrderSchema } from '@/lib/validation/schemas/sales-order';
import { OrderHeaderFields } from '@/pages/sales-orders/_components/order-header-fields';
import {
    OrderLinesCard,
    seedLines,
    toPayloadLines,
} from '@/pages/sales-orders/_components/order-lines-card';
import {
    create,
    edit,
    index,
    show,
    store,
    update,
} from '@/routes/sales-orders';

type Order = App.Data.SalesOrderData;

type Props = {
    /** The order being edited, or null while taking a new one. */
    order: Order | null;
    /** Its lines, in the order they were entered. Empty for a new order. */
    items: App.Data.SalesOrderItemData[];
    customers: App.Data.OptionData[];
    products: App.Data.StockItemOptionData[];
    currencies: string[];
    /** A percentage: `'6'`, not `'0.06'`. The lines quote it back in their tax row. */
    taxRate: string;
};

/**
 * Taking an order, and amending one that has not shipped yet.
 *
 * **A page, not a dialog.** An order is a document: a header, then as many lines as the
 * customer asked for, each with its own price and discount. A dialog would have to scroll to
 * reach its own submit button by the third line, and it would put the running total
 * somewhere nobody can see while typing into the row above it.
 *
 * **Create and edit are one screen, told apart by `order` being null.** They validate
 * identically and post to the same controller, so the only real differences are a URL and
 * four strings — and keeping them together is what stops one growing a field the other
 * forgets.
 *
 * **What the page holds, and what it does not.** The header fields are uncontrolled, the way
 * every form in this app is: the DOM keeps what was typed and hands it over on submit. Two
 * things are exceptions and both earn it — the lines, because the totals redraw as somebody
 * types and a running figure cannot be read back out of the DOM; and the currency, because
 * whether there is an exchange-rate field at all depends on it.
 *
 * `useForm` is the envelope rather than the state: the error bag, the in-flight flag, and
 * `transform`, which is where the payload is assembled. What gets sent is built here rather
 * than scraped off the form, so the wire is something this file states outright.
 *
 * **Nothing here checks stock, and nothing should.** A sales order is a commitment, routinely
 * taken before the goods exist; the order names no warehouse until it ships. Whether there is
 * enough to send is a question about one warehouse at one instant, and it belongs to
 * fulfilment, under a lock.
 *
 * **No total is ever sent.** The lines are the whole of it; `App\Support\OrderTotals` computes
 * the figures again under the same rules `lib/money.ts` previews them with.
 */
export default function SalesOrderForm({
    order,
    items,
    customers,
    products,
    currencies,
    taxRate,
}: Props) {
    const { t } = useTranslation();

    const [currency, setCurrency] = useState(
        order?.currency ?? baseCurrency(currencies),
    );
    const [lines, setLines] = useState<OrderLine[]>(() => seedLines(items));

    // Seeded from the order so the first render is honest; from there the DOM holds the
    // header and `transform` below assembles what is actually sent.
    const form = useForm({
        customer_id:
            order?.customer_id == null ? '' : String(order.customer_id),
        currency,
        exchange_rate: order?.exchange_rate ?? '',
        // Stored verbatim, so the seed is the stored value. Nothing converts a promised
        // delivery date — see ExpectedDate.
        expected_date: order?.expected_date ?? '',
        notes: order?.notes ?? '',
        items: toPayloadLines(lines),
    });

    // Inertia keys a nested failure by dot path — `items.2.quantity` — which its own
    // per-field typing cannot express. The bag is that shape at runtime.
    const errors = form.errors as Record<string, string>;

    const schema = useMemo(() => salesOrderSchema(currencies), [currencies]);

    const foreign = currency !== '' && currency !== baseCurrency(currencies);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const fields = new FormData(event.currentTarget);
        const read = (name: string): string => {
            const value = fields.get(name);

            return typeof value === 'string' ? value.trim() : '';
        };

        const payload = {
            customer_id: read('customer_id'),
            currency: read('currency'),
            // A base-currency order has no rate box to read: one unit of the order's money
            // IS one unit of the workspace's, and the form stopped asking rather than pose
            // a question with a single legal answer. See OrderHeaderFields.
            exchange_rate: foreign ? read('exchange_rate') : '1',
            expected_date: read('expected_date'),
            notes: read('notes'),
            items: toPayloadLines(lines),
        };

        form.transform(() => payload);

        const options = {
            preserveScroll: true,
            // Checked before it is sent, so a quantity the column would silently round is
            // refused here rather than stored as a different number.
            onBefore: () => runGate(schema, payload, form, t),
        };

        if (order === null) {
            form.post(store().url, options);
        } else {
            // PATCH, not PUT: the document's number, status and fulfilment columns are
            // untouchable from this form however complete it looks. See routes/tenant.php.
            form.patch(update({ salesOrder: order.id }).url, options);
        }
    };

    const title =
        order === null
            ? t('sales-orders.create.title')
            : t('sales-orders.edit.title', { number: order.number });

    setLayoutProps({
        breadcrumbs: [
            { title: t('sales-orders.title'), href: index() },
            ...(order === null
                ? [{ title: t('sales-orders.create.crumb'), href: create() }]
                : [
                      {
                          title: order.number,
                          href: show({ salesOrder: order.id }),
                      },
                      {
                          title: t('sales-orders.edit.crumb'),
                          href: edit({ salesOrder: order.id }),
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
                    {t('sales-orders.create.subtitle')}
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
                            customers={customers}
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
                    products={products}
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
                                    : show({ salesOrder: order.id })
                            }
                        >
                            {t('common.actions.cancel')}
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {t(
                            form.processing
                                ? 'sales-orders.create.submitting'
                                : 'sales-orders.create.submit',
                        )}
                    </Button>
                </div>
            </form>
        </>
    );
}
