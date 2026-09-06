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
             * The IANA zone the SERVER formatted dates in, reported by the browser
             * through a cookie. Never call `resolvedOptions()` during a render.
             */
            timezone: string;
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
