import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { LocationFormDialog } from '@/pages/locations/_components/location-form-dialog';

/**
 * The one way to start a new site, used in two places: the list's toolbar, and the
 * empty state — where it is the only thing on screen worth clicking.
 *
 * Renders nothing for someone without `locations.create`, so the empty state falls
 * back to explaining rather than offering an action that would 403.
 */
export function NewLocationButton({
    variant,
}: {
    variant?: 'default' | 'outline';
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    if (!can('locations.create')) {
        return null;
    }

    return (
        <>
            <Button variant={variant} onClick={() => setOpen(true)}>
                <Plus className="size-4" />
                {t('locations.create.trigger')}
            </Button>

            <LocationFormDialog open={open} onOpenChange={setOpen} />
        </>
    );
}
