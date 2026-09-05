import { usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { WarehouseFormDialog } from '@/pages/warehouses/_components/warehouse-form-dialog';

/**
 * The one way to start a new warehouse, used in the toolbar and the empty state.
 *
 * Renders nothing without `warehouses.create`, and nothing when the workspace has no
 * sites — a warehouse belongs to one, so with none the form would open onto a picker
 * that cannot be satisfied. The empty state says so in words instead; see
 * `warehouses.no_sites`.
 */
export function NewWarehouseButton({
    variant,
}: {
    variant?: 'default' | 'outline';
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const { locations } = usePage<{ locations: App.Data.OptionData[] }>().props;
    const [open, setOpen] = useState(false);

    if (!can('warehouses.create') || locations.length === 0) {
        return null;
    }

    return (
        <>
            <Button variant={variant} onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('warehouses.create.trigger')}
            </Button>

            <WarehouseFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
