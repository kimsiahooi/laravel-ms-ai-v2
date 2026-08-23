import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/hooks/use-translation';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { TranslationKey } from '@/types/lang';

/**
 * Titles are keys, and the routes are FUNCTIONS rather than resolved URLs.
 *
 * Both because this array is built at module scope. There is no locale there, so a
 * title has to stay a key — and, less obviously, there is no tenant either: the slug is
 * registered as a URL default per render (see app.tsx), while module scope is evaluated
 * once when the bundle loads. Calling `edit()` here produced a literal
 * `/$tenant/settings/profile` on the server against `/demo/settings/profile` on the
 * client, which is a hydration mismatch — invisible until a hard page load.
 */
const SECTIONS: { title: TranslationKey; route: typeof edit }[] = [
    { title: 'settings.nav.profile', route: edit },
    { title: 'settings.nav.security', route: editSecurity },
    { title: 'settings.nav.appearance', route: editAppearance },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    return (
        <div className="px-4 py-6">
            <Heading
                title={t('settings.heading.title')}
                description={t('settings.heading.description')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-x-0 space-y-1"
                        aria-label={t('settings.heading.nav_label')}
                    >
                        {SECTIONS.map((section) => {
                            // Resolved here, in render, where the tenant default exists.
                            const href = section.route();

                            return (
                                <Button
                                    key={toUrl(href)}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': isCurrentOrParentUrl(href),
                                    })}
                                >
                                    <Link href={href}>{t(section.title)}</Link>
                                </Button>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
