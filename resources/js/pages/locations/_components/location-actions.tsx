import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { LocationFormDialog } from '@/pages/locations/_components/location-form-dialog';
import { destroy } from '@/routes/locations';
import { index as warehouses } from '@/routes/warehouses';

type Location = App.Data.LocationData;

/**
 * What one row can do. The row owns both of its dialogs rather than reaching for
 * state on the page, which is what lets the column definitions stay at module scope
 * — see CategoryActions on why.
 */
export function LocationActions({ location }: { location: Location }) {
    const { t, tChoice } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const remove = useResourceDelete(destroy({ location: location.id }).url);

    const standingCount = location.warehouse_count;
    // The names are capped by the DTO, so say so when the list is short of the count.
    const standingNames =
        location.warehouses.join(', ') +
        (standingCount > location.warehouses.length ? '…' : '');
    const canSeeWarehouses = can('warehouses.view');

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

            {/*
                A site with warehouses on it cannot be deleted — the foreign key is
                restricted, and a soft delete would slip past that and strand them.
                The controller refuses either way; this is what makes Delete explain
                itself instead of failing.
            */}
            {standingCount > 0 ? (
                <ConfirmDialog
                    blocked
                    open={remove.confirming}
                    onOpenChange={remove.onOpenChange}
                    title={t('locations.confirm.blocked_title', {
                        name: location.name,
                    })}
                    description={tChoice(
                        'locations.confirm.blocked_description',
                        standingCount,
                        { warehouses: standingNames },
                    )}
                >
                    {canSeeWarehouses && (
                        <Link
                            href={warehouses(undefined, {
                                query: { site: String(location.id) },
                            })}
                            className="rounded-sm text-link underline underline-offset-4 ring-offset-background transition-colors hover:text-link-hover focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-offset-2"
                        >
                            {tChoice(
                                'locations.confirm.blocked_link',
                                standingCount,
                                { count: standingCount },
                            )}
                        </Link>
                    )}
                </ConfirmDialog>
            ) : (
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
            )}
        </>
    );
}
