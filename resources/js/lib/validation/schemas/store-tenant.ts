import { z } from 'zod';

/**
 * Mirrors App\Http\Requests\Central\StoreTenantRequest. The server stays the
 * authority; this refuses the same values before the request is built, so a typo
 * costs a keystroke rather than a round trip.
 *
 * `bun run check:validation` fails if the two ever stop covering the same fields.
 */

/**
 * Lowercase kebab, and the same shape as the {tenant} route pattern — a workspace
 * whose slug does not match it could never be reached by URL.
 */
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export const storeTenantSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'The name field is required.')
        .max(255, 'The name field must not be greater than 255 characters.'),
    slug: z
        .string()
        .trim()
        .min(1, 'The slug field is required.')
        // 50, so `<db prefix><slug>` fits MySQL's 64-character database-name limit.
        .max(50, 'The slug field must not be greater than 50 characters.')
        .regex(
            SLUG,
            'The slug may only contain lowercase letters, numbers and hyphens.',
        ),
    admin_name: z
        .string()
        .trim()
        .min(1, 'The administrator name field is required.')
        .max(
            255,
            'The administrator name field must not be greater than 255 characters.',
        ),
    admin_email: z
        .string()
        .trim()
        .min(1, 'The administrator email field is required.')
        .max(
            255,
            'The administrator email field must not be greater than 255 characters.',
        )
        .email('The administrator email field must be a valid email address.'),
    admin_password: z
        .string()
        .min(
            8,
            'The administrator password field must be at least 8 characters.',
        ),
});
