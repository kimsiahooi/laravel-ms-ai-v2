import { usePage } from '@inertiajs/react';
import { Boxes, Workflow } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LanguageSwitcher } from '@/components/layout/language-switcher';
import { ThemeToggle } from '@/components/layout/theme-toggle';
import { useTranslation } from '@/hooks/use-translation';
import type { AuthLayoutProps } from '@/types';

/**
 * The workspace sign-in screens: a branded panel beside the form.
 *
 * Same pattern as the console's own sign-in, which is the point — a customer signing
 * into their workspace and an administrator signing into the console should not feel
 * like they are using two different products.
 *
 * The panel is decorative and hidden from assistive tech: it repeats nothing the form
 * does not already say, so announcing it would just delay reaching the fields. It also
 * disappears below `lg`, where a phone has no room for it and the form is the whole job.
 *
 * The language switcher lives here deliberately. Someone who cannot read the form is
 * exactly who needs it, and until now it existed only behind the sign-in.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();
    const { tenant } = usePage().props;

    return (
        <div className="relative grid min-h-svh lg:grid-cols-2">
            <div className="absolute top-4 right-4 z-10 flex items-center gap-1">
                <LanguageSwitcher />
                <ThemeToggle />
            </div>

            <aside
                aria-hidden="true"
                className="hidden flex-col justify-between bg-sidebar p-10 text-sidebar-foreground lg:flex"
            >
                <div className="flex items-center gap-2 font-medium">
                    <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                        <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                    </div>
                    {tenant?.name}
                </div>

                <div className="max-w-md space-y-6">
                    <p className="font-semibold text-2xl leading-tight">
                        {t('auth.panel.heading')}
                    </p>
                    <ul className="space-y-4 text-sidebar-foreground/70 text-sm">
                        <li className="flex gap-3">
                            <Boxes className="mt-0.5 size-4 shrink-0" />
                            <span>{t('auth.panel.point_stock')}</span>
                        </li>
                        <li className="flex gap-3">
                            <Workflow className="mt-0.5 size-4 shrink-0" />
                            <span>{t('auth.panel.point_orders')}</span>
                        </li>
                    </ul>
                </div>

                <p className="text-sidebar-foreground/60 text-xs">
                    {tenant
                        ? t('auth.panel.footer', { workspace: tenant.name })
                        : ''}
                </p>
            </aside>

            <main className="flex flex-col items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm space-y-8">
                    <div className="space-y-2">
                        {/* The logo repeats on small screens, where the panel is gone
                            and the page would otherwise open on a bare form. */}
                        <div className="mb-6 flex justify-center lg:hidden">
                            <AppLogoIcon className="size-9 fill-current text-foreground dark:text-white" />
                        </div>
                        <h1 className="font-semibold text-xl tracking-tight">
                            {title && t(title)}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {description && t(description)}
                        </p>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
