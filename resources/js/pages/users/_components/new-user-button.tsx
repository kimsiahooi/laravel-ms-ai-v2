import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { UserFormDialog } from '@/pages/users/_components/user-form-dialog';

/**
 * Adds somebody. Owns its own dialog, so both the header and the empty state can render one
 * without the page holding state neither of them needs.
 */
export function NewUserButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    if (!can('users.create')) {
        return null;
    }

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <UserPlus className="size-4" />
                {t('users.action.new')}
            </Button>

            <UserFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
