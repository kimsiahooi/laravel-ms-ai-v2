import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The first administrator, asked for in the same pass as the workspace itself — a
 * workspace nobody can sign into is not useful.
 *
 * Every field here is uncontrolled: nothing derives from them and nothing reads them
 * back, so the DOM is a perfectly good place to keep the values until submit.
 */
export function WorkspaceAdminFields({
    errors,
}: {
    errors: Record<string, string | undefined>;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-4 rounded-lg border bg-muted/40 p-4">
            <p className="font-medium text-sm">
                {t('console.create.admin_section')}
            </p>

            <div className="space-y-2">
                <Label htmlFor="admin_name">
                    {t('console.create.admin_name')}
                </Label>
                <Input
                    id="admin_name"
                    name="admin_name"
                    placeholder={t('console.create.admin_name_placeholder')}
                    aria-invalid={Boolean(errors.admin_name)}
                />
                <InputError role="alert" message={errors.admin_name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="admin_email">
                    {t('console.create.admin_email')}
                </Label>
                <Input
                    id="admin_email"
                    name="admin_email"
                    type="email"
                    autoComplete="off"
                    placeholder={t('console.create.admin_email_placeholder')}
                    aria-invalid={Boolean(errors.admin_email)}
                />
                <InputError role="alert" message={errors.admin_email} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="admin_password">
                    {t('console.create.admin_password')}
                </Label>
                <PasswordInput
                    id="admin_password"
                    name="admin_password"
                    autoComplete="new-password"
                    placeholder={t('console.create.admin_password_placeholder')}
                    aria-invalid={Boolean(errors.admin_password)}
                />
                <InputError role="alert" message={errors.admin_password} />
            </div>
        </div>
    );
}
