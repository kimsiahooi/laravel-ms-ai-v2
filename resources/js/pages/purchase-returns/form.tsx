import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { runGate } from '@/lib/validation/gate';
import { purchaseReturnSchema } from '@/lib/validation/schemas/purchase-return';
import { ReturnFormFooter } from '@/pages/purchase-returns/_components/return-form-footer';
import { ReturnHeaderFields } from '@/pages/purchase-returns/_components/return-header-fields';
import {
    fillAllQuantities,
    payloadItems,
    ReturnableLinesCard,
    returnRows,
} from '@/pages/purchase-returns/_components/returnable-lines-card';
import { show as showOrder } from '@/routes/purchase-orders';
import {
    create,
    edit,
    index,
    show,
    store,
    update,
} from '@/routes/purchase-returns';

type Props = {
    /** The return being edited, or null while raising one. */
    return: App.Data.PurchaseReturnData | null;
    /** The delivery being credited. Read-only on both paths — see the component note. */
    order: App.Data.PurchaseOrderData;
    /**
     * Every line of that delivery with something still returnable, plus any this return
     * already holds.
     *
     * `returned` and `remaining` on each already exclude this return, so the ceiling shown
     * beside a box is the ceiling the server will enforce against it.
     */
    lines: App.Data.ReturnableLineData[];
};

/**
 * Saying how much of a delivery is going back.
 *
 * **A page, not a dialog**, for the reason the order forms give — a header and a grid — plus
 * one they do not have: every row carries a remaining figure that has to be readable while
 * typing into the box beside it.
 *
 * **Create and edit are one screen, told apart by `return` being null.** They validate
 * identically and post to the same Action, so the only real differences are a URL and four
 * strings.
 *
 * **Only rows with a typed quantity are posted, so the payload index is not the row index.**
 * That is the one genuinely fiddly thing here, and it is computed once: `rows` carries each
 * line's payload position or null, and the same list builds the request, names the inputs and
 * resolves the error keys. The alternative — posting every row including the blanks so the
 * indices always line up — would force the quantity rule to be `nullable`, and a half-typed row
 * would then be silently dropped instead of refused.
 *
 * **The header is uncontrolled and the quantities are not.** The DOM keeps the reason and the
 * notes, the way every form here does; the quantities have to be state because the running
 * credit redraws as somebody types and a total cannot be read back out of the DOM.
 *
 * **No money is sent.** The prices are the delivery's, and the Action copies them — see
 * `SavePurchaseReturn`. What this form posts is a reason, some notes, and a quantity per line.
 */
export default function PurchaseReturnForm({
    return: row,
    order,
    lines,
}: Props) {
    const { t } = useTranslation();

    // Seeded from what the return already holds; owned here from the first keystroke.
    const [quantities, setQuantities] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            lines
                .filter((line) => line.quantity !== '')
                .map((line) => [
                    String(line.purchase_order_item_id),
                    line.quantity,
                ]),
        ),
    );

    // One list, three consumers: the request, the input names and the error keys. The
    // arithmetic lives beside the rows it describes — see `returnRows`.
    const rows = useMemo(
        () => returnRows(lines, quantities),
        [lines, quantities],
    );

    const items = useMemo(() => payloadItems(rows), [rows]);

    // Exactly what the server will measure against — the same numbers, from the same read.
    const remaining = useMemo(
        () =>
            Object.fromEntries(
                lines.map((line) => [
                    String(line.purchase_order_item_id),
                    line.remaining,
                ]),
            ),
        [lines],
    );

    const schema = useMemo(
        () => purchaseReturnSchema(order.id, remaining),
        [order.id, remaining],
    );

    const form = useForm({
        purchase_order_id: String(order.id),
        reason: row?.reason ?? '',
        notes: row?.notes ?? '',
        items,
    });

    // Inertia keys a nested failure by dot path — `items.2.quantity` — which its own
    // per-field typing cannot express. The bag is that shape at runtime.
    const errors = form.errors as Record<string, string>;

    const setQuantity = (orderItemId: number, quantity: string) =>
        setQuantities((current) => ({
            ...current,
            [String(orderItemId)]: quantity,
        }));

    const fillAll = () =>
        setQuantities((current) => fillAllQuantities(lines, current));

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const fields = new FormData(event.currentTarget);
        const read = (name: string): string => {
            const value = fields.get(name);

            return typeof value === 'string' ? value.trim() : '';
        };

        const payload = {
            // From the prop, not the form: the parent is not something this screen can
            // change, and the server pins it again on an edit.
            purchase_order_id: String(order.id),
            reason: read('reason'),
            notes: read('notes'),
            items,
        };

        form.transform(() => payload);

        const options = {
            preserveScroll: true,
            onBefore: () => runGate(schema, payload, form, t),
        };

        if (row === null) {
            form.post(store().url, options);
        } else {
            form.patch(update({ purchaseReturn: row.id }).url, options);
        }
    };

    const title =
        row === null
            ? t('purchase-returns.create.title', { number: order.number })
            : t('purchase-returns.edit.title', { number: row.number });

    setLayoutProps({
        breadcrumbs: [
            { title: t('purchase-returns.title'), href: index() },
            ...(row === null
                ? [
                      {
                          title: t('purchase-returns.create.crumb'),
                          href: create(),
                      },
                  ]
                : [
                      {
                          title: row.number,
                          href: show({ purchaseReturn: row.id }),
                      },
                      {
                          title: t('purchase-returns.edit.crumb'),
                          href: edit({ purchaseReturn: row.id }),
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
                    {t(
                        row === null
                            ? 'purchase-returns.create.subtitle'
                            : 'purchase-returns.edit.subtitle',
                    )}
                </p>
            </div>

            {/* `noValidate`, or the browser's own bubble fires on the first required field
                and the zod gate never runs. */}
            <form onSubmit={submit} noValidate className="space-y-6">
                <Card>
                    <CardContent>
                        <ReturnHeaderFields
                            order={order}
                            reason={row?.reason ?? null}
                            notes={row?.notes ?? null}
                            errors={errors}
                        />
                    </CardContent>
                </Card>

                <ReturnableLinesCard
                    rows={rows}
                    currency={order.currency}
                    // The order's rate, copied when the return is saved — never today's
                    // setting, or the credit would not match the charge.
                    taxRate={order.tax_rate}
                    errors={errors}
                    onChange={setQuantity}
                    onFillAll={fillAll}
                />

                <ReturnFormFooter
                    editing={row !== null}
                    processing={form.processing}
                    // Cancelling a new return goes back to the delivery it would have
                    // credited; cancelling an edit goes back to the document.
                    back={
                        row === null
                            ? showOrder({ purchaseOrder: order.id })
                            : show({ purchaseReturn: row.id })
                    }
                />
            </form>
        </>
    );
}
