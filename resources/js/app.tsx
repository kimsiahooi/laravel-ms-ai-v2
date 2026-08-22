import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { loadMessages } from '@/lib/i18n-bundles';
import { setUrlDefaults } from '@/wayfinder';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Hand-written rather than left to @inertiajs/vite's generated resolver, so the
    // locale bundle can be awaited alongside the page component. This is the only
    // hook Inertia awaits on BOTH the server render and every client visit, which is
    // what lets `t()` be synchronous — and therefore SSR-safe — everywhere else.
    resolve: async (name, page) => {
        const [module] = await Promise.all([
            resolvePageComponent<{ default: ComponentType }>(
                `./pages/${name}.tsx`,
                import.meta.glob<{ default: ComponentType }>(
                    './pages/**/*.tsx',
                ),
            ),
            loadMessages(page?.props?.locale ?? 'en'),
        ]);

        return module.default;
    },
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            // The central console pages import AdminLayout themselves, so its login
            // screen — which has no shell — simply doesn't.
            case name.startsWith('admin/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app, { page }) {
        // Every app route is prefixed /{tenant}/. Registering the slug as a URL
        // default lets route helpers omit it — `dashboard()` resolves to the current
        // workspace, while `dashboard({ tenant: 'other' })` still works.
        //
        // Set here, per render, from this render's own page: under SSR one Node
        // process serves every tenant, so a value captured once at module scope
        // would leak one tenant's slug into another's links.
        const tenant = (page.props as { tenant?: { slug?: string } | null })
            .tenant;
        setUrlDefaults(tenant?.slug ? { tenant: tenant.slug } : {});

        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        // The top loading bar uses the brand token, not a raw literal.
        color: 'var(--primary)',
    },
});

// This will set light / dark mode on load...
initializeTheme();
