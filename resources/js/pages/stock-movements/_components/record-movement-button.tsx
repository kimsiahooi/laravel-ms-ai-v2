import { usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { MovementFormDialog } from '@/pages/stock-movements/_components/movement-form-dialog';

type PageProps = {
    warehouses: App.Data.WarehouseOptionData[];
    items: App.Data.StockItemOptionData[];
};

/**
 * The one way to record a movement.
 *
 * Renders nothing without the permission, and nothing when there is no warehouse or
 * nothing to move — the form's two required pickers would both be empty, and a form
 * that cannot be completed is worse than a sentence saying why. The empty states say
 * it instead, and point at the screen that fixes it.
 */
export function RecordMovementButton({
    variant,
}: {
    variant?: 'default' | 'outline';
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const { warehouses, items } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);

    if (
        !can('stock-movements.create') ||
        warehouses.length === 0 ||
        items.length === 0
    ) {
        return null;
    }

    return (
        <>
            <Button variant={variant} onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('stock-movements.create.trigger')}
            </Button>

            <MovementFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
