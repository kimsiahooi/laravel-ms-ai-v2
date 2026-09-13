import type { Auth, Tenant } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** The active workspace, or null on central (/admin, /) pages. */
            tenant: Tenant | null;
            /** The locale the SERVER rendered with. Never read navigator.language. */
            locale: string;
            locales: { code: string; label: string }[];
            /**
             * The IANA zone the SERVER formatted dates in: the workspace's own, from
             * business settings, so every reader sees a date the same way. The browser's
             * reported zone answers only on /admin, where there is no workspace. Never
             * call `resolvedOptions()` during a render.
             */
            timezone: string;
            /**
             * Today's date, `Y-m-d`, resolved server-side in `timezone` — so a calendar
             * marks the business's today rather than whatever day it is where the reader
             * is. It has to be a prop because reading the clock during render is a
             * hydration mismatch — see `components/form/date-field.tsx`.
             */
            today: string;
            /**
             * Which columns this person looks at, per list — only the lists they have
             * actually changed. A prop rather than anything the browser reads for itself:
             * the table seeds its state from this during render, so both sides have to be
             * looking at the same value or the first paint disagrees.
             */
            tableColumns: Partial<
                Record<
                    App.Enums.TableKey,
                    { order: string[]; hidden: string[] }
                >
            >;
            [key: string]: unknown;
        };
    }
}
