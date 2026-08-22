import { usePage } from '@inertiajs/react';

/**
 * The signed-in tenant user's permissions, from shared props.
 *
 * `can(name)` mirrors the server-side gate (AuthorizeTenantRoute) so the UI can hide
 * what a person cannot do. It is convenience, never the security boundary — every
 * gated route is enforced again on the server. `isAdmin` is the built-in
 * Administrator role, which always holds the whole catalog.
 *
 * Outside a workspace (the central /admin area, guest pages) the list is empty and
 * `can()` is uniformly false.
 */
export function usePermissions(): {
    can: (permission: string) => boolean;
    isAdmin: boolean;
} {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];

    return {
        can: (permission: string) => permissions.includes(permission),
        isAdmin: auth?.is_admin ?? false,
    };
}
