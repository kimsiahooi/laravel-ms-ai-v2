import { useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { runGate } from '@/lib/validation/gate';
import { warehouseReorderLevelSchema } from '@/lib/validation/schemas/warehouse-reorder-level';
import { update } from '@/routes/warehouses/reorder-levels';

type Item = App.Data.WarehouseItemData;

type PageProps = {
    warehouse: App.Data.WarehouseData;
    items: { data: Item[] };
};

/**
 * The one editable thing on the screen: the level at which this item wants restocking
 * in this warehouse.
 *
 * **An input in the row rather than a dialog per row.** Every other write in the app
 * opens a form, and that is right for a record with several fields — but this is one
 * number, and setting them up means going down a list doing the same thing twenty
 * times. A dialog would turn twenty keystrokes' worth of decision into twenty
 * open-type-save-close cycles.
 *
 * **It commits on blur and on Enter, not on every keystroke.** Typing `120` would
 * otherwise be three saves, the first two of them wrong — and the middle one would
 * briefly flag the row for reorder. Leaving the box is the moment somebody has finished
 * deciding.
 *
 * **Empty is not zero.** An empty box means no level at all, which is a state worth
 * being able to get back to; the server deletes the row for either, and the placeholder
 * is what says which one you are looking at.
 */
export function ReorderLevelCell({ item }: { item: Item }) {
    const { t } = useTranslation();
    const { can } = usePermissions();

    const saved = item.min_stock ?? '';

    if (!can('warehouses.update')) {
        return (
            <span className="text-muted-foreground tabular-nums">
                {saved === ''
                    ? t('warehouses.detail.level_placeholder')
                    : saved}
            </span>
        );
    }

    return <LevelInput item={item} saved={saved} />;
}

/**
 * Split from the cell above so the hooks below sit past the permission check. A cell
 * that may render as plain text cannot call hooks conditionally, and the alternative —
 * running the form machinery for a reader who can never submit it — is worse.
 */
function LevelInput({ item, saved }: { item: Item; saved: string }) {
    const { t } = useTranslation();

    // Off the page rather than through props: the columns that render this are built at
    // module scope and cannot close over page data. See WarehouseFormDialog, which
    // reads its site picker the same way.
    const { warehouse, items } = usePage<PageProps>().props;

    // The browser can only refuse an item it was told does not exist, and the rows on
    // this page are the only values a cell here can produce.
    const schema = useMemo(
        () => warehouseReorderLevelSchema(items.data.map((row) => row.item)),
        [items],
    );

    // What is in the box, which is not what is stored: an uncommitted keystroke has to
    // survive a re-render, and a refused save has to be able to put the old number
    // back. The form owns the request, its errors and its in-flight flag; this owns the
    // text.
    const [value, setValue] = useState(saved);
    const [seen, setSeen] = useState(saved);

    const form = useForm({ item: item.item, min_stock: saved });

    // Follow the server's answer without an effect — the pattern React documents for
    // state that has to track a prop. It runs only when the stored value actually
    // changed, so it cannot interrupt somebody mid-type, and it is what puts `12.50`
    // back as `12.5` once the column has had it.
    if (seen !== saved) {
        setSeen(saved);
        setValue(saved);
    }

    const error = form.errors.min_stock;
    const errorId = `reorder-error-${item.item}`;

    const commit = () => {
        const next = value.trim();

        // Nothing to save. Also the common case: tabbing down a column of levels to
        // reach one of them must not write twenty rows.
        if (next === saved) {
            return;
        }

        const payload = { item: item.item, min_stock: next };

        form.transform(() => payload);
        form.put(update({ warehouse: warehouse.id }).url, {
            // Rows save independently — one slow request must not queue the next.
            async: true,
            preserveScroll: true,
            preserveState: true,
            // The summary above the list counts what needs reordering, so it changes
            // whenever a level does.
            only: ['items', 'summary'],
            // Checked before it is sent, so a level the column would round is refused
            // here rather than saved as a different number.
            onBefore: () => runGate(schema, payload, form, t),
            // Put the box back to what is actually stored. The reason stays under it,
            // in the server's own words.
            onError: () => setValue(saved),
        });
    };

    return (
        <div className="flex flex-col items-end gap-1">
            <div className="flex items-center justify-end gap-1.5">
                {form.processing && (
                    <Spinner className="size-3.5 text-muted-foreground" />
                )}
                <Input
                    // `inputMode`, not `type="number"`: a number input scrolls its value
                    // when the wheel passes over it and reads the decimal separator from
                    // the OS locale. See TextField, which makes the same choice.
                    inputMode="decimal"
                    value={value}
                    disabled={form.processing}
                    aria-label={t('warehouses.detail.level_for', {
                        name: item.name,
                    })}
                    aria-invalid={error !== undefined}
                    aria-describedby={error === undefined ? undefined : errorId}
                    placeholder={t('warehouses.detail.level_placeholder')}
                    onChange={(event) => setValue(event.target.value)}
                    onBlur={commit}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.currentTarget.blur();
                        }
                    }}
                    className="h-8 w-24 text-right tabular-nums sm:w-28"
                />
            </div>

            <InputError id={errorId} message={error} className="text-right" />
        </div>
    );
}
