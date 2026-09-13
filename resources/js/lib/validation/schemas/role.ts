import { z } from 'zod';
import { manyOf, text } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\RoleRequest.
 *
 * A factory, because the catalog arrives as a page prop: the editor renders whatever
 * `TenantPermissions::matrix()` sent, and checking against a list compiled into the bundle
 * would mean a new screen's permissions being refused here until somebody rebuilt. The same
 * argument `customerSchema` makes about country codes.
 *
 * The ceiling is the size of the catalog itself, which is the honest bound: a role cannot
 * reach more screens than exist. The server says the same thing with
 * `'max:'.count(TenantPermissions::names())`.
 */
export const roleSchema = (permissions: readonly string[]) =>
    z.object({
        name: text({ attribute: 'validation.attributes.name', max: 255 }),
        permissions: manyOf({
            values: permissions,
            attribute: 'validation.attributes.permissions',
            max: permissions.length,
            // Not "the permissions field is required" — see `manyOf`. The FormRequest
            // overrides its own message with this same key.
            empty: 'roles.validation.permissions',
        }),
    });
