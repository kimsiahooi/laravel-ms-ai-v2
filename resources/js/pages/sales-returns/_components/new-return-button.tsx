import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { index as salesOrders } from '@/routes/sales-orders';

/**
 * Starts a return — by going to the despatches it could be raised against.
 *
 * **It does not open the return form, and that is the point.** A return credits one despatch,
 * so the form cannot be filled in without one; and a picker of every fulfilled order a
 * workspace has ever had is a list nobody capped — unlike the catalogue, fulfilled orders
 * accumulate forever. So this goes where the decision actually gets made: the sales orders
 * list, already filtered to what has shipped.
 *
 * Renders nothing without the permission. Convenience rather than a boundary — the route is
 * refused regardless.
 */
export function NewReturnButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();

    if (!can('sales-returns.create')) {
        return null;
    }

    return (
        <Button asChild>
            {/* The query goes in wayfinder's `options`, not its route args — the first
                parameter is the tenant. */}
            <Link
                href={salesOrders(undefined, {
                    // `fulfilled`, not the purchase side's `received`: these are the two
                    // status vocabularies, and the wrong one filters to nothing at all.
                    query: { status: 'fulfilled' },
                })}
            >
                <Plus className="size-4" />
                {t('sales-returns.action.new')}
            </Link>
        </Button>
    );
}
