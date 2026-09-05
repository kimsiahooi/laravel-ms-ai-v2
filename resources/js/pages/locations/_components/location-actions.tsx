import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { LocationFormDialog } from '@/pages/locations/_components/location-form-dialog';
import { destroy } from '@/routes/locations';

type Location = App.Data.LocationData;

/**
 * What one row can do. The row owns both of its dialogs rather than reaching for
 * state on the page, which is what lets the column definitions stay at module scope
 * — see CategoryActions on why.
 */
export function LocationActions({ location }: { location: Location }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const remove = useResourceDelete(destroy({ location: location.id }).url);

    return (
        <>
            <RowActions
                name={location.name}
                canEdit={can('locations.update')}
                canDelete={can('locations.delete')}
                onEdit={() => setEditing(true)}
                onDelete={remove.ask}
            />

            <LocationFormDialog
                open={editing}
                onOpenChange={setEditing}
                location={location}
            />

            <ConfirmDialog
                open={remove.confirming}
                onOpenChange={remove.onOpenChange}
                title={t('locations.confirm.delete_title', {
                    name: location.name,
                })}
                description={t('locations.confirm.delete_description')}
                confirmLabel={t('locations.confirm.delete_submit')}
                busyLabel={t('locations.confirm.delete_submitting')}
                variant="destructive"
                processing={remove.processing}
                onConfirm={remove.confirm}
            />
        </>
    );
}
