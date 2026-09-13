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
 * Only the primitives the app actually uses live here. Money, dates and percentages
 * still have no consumer and so no primitive; each arrives with the module that needs
 * it, along with the message keys it introduces.
 */

/**
 * Decimal places a `decimal(15,4)` column stores, and the largest value one holds —
 * 11 integer digits, since 4 of the 15 are spent after the point.
 *
 * The same two numbers as `TenantFormRequest::DECIMAL_SCALE` and `::DECIMAL_MAX`.
 * Duplicated rather than generated because they describe a column shape that is fixed
 * by a migration, not a policy either side is free to change: if these ever disagree
 * with the PHP constants, one of the two is wrong about the database.
 */
export const DECIMAL_SCALE = 4;
export const DECIMAL_MAX = 99_999_999_999;

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
 * Whether a value is a file the browser picked. Duck-typed rather than
 * `z.instanceof(File)`, because these schemas are built during *render*, and render also
 * happens inside the Node process that server-renders the page.
 *
 * `z.instanceof` reads the `File` global when the schema is constructed, so it makes the
 * page depend on the SSR runtime having a browser global. Node has had one since v20 and
 * this app runs v24, so nothing is broken today — the point is that a shape check reads
 * nothing at all until something is parsed, which only ever happens in a browser on
 * submit. It cannot become a version problem later.
 */
function isFile(value: unknown): value is File {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as File).name === 'string' &&
        typeof (value as File).size === 'number' &&
        typeof (value as File).type === 'string'
    );
}

/**
 * An optional upload — `['nullable', 'image', 'mimes:…', 'max:N']`.
 *
 * The checks are the ones worth doing before the bytes leave: a two-megabyte photo that
 * the server is going to refuse costs an upload, a wait, and a re-pick, and on a phone
 * it costs them out of a data plan. Everything the server checks *about the file itself*
 * is checkable here.
 *
 * `mimes` and `values` are the same fact in two spellings, and both are needed. A
 * browser reports what it picked as a mime type (`image/jpeg`) and knows nothing about
 * the extension, while Laravel's rule matches on the extension and prints that list in
 * its message. Deriving one from the other would mean either checking something the
 * browser cannot see or showing a sentence the server would never have written.
 *
 * A field nobody touched is *absent*, not empty: Inertia drops an empty file input
 * before the data is assembled, so `undefined` is the untouched case and `.optional()`
 * is what accepts it.
 */
export function optionalFile({
    attribute,
    mimes,
    values,
    maxKb,
}: {
    attribute: TranslationKey;
    /** What the browser must report — `['image/jpeg', …]`. */
    mimes: readonly string[];
    /** What the message lists — Laravel's `:values`, e.g. `'jpg, jpeg, png, webp'`. */
    values: string;
    /** `max:N`, in kilobytes, exactly as the rule counts it. */
    maxKb: number;
}) {
    return (
        z
            // First, and its own message: something that is not a file at all is not a
            // file of the wrong type. zod stops here when this fails, so the two checks
            // below can assume a file.
            .custom<File>(isFile, message('validation.image', attribute))
            .refine(
                (file) => mimes.includes(file.type),
                message('validation.mimes', attribute, { values }),
            )
            .refine(
                (file) => file.size <= maxKb * 1024,
                message('validation.max.file', attribute, { max: maxKb }),
            )
            .optional()
    );
}

/**
 * An optional on/off flag — `['nullable', 'boolean']`.
 *
 * Not `z.boolean()`. A form submits strings, and the only way a checkbox or a hidden
 * marker reaches the server is as `'1'`, `'0'`, or not at all — which is also precisely
 * what Laravel's `boolean` rule accepts from a request. Anything else is a form that has
 * been tampered with, and it should be refused with the sentence the server would use.
 */
