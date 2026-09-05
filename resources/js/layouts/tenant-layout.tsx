import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { LanguageSwitcher } from '@/components/layout/language-switcher';
import { TenantSidebar } from '@/components/layout/tenant-sidebar';
import { ThemeToggle } from '@/components/layout/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useDocumentLocale } from '@/hooks/use-document-locale';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

/**
 * Shell for a workspace — everything under /{tenant}/ that is not an auth screen.
 *
 * The first breadcrumb is supplied here rather than by each page. A page's own
 * breadcrumbs are frequently declared at module scope, where `t()` cannot be called
 * (it reads the current page's locale), so a page that owned its root crumb would have
 * to hard-code it in English.
 */
export default function TenantLayout({
    children,
    breadcrumbs = [],
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { auth, tenant } = usePage().props;
    const { t } = useTranslation();

    useDocumentLocale();

    return (
        <AppShell variant="sidebar">
            <TenantSidebar user={auth.user} tenant={tenant} />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <header className="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 border-sidebar-border/50 border-b bg-background/80 px-4 backdrop-blur-sm transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
                    {/*
                        The vendored SidebarTrigger has "Toggle sidebar" baked into an
                        sr-only span, so without this the one control on every screen
                        announced English in Malay and in Chinese. `aria-label` wins over
                        element text for the accessible name, which fixes it from outside
                        rather than by editing a read-only file.
                    */}
                    <SidebarTrigger
                        className="-ml-1"
                        aria-label={t('common.actions.toggle_sidebar')}
                    />
                    <Breadcrumbs
                        breadcrumbs={[
                            {
                                title: tenant?.name ?? t('tenant.name'),
                                href: dashboard(),
                            },
                            ...breadcrumbs,
                        ]}
                    />
                    <div className="ml-auto flex items-center gap-1">
                        <LanguageSwitcher />
                        <ThemeToggle />
                    </div>
                </header>
                <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
