import { useMemo } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import type { DateNames } from '@/lib/date-names';
import { dateNames } from '@/lib/date-names';

/**
 * The month and weekday names for the language this page was rendered in.
 *
 * The React half of {@see dateNames} — and the only place React and those names meet, because
 * `lib/` may not import React and `lib/format.ts` is where the names are consumed. Every
 * component that formats a date calls this and passes the result down.
 *
 * `useTranslation()` already memoises `t` on the locale, so this rebuilds only when somebody
 * switches language — not on every render, and not once per table row.
 *
 * Nothing here reads the clock, the browser's locale or its zone, so it is safe in render:
 * the locale comes from the page props the server rendered with, exactly as `t()` does.
 */
export function useDateNames(): DateNames {
    const { t } = useTranslation();

    return useMemo(() => dateNames(t), [t]);
}
