import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Row = App.Data.StockAvailabilityData;

/**
 * What the chosen warehouse holds against what a document is about to take out of it.
 *
 * Without it the warehouse picker is chosen blind: you pick a building, press the button, and
 * the refusal is the first thing that tells you the goods were never there — after a round trip,
 * and naming one item out of twelve. The same argument {@see OnHandLine} makes about a quantity
 * box, on a document instead of a field.
 *
 * **Shared, because it is the same question whichever document asks it.** A sales order issues
 * products and a purchase return sends raw materials back; `App\Data\StockAvailabilityData`
 * already serves both, so this reads `orders.availability.*` and says "Item" rather than naming
 * either. It started in sales orders' `_components/` and moved here when the returns module
 * became its second consumer — the rule of three's "second, if the logic is non-trivial".
 *
 * **One row per item, not per line.** A document may carry the same item twice at two prices,
 * and what can be moved depends on the two added together — `App\Support\OrderAvailability`
 * does that addition once, for this panel and for the Action that refuses. So a two-line document
 * for one item shows one row saying it needs ten, which is the number that decides.
 *
 * **`short` is the server's answer, not a comparison made here.** Same reason the warehouse
 * screen computes `needs_reorder` in SQL: the row's warning and the despatch's refusal are the
 * same question, and a JavaScript `<` on two decimal strings would be a second answer to it —
 * one that disagrees the first time a quantity has four places.
 *
 * **It never disables anything, and the hint says so out loud.** The figures are read without
 * a lock, so they are stale the moment they arrive; showing three must not stop somebody
 * moving four that a colleague's delivery has just made possible. The button above stays live
 * and the server settles it.
 */
export function AvailabilityPanel({ rows }: { rows: Row[] }) {
    const { t } = useTranslation();

    if (rows.length === 0) {
        // Every line points at an item that has been hard-deleted. Nothing will be issued,
        // and saying so beats an empty table that looks like a failed lookup.
        return (
            <p className="text-muted-foreground text-sm">
                {t('orders.availability.empty')}
            </p>
        );
    }

    return (
        <div className="space-y-2">
            <div className="space-y-1">
                <h3 className="font-medium text-sm">
                    {t('orders.availability.heading')}
                </h3>
                <p className="max-w-2xl text-muted-foreground text-xs">
                    {t('orders.availability.hint')}
                </p>
            </div>

            {/* `Table` brings its own horizontal scroll, so three columns and a badge scroll
                inside the card on a phone rather than taking the page with them. */}
            <div className="overflow-hidden rounded-md border">
                <Table>
                    <TableHeader className="bg-muted/40">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="pl-4">
                                {t('orders.availability.item')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('orders.availability.required')}
                            </TableHead>
                            <TableHead className="pr-4 text-right">
                                {t('orders.availability.on_hand')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {rows.map((row) => (
                            <AvailabilityRow key={row.item} row={row} />
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

/**
 * One item's line.
 *
 * The shortfall is marked on the *available* figure rather than on the whole row, because that
 * is the number that is wrong for the job. It carries a word as well as a colour: a red number
 * on its own is not a difference every reader can see, and the badge is what a screen reader
 * has to read out.
 */
function AvailabilityRow({ row }: { row: Row }) {
    const { t } = useTranslation();

    const unit = t(`units.symbol.${row.unit}` as const);

    return (
        <TableRow>
            <TableCell className="py-3 pl-4">
                <div className="min-w-0">
                    <span className="block truncate font-medium">
                        {row.name}
                    </span>
                    <span className="block truncate font-mono text-muted-foreground text-xs">
                        {row.sku}
                    </span>
                </div>
            </TableCell>
            <TableCell className="whitespace-nowrap py-3 text-right tabular-nums">
                <Quantity value={row.required} unit={unit} />
            </TableCell>
            <TableCell className="whitespace-nowrap py-3 pr-4 text-right">
                <span className="inline-flex items-center justify-end gap-2">
                    {row.short && (
                        <Badge variant="destructive">
                            {t('orders.availability.short')}
                        </Badge>
                    )}
                    <span
                        className={cn(
                            'tabular-nums',
                            row.short && 'font-medium text-destructive',
                        )}
                    >
                        <Quantity value={row.on_hand} unit={unit} />
                    </span>
                </span>
            </TableCell>
        </TableRow>
    );
}

/**
 * A number and the unit it is counted in.
 *
 * The unit is muted and small rather than the same weight as the figure, so a column of them
 * reads as numbers with a label attached rather than as two columns crammed into one — the
 * same treatment the order's own line table gives a quantity.
 */
function Quantity({ value, unit }: { value: string; unit: string }) {
    return (
        <>
            {value}
            <span className="ml-1 text-muted-foreground text-xs">{unit}</span>
        </>
    );
}
