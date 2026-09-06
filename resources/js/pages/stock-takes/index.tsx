import { Head, setLayoutProps } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { InlineLink } from '@/components/inline-link';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { NewTakeButton } from '@/pages/stock-takes/_components/new-take-dialog';
import { TakeStatusBadge } from '@/pages/stock-takes/_components/take-status-badge';
import { index, show } from '@/routes/stock-takes';
import type { Paginated, ResourceFilters } from '@/types';
import type { TranslationKey } from '@/types/lang';

/** Generated from App\Data\StockTakeData — `bun run types:generate`. */
type Take = App.Data.StockTakeData;

type Props = {
    takes: Paginated<Take>;
    filters: ResourceFilters;
    /** Every warehouse — the create dialog's picker, and its only required field. */
    warehouses: App.Data.WarehouseOptionData[];
};

/**
 * The words for each status, as a `Record` over the enum rather than a hand-written
 * array: a fourth status is then a compile error here instead of a value the filter
 * quietly cannot select. Insertion order is the lifecycle order, which is the order
 * the menu shows them in.
 */
const STATUS_LABEL: Record<App.Enums.StockTakeStatus, TranslationKey> = {
    draft: 'stock-takes.status.draft',
    posted: 'stock-takes.status.posted',
    cancelled: 'stock-takes.status.cancelled',
};

const STATUS_OPTIONS = Object.entries(STATUS_LABEL).map(([value, label]) => ({
    value,
    label,
}));

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 */
const column = columnsFor<Take>();

const columns = column.columns([
    column.accessor('id', {
        ...heading('stock-takes.column.id', { width: 'w-20' }),
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {row.original.id}
            </span>
        ),
    }),
    column.accessor('warehouse', {
        ...heading('stock-takes.column.warehouse', { width: 'max-w-[18rem]' }),
        cell: ({ row }) => <WarehouseCell take={row.original} />,
    }),
    column.accessor('status', {
        ...heading('stock-takes.column.status'),
        cell: ({ row }) => <TakeStatusBadge status={row.original.status} />,
    }),
    column.accessor('counted_count', {
        ...heading('stock-takes.column.progress', { align: 'end' }),
        cell: ({ row }) => <ProgressCell take={row.original} />,
    }),
    column.accessor('variance_count', {
        ...heading('stock-takes.column.variances', { align: 'end' }),
        cell: ({ row }) => <VarianceCell count={row.original.variance_count} />,
    }),
    column.accessor('created_at', {
        ...heading('stock-takes.column.created_at', { hideBelow: 'sm' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
]);

/**
 * Which warehouse was counted, and the way in to the sheet.
 *
 * The warehouse name is the link, not a row-menu entry: a count sheet reached only
 * through a menu is one most people never find, and everybody on this list already has
 * `stock-takes.view`. Bold *and* underlined — the weight makes it the row's name, the
 * link styling makes it followable. Same trade the warehouse list makes.
 *
 * **No `aria-label`, deliberately.** "Open count sheet" is the one sentence that would
 * fit, and repeating it on twenty-five rows would replace the only thing telling them
 * apart with the same four words. The warehouse name announced under a "Warehouse"
 * header is what a screen reader should hear.
 *
 * The site rides underneath at every width rather than on a phone only. It has no
 * column of its own here, and two sites with a "Main store" are ordinary enough that
 * without it a reader cannot tell which building was counted.
 */
function WarehouseCell({ take }: { take: Take }) {
    return (
        <div className="min-w-0">
            <InlineLink
                href={show({ stockTake: take.id })}
                className="block truncate font-medium"
            >
                {take.warehouse}
            </InlineLink>
            <span className="block truncate text-muted-foreground text-xs">
                {take.site}
            </span>
        </div>
    );
}

/**
 * How far the count has got: lines counted out of lines on the sheet.
 *
 * A fraction rather than a bar or a percentage. "47 / 50" says both how much is done
 * and how big the job is, which is what somebody deciding whether to go and finish it
 * needs; a bar says neither number, and a percentage rounds three uncounted lines out
 * of a hundred to 97% — the same thing it says for one.
 *
 * **A finished sheet stops being muted.** Every other row is a job in progress and
 * reads as quiet grey; the one that is ready to post is the one worth noticing, and it
 * earns that with weight rather than with a colour it would have to share meaning with
 * the differences column. An empty sheet is not finished, whatever `0 / 0` divides to.
 */
function ProgressCell({ take }: { take: Take }) {
    const complete =
        take.line_count > 0 && take.counted_count === take.line_count;

    return (
        <span
            className={cn(
                'tabular-nums',
                complete ? 'font-medium' : 'text-muted-foreground',
            )}
        >
            {take.counted_count} / {take.line_count}
        </span>
    );
}

/**
 * How many counted lines disagree with what the system expected.
 *
 * **A count of lines, never a sum of them.** Adding signed variances across mixed units
 * cancels ten kilograms of flour against ten bolts and reports nothing wrong while two
 * corrections are still waiting to be posted — see `StockTakeData` on why the DTO
 * carries no total. A number of lines is honest whatever the units are.
 *
 * Amber and muted zero, the shape {@see ReorderCountCell} uses for the same kind of
 * number, so somebody who has seen one recognises the other. Without its warning
 * triangle, though: a reorder level reached is a thing to act on, while a difference
 * found is the count doing its job, and a fault icon on a correct result would say
 * otherwise.
 *
 * **Zero is printed, not hidden.** A blank cell reads as "not calculated", and on a
 * list whose purpose is finding the count that needs a person, "nothing differs here"
 * is an answer worth printing. It is muted, so a column of them stays quiet.
 */
function VarianceCell({ count }: { count: number }) {
    if (count === 0) {
        return <span className="text-muted-foreground tabular-nums">0</span>;
    }

    return (
        <span className="font-medium text-chart-3 tabular-nums">{count}</span>
    );
}

export default function StockTakesIndex({ takes, filters, warehouses }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('stock-takes.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('stock-takes.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('stock-takes.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('stock-takes.subtitle')}
                    </p>
                </div>
                <NewTakeButton warehouses={warehouses} />
            </div>

            <DataTable
                href={index().url}
                tableKey="stock-takes"
                page={takes}
                filters={filters}
                columns={columns}
                getRowId={(take) => String(take.id)}
                only={['takes']}
                searchPlaceholder={t('stock-takes.search_placeholder')}
                // The status filter is always worth showing, unlike the warehouse
                // filters elsewhere: every list has all three statuses available to it
                // from the first take, and "the ones still being counted" is the
                // question this screen is usually opened to answer.
                toolbar={(filter) => (
                    <FilterPanel filter={filter}>
                        <SelectFilter
                            value={filter.values.status ?? ''}
                            onChange={(status) => filter.set('status', status)}
                            options={STATUS_OPTIONS}
                            label="stock-takes.filter.status"
                            allLabel="stock-takes.filter.all_statuses"
                        />
                    </FilterPanel>
                )}
                emptyState={
                    <EmptyState
                        icon={ClipboardList}
                        title={t('stock-takes.empty.title')}
                        description={t('stock-takes.empty.description')}
                        action={<NewTakeButton warehouses={warehouses} />}
                    />
                }
            />
        </>
    );
}
