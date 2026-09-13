import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { RoleActions } from '@/pages/roles/_components/role-actions';

type Role = App.Data.RoleData;

/**
 * One role, as the list reads it: what it is called, who holds it, and how much it reaches.
 *
 * **Both figures are plural forms rather than a number beside a fixed noun**, because Malay
 * and Chinese do not inflect where English does — and because the zero cases are the ones that
 * matter here. A role nobody holds is exactly the role somebody is about to delete, and the
 * menu beside it only offers Delete in that case.
 *
 * The built-in Administrator carries a badge and a sentence instead of a menu. Saying why it
 * cannot be changed is cheaper than letting somebody discover that the menu is missing.
 */
export function RoleCard({ role }: { role: Role }) {
    const { t, tChoice } = useTranslation();

    return (
        <Card>
            <CardContent className="space-y-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            {/* The workspace's own word for this role — data, not a string
                                with a locale. i18n-allow */}
                            <h2 className="truncate font-medium">
                                {role.name}
                            </h2>
                            {role.is_locked && (
                                <Badge variant="secondary">
                                    {t('roles.card.built_in')}
                                </Badge>
                            )}
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {tChoice('roles.card.holders', role.holders, {
                                count: role.holders,
                            })}
                        </p>
                    </div>

                    <RoleActions role={role} />
                </div>

                <p className="text-sm">
                    {tChoice(
                        'roles.card.permissions',
                        role.permissions.length,
                        { count: role.permissions.length },
                    )}
                </p>

                {role.is_locked && (
                    <p className="text-muted-foreground text-xs">
                        {t('roles.card.locked')}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
