import { z } from 'zod';
import { encodeMessage } from '@/lib/validation/message';
import type { TranslationKey } from '@/types/lang';

/**
 * The building blocks a schema is written from, each one shaped like the Laravel rule
 * it mirrors and each one speaking the user's language.
 *
 * Write `text({ attribute: 'validation.attributes.name', max: 255 })` where the
 * FormRequest says `['required', 'string', 'max:255']`, and the browser refuses the
 * value with the sentence the server would have used — same rule, same field name,
 * same translation file. `lang/{locale}/validation.php` is that shared source; see
 * `message.ts` for why the key travels instead of the finished string.
 *
 * `attribute` is a key, not a label. It names the field the way Laravel names it
 * (`validation.attributes.*`), which is also where Laravel reads it from, so neither
 * side can drift into calling the same field something different.
 *
 * Only the primitives the app actually uses live here. The numeric ones — quantities,
 * money, the decimal-scale guard that stops MySQL silently rounding an invoice — come
 * with the stock and order modules that need them, along with the message keys they
 * introduce. Adding one before its consumer would mean guessing at both.
 */

/** A rule's message, bound to the field it is about. */
function message(
    key: TranslationKey,
    attribute: TranslationKey,
    params?: Record<string, string | number>,
) {
    return encodeMessage({ key, attribute, params });
}

type TextOptions = {
    /** A `validation.attributes.*` key naming this field. */
    attribute: TranslationKey;
    /** `max:N` — the column's length. */
    max?: number;
    /** `min:N` characters. */
    min?: number;
    /** `regex:…`, with the message the FormRequest gives it. */
    pattern?: { regex: RegExp; message?: TranslationKey };
};

/**
 * A required string — `['required', 'string', …]`.
 *
 * The checks are declared in the order Laravel evaluates them, which matters: only the
 * first failure per field is shown, and an empty value should read "is required"
 * rather than "must be at least 8 characters".
 */
export function text({ attribute, max, min, pattern }: TextOptions) {
    let schema = z
        .string(message('validation.string', attribute))
        .trim()
        .min(1, message('validation.required', attribute));

    if (min !== undefined) {
        schema = schema.min(
            min,
            message('validation.min.string', attribute, { min }),
        );
    }

    if (max !== undefined) {
        schema = schema.max(
            max,
            message('validation.max.string', attribute, { max }),
        );
    }

    if (pattern) {
        schema = schema.regex(
            pattern.regex,
            pattern.message
                ? encodeMessage({ key: pattern.message, attribute })
                : message('validation.regex', attribute),
        );
    }

    return schema;
}

/**
 * An optional string — `['nullable', 'string', 'max:N']`.
 *
 * No `min(1)`, and that is the whole difference from {@see text}: a field nobody typed
 * in submits `''`, not `undefined`, and Laravel's `nullable` accepts exactly that. Only
 * the ceiling is checked, and it is the column's length.
 *
 * `.optional()` on top covers the other shape the same field takes — absent entirely,
 * from a form that does not render it.
 */
export function optionalText({
    attribute,
    max,
}: {
    attribute: TranslationKey;
    max?: number;
}) {
    const schema = z.string(message('validation.string', attribute)).trim();

    return (
        max === undefined
            ? schema
            : schema.max(
                  max,
                  message('validation.max.string', attribute, { max }),
              )
    ).optional();
}

/**
 * A required value from a fixed list — `['required', Rule::enum(...)]`.
 *
 * The {@see optionalOneOf} of this, with one difference that matters: empty is a
 * failure, and it reports "is required" rather than "is invalid". Somebody who has not
 * touched the picker has not chosen a wrong value, they have not chosen one — and the
 * two sentences send them to different places.
 *
 * `values` arrives at call time for the same reason as below: the list is the server's,
 * sent as a page prop, so the browser cannot end up checking against a stale copy.
 */
export function oneOf({
    values,
    attribute,
}: {
    values: readonly string[];
    attribute: TranslationKey;
}) {
    return z
        .string(message('validation.string', attribute))
        .trim()
        .min(1, message('validation.required', attribute))
        .refine(
            (value) => values.includes(value),
            message('validation.enum', attribute),
        );
}

