import { Link } from '@inertiajs/react';
import { ShieldPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { create } from '@/routes/roles';

/**
 * Starts a new role.
 *
 * A link rather than a dialog trigger, because the editor is a page — see `roles/form.tsx`.
 * The permission only decides whether the button is offered; `roles.create` is enforced on the
 * route itself.
 */
export function NewRoleButton() {
    const { t } = useTranslation();
    const { can } = usePermissions();

    if (!can('roles.create')) {
        return null;
    }

    return (
        <Button asChild>
            <Link href={create()}>
                <ShieldPlus className="size-4" />
                {t('roles.action.new')}
            </Link>
        </Button>
    );
}
