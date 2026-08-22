import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Keeps `<html lang>` in step with the locale.
 *
 * The blade template sets it correctly on a full page load, but a language switch is an
 * Inertia visit: the page re-renders in the new language while the `<html>` element from
 * the original load persists, still claiming the old one. A screen reader then
 * pronounces Malay with English phonetics until the next hard refresh.
 *
 * Read from `usePage()` inside the tree, for the same two reasons `useTranslation` gives
 * for having no provider: `withApp` sits outside Inertia's context, and it runs only for
 * the first page — a locale captured there is frozen at whatever the app booted with,
 * which is exactly the value that was already correct. A `router.on('navigate')`
 * subscription is no better; it reports the page being left, so the attribute ends up
 * one visit behind.
 *
 * In an effect, never in render: SSR has no `document`.
 */
export function useDocumentLocale(): void {
    const locale = usePage().props.locale ?? 'en';

    useEffect(() => {
        // BCP-47 for the attribute — `zh_Hans` is Laravel's directory name, `zh-Hans`
        // is what assistive tech expects; the two are not interchangeable.
        document.documentElement.lang = locale.replace('_', '-');
    }, [locale]);
}
