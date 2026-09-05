import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

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
 * Why it moved.
 *
 * A badge rather than plain text because most of these are written by the system as the
 * side effect of something else — a receipt, a sale, a transfer — so it reads as a label
 * the row carries rather than a field somebody filled in.
 */
export function ReasonCell({
    reason,
}: {
    reason: App.Enums.StockMovementReason;
}) {
    const { t } = useTranslation();

    return (
        <Badge variant="secondary" className="font-normal">
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
