import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CategoryFormDialog } from '@/pages/categories/_components/category-form-dialog';

/**
 * The one way to start a new category, used in two places: the list's toolbar, and the
 * empty state — where it is the only thing on screen worth clicking.
 *
 * Renders nothing for someone without `categories.create`, so the empty state falls
 * back to explaining rather than offering an action that would 403.
 */
export function NewCategoryButton({
    variant,
}: {
    variant?: 'default' | 'outline';
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    if (!can('categories.create')) {
        return null;
    }

    return (
        <>
            <Button variant={variant} onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('categories.create.trigger')}
            </Button>

            <CategoryFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
