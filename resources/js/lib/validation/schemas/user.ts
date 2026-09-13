import { z } from 'zod';
import {
    confirmed,
    email,
    id,
    optionalPassword,
    optionalText,
    password,
    text,
} from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\UserRequest.
 *
 * A factory for two reasons rather than the usual one. The role ids arrive as a page
 * prop, so the browser cannot end up checking against a list the server has since
 * changed — the same argument `customerSchema` makes about country codes. And the
 * password is required when adding somebody and optional when editing them, which is the
 * one rule on this form that genuinely differs between the two.
 *
 * **The password check is a floor, not a mirror**, and that is deliberate — see
 * {@link password} for why matching `Password::defaults()` here is not possible and
 * would be a lie if attempted. `bun run check:validation` only asks that every field the
 * server validates is checked here at all.
 *
 * `password_confirmation` has no server rule of its own — Laravel's `confirmed` reads it
 * off the request — so it exists here purely so the mismatch is caught before the round
 * trip, under the box that was mistyped.
 */
export const userSchema = (roles: readonly number[], editing: boolean) =>
    z
        .object({
            name: text({ attribute: 'validation.attributes.name', max: 255 }),
            email: email({
                attribute: 'validation.attributes.email',
                max: 255,
            }),
            // Not merely "a number": a role that does not exist would be refused by the
            // server's `exists` rule a round trip later, and the picker only ever offers
            // these.
            role_id: id({
                ids: roles,
                attribute: 'validation.attributes.role_id',
            }),
            password: editing
                ? optionalPassword({
                      attribute: 'validation.attributes.password',
                  })
                : password({ attribute: 'validation.attributes.password' }),
            password_confirmation: optionalText({
                attribute: 'validation.attributes.password',
                max: 255,
            }),
        })
        .superRefine(
            confirmed({
                field: 'password',
                attribute: 'validation.attributes.password',
            }),
        );
