import { Link } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { index as products } from '@/routes/products';
import { index as movements } from '@/routes/stock-movements';
import { show } from '@/routes/warehouses';

/**
 * The two ways this screen can have nothing on it.
 *
 * They look identical and are different problems. An empty *catalogue* is fixed on the
 * products screen — there is nothing anywhere to put in any warehouse. An empty
 * *warehouse* is fixed by moving something into this one. Offering the wrong way out
 * sends somebody to a page that cannot help them, so which one this is arrives from the
 * server as `hasItems` rather than being guessed from a row count.
 */
export function WarehouseEmpty({
    warehouseId,
    hasItems,
}: {
    warehouseId: number;
    /** Whether the workspace has a catalogue at all. */
    hasItems: boolean;
}) {
    const { t } = useTranslation();

    if (!hasItems) {
        return (
            <EmptyState
                icon={Package}
                title={t('warehouses.detail.empty.title')}
                description={t('warehouses.detail.empty.description')}
                action={
                    <Button variant="outline" asChild>
                        <Link href={products()}>
                            {t('warehouses.detail.empty.action')}
                        </Link>
                    </Button>
                }
            />
        );
    }

    return (
        <EmptyState
            icon={Package}
            title={t('warehouses.detail.no_stock.title')}
            description={t('warehouses.detail.no_stock.description')}
            action={
                // Two ways on, because there are two reasons to be here. The second is
                // not decoration: the toolbar is not rendered when a list is genuinely
                // empty, so the Show control this state wants to point at is not on
                // screen — it has to be offered.
                <>
                    <Button variant="outline" asChild>
                        <Link href={movements()}>
                            {t('warehouses.detail.no_stock.action')}
                        </Link>
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link
                            href={show(
                                { warehouse: warehouseId },
                                { query: { show: 'all' } },
                            )}
                        >
                            {t('warehouses.detail.no_stock.action_all')}
                        </Link>
                    </Button>
                </>
            }
        />
    );
}
