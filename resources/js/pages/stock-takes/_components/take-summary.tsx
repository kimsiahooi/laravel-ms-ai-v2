import { ClipboardCheck, ListChecks, Scale } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { useTimeZone } from '@/hooks/use-time-zone';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

type Take = App.Data.StockTakeData;

/**
 * Where the count has got to, above the sheet.
 *
 * Three numbers, and the middle one is the only reason somebody scrolls back up: how
 * much of a five-hundred-line sheet is done. The other two frame it — how big the job
 * is, and how much of what has been counted disagrees with the system.
 *
 * **The third number counts lines, it does not sum them.** v1 showed a signed total
 * variance, which added ten kilograms of flour to minus ten bolts and reported zero
 * while still posting two adjustments. Quantities in different units are not
 * addable; a count of the lines that differ is true whatever they are measured in.
 */
export function TakeSummary({ take }: { take: Take }) {
    return (
        <div className="space-y-4">
            {/* Two up on a phone, three from `sm`. Three across at 375 leaves each card
                about 100px wide, which overflows the row by a few pixels and wraps every
                label onto a third line — and a count sheet is read on a phone more than
                anywhere else. The last card spans the empty cell rather than sitting in
                half of one, so the row below reads as deliberate instead of ragged. */}
            <div className="grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 [&>:last-child]:col-span-2 sm:[&>:last-child]:col-span-1">
                <Stat
                    icon={ListChecks}
                    value={take.line_count}
                    label="stock-takes.summary.lines"
                />
                {/* A fraction rather than a bare count: "42" answers nothing without
                    the sheet it is out of, and this is the figure being watched. */}
                <Stat
                    icon={ClipboardCheck}
                    value={`${take.counted_count} / ${take.line_count}`}
                    label="stock-takes.summary.counted"
                />
                <Stat
                    icon={Scale}
                    value={take.variance_count}
                    label="stock-takes.summary.variances"
                    alert={take.variance_count > 0}
                />
            </div>

            <TakeMeta take={take} />
        </div>
    );
}

/**
 * One figure and what it counts, in the shape the warehouse detail screen established:
 * the label first, so a number is never put in front of somebody before they have been
 * told what it is a number of.
 *
 * **Colour only when there is something to colour.** A permanently amber "0 differences"
 * is a warning about nothing, and a reader learns to ignore it long before there is ever
 * anything in it. It goes in the icon and the figure rather than a background wash —
 * a tint behind `muted-foreground` label text is what drops it under AA.
 */
function Stat({
    icon: Icon,
    value,
    label,
    alert,
}: {
    icon: ComponentType<{ className?: string }>;
    value: ReactNode;
    label: TranslationKey;
    alert?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Card className={cn('gap-0 py-4', alert && 'border-chart-3/50')}>
            <CardContent className="px-4">
                <div className="flex items-start justify-between gap-2">
                    {/*
                        Two lines' worth of room whether or not the label needs them, so
                        the three figures sit on one line across the row. At 375 in
                        Malay two of these labels wrap and one does not, which put the
                        numbers at three different heights and made the row read as
                        broken rather than as a set.
                    */}
                    <p className="min-h-[2lh] font-medium text-muted-foreground text-xs leading-tight sm:text-sm">
                        {t(label)}
                    </p>
                    <Icon
                        className={cn(
                            'size-4 shrink-0',
                            alert ? 'text-chart-3' : 'text-muted-foreground',
                        )}
                    />
                </div>

                <p
                    className={cn(
                        'font-semibold text-2xl tabular-nums tracking-tight sm:text-3xl',
                        alert && 'text-chart-3',
                    )}
                >
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

/**
 * Who and when, and whatever the count was opened for.
 *
 * **Two people, never one.** The take records who opened it and who posted it as
 * separate columns because they are usually separate people — v1 overwrote the creator
 * with the poster, so afterwards only one of them was knowable and it was the wrong one
 * for answering "who counted this".
 *
 * The posting half appears only once there is one. An empty "Posted by —" on every
 * draft is a row that says nothing on the screens where it is shown most.
 */
function TakeMeta({ take }: { take: Take }) {
    const { t } = useTranslation();
    const timeZone = useTimeZone();

    return (
        <dl className="grid max-w-3xl gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            <Row label="stock-takes.summary.opened_by">
                {/* Null once the person has been removed. i18n-allow */}
                {take.created_by ?? '—'}
            </Row>
            <Row label="stock-takes.column.created_at">
                <time dateTime={take.created_at} className="tabular-nums">
                    {formatDateTime(take.created_at, timeZone)}
                </time>
            </Row>

            {take.posted_at !== null && (
                <>
                    <Row label="stock-takes.summary.posted_by">
                        {/* i18n-allow */}
                        {take.posted_by ?? '—'}
                    </Row>
                    <Row label="stock-takes.column.posted_at">
                        <time
                            dateTime={take.posted_at}
                            className="tabular-nums"
                        >
                            {formatDateTime(take.posted_at, timeZone)}
                        </time>
                    </Row>
                </>
            )}

            {take.notes !== null && (
                <div className="sm:col-span-2">
                    <dt className="text-muted-foreground text-xs">
                        {t('stock-takes.summary.notes')}
                    </dt>
                    {/* The counter's own words, so line breaks are theirs to keep. */}
                    <dd className="whitespace-pre-line">{take.notes}</dd>
                </div>
            )}
        </dl>
    );
}

/** One label-over-value pair. A `div` inside the list keeps the two together. */
function Row({
    label,
    children,
}: {
    label: TranslationKey;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div>
            <dt className="text-muted-foreground text-xs">{t(label)}</dt>
            <dd>{children}</dd>
        </div>
    );
}
