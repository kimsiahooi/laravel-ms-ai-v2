import { Head, setLayoutProps } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CountSheet } from '@/pages/stock-takes/_components/count-sheet';
import { PostTakeDialog } from '@/pages/stock-takes/_components/post-take-dialog';
import { TakeStatusBadge } from '@/pages/stock-takes/_components/take-status-badge';
import { TakeSummary } from '@/pages/stock-takes/_components/take-summary';
import { index, show } from '@/routes/stock-takes';

/** Generated from App\Data\StockTakeData — `bun run types:generate`. */
type Take = App.Data.StockTakeData;

type Props = {
    take: Take;
    /** The whole sheet, unpaginated — see {@see CountSheet} on why. */
    items: App.Data.StockTakeItemData[];
    /**
     * What may still be added to the count. The controller sends it only for a draft,
     * because nothing can be added to a sheet that has been posted or cancelled — and
     * a picker full of options that the server will refuse is worse than no picker.
     */
    items_available?: App.Data.StockItemOptionData[];
};

/**
 * The count sheet — the screen the whole module exists for.
 *
 * **Counts are saved as they are entered.** v1 held the sheet in browser state and
 * posted the lot at the end, so a refresh, a flat battery or a warehouse's patchy wifi
 * threw away an afternoon of counting. Every box here writes its own line the moment it
 * is left, which is also what lets two people work the same sheet from two phones.
 *
 * **Nothing on this page computes what will be posted.** The differences beside each
 * row are display, and they say so: the arithmetic that moves stock happens under the
 * row lock inside `StockService::setLevel()` at posting time. That is the race v1 had —
 * it worked out a delta from a snapshot read hours earlier and then applied it against
 * whatever the shelf had become in the meantime.
 *
 * **Once it is over, it is over.** A posted or cancelled take renders with no inputs, no
 * way to add a line and no footer, and its last column shows what actually posted rather
 * than a difference recomputed from the sheet. The two numbers are not the same, and
 * showing the recomputed one would have the screen quietly disagree with the ledger.
 */
export default function StockTakeShow({
    take,
    items,
    items_available: itemsAvailable,
}: Props) {
    const { t } = useTranslation();
    const { can } = usePermissions();

    // Draft is the only status anything can be written to, and every write on this
    // screen — a count, a found item, the posting itself — is gated behind the one
    // permission that opened the take. See TenantPermissions::ROUTE_OVERRIDES.
    const editable = take.status === 'draft' && can('stock-takes.create');

    setLayoutProps({
        breadcrumbs: [
            { title: t('stock-takes.title'), href: index() },
            {
                title: take.warehouse,
                href: show({ stockTake: take.id }),
            },
        ],
    });

    return (
        <>
            <Head title={take.warehouse} />

            <div className="min-w-0 space-y-1">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {take.warehouse}
                    </h1>
                    {/*
                        Beside the name rather than in the summary below. Whether this
                        sheet can still be written to is the first thing to know about
                        it, and it is the answer to why the boxes have gone.
                    */}
                    <TakeStatusBadge status={take.status} />
                </div>
                <p className="text-muted-foreground text-sm">{take.site}</p>
            </div>

            <TakeSummary take={take} />

            <CountSheet
                take={take}
                lines={items}
                itemsAvailable={itemsAvailable}
                editable={editable}
            />

            {editable && <PostTakeDialog take={take} />}
        </>
    );
}
