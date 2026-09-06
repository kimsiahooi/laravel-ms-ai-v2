import { useEffect, useMemo, useState } from 'react';

/** How long ticking pauses before the list is re-requested. */
const SETTLE = 300;

/**
 * The state behind a multi-select filter: what is ticked now, and when to tell the list.
 *
 * **The value is a comma-separated string, not an array**, because that is what travels
 * in the URL and what the server echoes back. A string also keeps the effect below
 * stable — an array prop would be a new object every render, so the debounce would
 * restart every render and never fire.
 *
 * Ticked here, sent shortly after: three boxes should be one request and one history
 * entry, not three of each. The same 300ms, and the same reason, as the search box.
 *
 * Shared by {@see ComboboxFilter} and {@see CheckboxFilter} rather than written twice.
 * They differ in how the choices are shown — searchable workspace rows against a short
 * translated list — and not at all in what ticking one means, and two copies of this
 * would be two filters in one panel that felt subtly different.
 */
export function usePickedValues(
    value: string,
    onChange: (value: string) => void,
): {
    picked: string[];
    toggle: (option: string) => void;
    clear: () => void;
} {
    const applied = useMemo(
        () => (value === '' ? [] : value.split(',')),
        [value],
    );

    const [picked, setPicked] = useState<string[]>(applied);

    // The server's answer is the source of truth: a Clear elsewhere, or the back button,
    // must be reflected here rather than fought.
    useEffect(() => {
        setPicked(applied);
    }, [applied]);

    useEffect(() => {
        const next = picked.join(',');

        if (next === value) {
            return;
        }

        const timer = setTimeout(() => onChange(next), SETTLE);

        return () => clearTimeout(timer);
    }, [picked, value, onChange]);

    return {
        picked,
        toggle: (option) =>
            setPicked((current) =>
                current.includes(option)
                    ? current.filter((each) => each !== option)
                    : [...current, option],
            ),
        clear: () => setPicked([]),
    };
}
