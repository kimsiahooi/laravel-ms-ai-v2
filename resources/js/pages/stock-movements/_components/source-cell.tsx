import type { InertiaLinkProps } from '@inertiajs/react';
import { InlineLink } from '@/components/inline-link';
import { useTranslation } from '@/hooks/use-translation';
import { show as showPurchaseOrder } from '@/routes/purchase-orders';
import { show as showStockTake } from '@/routes/stock-takes';

type Source = App.Enums.MovementSource;

/** Where each source can be looked at, or null where there is nothing to look at. */
const LINKS: Record<
    Source,
    ((id: number) => NonNullable<InertiaLinkProps['href']>) | null
> = {
    stock_take: (id) => showStockTake({ stockTake: id }),
    stock_transfer: null,
    purchase_order: (id) => showPurchaseOrder({ purchaseOrder: id }),
};

/**
 * What caused this row, named in the reader's language and opened where there is
 * something to open.
 *
 * **The words are chosen here, not stored.** Until this column existed the reference was
 * spelled into `notes` at posting time — v1 did it in six Actions by concatenation, and
 * v2's stock takes did it through the translator, which was worse: it froze the poster's
 * language into a column every locale reads. The row now holds `stock_take` and `12`, and
 * the sentence is built at render time out of the asking reader's bundle.
 *
 * **Only some sources have a screen.** A stock take has a count sheet and a purchase order
 * has its document, so both are followable — and a receipt is exactly the row somebody
 * questions ("where did forty of these come from?"), which makes the link back to the order
 * the shortest answer there is. A transfer has no detail page, so its label renders as plain
 * text rather than pointing at something that does not exist; the day transfers grow one,
 * this is the single place that changes.
 *
 * `LINKS` is a `Record` over the whole enum rather than a chain of `if`s, so a fifth source
 * is a compile error here until somebody has said whether it can be opened.
 */
export function SourceCell({
    type,
    id,
}: {
    type: Source | null;
    id: number | null;
}) {
    const { t } = useTranslation();

    // Null for a hand-recorded adjustment — nothing caused it but a person — and for
    // every row written before the column existed. i18n-allow
    if (type === null || id === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const label = t(`stock-movements.source.${type}`, { id });
    const href = LINKS[type];

    if (href === null) {
        return <span className="whitespace-nowrap">{label}</span>;
    }

    return <InlineLink href={href(id)}>{label}</InlineLink>;
}
