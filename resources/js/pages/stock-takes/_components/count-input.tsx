import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { runGate } from '@/lib/validation/gate';
import { stockTakeCountSchema } from '@/lib/validation/schemas/stock-take-count';
import { count as countRoute } from '@/routes/stock-takes';

type Line = App.Data.StockTakeItemData;

/**
 * The id of the sheet's "Counted" heading, so each row's box can name itself with it.
 *
 * A screen reader meeting an input in a table cell is told nothing about the column it
 * is in, and every row would otherwise announce the same word. Pointing
 * `aria-labelledby` at the item's own name *and* this heading says "Blue widget,
 * Counted" out of two strings already on the page, in the reader's language, rather
 * than out of a per-row sentence somebody would have to translate.
 *
 * A module constant rather than a `useId` because two components have to quote it; only
 * one sheet renders per page, so it cannot collide with itself.
 */
export const COUNTED_HEADER_ID = 'count-sheet-counted';

/**
 * Enter moves down the column, because a count is read off a shelf one line at a time
 * and reaching for the mouse between each is the difference between a tolerable job and
 * an intolerable one. Moving the focus blurs the box behind it, which is what saves it.
 *
 * The DOM supplies the order rather than an index passed down: it is already the order
 * somebody is reading, and it stays right when a found item joins the sheet mid-count.
 */
function advance(from: HTMLInputElement): void {
    const boxes = [
        ...document.querySelectorAll<HTMLInputElement>('[data-count-input]'),
    ];
    const next = boxes[boxes.indexOf(from) + 1];

    if (next === undefined) {
        // The last row has nowhere to go, so it commits by leaving itself.
        from.blur();

        return;
    }

    next.focus();
    next.select();
}

/**
 * The box somebody counts into.
 *
 * **`inputMode`, never `type="number"`.** A wheel passing over a focused number input
 * silently changes it, and on this screen that is a stock adjustment nobody typed. The
 * spinner arrows and the OS-locale decimal separator come off with it. TextField and the
 * reorder-level box make the same trade for the same reason.
 *
 * **Empty means not counted, and it has to stay reachable.** Clearing the box sends null
 * and the line goes back to being a question rather than an answer. It is the only way
 * to undo a mistyped count, and a sheet you cannot correct is a sheet that gets posted
 * wrong.
 *
 * **It saves on leaving the box, never on typing in it.** Typing `120` would otherwise
 * be three writes, two of them wrong, and the middle one would flag the row as a
 * variance of a hundred and eight. Leaving is the moment somebody has finished deciding.
 * The short timer is what makes blur and Enter — which causes a blur on its way out —
 * one request rather than two.
 */
export function CountInput({
    line,
    takeId,
    labelId,
}: {
    line: Line;
    takeId: number;
    labelId: string;
}) {
    const { t } = useTranslation();

    const saved = line.counted_quantity ?? '';

    // What is in the box, which is not what is stored: an uncommitted keystroke has to
    // survive the partial reload the row beside it triggers, and a refused save has to
    // be able to put the old number back.
    const [value, setValue] = useState(saved);
    const [seen, setSeen] = useState(saved);
    const [flash, setFlash] = useState(false);
    const commitTimer = useRef<number | undefined>(undefined);
    const flashTimer = useRef<number | undefined>(undefined);

    const form = useForm({ line: String(line.id), counted_quantity: saved });

    // Scoped to this one line rather than the whole sheet's, which is a narrower version
    // of the closure the FormRequest uses: a row can only ever submit itself.
    const schema = useMemo(() => stockTakeCountSchema([line.id]), [line.id]);

    // Follow the server's answer without an effect — the pattern React documents for
    // state that tracks a prop. It runs only when the stored value actually changed, so
    // it cannot interrupt somebody mid-type, and it is what puts `12.5000` back as
    // `12.5` once the sheet has been told.
    if (seen !== saved) {
        setSeen(saved);
        setValue(saved);
    }

    useEffect(
        () => () => {
            window.clearTimeout(commitTimer.current);
            window.clearTimeout(flashTimer.current);
        },
        [],
    );

    const commit = () => {
        const next = value.trim();

        // Nothing changed. Also the common case: walking down a column to reach the one
        // count that moved must not write every row on the way past.
        if (next === saved) {
            return;
        }

        const payload = { line: String(line.id), counted_quantity: next };

        form.transform(() => payload);
        form.post(countRoute({ stockTake: takeId }).url, {
            // Rows save independently — one slow request must not queue the next.
            async: true,
            preserveScroll: true,
            preserveState: true,
            // The header counts what has been counted and what differs, so both move
            // whenever one of these does.
            only: ['take', 'items'],
            // Checked before it is sent, so a count the column would round is refused
            // here rather than stored as a different number.
            onBefore: () => runGate(schema, payload, form, t),
            onSuccess: () => {
                setFlash(true);
                window.clearTimeout(flashTimer.current);
                flashTimer.current = window.setTimeout(
                    () => setFlash(false),
                    2000,
                );
            },
            // Put the box back to what is stored. The reason stays under it, in the
            // server's own words.
            onError: () => setValue(saved),
        });
    };

    const scheduleCommit = () => {
        window.clearTimeout(commitTimer.current);
        commitTimer.current = window.setTimeout(commit, 250);
    };

    const error = form.errors.counted_quantity;
    const errorId = `count-error-${line.id}`;

    return (
        <div className="flex flex-col items-end gap-1">
            <div className="flex items-center justify-end gap-1.5">
                {(form.processing || flash) && (
                    <span role="status" className="flex items-center">
                        {form.processing ? (
                            <Spinner className="size-3.5 text-muted-foreground" />
                        ) : (
                            <Check className="size-3.5 text-muted-foreground" />
                        )}
                        <span className="sr-only">
                            {t(
                                form.processing
                                    ? 'stock-takes.sheet.saving'
                                    : 'stock-takes.sheet.saved',
                            )}
                        </span>
                    </span>
                )}

                <Input
                    data-count-input=""
                    inputMode="decimal"
                    value={value}
                    disabled={form.processing}
                    aria-labelledby={`${labelId} ${COUNTED_HEADER_ID}`}
                    aria-invalid={error !== undefined}
                    aria-describedby={error === undefined ? undefined : errorId}
                    placeholder={t('stock-takes.sheet.not_counted')}
                    onChange={(event) => setValue(event.target.value)}
                    onBlur={scheduleCommit}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            // There is no enclosing form — the sheet is a table of
                            // independent saves — so let nothing be submitted.
                            event.preventDefault();
                            advance(event.currentTarget);
                        }
                    }}
                    className="h-8 w-24 text-right tabular-nums sm:w-28"
                />
            </div>

            <InputError
                id={errorId}
                role="alert"
                message={error}
                className="text-right"
            />
        </div>
    );
}
