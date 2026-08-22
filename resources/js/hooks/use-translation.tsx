import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { TranslationParams } from '@/lib/i18n';
import { translate, translateChoice } from '@/lib/i18n';
import { messagesFor } from '@/lib/i18n-bundles';
import type { TranslationKey } from '@/types/lang';

export type Translate = (
    key: TranslationKey,
    params?: TranslationParams,
) => string;

export type TranslateChoice = (
    key: TranslationKey,
    count: number,
    params?: TranslationParams,
) => string;

/**
 * `const { t } = useTranslation()` — the only way a component should produce
 * user-facing text.
 *
 * `t` interpolates (`t('common.pagination.showing', { from, to, total })`); `tChoice`
 * also picks the plural form for the active language. Both take a key from the
 * generated union, so a typo is a tsc error rather than a blank space on the page.
 *
 * Deliberately NO context provider. Two reasons, both learned the hard way:
 *
 *  - A provider would have to be installed in `withApp`, which wraps Inertia's own
 *    <App> from the OUTSIDE — `usePage()` is not available there, and SSR dies with
 *    "usePage must be used within the Inertia component".
 *  - `withApp` runs only for the FIRST page on the client, so a locale captured there
 *    would go stale the moment someone switched language.
 *
 * Reading the page props directly avoids both: the locale always belongs to the visit
 * being rendered. The bundle itself is already loaded — `resolve` in app.tsx awaits it
 * before any render — so this stays synchronous and safe under SSR.
 */
export function useTranslation(): {
    t: Translate;
    tChoice: TranslateChoice;
    locale: string;
} {
    const locale = usePage().props.locale ?? 'en';

    return useMemo(() => {
        const messages = messagesFor(locale);

        return {
            locale,
            t: (key, params) => translate(messages, key, params),
            tChoice: (key, count, params) =>
                translateChoice(messages, locale, key, count, params),
        };
    }, [locale]);
}
