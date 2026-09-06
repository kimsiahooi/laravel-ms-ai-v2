import type { ReactNode } from 'react';
import { useId } from 'react';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { CountInput } from './count-input';

type Line = App.Data.StockTakeItemData;

/**
 * One line of the sheet: what the system expects, what was found, and the gap.
 *
 * The gap arrives already rendered rather than being worked out here. Whether that
 * column is a difference still being aimed at or the delta that actually posted is a
 * decision about the *column*, and {@see CountSheet} is where the heading above it is
 * chosen — one place deciding both is what keeps the two from disagreeing.
 */
export function CountRow({
    line,
    takeId,
    editable,
    difference,
}: {
    line: Line;
    takeId: number;
    /** Whether this visitor may write to this take. A reader gets text, not a box. */
    editable: boolean;
    difference: ReactNode;
}) {
    const { t } = useTranslation();
    const nameId = useId();

    return (
        <TableRow>
            <TableCell className="max-w-[14rem] py-3 pl-4 sm:max-w-[24rem]">
                <span id={nameId} className="block truncate font-medium">
                    {/* Force-deleted from the catalogue after the snapshot. The line
                        still counted something, so it keeps its row. i18n-allow */}
                    {line.name ?? '—'}
                </span>
                {line.sku !== null && (
                    <span className="block truncate font-mono text-muted-foreground text-xs">
                        {line.sku}
                    </span>
                )}
            </TableCell>

            <TableCell className="whitespace-nowrap py-3 text-right tabular-nums">
                {line.system_quantity}{' '}
                {/* No unit when the catalogue row was force-deleted after the
                    snapshot — the same reason the name falls back to a dash.
                    The quantity is still real, so it is shown bare. */}
                {line.unit !== null && (
                    <span className="text-muted-foreground text-xs">
                        {t(`units.symbol.${line.unit}` as const)}
                    </span>
                )}
            </TableCell>

            <TableCell className="py-3 text-right">
                {editable ? (
                    <CountInput line={line} takeId={takeId} labelId={nameId} />
                ) : (
                    <span
                        className={cn(
                            'tabular-nums',
                            line.counted_quantity === null &&
                                'text-muted-foreground text-xs',
                        )}
                    >
                        {line.counted_quantity ??
                            t('stock-takes.sheet.not_counted')}
                    </span>
                )}
            </TableCell>

            <TableCell className="py-3 pr-4 text-right">{difference}</TableCell>
        </TableRow>
    );
}