export function optionalFlag({ attribute }: { attribute: TranslationKey }) {
    return z
        .string(message('validation.boolean', attribute))
        .refine(
            (value) => value === '' || value === '0' || value === '1',
            message('validation.boolean', attribute),
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

/**
 * A required reference to a row the workspace owns — `['required', ActiveExists::of(…)]`.
 *
 * The {@see optionalId} of this, differing only in that empty is a failure and reports
 * "is required" rather than "is invalid" — the same distinction {@see oneOf} draws, and
 * for the same reason: not choosing is not the same as choosing wrongly.
 */
export function id({
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
        .min(1, message('validation.required', attribute))
        .refine(
            (value) => known.has(value),
            message('validation.exists', attribute),
        );
}

type DecimalOptions = {
    /** A `validation.attributes.*` key naming this field. */
    attribute: TranslationKey;
    /** `decimal:0,S`. Defaults to the column scale. */
    scale?: number;
    /** `max:M`. Defaults to what the column holds. */
    max?: number;
    /**
     * `gt:N`. The lower bound the value must exceed. Defaults to 0, which is the right
     * bound for a quantity being moved.
     */
    gt?: number;
    /**
     * `gte:N` instead of `gt:N`, when the bound itself is a legitimate answer.
     *
     * The two are mutually exclusive and `gte` wins if both are given. It exists for
     * one real case: a movement that *sets* a level rather than moving an amount, where
     * zero means "the shelf is empty" and refusing it would make an empty shelf
     * unrecordable. See `stockMovementSchema`.
     */
    gte?: number;
};

/**
 * A required `decimal(15,4)` quantity — the browser half of
 * `TenantFormRequest::decimalRules()`.
 *
 * **The scale check is the point of this existing.** MySQL's strict mode refuses too
 * many integer digits but silently *rounds* extra decimal places, so `1.12345` would be
 * accepted and stored as `1.1235` with nothing to show for it. The server's
 * `decimal:0,4` refuses that, and this refuses it here first.
 *
 * A string, not `z.number()`. A form submits strings, and `Number("")` is 0 — so a
 * numeric schema would read an empty box as a legitimate zero and report the wrong
 * failure, or none. Parsing is left until after the shape has been checked.
 *
 * Empty is the one thing this decides for itself — it is the difference between this
 * and {@see optionalDecimal}, and everything after it is shared. See
 * {@see checkDecimal}.
 */
export function decimal(options: DecimalOptions) {
    return z
        .string(message('validation.string', options.attribute))
        .trim()
        .superRefine((value, ctx) => {
            if (value === '') {
                ctx.addIssue({
                    code: 'custom',
                    message: message('validation.required', options.attribute),
                });

                return;
            }

            checkDecimal(value, ctx, options);
        });
}

/**
 * The same field where an empty box is a legitimate answer — `['nullable', …]`.
 *
 * Not `decimal(...).optional()`, which would still refuse `''`: a form sends an
 * untouched input as the empty string, not as `undefined`, so "optional" has to mean
 * empty-able to be worth anything. `.optional()` is on top as well, for the other shape
 * the field takes — absent from a payload that never rendered it.
 *
 * **Empty is not zero.** The one caller so far is a reorder level, where the difference
 * carries the whole meaning: zero is a threshold somebody chose and empty is no
 * threshold at all. Turning one into the other here would put that decision in the
 * wrong layer, so this reports nothing and lets the server decide what absence means.
 */
export function optionalDecimal(options: DecimalOptions) {
    return z
        .string(message('validation.string', options.attribute))
        .trim()
        .superRefine((value, ctx) => {
            if (value === '') {
                return;
            }

            checkDecimal(value, ctx, options);
        })
        .optional();
}

/**
 * Everything both of the above check, for a value already known to be non-empty.
 *
 * Early returns rather than a chain of checks, so exactly one issue is produced:
 * `"abc"` fails all four, and which message surfaces should be the first that applies
 * rather than whichever the issue list happens to order first.
 */
function checkDecimal(
    value: string,
    ctx: z.RefinementCtx,
    {
        attribute,
        scale = DECIMAL_SCALE,
        max = DECIMAL_MAX,
        gt = 0,
        gte,
    }: DecimalOptions,
) {
    const fail = (
        key: TranslationKey,
        params?: Record<string, string | number>,
    ) => {
        ctx.addIssue({
            code: 'custom',
            message: message(key, attribute, params),
        });
    };

    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        return fail('validation.numeric');
    }

    // Laravel's own `decimal` rule, verbatim: an optional sign, digits, an optional
    // point, digits. It is what makes `1e3` — which `numeric` accepts — a failure here
    // too, so the two layers agree on more than the digit count.
    const digits = /^[+-]?\d*\.?(\d*)$/.exec(value)?.[1];

    if (digits === undefined || digits.length > scale) {
        // `:decimal` is "0-4", the way Laravel renders a range — see
        // ReplacesAttributes::replaceDecimal.
        return fail('validation.decimal', { decimal: `0-${scale}` });
    }

    if (gte !== undefined) {
        if (parsed < gte) {
            return fail('validation.gte.numeric', { value: gte });
        }
    } else if (parsed <= gt) {
        return fail('validation.gt.numeric', { value: gt });
    }

    if (parsed > max) {
        return fail('validation.max.numeric', { max });
    }
}

/**
 * An optional calendar day, with an optional time on it — the browser half of
 * `['nullable', 'date_format:Y-m-d,Y-m-d H:i']`.
 *
 * Three shapes and no others: `''`, `2026-10-15`, `2026-10-15 14:30`. They are the wire
 * contract with `PurchaseOrderRequest::expectedInstant()`, which tells them apart by the
 * space, and stores them exactly as sent — nothing converts a promised delivery date.
 *
 * **A calendar day, deliberately not an instant.** The day carries no zone; the server
 * anchors it to the zone the picker was in. Parsing it here as an instant would make it a
 * different day for a reader west of UTC.
 *
 * **Zero-padded, because the server is.** Laravel's `date_format` re-formats what it
 * parsed and compares it to the input, so `2026-10-15 9:30` fails there. A browser that
 * accepted it would pass the value to a server that refuses it, which is the one thing
 * two-layer validation exists to prevent.
 *
 * **Not stricter than the server, either.** The picker offers quarter-hours, but the
 * minute is checked as `0-59` rather than against that grid: a schema that refuses a
 * value the server accepts makes stored data uneditable, which is the trap the
 * `taxable` note in `schemas/purchase-order.ts` describes.
 *
 * The message key is `validation.date_format`, the same one the server would use, with
 * the same `:format` — Laravel interpolates only the *first* of the two accepted formats
 * into that sentence, so `Y-m-d` is what both sides say.
 */
export function optionalDateTime(attribute: TranslationKey) {
    return z
        .string(message('validation.string', attribute))
        .trim()
        .refine(
            (value) => value === '' || isCalendarDateTime(value),
            message('validation.date_format', attribute, { format: 'Y-m-d' }),
        )
        .optional();
}

/** `HH:MM`, zero-padded, 24-hour — exactly what `H:i` round-trips through. */
const CLOCK = /^([01]\d|2[0-3]):[0-5]\d$/;

/** Whether `value` is one of the three shapes above. */
function isCalendarDateTime(value: string): boolean {
    const parts = value.split(' ');

    if (parts.length === 1) {
        return isCalendarDate(parts[0]);
    }

    return (
        parts.length === 2 && isCalendarDate(parts[0]) && CLOCK.test(parts[1])
    );
}

/** Whether `value` is a real `Y-m-d` day rather than merely a string shaped like one. */
function isCalendarDate(value: string): boolean {
    const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (parts === null) {
        return false;
    }

    const date = new Date(`${value}T00:00:00Z`);

    if (Number.isNaN(date.getTime())) {
        return false;
    }

    // A month that rolled over — `2026-02-31` becomes the 3rd of March — comes back as
    // a different day than it went in as, which is the whole test.
    return (
        date.getUTCFullYear() === Number(parts[1]) &&
        date.getUTCMonth() + 1 === Number(parts[2]) &&
        date.getUTCDate() === Number(parts[3])
    );
}

/**
 * A repeating group of rows — `['nullable', 'array', 'max:N']` over `items.*.…`.
 *
 * Optional, not merely empty-able: a form with no rows renders no inputs, so the key is
 * absent from the payload rather than present and empty. That is the shape the server
 * accepts too — see BomRequest on why absent and empty mean the same thing there.
 *
 * `distinct` is checked here rather than left to the server because the failure is
 * about the list as a whole and has to be reported on one row. The issue is filed at
 * the *second* occurrence, which is the one somebody just chose, and under that row's
 * own field — so the message lands beside the picker that repeats rather than at the
 * top of a list of ten.
 */
export function lines<T extends z.ZodType>({
    item,
    max,
    attribute,
    distinct,
}: {
    item: T;
    /** `max:N` — the ceiling on how many rows may be sent. */
    max: number;
    /** A `validation.attributes.*` key naming the collection. */
    attribute: TranslationKey;
    /** The field no two rows may share, and the key naming it. */
    distinct?: { field: string; attribute: TranslationKey };
}) {
    const rows = z
        .array(item, message('validation.array', attribute))
        .max(max, message('validation.max.array', attribute, { max }));

    if (!distinct) {
        return rows.optional();
    }

    return rows
        .superRefine((values, ctx) => {
            const seen = new Set<unknown>();

            values.forEach((row, index) => {
                const value = (row as Record<string, unknown>)[distinct.field];

                // An empty picker is not a duplicate of another empty picker — it is
                // two rows nobody has filled in, and `required` already says so.
                if (value === undefined || value === '') {
                    return;
                }

                if (seen.has(value)) {
                    ctx.addIssue({
                        code: 'custom',
                        path: [index, distinct.field],
                        message: message(
                            'validation.distinct',
                            distinct.attribute,
                        ),
                    });

                    return;
                }

                seen.add(value);
            });
        })
        .optional();
}

/**
 * `different:other` — two fields that must not hold the same value.
 *
 * A check on the object rather than on a field, because zod cannot see a sibling from
 * inside one. Returns the callback for `.superRefine()`, so a schema reads in the order
 * Laravel's rules do: fields first, then the rule that spans them.
 *
 * The issue is filed against `field`, not the pair, so it lands under the input the
 * reader should change — Laravel does the same, reporting `different` on the attribute
 * carrying the rule rather than on the one it names.
 *
 * Both names are translated: `:other` is a field name too, and a Malay sentence naming
 * an English field is the bug `message.ts` describes.
 */
export function different({
    field,
    other,
    attribute,
    otherAttribute,
}: {
    /** The field the rule is written on, and where the message lands. */
    field: string;
    /** The field it must differ from. */
    other: string;
    attribute: TranslationKey;
    otherAttribute: TranslationKey;
}) {
    return (value: Record<string, unknown>, ctx: z.RefinementCtx): void => {
        const picked = value[field];

        // Nothing to compare until both are filled, and `required` has already said so.
        // Two empty boxes are not "the same warehouse twice".
        if (picked === undefined || picked === null || picked === '') {
            return;
        }

        if (picked !== value[other]) {
            return;
        }

        ctx.addIssue({
            code: 'custom',
            path: [field],
            message: encodeMessage({
                key: 'validation.different',
                attribute,
                others: { other: otherAttribute },
            }),
        });
    };
}

/**
 * A password, as the browser can check one.
 *
 * **This deliberately does not mirror the server's rule, and that is not a gap.**
 * `Password::defaults()` is set in `AppServiceProvider` to six checks in production —
 * length, mixed case, letters, numbers, symbols, and `uncompromised()` — and to nothing
 * at all outside it. One of those checks needs a network call to Have I Been Pwned, and
 * the whole set changes by environment. A mirror that tried to match it would be wrong
 * in development, wrong about the leak check everywhere, and would drift the first time
 * the policy moved.
 *
 * So this is a floor: something true in every environment, which catches the empty box
 * and the four-character attempt before a round trip. The server stays the authority and
 * says so — anything it refuses lands under the field as the server's own sentence.
 *
 * `min` defaults to 8, which is `Password::min(8)`'s default and therefore the weakest
 * the server could ever be.
 */
export function password({
    attribute,
    min = 8,
}: {
    attribute: TranslationKey;
    min?: number;
}) {
    // No `.trim()`, unlike every other string here: leading and trailing spaces are part
    // of a password, and silently removing them would let somebody set one the sign-in
    // form then refuses.
    return z
        .string(message('validation.string', attribute))
        .min(1, message('validation.required', attribute))
        .min(min, message('validation.min.string', attribute, { min }));
}

/**
 * The same, where leaving it blank means "don't change it".
 *
 * An edit form's password box submits `''` when untouched, which Laravel's `nullable`
 * accepts — so the floor applies only once somebody has typed something.
 */
export function optionalPassword({
    attribute,
    min = 8,
}: {
    attribute: TranslationKey;
    min?: number;
}) {
    return z.union([
        z.literal(''),
        z
            .string(message('validation.string', attribute))
            .min(min, message('validation.min.string', attribute, { min })),
    ]);
}

/**
 * `confirmed` — a field that must match its `_confirmation` twin.
 *
 * A check on the object rather than on a field, for the reason {@see different} gives:
 * zod cannot see a sibling from inside one. Returns the callback for `.superRefine()`.
 *
 * The issue lands on the **confirmation** field rather than on the field itself, which is
 * where Laravel puts it too and where the reader can act on it — the original is right and
 * the copy is the one that was mistyped.
 *
 * Nothing is compared while the original is empty: `required` has already said that, and
 * two empty boxes are not a mismatch.
 */
export function confirmed({
    field,
    attribute,
}: {
    /** The field carrying the rule. Its twin is `{field}_confirmation`. */
    field: string;
    attribute: TranslationKey;
}) {
    return (value: Record<string, unknown>, ctx: z.RefinementCtx): void => {
        const picked = value[field];

        if (picked === undefined || picked === null || picked === '') {
            return;
        }

        if (picked === value[`${field}_confirmation`]) {
            return;
        }

        ctx.addIssue({
            code: 'custom',
            path: [`${field}_confirmation`],
            message: encodeMessage({ key: 'validation.confirmed', attribute }),
        });
    };
}
