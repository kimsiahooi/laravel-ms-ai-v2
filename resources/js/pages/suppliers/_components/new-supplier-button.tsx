import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { SupplierFormDialog } from '@/pages/suppliers/_components/supplier-form-dialog';

/**
 * The one way to start a new supplier, used in the list's toolbar and again in the
 * empty state. Renders nothing without `suppliers.create`, so the empty state explains
 * rather than offering an action that would 403.
 */
export function NewSupplierButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    if (!can('suppliers.create')) {
        return null;
    }

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('suppliers.create.trigger')}
            </Button>

            <SupplierFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
