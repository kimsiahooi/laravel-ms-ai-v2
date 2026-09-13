import { Form, Head, setLayoutProps } from '@inertiajs/react';
import BusinessSettingsController from '@/actions/App/Http/Controllers/Tenant/BusinessSettingsController';
import { SelectField, type SelectOption } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { CURRENCY_NAMES } from '@/config/currencies';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CurrencyChoices } from '@/pages/business-settings/_components/currency-choices';
import { DocumentNumberFields } from '@/pages/business-settings/_components/document-number-fields';
import { TimezoneField } from '@/pages/business-settings/_components/timezone-field';
import { index } from '@/routes/settings';

/**
 * The names of the currencies this app knows, keyed by ISO code.
 *
 * The catalog itself arrives as a page prop, so the browser can never offer a code the
 * request would refuse — but the *words* come from {@see CURRENCY_NAMES} in
 * `config/currencies.ts`, because a `SelectOption` label is a `TranslationKey` and the
 * compiler proves each one exists. A code with no entry is left out of the pickers rather
 * than rendered as its own key: adding a currency means naming it in `lang/`.
 */

/**
 * The workspace's money settings, on one page.
 *
 * **A page rather than a dialog**, unlike every other form so far. These are read far
 * more often than they are changed, several of them only make sense beside another — a
 * rate beside its label, a prefix beside its reset mode — and a dialog that has to be
 * opened before it can be read is the wrong shape for something people come to check.
 *
 * One form and one save, for the same reason. The fields are read together, and
 * separate saves per section would let a workspace sit between two coherent settings.
 */
export default function Business({
    settings,
    currencies,
    timezones,
}: {
    settings: App.Data.BusinessSettingsData;
    /** Every code the server will accept — its catalog, not this workspace's choice. */
    currencies: string[];
    /** Every IANA zone the server will accept. See TimeZones::options(). */
    timezones: string[];
}) {
    const { t } = useTranslation();

    // setLayoutProps rather than a static `Business.layout`: a breadcrumb title is a
    // plain string, and resolving one needs t(), which cannot run at module scope.
    setLayoutProps({
        breadcrumbs: [{ title: t('business-settings.head'), href: index() }],
    });

    const options = currencyOptions(currencies);

    const { can } = usePermissions();
    const canUpdate = can('settings.update');

    return (
        <>
            <Head title={t('business-settings.head')} />

            <h1 className="sr-only">{t('business-settings.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('business-settings.title')}
                    description={t('business-settings.subtitle')}
                />

                <Form
                    {...BusinessSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <section className="space-y-4">
                                <Heading
                                    variant="small"
                                    title={t('business-settings.money.title')}
                                    description={t(
                                        'business-settings.money.description',
                                    )}
                                />

                                <SelectField
                                    name="base_currency"
                                    label="business-settings.field.base_currency"
                                    placeholder="business-settings.field.base_currency_placeholder"
                                    hint="business-settings.field.base_currency_hint"
                                    options={options}
                                    defaultValue={settings.base_currency}
                                    error={errors.base_currency}
                                />

                                <CurrencyChoices
                                    options={options}
                                    chosen={settings.currencies}
                                    base={settings.base_currency}
                                    error={errors.currencies}
                                />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <TextField
                                        name="tax_rate"
                                        label="business-settings.field.tax_rate"
                                        placeholder="business-settings.field.tax_rate_placeholder"
                                        hint="business-settings.field.tax_rate_hint"
                                        inputMode="decimal"
                                        defaultValue={settings.tax_rate}
                                        error={errors.tax_rate}
                                    />

                                    <TextField
                                        name="tax_label"
                                        label="business-settings.field.tax_label"
                                        placeholder="business-settings.field.tax_label_placeholder"
                                        hint="business-settings.field.tax_label_hint"
                                        defaultValue={settings.tax_label}
                                        error={errors.tax_label}
                                    />
                                </div>
                            </section>

                            <Separator />

                            <section className="space-y-4">
                                <Heading
                                    variant="small"
                                    title={t(
                                        'business-settings.documents.title',
                                    )}
                                    description={t(
                                        'business-settings.documents.description',
                                    )}
                                />

                                <DocumentNumberFields
                                    settings={settings}
                                    errors={errors}
                                />
                            </section>

                            <Separator />

                            <section className="space-y-4">
                                <Heading
                                    variant="small"
                                    title={t('business-settings.clock.title')}
                                    description={t(
                                        'business-settings.clock.description',
                                    )}
                                />

                                <div className="sm:max-w-sm">
                                    <TimezoneField
                                        name="timezone"
                                        options={timezones}
                                        defaultValue={settings.timezone}
                                        error={errors.timezone}
                                    />
                                </div>
                            </section>

                            {/* Hidden rather than disabled for a reader: a greyed-out
                                button invites a hover to find out why, and there is no
                                why worth explaining — they can read these settings and
                                not change them. The server gate is the authority; this
                                only stops the form offering a save that would 403. */}
                            {canUpdate && (
                                <Button disabled={processing}>
                                    {t(
                                        processing
                                            ? 'business-settings.action.saving'
                                            : 'business-settings.action.save',
                                    )}
                                </Button>
                            )}
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

/**
 * The server's catalog paired with the words for each code.
 *
 * A code this app has no name for is dropped rather than shown as its raw key — see
 * {@see CURRENCY_NAMES}.
 */
function currencyOptions(codes: string[]): SelectOption[] {
    return codes.flatMap((code) => {
        const label = CURRENCY_NAMES[code];

        return label ? [{ value: code, label }] : [];
    });
}
