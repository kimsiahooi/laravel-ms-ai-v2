import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { ProductFormDialog } from '@/pages/products/_components/product-form-dialog';

/**
 * The one way to start a new product, used in the list's toolbar and again in the
 * empty state. Renders nothing without `products.create`.
 */
export function NewProductButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    if (!can('products.create')) {
        return null;
    }

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('products.create.trigger')}
            </Button>

            <ProductFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
