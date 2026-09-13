import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { create } from '@/routes/sales-orders';

/**
 * The one way to take an order.
 *
 * A link, not a dialog trigger: an order is a document with a header and any number of
 * lines, and a dialog that has to scroll to reach its own submit button is the wrong
 * container for it. See `form.tsx`.
 *
 * Renders nothing without the permission. It is convenience rather than a boundary:
 * AuthorizeTenantRoute refuses the route regardless, and a person who types the URL gets
 * the same answer.
 */
export function NewOrderButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();

    if (!can('sales-orders.create')) {
        return null;
    }

    return (
        <Button asChild>
            <Link href={create()}>
                <Plus className="size-4" />
                {t('sales-orders.action.new')}
            </Link>
        </Button>
    );
}
