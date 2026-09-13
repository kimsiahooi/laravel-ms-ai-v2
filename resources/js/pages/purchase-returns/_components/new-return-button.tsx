import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { index as purchaseOrders } from '@/routes/purchase-orders';

/**
 * Starts a return — by going to the deliveries it could be raised against.
 *
 * **It does not open the return form, and that is the point.** A return credits one delivery,
 * so the form cannot be filled in without one; and a picker of every received order a
 * workspace has ever had is a list nobody capped — unlike the catalogue, received orders
 * accumulate forever. So this goes where the decision actually gets made: the purchase orders
 * list, already filtered to what has arrived.
 *
 * Renders nothing without the permission. Convenience rather than a boundary — the route is
 * refused regardless.
 */
export function NewReturnButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();

    if (!can('purchase-returns.create')) {
        return null;
    }

    return (
        <Button asChild>
            {/* The query goes in wayfinder's `options`, not its route args — the first
                parameter is the tenant. */}
            <Link
                href={purchaseOrders(undefined, {
                    query: { status: 'received' },
                })}
            >
                <Plus className="size-4" />
                {t('purchase-returns.action.new')}
            </Link>
        </Button>
    );
}