/**
 * An optional value from a fixed list — `['nullable', Rule::enum(...)]` or
 * `Rule::in(...)`.
 *
 * `values` arrives at call time rather than being baked in, because the list is the
 * server's: it is sent as a page prop so that adding a country cannot leave the browser
 * checking against a stale copy. That makes the schema a value built per render, which
 * is why the caller memoises it.
 *
 * Empty is acceptable — an untouched picker submits `''`, and that is what `nullable`
 * accepts on the server.
 */
export function optionalOneOf({
    values,
    attribute,
}: {
    values: readonly string[];
    attribute: TranslationKey;
}) {
    return z
        .string(message('validation.string', attribute))
        .trim()
        .refine(
            (value) => value === '' || values.includes(value),
            // `validation.enum`, not `validation.in`: the server side of this is
            // Rule::enum, and Laravel picks the message by rule name. Two keys with
            // identical English would still diverge the moment one is translated.
            message('validation.enum', attribute),
        )
        .optional();
}

/**
 * An optional reference to a row the workspace owns — `['nullable', ActiveExists::of(…)]`.
 *
 * The browser cannot answer the question the server is asking. Whether row 7 still
 * exists, and is not trashed, is a fact about the database at the moment of the request.
 * What this checks is the honest browser-side half: the value is one of the ids that
 * were sent to the picker. That catches the ordinary failure — a stale page offering a
 * category somebody deleted in another tab — and leaves the authoritative answer to
 * `ActiveExists`.
 *
 * The message is `validation.exists`, not `validation.enum`, because the rule it mirrors
 * is `exists`. The two have identical English today and would diverge the moment either
 * is reworded — the same trap `Rule::enum` set earlier.
 *
 * Ids arrive as strings because that is what a form submits, empty string included.
 */
export function optionalId({
    ids,
    attribute,
}: {
    ids: readonly number[];
    attribute: TranslationKey;
}) {
    const known = new Set(ids.map(String));

    return z
        .string(message('validation.string', attribute))
        .trim()
        .refine(
            (value) => value === '' || known.has(value),
            message('validation.exists', attribute),
        )
        .optional();
}

/**
 * The address shape itself, with no message of its own — {@see optionalEmail} supplies
 * one. Built once at module scope because it carries no locale and never changes.
 */
const EMAIL_SHAPE = z.email();

/**
 * An optional email address — `['nullable', 'string', 'email', 'max:N']`.
 *
 * Not a union with `z.literal('')`, which is the obvious spelling and the wrong one: a
 * union reports every branch's failure, so a typo would come back as "expected empty
 * string" alongside the real message. Trimming first and then treating empty as
 * acceptable produces one sentence, and it is the sentence Laravel would have used.
 *
 * Empty is acceptable because that is what an untouched input submits — `''`, never
 * `undefined` — and it is exactly what `nullable` accepts on the server.
 */
export function optionalEmail({
    attribute,
    max,
}: {
    attribute: TranslationKey;
    max?: number;
}) {
    const address = z
        .string(message('validation.string', attribute))
        .trim()
        .refine(
            (value) => value === '' || EMAIL_SHAPE.safeParse(value).success,
            message('validation.email', attribute),
        );

    return (
        max === undefined
            ? address
            : address.max(
                  max,
                  message('validation.max.string', attribute, { max }),
              )
    ).optional();
}

/**
 * A required email address — `['required', 'string', 'email', 'max:N']`.
 *
 * Piped rather than chained so the address is checked *after* trimming. Laravel's
 * `TrimStrings` middleware trims before its own `email` rule runs, and a browser that
 * refused " a@b.co " while the server accepted it would be worse than no check at all.
 */
export function email({
    attribute,
    max,
}: {
    attribute: TranslationKey;
    max?: number;
}) {
    return text({ attribute, max }).pipe(
        z.email(message('validation.email', attribute)),
    );
}
