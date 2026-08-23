import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CustomerFormDialog } from '@/pages/customers/_components/customer-form-dialog';

/**
 * The one way to start a new customer, used in the list's toolbar and again in the
 * empty state. Renders nothing without `customers.create`.
 */
export function NewCustomerButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    if (!can('customers.create')) {
        return null;
    }

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('customers.create.trigger')}
            </Button>

            <CustomerFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
