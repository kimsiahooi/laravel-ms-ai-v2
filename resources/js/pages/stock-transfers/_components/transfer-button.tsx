import { usePage } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { TransferFormDialog } from '@/pages/stock-transfers/_components/transfer-form-dialog';

type PageProps = {
    warehouses: App.Data.WarehouseOptionData[];
    items: App.Data.StockItemOptionData[];
};

/**
 * The one way to record a transfer.
 *
 * Renders nothing without the permission, nothing with fewer than **two** warehouses,
 * and nothing with an empty catalogue. Two, not one: a transfer needs somewhere to
 * come from and somewhere to go, and a single warehouse can only ever fail the
 * `different` rule. The empty states say so instead, and point at the screen that fixes
 * it — a form that cannot be completed is worse than a sentence explaining why.
 */
export function TransferButton({
    variant,
}: {
    variant?: 'default' | 'outline';
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const { warehouses, items } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);

    if (
        !can('stock-transfers.create') ||
        warehouses.length < 2 ||
        items.length === 0
    ) {
        return null;
    }

    return (
        <>
            <Button variant={variant} onClick={() => setOpen(true)}>
                <ArrowLeftRight className="size-4" />
                {t('stock-transfers.create.trigger')}
            </Button>

            <TransferFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
