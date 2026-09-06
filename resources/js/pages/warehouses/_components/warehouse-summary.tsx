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
 * The second card only takes on colour when the number is not zero. A permanently amber
 * panel saying "0 needs reorder" is a warning about nothing, and a reader learns to skip
 * it long before there is something in it.
 */
export function WarehouseSummary({
    summary,
}: {
    summary: { in_stock: number; needs_reorder: number };
}) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
        <Card className={cn(alert && 'border-chart-3/40 bg-chart-3/5')}>
            <CardContent className="flex items-center gap-3 p-5">
                <Icon
                    className={cn(
                        'size-5 shrink-0',
                        alert ? 'text-chart-3' : 'text-muted-foreground',
                    )}
                />
                <div className="min-w-0">
                    <p className="font-semibold text-2xl tabular-nums">
                        {value}
                    </p>
                    <p className="text-sm">{t(label)}</p>
                    <p className="text-muted-foreground text-xs">{t(hint)}</p>
                </div>
            </CardContent>
        </Card>
    );
}
