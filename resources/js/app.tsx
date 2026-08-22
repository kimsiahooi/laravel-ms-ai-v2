import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { setUrlDefaults } from '@/wayfinder';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
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
