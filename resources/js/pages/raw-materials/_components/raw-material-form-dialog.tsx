import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import type { SelectOption } from '@/components/form/select-field';
import { SelectField } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { rawMaterialSchema } from '@/lib/validation/schemas/raw-material';
import { store, update } from '@/routes/raw-materials';

type RawMaterial = App.Data.RawMaterialData;

/** Unit codes grouped by what they measure — see App\Enums\Unit::grouped(). */
type PageProps = { units: Record<App.Enums.Dimension, App.Enums.Unit[]> };

/**
 * Four fields, three of them required — the first form in the catalog that asks for
 * more than a name.
 *
 * It asks because the answers are load-bearing. The SKU is how a purchase order line, a
 * stock movement and a BOM row all refer back to this material, and the unit is what
 * makes their quantities mean something. A material saved without them would be a row
 * nothing downstream could use, so both carry a hint saying what they are for rather
 * than a bare label and a shrug.
 *
 * The barcode is the exception and stays optional: not every material has one, and one
 * that does gets scanned rather than typed.
 *
 * The unit is a picker rather than a box, because a stock engine that adds quantities
 * together cannot tell "kg" from "KG". The codes come from the server grouped by what
 * they measure; the words are looked up here, like every other user-facing string.
 */
export function RawMaterialFormDialog({
    open,
    onOpenChange,
    rawMaterial,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the create form. */
    rawMaterial?: RawMaterial;
}) {
    const { units } = usePage<PageProps>().props;
    const editing = rawMaterial !== undefined;

    // Both derived from the same prop, and both memoised on it: the option list is a
    // prop TanStack-free but still an input React compares by identity, and the schema
    // is a value that should live as long as the page rather than the render.
    const options = useMemo(() => unitOptions(units), [units]);
    const schema = useMemo(
        () => rawMaterialSchema(options.map((option) => option.value)),
        [options],
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={
                editing
                    ? update.form({ rawMaterial: rawMaterial.id })
                    : store.form()
            }
            schema={schema}
            title={
                editing
                    ? 'raw-materials.edit.title'
                    : 'raw-materials.create.title'
            }
            description={
                editing
                    ? 'raw-materials.edit.description'
                    : 'raw-materials.create.description'
            }
            submit={
                editing
                    ? 'raw-materials.edit.submit'
                    : 'raw-materials.create.submit'
            }
            submitting={
                editing
                    ? 'raw-materials.edit.submitting'
                    : 'raw-materials.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <TextField
                            name="name"
                            label="raw-materials.field.name"
                            placeholder="raw-materials.field.name_placeholder"
                            defaultValue={rawMaterial?.name}
                            error={errors.name}
                            autoFocus
                        />
                    </div>

                    <TextField
                        name="sku"
                        label="raw-materials.field.sku"
                        placeholder="raw-materials.field.sku_placeholder"
                        hint="raw-materials.field.sku_hint"
                        defaultValue={rawMaterial?.sku}
                        error={errors.sku}
                    />

                    <SelectField
                        name="unit"
                        label="raw-materials.field.unit"
                        placeholder="raw-materials.field.unit_placeholder"
                        hint="raw-materials.field.unit_hint"
                        options={options}
                        defaultValue={rawMaterial?.unit}
                        error={errors.unit}
                    />

                    <div className="sm:col-span-2">
                        <TextField
                            name="barcode"
                            label="raw-materials.field.barcode"
                            placeholder="raw-materials.field.barcode_placeholder"
                            hint="raw-materials.field.barcode_hint"
                            defaultValue={rawMaterial?.barcode}
                            error={errors.barcode}
                            optional
                        />
                    </div>
                </div>
            )}
        </ResourceFormDialog>
    );
}

/**
 * The server's `{ mass: ['g', 'kg', …], … }` flattened into picker options, keeping the
 * server's order so the groups read Mass, Volume, Length, Count.
 *
 * The labels are built from the codes rather than sent: `units.name.kg` is a string like
 * any other and belongs in `lang/`, not in a page prop.
 */
function unitOptions(
    units: Record<App.Enums.Dimension, App.Enums.Unit[]>,
): SelectOption[] {
    return Object.entries(units).flatMap(([dimension, codes]) =>
        codes.map((code) => ({
            value: code,
            label: `units.name.${code}` as const,
            group: `units.dimension.${dimension as App.Enums.Dimension}` as const,
        })),
    );
}
