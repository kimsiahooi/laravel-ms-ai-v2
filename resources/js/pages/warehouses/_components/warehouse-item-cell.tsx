import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

type Item = App.Data.WarehouseItemData;

/**
 * What the item is, and whether it wants restocking.
 *
 * **The badge carries no explanation, and does not need one.** Both numbers it is
 * derived from — what is on hand and the level somebody set — are columns on the same
 * row, always visible, at every width. v1 put the same two numbers in a tooltip, which
 * on a phone is a sentence nobody can reach saying what is already on screen.
 *
 * Amber rather than `destructive`: a level reached is a thing to act on this week, not
 * a failure. The word says it too — the colour is reinforcement for someone skimming
 * the column, never the only channel.
 *
 * **On hand rides under the name on a phone.** Its own column drops out below `sm`,
 * because three columns and an editable box in 375 pixels puts the box behind a
 * sideways scroll — and the box is what somebody came here to use. The number cannot
 * simply go, though: it is half of what the badge above is claiming, so it moves here
 * rather than disappearing. Same trade the warehouse list makes with the site.
 */
export function WarehouseItemCell({ item }: { item: Item }) {
    const { t } = useTranslation();

    return (
        <div className="min-w-0">
            <div className="flex items-center gap-2">
                <span className="truncate font-medium">{item.name}</span>
                {item.needs_reorder && (
                    <Badge
                        variant="secondary"
                        className="shrink-0 border-chart-3/25 bg-chart-3/10 font-normal text-chart-3"
                    >
                        {t('warehouses.detail.badge')}
                    </Badge>
                )}
            </div>
            <span className="block truncate text-muted-foreground text-xs">
                {t(`stock-movements.item_type.${item.type}` as const)}
                {` · ${item.sku}`}
            </span>
            <span className="block truncate text-muted-foreground text-xs sm:hidden">
                {t('common.field.on_hand', {
                    quantity: `${item.on_hand} ${t(`units.symbol.${item.unit}` as const)}`,
                })}
            </span>
        </div>
    );
}
