import { Package, TriangleAlert } from 'lucide-react';
import type { ComponentType } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

/**
 * The two numbers above the list, over the whole warehouse rather than the page.
 *
 * Counted by the same SQL that decides each row's badge — see `WarehouseInventory` — so
 * "3 need reorder" and three badges in the table are the same fact rather than two
 * agreeing calculations.
 *
 * **Capped width, and it is not cosmetic.** Two numbers stretched across a desktop are
 * two mostly-empty panels, and the eye reads the emptiness as the content. Stat cards
 * want to be about as wide as the number and its label, so the row stops at `max-w-2xl`
 * and sits left rather than spanning whatever the viewport happens to be.
 *
 * Two columns at every width, including a phone. These are a pair — "how much is here"
 * against "how much of it is short" — and stacked they read as two unrelated facts.
 */
export function WarehouseSummary({
    summary,
}: {
    summary: { in_stock: number; needs_reorder: number };
}) {
    return (
        <div className="grid max-w-2xl grid-cols-2 gap-3 sm:gap-4">
            <Stat
                icon={Package}
                value={summary.in_stock}
                label="warehouses.detail.in_stock"
                hint="warehouses.detail.in_stock_hint"
            />
            <Stat
                icon={TriangleAlert}
                value={summary.needs_reorder}
                label="warehouses.detail.needs_reorder"
                hint="warehouses.detail.needs_reorder_hint"
                alert={summary.needs_reorder > 0}
            />
        </div>
    );
}

/**
 * One number, and what it counts.
 *
 * **The label comes first and the number is the loud thing.** It read the other way
 * round — a large number, then its label, then a note — which puts a bare `2` in front
 * of somebody before they have been told what two of anything it is. Naming it first
 * costs nothing to skim past and means the figure lands already understood.
 *
 * The icon sits in the top corner at label size, because that is all it is: a marker
 * for a card being scanned rather than read. Vertically centred against the whole
 * stack, as it was, it lines up with nothing in particular and pushes the text into a
 * column of its own.
 *
 * **Colour only when the number is not zero.** A permanently amber panel saying "0
 * needs reorder" is a warning about nothing, and a reader learns to skip it long before
 * there is ever something in it.
 *
 * **The colour is in the border, the icon and the figure — not in a background wash.**
 * A tinted panel was the obvious way to do it and it fails: `muted-foreground` clears
 * AA against a plain card by 4.74:1, which is thin enough that a 5% amber overlay drops
 * the label and the note to 4.40:1, and thinning the tint until they pass leaves two
 * hundredths of margin rather than a decision. Colouring the number costs nothing —
 * at 30px semibold it is large text, which needs 3:1 and has 5.71:1 — and a big amber
 * figure is a louder signal than a wash was.
 */
function Stat({
    icon: Icon,
    value,
    label,
    hint,
    alert,
}: {
    icon: ComponentType<{ className?: string }>;
    value: number;
    label: TranslationKey;
    hint: TranslationKey;
    alert?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Card
            className={cn(
                // The primitive is built for a header/content/footer stack — six units
                // of vertical padding and six of gap. This holds three lines.
                'gap-0 py-4',
                alert && 'border-chart-3/50',
            )}
        >
            <CardContent className="px-4">
                <div className="flex items-start justify-between gap-2">
                    {/*
                        Two lines' worth of room whether or not the label needs them,
                        so the figures below line up across the row. Not padding: at
                        375 in Malay, "Perlu pesan semula" wraps and "Item ada stok"
                        does not, which put the two numbers seventeen pixels apart and
                        made the pair read as broken rather than as a pair. `lh` is the
                        element's own line-height, so this follows the type size.
                    */}
                    <p className="min-h-[2lh] font-medium text-muted-foreground text-sm leading-tight">
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
                        'font-semibold text-3xl tabular-nums tracking-tight',
                        alert && 'text-chart-3',
                    )}
                >
                    {value}
                </p>

                <p className="mt-0.5 text-muted-foreground text-xs">
                    {t(hint)}
                </p>
            </CardContent>
        </Card>
    );
}
