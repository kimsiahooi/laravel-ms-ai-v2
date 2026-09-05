import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo } from 'react';
import { ComboboxField } from '@/components/form/combobox-field';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import type { SelectOption } from '@/components/form/select-field';
import { SelectField } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';
import { productSchema } from '@/lib/validation/schemas/product';
import { ImageField } from '@/pages/products/_components/image-field';
import { store, update } from '@/routes/products';
import type { TranslationKey } from '@/types/lang';

type Product = App.Data.ProductData;

type PageProps = {
    categories: App.Data.OptionData[];
    suppliers: App.Data.OptionData[];
    units: Record<App.Enums.Dimension, App.Enums.Unit[]>;
};

/**
 * Eight fields in two groups.
 *
 * The split is the design: what the product *is* — the part somebody can fill in from
 * the thing in front of them — and how it is *filed*, which is a decision about the
 * catalog rather than about the product. Both filing fields are optional, and saying so
 * in the group's own line means leaving them empty is a choice rather than an oversight.
 */
export function ProductFormDialog({
    open,
    onOpenChange,
    product,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the create form. */
    product?: Product;
}) {
    const { categories, suppliers, units } = usePage<PageProps>().props;
    const editing = product !== undefined;

    const unitOptions = useMemo(() => toUnitOptions(units), [units]);
    const schema = useMemo(
        () =>
            productSchema(
                unitOptions.map((option) => option.value),
                categories.map((category) => category.id),
                suppliers.map((supplier) => supplier.id),
            ),
        [unitOptions, categories, suppliers],
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={
                editing ? update.form({ product: product.id }) : store.form()
            }
            schema={schema}
            size="lg"
            title={editing ? 'products.edit.title' : 'products.create.title'}
            description={
                editing
                    ? 'products.edit.description'
                    : 'products.create.description'
            }
            submit={editing ? 'products.edit.submit' : 'products.create.submit'}
            submitting={
                editing
                    ? 'products.edit.submitting'
                    : 'products.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="space-y-6">
                    <Group title="products.group.identity">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <TextField
                                    name="name"
                                    label="products.field.name"
                                    placeholder="products.field.name_placeholder"
                                    defaultValue={product?.name}
                                    error={errors.name}
                                    autoFocus
                                />
                            </div>

                            <TextField
                                name="sku"
                                label="products.field.sku"
                                placeholder="products.field.sku_placeholder"
                                hint="products.field.sku_hint"
                                defaultValue={product?.sku}
                                error={errors.sku}
                            />

                            <SelectField
                                name="unit"
                                label="products.field.unit"
                                placeholder="products.field.unit_placeholder"
                                hint="products.field.unit_hint"
                                options={unitOptions}
                                defaultValue={product?.unit}
                                error={errors.unit}
                            />

                            <div className="sm:col-span-2">
                                <TextField
                                    name="barcode"
                                    label="products.field.barcode"
                                    placeholder="products.field.barcode_placeholder"
                                    hint="products.field.barcode_hint"
                                    defaultValue={product?.barcode}
                                    error={errors.barcode}
                                    optional
                                />
                            </div>

                            <div className="sm:col-span-2">
                                <TextField
                                    name="description"
                                    label="products.field.description"
                                    placeholder="products.field.description_placeholder"
                                    defaultValue={product?.description}
                                    error={errors.description}
                                    optional
                                    rows={2}
                                />
                            </div>

                            {/*
                                Last in the group, not first. The photo is the most
                                recognisable thing about a product and the least urgent
                                to supply — putting it above the name would push the
                                field that is focused on open, and the two that are
                                required, below the fold on a phone.
                            */}
                            <div className="sm:col-span-2">
                                <ImageField
                                    name="image"
                                    removeName="remove_image"
                                    label="products.field.image"
                                    hint="products.field.image_hint"
                                    removeLabel="products.field.image_remove"
                                    alt="products.field.image_alt"
                                    currentUrl={product?.thumb_url}
                                    error={errors.image}
                                />
                            </div>
                        </div>
                    </Group>

                    <Separator />

                    <Group
                        title="products.group.filing"
                        hint="products.group.filing_hint"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <ComboboxField
                                name="category_id"
                                label="products.field.category"
                                placeholder="products.field.category_placeholder"
                                searchPlaceholder="products.field.category_search"
                                emptyMessage="products.field.category_empty"
                                options={categories}
                                defaultValue={product?.category_id}
                                error={errors.category_id}
                                optional
                            />

                            <ComboboxField
                                name="supplier_id"
                                label="products.field.supplier"
                                placeholder="products.field.supplier_placeholder"
                                searchPlaceholder="products.field.supplier_search"
                                emptyMessage="products.field.supplier_empty"
                                options={suppliers}
                                defaultValue={product?.supplier_id}
                                error={errors.supplier_id}
                                optional
                            />
                        </div>
                    </Group>
                </div>
            )}
        </ResourceFormDialog>
    );
}

/** A named run of fields, with an optional line explaining what it is for. */
function Group({
    title,
    hint,
    children,
}: {
    title: TranslationKey;
    hint?: TranslationKey;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <section className="space-y-4">
            <div className="space-y-1">
                <h3 className="font-medium text-sm">{t(title)}</h3>
                {hint && (
                    <p className="text-muted-foreground text-xs">{t(hint)}</p>
                )}
            </div>
            {children}
        </section>
    );
}

/** The server's `{ mass: ['g', 'kg', …], … }` as grouped picker options. */
function toUnitOptions(
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
