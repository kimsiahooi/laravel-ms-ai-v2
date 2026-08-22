import { z } from 'zod';
import { email, text } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Central\StoreTenantRequest. The server stays the
 * authority; this refuses the same values before the request is built, so a typo
 * costs a keystroke rather than a round trip.
 *
 * Each line reads as its FormRequest counterpart does, and the messages come from the
 * same `lang/` files the server uses — so a value refused here and a value refused
 * there produce one sentence, in the user's language, not two in English.
 *
 * `bun run check:validation` fails if the two ever stop covering the same fields.
 */

/**
 * Lowercase kebab, and the same shape as the {tenant} route pattern — a workspace
 * whose slug does not match it could never be reached by URL.
 */
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export const storeTenantSchema = z.object({
    name: text({ attribute: 'validation.attributes.name', max: 255 }),
    slug: text({
        attribute: 'validation.attributes.slug',
        // 50, so `<db prefix><slug>` fits MySQL's 64-character database-name limit.
        max: 50,
        pattern: { regex: SLUG, message: 'console.validation.slug_regex' },
    }),
    admin_name: text({
        attribute: 'validation.attributes.admin_name',
        max: 255,
    }),
    admin_email: email({
        attribute: 'validation.attributes.admin_email',
        max: 255,
    }),
    admin_password: text({
        attribute: 'validation.attributes.admin_password',
        min: 8,
    }),
});

/**
 * Reserved slugs and slugs already taken are checked by the server alone: one is a
 * list that lives in PHP, the other is a database lookup. Both come back through the
 * same error bag as everything above.
 */
