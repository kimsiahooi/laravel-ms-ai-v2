import { ArrowRight } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

type Transfer = App.Data.StockTransferData;

/**
 * What moved, and what kind of thing it was.
 *
 * The name can be null. A product removed from the catalogue is soft-deleted and still
 * resolves — see `StockTransfer::stockable()` — but a *force*-delete takes the row, and
 * the record outlives it. A dash rather than a broken row: the transfer still happened,
 * and the two warehouses beside it are still true.
 */
export function ItemCell({ transfer }: { transfer: Transfer }) {
    const { t } = useTranslation();

    return (
        <div className="min-w-0">
            <span className="block truncate font-medium">
                {/* i18n-allow */}
                {transfer.item ?? '—'}
            </span>
            <span className="block truncate text-muted-foreground text-xs">
                {t(`stock-movements.item_type.${transfer.item_type}` as const)}
                {transfer.item_sku !== null && ` · ${transfer.item_sku}`}
            </span>
            {/*
                The whole route, on a phone. From and To have their own columns from md
                up and drop out entirely below it — and a transfer that does not say
                where it went is not a record of a transfer. The arrow does the work a
                column heading does at wider widths.
            */}
            <span className="flex items-center gap-1 truncate pt-0.5 text-muted-foreground text-xs md:hidden">
                {transfer.from_warehouse}
                <ArrowRight aria-hidden className="size-3 shrink-0" />
                {transfer.to_warehouse}
            </span>
        </div>
    );
}

/**
 * One end of the transfer: the warehouse, and the site it stands on.
 *
 * Both, because "Main store" at two different sites is ordinary and the name alone
 * would name neither of them. The site is the quieter line for the same reason it is
 * the second sort key in the picker: you know where before you know what.
 */
export function EndpointCell({
    warehouse,
    site,
}: {
    warehouse: string;
    site: string;
}) {
    return (
        <div className="min-w-0">
            <span className="block truncate">{warehouse}</span>
            <span className="block truncate text-muted-foreground text-xs">
                {site}
            </span>
        </div>
    );
}

/**
 * How much moved.
 *
 * Unsigned, unlike the ledger's, and that is the difference between the two screens: a
 * movement's sign *is* its direction, while a transfer carries its direction in two
 * columns and a `-` here would only ask which end it referred to.
 */
export function QuantityCell({ quantity }: { quantity: string }) {
    return <span className="font-medium tabular-nums">{quantity}</span>;
}

/** Whatever somebody wrote down. Off by default — see the Columns panel. */
export function NotesCell({ notes }: { notes: string | null }) {
    if (notes === null || notes === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="block truncate text-muted-foreground" title={notes}>
            {notes}
        </span>
    );
}
