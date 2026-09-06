import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Movement = App.Data.StockMovementData;

/**
 * What moved, and what kind of thing it was.
 *
 * The name can be null. A product removed from the catalogue is soft-deleted and still
 * resolves — see `StockMovement::stockable()` — but a *force*-delete takes the row, and
 * the ledger outlives it. A dash rather than a broken row: the movement still happened,
 * and the quantity and warehouse beside it are still true.
 */
export function ItemCell({ movement }: { movement: Movement }) {
    const { t } = useTranslation();

    return (
        <div className="min-w-0">
            <span className="block truncate font-medium">
                {/* i18n-allow */}
                {movement.item ?? '—'}
            </span>
            <span className="block truncate text-muted-foreground text-xs">
                {t(`stock-movements.item_type.${movement.item_type}` as const)}
                {movement.item_sku !== null && ` · ${movement.item_sku}`}
            </span>
            {/*
                Where it happened, on a phone. The warehouse has its own column from md
                up, but below that it drops out entirely — and a ledger row that says
                what moved and how much without saying where is half a record. The site
                comes too, because two sites with a "Main store" are ordinary.
            */}
            <span className="block truncate text-muted-foreground text-xs md:hidden">
                {movement.warehouse} · {movement.site}
            </span>
        </div>
    );
}

/**
 * Which part of the business each reason belongs to.
 *
 * Ten reasons is too many to tell apart by colour, and they are not ten unrelated
 * things: the enum already groups them in pairs, and the pairs are the families a person
 * actually thinks in — bought, sold, made, moved, or counted by hand. Five is a number
 * you can learn.
 *
 * A `Record` over the whole enum on purpose: adding a reason is then a compile error
 * here until somebody says which family it joins, rather than a badge that silently
 * falls back to grey.
 */
const FAMILY: Record<App.Enums.StockMovementReason, string> = {
    // Somebody did this by hand, rather than a purchase or a sale doing it for them.
    // A real hue like the rest: a near-neutral one was tried first, on the reasoning
    // that this is the ordinary case, and it just read as the grey it replaced.
    adjustment: 'border-chart-5/25 bg-chart-5/10 text-chart-5',
    stock_take: 'border-chart-5/25 bg-chart-5/10 text-chart-5',

    // Moved within the business. The brand hue, since nothing entered or left.
    transfer_in: 'border-chart-1/25 bg-chart-1/10 text-chart-1',
    transfer_out: 'border-chart-1/25 bg-chart-1/10 text-chart-1',

    // Bought — stock arriving from a supplier, or going back to one.
    purchase_receipt: 'border-chart-2/25 bg-chart-2/10 text-chart-2',
    purchase_return: 'border-chart-2/25 bg-chart-2/10 text-chart-2',

    // Sold — stock leaving for a customer, or coming back from one.
    sales_fulfillment: 'border-chart-3/25 bg-chart-3/10 text-chart-3',
    sales_return: 'border-chart-3/25 bg-chart-3/10 text-chart-3',

    // Made — consumed into something, or produced out of something.
    production_consume: 'border-chart-4/25 bg-chart-4/10 text-chart-4',
    production_output: 'border-chart-4/25 bg-chart-4/10 text-chart-4',
};

/**
 * Why it moved.
 *
 * A badge rather than plain text because most of these are written by the system as the
 * side effect of something else — a receipt, a sale, a transfer — so it reads as a label
 * the row carries rather than a field somebody filled in.
 *
 * **Coloured by family, not by reason**, so a column of them is scannable: you can see
 * that a run of rows came from sales without reading any of them. The two halves of a
 * pair share a colour because the sign in the quantity column already says which
 * direction, and colouring it twice would spend a second channel on the same fact.
 *
 * **A tint and coloured text, not a solid fill.** Twenty-five saturated badges down a
 * page stop being labels and start being the page. And the colour is never the only
 * channel — the badge says the reason in words, in the reader's language; the hue is
 * reinforcement for someone skimming, not information for someone reading.
 */
export function ReasonCell({
    reason,
}: {
    reason: App.Enums.StockMovementReason;
}) {
    const { t } = useTranslation();

    return (
        <Badge
            variant="secondary"
            className={cn('font-normal', FAMILY[reason])}
        >
            {t(`stock-movements.reason.${reason}` as const)}
        </Badge>
    );
}

/**
 * Which warehouse, and the site it stands on.
 *
 * Two lines rather than "Site · Warehouse" on one: the separator would be a choice made
 * in a component about two pieces of somebody else's data, and two sites with a "Main
 * store" are ordinary enough that the site has to be readable rather than appended.
 */
export function WarehouseCell({ movement }: { movement: Movement }) {
    return (
        <div className="min-w-0">
            <span className="block truncate">{movement.warehouse}</span>
            <span className="block truncate text-muted-foreground text-xs">
                {movement.site}
            </span>
        </div>
    );
}
