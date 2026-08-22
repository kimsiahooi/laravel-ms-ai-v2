import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { AdminSidebar } from '@/components/layout/admin-sidebar';
import { LanguageSwitcher } from '@/components/layout/language-switcher';
import { ThemeToggle } from '@/components/layout/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useDocumentLocale } from '@/hooks/use-document-locale';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

/**
 * Shell for the central super-admin area (/admin/*). Imported directly by each admin
 * page rather than resolved in app.tsx, so the login page — which has no shell — can
 * opt out simply by not using it.
 */
export default function AdminLayout({
    children,
    breadcrumbs = [],
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    useDocumentLocale();

    return (
        <AppShell variant="sidebar">
            <AdminSidebar user={auth.user} />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <header className="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 border-sidebar-border/50 border-b bg-background/80 px-4 backdrop-blur-sm transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs
                        breadcrumbs={[
                            { title: t('console.name'), href: dashboard() },
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
