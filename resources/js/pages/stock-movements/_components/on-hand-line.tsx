import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { onHand as onHandRoute } from '@/routes/stock';

type OnHand = App.Data.StockOnHandData;

/**
 * What is currently in the chosen warehouse, shown beside the quantity box.
 *
 * Without it the box is typed into blind: you pick a warehouse and an item, ask to take
 * six, and the refusal is the first thing that tells you six was never possible. It also
 * changes what "set the level" means from a guess into a correction — you can see the
 * number you are replacing.
 *
 * **Fetched, not shipped with the page.** Every on-hand row could travel as a prop, and
 * for a small workspace that would be a few dozen. But it changes whenever anybody else
 * records anything, and a figure baked in at page load goes quietly stale while a dialog
 * sits open. One request per choice is cheap and current.
 *
 * **It is still only a guide.** The lookup takes no lock, so the number is out of date
 * the moment it arrives; the refusal at submit time is the guarantee. Which is why this
 * never disables anything — showing "3" must not stop somebody submitting 4 that a
 * colleague's delivery has just made possible.
 *
 * Nothing renders until both pickers are set, so an empty form stays quiet rather than
 * showing a placeholder for an answer nobody has asked for yet.
 */
export function OnHandLine({
    warehouseId,
    item,
}: {
    warehouseId: string;
    item: string;
}) {
    const { t } = useTranslation();
    const [state, setState] = useState<OnHand | 'loading' | 'failed' | null>(
        null,
    );

    useEffect(() => {
        if (warehouseId === '' || item === '') {
            setState(null);

            return;
        }

        // The guard against an out-of-order answer. Choosing a second item before the
        // first request lands would otherwise leave the earlier number on screen under
        // the later item — a wrong figure that looks exactly like a right one.
        const controller = new AbortController();

        setState('loading');

        fetch(
            // The route's own parameters come first — only {tenant}, filled in by
            // SetTenantUrlDefault — and the query is the second argument. Same shape
            // the FilingLink components use.
            onHandRoute(undefined, {
                query: { warehouse_id: warehouseId, item },
            }).url,
            {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        )
            .then((response) =>
                response.ok ? response.json() : Promise.reject(response),
            )
            .then((data: OnHand) => setState(data))
            .catch(() => {
                if (!controller.signal.aborted) {
                    setState('failed');
                }
            });

        return () => controller.abort();
    }, [warehouseId, item]);

    if (state === null) {
        return null;
    }

    // A failed lookup says nothing rather than something wrong. The form still works —
    // this was only ever a convenience, and the server is the one that decides.
    if (state === 'failed') {
        return null;
    }

    return (
        <p
            className="text-muted-foreground text-xs"
            aria-live="polite"
            // Announced when it settles, not while it is in flight: a screen reader
            // reading "loading" on every keystroke of a search is worse than silence.
            aria-busy={state === 'loading'}
        >
            {state === 'loading'
                ? ' ' // Holds the line's height so the form does not jump. i18n-allow
                : t('stock-movements.field.on_hand', {
                      quantity: `${state.on_hand} ${t(`units.symbol.${state.unit}` as const)}`,
                  })}
        </p>
    );
}
