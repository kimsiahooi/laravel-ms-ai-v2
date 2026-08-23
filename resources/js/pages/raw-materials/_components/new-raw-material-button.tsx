import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { RawMaterialFormDialog } from '@/pages/raw-materials/_components/raw-material-form-dialog';

/**
 * The one way to start a new material, used in the list's toolbar and again in the
 * empty state. Renders nothing without `raw-materials.create`.
 */
export function NewRawMaterialButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    if (!can('raw-materials.create')) {
        return null;
    }

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('raw-materials.create.trigger')}
            </Button>

            <RawMaterialFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
