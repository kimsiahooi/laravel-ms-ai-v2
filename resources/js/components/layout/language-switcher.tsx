import { router, usePage } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { update as updateCentralLocale } from '@/routes/locale';
import { update as updateTenantLocale } from '@/routes/tenant/locale';

/**
 * Switches the UI language.
 *
 * A server round-trip, not client state: the server has to re-render in the new
 * language anyway (validation messages, toasts and emails all come from Laravel's
 * `lang/`), and letting the client change locale on its own would put it out of step
 * with the markup the server produced.
 *
 * Each language is listed in its own name, never translated — someone looking for
 * 简体中文 will not recognise "Chinese (Simplified)" written in Malay.
 *
 * Two routes, one per area, and the difference is not cosmetic. The session driver is
 * `database` and DatabaseTenancyBootstrapper switches the connection, so a workspace's
 * session row lives in its own database. Posting to the central route from inside a
 * workspace looks the cookie up in `central.sessions`, finds nothing, starts a fresh
 * session, and CSRF rejects it as a 419.
 */
export function LanguageSwitcher() {
    const { t } = useTranslation();
    const { locale, locales, tenant } = usePage().props;

    // Which area is rendering this decides which session the request must reach.
    const update = tenant ? updateTenantLocale : updateCentralLocale;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('common.language.change')}
                >
                    <Languages className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {locales.map((option) => (
                    <DropdownMenuItem
                        key={option.code}
                        onSelect={() =>
                            router.put(
                                update().url,
                                { locale: option.code },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <span className="flex-1">{option.label}</span>
                        {locale === option.code && <Check className="size-4" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
