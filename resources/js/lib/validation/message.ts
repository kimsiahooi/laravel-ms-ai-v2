import type { Translate, TranslationParams } from '@/lib/i18n';
import type { TranslationKey } from '@/types/lang';

/**
 * How a zod schema carries a *translatable* message.
 *
 * zod hands a plain string to every check, and that string is chosen when the schema
 * is built — at module scope, once, for the life of the process. Baking the active
 * language into it there is exactly the shared-state bug that ruled out a full i18n
 * library for this app: one SSR render would set the language and another would read
 * it. So a check carries a translation *key* instead, encoded into the message slot,
 * and the gate resolves it at parse time with the translator belonging to the render
 * that asked. The schema stays a static, locale-free value.
 *
 * `attribute` is separate from `params` because it is itself a key: Laravel's
 * `:attribute` is filled from `validation.attributes.*`, so it has to be translated
 * before it can be interpolated into the sentence around it. Getting this wrong is
 * visible — it produces "The e-mel pentadbir field must be a valid email address.",
 * which is how the server behaved before these files existed.
 */
export type ValidationMessage = {
    /** A rule's message, e.g. `validation.max.string`. */
    key: TranslationKey;
    /** The field's name, e.g. `validation.attributes.admin_email`. */
    attribute?: TranslationKey;
    /** Everything else the message interpolates, e.g. `{ max: 255 }`. */
    params?: TranslationParams;
};

/**
 * Marks a message slot as carrying a payload rather than a finished sentence. Any
 * message without it is passed through untouched, so a schema written with literal
 * strings still works and forms can be converted one at a time.
 */
const SENTINEL = '@i18n:';

export function encodeMessage(message: ValidationMessage): string {
    return SENTINEL + JSON.stringify(message);
}

export function decodeMessage(message: string): ValidationMessage | null {
    if (!message.startsWith(SENTINEL)) {
        return null;
    }

    try {
        return JSON.parse(message.slice(SENTINEL.length)) as ValidationMessage;
    } catch {
        // A message that merely looks like a payload is still a message.
        return null;
    }
}

/** Turn whatever zod produced into the sentence to show under the field. */
export function resolveMessage(t: Translate, message: string): string {
    const payload = decodeMessage(message);

    if (!payload) {
        return message;
    }

    const { key, attribute, params } = payload;

    return t(key, {
        ...params,
        ...(attribute ? { attribute: t(attribute) } : {}),
    });
}
