import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { AddItemDialog } from '@/pages/stock-takes/_components/add-item-dialog';
import { COUNTED_HEADER_ID } from '@/pages/stock-takes/_components/count-input';
import { CountRow } from '@/pages/stock-takes/_components/count-row';
import { Difference } from '@/pages/stock-takes/_components/difference-cell';

type Line = App.Data.StockTakeItemData;

/**
 * The sheet itself: every line the take is asking about, in one table.
 *
 * **Not a `DataTable`.** That is a server-driven list — it searches, sorts and pages, and
 * each of those would be wrong here. A count is worked through in the order it was
 * snapshotted, and a page two that quietly hides the lines nobody has counted yet is how
 * a take gets posted half-finished. The whole sheet being on screen is also what makes
 * Enter-to-the-next-row a promise the page can keep.
 *
 * **The sheet is open.** v1 could only count what the warehouse already had a stock row
 * for, so a box found on a shelf the system had never heard of could not be recorded at
 * all — the one thing a physical count exists to discover.
 */
export function CountSheet({
    take,
    lines,
    itemsAvailable,
    editable,
}: {
    take: App.Data.StockTakeData;
    lines: Line[];
    /** The picker's entries. Absent unless the take is a draft. */
    itemsAvailable?: App.Data.StockItemOptionData[];
    /** Whether this visitor may write to this take at all. */
    editable: boolean;
}) {
    const { t } = useTranslation();
    const [adding, setAdding] = useState(false);

    // Posted or cancelled. Distinct from `editable`, which is also false for a reader
    // looking at a draft — that sheet has no boxes but its last column is still a
    // difference being aimed at rather than one that was applied.
    const finished = take.status !== 'draft';

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 className="font-medium">
                    {t('stock-takes.sheet.heading')}
                </h2>

                {editable && itemsAvailable !== undefined && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setAdding(true)}
                    >
                        <Plus />
                        {t('stock-takes.action.add_item')}
                    </Button>
                )}
            </div>

            {lines.length === 0 ? (
                // Not the app's EmptyState: a warehouse holding nothing is an ordinary
                // start to a count rather than a screen with nothing on it, and the way
                // forward is the button already sitting above this line.
                <p className="px-6 py-12 text-center text-muted-foreground text-sm">
                    {t('stock-takes.sheet.empty')}
                </p>
            ) : (
                // `Table` brings its own horizontal scroll, so a narrow phone scrolls
                // the sheet rather than the page.
                <Table>
                    <TableHeader className="bg-muted/40">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="pl-4">
                                {t('stock-takes.sheet.item')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('stock-takes.sheet.expected')}
                            </TableHead>
                            <TableHead
                                id={COUNTED_HEADER_ID}
                                className="text-right"
                            >
                                {t('stock-takes.sheet.counted')}
                            </TableHead>
                            {/*
                                Two different columns wearing one heading. Before posting
                                this is the gap somebody is working towards; after it, it
                                is what the ledger was actually told — and calling the
                                second one "Difference" would claim it had been
                                recomputed from the sheet, which is the v1 bug this
                                module exists to undo.
                            */}
                            <TableHead className="pr-4 text-right">
                                {t(
                                    finished
                                        ? 'stock-takes.sheet.applied'
                                        : 'stock-takes.sheet.difference',
                                )}
                            </TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {lines.map((line) => (
                            <CountRow
                                key={line.id}
                                line={line}
                                takeId={take.id}
                                editable={editable}
                                difference={
                                    <Difference
                                        line={line}
                                        finished={finished}
                                    />
                                }
                            />
                        ))}
                    </TableBody>
                </Table>
            )}

            {editable && itemsAvailable !== undefined && (
                <AddItemDialog
                    takeId={take.id}
                    options={itemsAvailable}
                    open={adding}
                    onOpenChange={setAdding}
                />
            )}
        </Card>
    );
}
