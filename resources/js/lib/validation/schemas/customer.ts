import { z } from 'zod';
import {
    optionalEmail,
    optionalOneOf,
    optionalText,
    text,
} from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\CustomerRequest and the `customers` columns.
 *
 * A factory, not a constant, and only because of `country_code`: the valid codes are
 * App\Enums\Country's, and they arrive as a page prop so the browser cannot end up
 * checking against a list the server has since changed. Every other field is static, so
 * the caller memoises this on the codes and builds it once per page rather than once
 * per render.
 *
 * `bun run check:validation` builds it with the codes in that script's FACTORY_ARGS and
 * fails if this and the FormRequest stop covering the same fields.
 */
export const customerSchema = (countries: readonly string[]) =>
    z.object({
        name: text({ attribute: 'validation.attributes.name', max: 255 }),
        contact_person: optionalText({
            attribute: 'validation.attributes.contact_person',
            max: 255,
        }),
        email: optionalEmail({
            attribute: 'validation.attributes.email',
            max: 255,
        }),
        phone: optionalText({
            attribute: 'validation.attributes.phone',
            max: 50,
        }),

        tin: optionalText({ attribute: 'validation.attributes.tin', max: 100 }),
        registration_no: optionalText({
            attribute: 'validation.attributes.registration_no',
            max: 100,
        }),
        sst_registration_no: optionalText({
            attribute: 'validation.attributes.sst_registration_no',
            max: 100,
        }),

        address: optionalText({
            attribute: 'validation.attributes.address',
            max: 1000,
        }),
        city: optionalText({
            attribute: 'validation.attributes.city',
            max: 100,
        }),
        postcode: optionalText({
            attribute: 'validation.attributes.postcode',
            max: 20,
        }),
        state_code: optionalText({
            attribute: 'validation.attributes.state_code',
            max: 10,
        }),
        // Not merely "two characters" — a made-up code travels into an e-invoice.
        country_code: optionalOneOf({
            values: countries,
            attribute: 'validation.attributes.country_code',
        }),

        notes: optionalText({
            attribute: 'validation.attributes.notes',
            max: 1000,
        }),
    });
