import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';

type User = App.Data.UserData;

/**
 * Whether this person can sign in, and whether they have finished joining.
 *
 * **Three states, not two**, and the third is the one worth having. "Deactivated" and
 * "Active" are the obvious pair; "Temporary password" sits between them and says that
 * somebody has been added but is still using the password their administrator chose — a
 * state an administrator should be able to see decaying rather than have to remember.
 *
 * Active with a password of their own is the ordinary case and renders **nothing**. A badge
 * on every row saying "fine" is a column of noise that makes the two rows that are not fine
 * harder to spot, which is the opposite of what a status column is for.
 */
export function UserStatusBadge({ user }: { user: User }) {
    const { t } = useTranslation();

    if (user.deleted_at !== null) {
        return (
            <Badge variant="secondary">{t('users.status.deactivated')}</Badge>
        );
    }

    if (user.must_change_password) {
        return (
            <Badge variant="outline">
                {t('users.status.temporary_password')}
            </Badge>
        );
    }

    return null;
}
