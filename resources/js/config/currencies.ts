import type { TranslationKey } from '@/types/lang';

/**
 * The names of the currencies this app knows, keyed by ISO code, and the two small
 * helpers that read the list a workspace actually allows.
 *
 * **Promoted here on the third copy, exactly as predicted.** The settings screen had the
 * first and the purchase-order form the second, whose docblock said: *"It moves to a
 * shared module on the third copy, which is sales orders; two is not yet a pattern."*
 * Sales orders arrived, so it moved.
 *
 * A `Record` of `TranslationKey` rather than a function that lowercases the code, so the
 * compiler proves each entry exists. The generated approach would prove nothing and
 * render `business-settings.currency.jpy` on screen the day a sixth currency arrives.
 *
 * A code with no entry here is left out of the picker rather than offered under its own
 * key: adding a currency means naming it in `lang/`, and this is what says so.
 *
 * In `config/` because it is a static descriptor — data, not logic, and no React and no
 * imports from `components/`. The option shape is written out structurally rather than
 * imported from `SelectField`, which keeps the dependency pointing one way; it is
 * assignable to `SelectOption` because that is what it is.
 */
export const CURRENCY_NAMES: Record<string, TranslationKey> = {
    MYR: 'business-settings.currency.myr',
    SGD: 'business-settings.currency.sgd',
    USD: 'business-settings.currency.usd',
    EUR: 'business-settings.currency.eur',
    CNY: 'business-settings.currency.cny',
};

/**
 * The workspace's own money — the first code in the list, and not by luck.
 *
 * `BusinessSetting::allowedCurrencies()` builds the list as `[base, ...chosen]` and
 * dedupes, precisely so the base is always offerable; the ordering is the same fact looked
 * at from here. Sending it as its own prop would be a second copy of it, and the two could
 * disagree.
 */
export function baseCurrency(currencies: string[]): string {
    return currencies[0] ?? '';
}

/** The codes this workspace allows, in the order it allows them, named in `lang/`. */
export function currencyOptions(
    currencies: string[],
): { value: string; label: TranslationKey }[] {
    return currencies
        .filter((code) => code in CURRENCY_NAMES)
        .map((code) => ({ value: code, label: CURRENCY_NAMES[code] }));
}
