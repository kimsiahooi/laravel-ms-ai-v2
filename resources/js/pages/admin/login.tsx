import { Form, Head } from '@inertiajs/react';
import { Building2, ShieldCheck } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/admin/login';

/**
 * Sign-in for the central console. A separate door from a workspace's own
 * /{slug}/login — different guard, different database — so it says so plainly
 * rather than looking like the tenant login and confusing the two.
 */
export default function AdminLogin() {
    const { t } = useTranslation();

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <Head title={t('console.login.title')} />

            {/* Brand panel — decorative, so it is hidden from assistive tech and
                from narrow screens where it would only push the form down. */}
            <div
                aria-hidden
                className="relative hidden flex-col justify-between bg-primary p-10 text-primary-foreground lg:flex"
            >
                <div className="flex items-center gap-3">
                    <div className="flex size-9 items-center justify-center rounded-md bg-primary-foreground/15">
                        <AppLogoIcon className="size-5 fill-current" />
                    </div>
                    <span className="font-semibold text-lg">
                        {t('console.name')}
                    </span>
                </div>

                <div className="space-y-6">
                    <h2 className="max-w-sm font-semibold text-3xl leading-tight">
                        {t('console.login.panel_heading')}
                    </h2>
                    <ul className="space-y-4 text-primary-foreground/80 text-sm">
                        <li className="flex items-start gap-3">
                            <Building2 className="mt-0.5 size-4 shrink-0" />
                            <span>{t('console.login.panel_point_create')}</span>
                        </li>
                        <li className="flex items-start gap-3">
                            <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                            <span>
                                {t('console.login.panel_point_archive')}
                            </span>
                        </li>
                    </ul>
                </div>

                <p className="text-primary-foreground/60 text-xs">
                    {t('console.login.panel_footer')}
                </p>
            </div>

            <div className="flex items-center justify-center bg-background p-6 md:p-10">
                <div className="w-full max-w-sm space-y-8">
                    <div className="space-y-2">
                        <div className="flex size-10 items-center justify-center rounded-md bg-primary text-primary-foreground lg:hidden">
                            <AppLogoIcon className="size-5 fill-current" />
                        </div>
                        <h1 className="font-semibold text-2xl tracking-tight">
                            {t('console.login.heading')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('console.login.subheading')}
                        </p>
                    </div>

                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        disableWhileProcessing
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="email">
                                        {t('console.login.email')}
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                        placeholder={t(
                                            'console.login.email_placeholder',
                                        )}
                                        aria-invalid={Boolean(errors.email)}
                                        aria-describedby={
                                            errors.email
                                                ? 'email-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="email-error"
                                        role="alert"
                                        message={errors.email}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password">
                                        {t('console.login.password')}
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                        placeholder={t(
                                            'console.login.password_placeholder',
                                        )}
                                        aria-invalid={Boolean(errors.password)}
                                        aria-describedby={
                                            errors.password
                                                ? 'password-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="password-error"
                                        role="alert"
                                        message={errors.password}
                                    />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Checkbox id="remember" name="remember" />
                                    <Label
                                        htmlFor="remember"
                                        className="font-normal text-muted-foreground text-sm"
                                    >
                                        {t('console.login.remember')}
                                    </Label>
                                </div>

                                <Button type="submit" className="w-full">
                                    {processing && <Spinner />}
                                    {processing
                                        ? t('console.login.submitting')
                                        : t('console.login.submit')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </div>
    );
}
