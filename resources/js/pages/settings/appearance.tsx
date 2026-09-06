import { Head, setLayoutProps } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';
import { ColumnResetCard } from '@/pages/settings/_components/column-reset-card';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { t } = useTranslation();

    // setLayoutProps rather than a static `Appearance.layout`: a breadcrumb title is a
    // plain string, and resolving it needs t(), which cannot run at module scope.
    setLayoutProps({
        breadcrumbs: [
            { title: t('settings.appearance.head'), href: editAppearance() },
        ],
    });

    return (
        <>
            <Head title={t('settings.appearance.head')} />

            <h1 className="sr-only">{t('settings.appearance.head')}</h1>

            {/*
                Two settings that are both about how the app looks, and that reach
                differently: the theme is written to this browser, while a column layout
                is stored against the account and follows it everywhere. The page used to
                say "on this device", which stopped being true the moment the second one
                landed — so each section states its own reach instead.
            */}
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.appearance.title')}
                    description={t('settings.appearance.description')}
                />

                <div className="space-y-3">
                    <p className="text-muted-foreground text-sm">
                        {t('settings.appearance.theme_scope')}
                    </p>
                    <AppearanceTabs />
                </div>

                <Separator />

                <ColumnResetCard />
            </div>
        </>
    );
}
