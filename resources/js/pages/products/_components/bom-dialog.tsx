import { usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { ComboboxField } from '@/components/form/combobox-field';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import { TextField } from '@/components/form/text-field';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { bomSchema } from '@/lib/validation/schemas/bom';
import { bom } from '@/routes/products';

type Product = App.Data.ProductData;

type PageProps = { rawMaterials: App.Data.OptionData[] };

/** The error bag, keyed the way both Laravel and the zod gate key it. */
type Errors = Record<string, string>;

/**
 * A row while it is being edited. The two values are **seeds**, read once when the row
 * mounts; after that the inputs hold their own values and this only remembers that the
 * row exists. `key` is what makes that safe — it keeps a row's DOM node identical
 * across an insert or a removal above it, so nothing is re-seeded and nothing typed is
 * lost when the indices shift.
 */
type Line = {
    key: number;
    rawMaterialId: number | null;
    quantity: string;
};

/**
 * The bill-of-materials editor: what goes into a product, and how much per unit.
 *
 * Its own dialog rather than a third group inside the product form, because it is a
 * different kind of editing — a list somebody grows and prunes, not a fixed set of
 * fields — and because the product form is already eight fields long.
 *
 * The whole bill is saved at once and replaces what was there. Nothing here is a patch:
 * removing a material, changing a quantity and adding two more is one save and one
 * request, which is also what makes the transaction on the server meaningful.
 */
export function BomDialog({
    open,
    onOpenChange,
    product,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    product: Product;
}) {
    const { rawMaterials } = usePage<PageProps>().props;

    // Not memoised on `rawMaterials`: the dialog's content is unmounted while closed,
    // so this is built once per opening rather than once per render.
    const schema = bomSchema(rawMaterials.map((material) => material.id));

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={bom.form({ product: product.id })}
            schema={schema}
            size="lg"
            title="products.bom.title"
            description="products.bom.description"
            headingParams={{ name: product.name }}
            submit="products.bom.submit"
            submitting="products.bom.submitting"
        >
            {({ errors }) => (
                // Keyed on the product so that opening the editor for a different row
                // builds a fresh list rather than reusing the seeds of the last one.
                <BomLines
                    key={product.id}
                    bom={product.bom}
                    materials={rawMaterials}
                    errors={errors}
                />
            )}
        </ResourceFormDialog>
    );
}

/**
 * The rows themselves.
 *
 * Deliberately a child of the dialog's content rather than of the dialog: Radix
 * unmounts the content on close, so this component — and the list it is holding — goes
 * with it. An abandoned edit cannot reappear on the next open, which is the same
 * property {@see ResourceFormDialog} relies on for ordinary uncontrolled fields.
 */
function BomLines({
    bom: initial,
    materials,
    errors,
}: {
    bom: App.Data.BomItemData[];
    materials: App.Data.OptionData[];
    errors: Errors;
}) {
    const { t, tChoice } = useTranslation();
    const nextKey = useRef(0);
    const [lines, setLines] = useState<Line[]>(() =>
        initial.map((item) => ({
            key: nextKey.current++,
            rawMaterialId: item.raw_material_id,
            quantity: item.quantity,
        })),
    );

    const add = () =>
        setLines((current) => [
            ...current,
            { key: nextKey.current++, rawMaterialId: null, quantity: '' },
        ]);

    const remove = (key: number) =>
        setLines((current) => current.filter((line) => line.key !== key));

    // A workspace with no materials at all. Offering an empty picker would be offering
    // a choice that cannot be made — say why instead, and point at the fix.
    if (materials.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                {t('products.bom.none_available')}
            </p>
        );
    }

    return (
        <div className="space-y-4">
            {/*
                Column headers, from `sm` up. Below that each row stacks and carries its
                own labels instead — see the `labelHidden="sm"` on the fields, which is
                what hands the naming back to the label when the header disappears.

                The grid template is repeated on every row rather than lifted into a
                real table: a table row cannot reflow into a stack, and this has to.
            */}
            <div className="hidden gap-3 sm:grid sm:grid-cols-[1fr_9rem_2.25rem]">
                <span className="font-medium text-muted-foreground text-xs">
                    {t('products.bom.column_material')}
                </span>
                <span className="font-medium text-muted-foreground text-xs">
                    {t('products.bom.column_quantity')}
                </span>
                <span />
            </div>

            {lines.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('products.bom.empty')}
                </p>
            ) : (
                <ul className="space-y-4 sm:space-y-3">
                    {lines.map((line, index) => (
                        <li
                            key={line.key}
                            className="grid gap-3 rounded-md border p-3 sm:grid-cols-[1fr_9rem_2.25rem] sm:items-start sm:rounded-none sm:border-0 sm:p-0"
                        >
                            <ComboboxField
                                name={`items[${index}][raw_material_id]`}
                                label="products.bom.column_material"
                                labelHidden="sm"
                                options={materials}
                                defaultValue={line.rawMaterialId}
                                placeholder="products.bom.material_placeholder"
                                searchPlaceholder="products.bom.material_search"
                                emptyMessage="products.bom.material_empty"
                                error={errors[`items.${index}.raw_material_id`]}
                            />

                            <TextField
                                name={`items[${index}][quantity]`}
                                label="products.bom.column_quantity"
                                labelHidden="sm"
                                inputMode="decimal"
                                placeholder="products.bom.quantity_placeholder"
                                defaultValue={line.quantity}
                                error={errors[`items.${index}.quantity`]}
                            />

                            {/*
                                Pushed down on `sm` so it lines up with the controls
                                rather than with the labels above them; on a phone it
                                sits under the two fields, where it is the only thing
                                left in the row.
                            */}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="justify-self-end sm:mt-0.5"
                                aria-label={t('products.bom.remove', {
                                    number: index + 1,
                                })}
                                onClick={() => remove(line.key)}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <Button type="button" variant="outline" onClick={add}>
                    <Plus className="size-4" />
                    {t('products.bom.add')}
                </Button>

                {/*
                    A running count, so the number is visible without scrolling back up
                    a long bill. tChoice, not t: "1 materials" is wrong in English and
                    the plural rule is the locale's to apply.
                */}
                <span className="text-muted-foreground text-xs tabular-nums">
                    {tChoice('products.bom.count', lines.length)}
                </span>
            </div>
        </div>
    );
}
