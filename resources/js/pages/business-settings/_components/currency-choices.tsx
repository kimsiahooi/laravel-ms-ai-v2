import { useId, useState } from 'react';
import type { SelectOption } from '@/components/form/select-field';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Which currencies an order may be raised in — several at once, from a short list the
 * server owns.
 *
 * **Not `SelectField` or `ComboboxField`, because neither takes more than one.** The
 * combobox is also the wrong shape for a different reason: it picks a row the workspace
 * owns, addressed by a numeric id, and a currency is a three-letter code from a fixed
 * catalog whose names live in `lang/`. Five checkboxes need no search box.
 *
 * **The base currency is ticked and cannot be unticked**, because
 * `BusinessSetting::allowedCurrencies()` folds it in whatever this form sends. Showing
 * it as a free choice would mean offering somebody a tick they can clear and that
 * nothing acts on. A disabled checkbox is not submitted by the browser, so the value
 * travels in the hidden inputs below instead — which is where every ticked code
 * travels, for the same reason `SelectField` posts through one: the visible control is
 * Radix's, and what reaches the server should be something this component states
 * outright rather than something a form serializer infers.
 */
export function CurrencyChoices({
    options,
    chosen,
    base,
    error,
}: {
    /** The catalog, already paired with the translation key naming each code. */
    options: SelectOption[];
    /** The codes ticked when the form opened. */
    chosen: string[];
    /** The workspace's base currency — always allowed, so always ticked. */
    base: string;
    error?: string;
}) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const [picked, setPicked] = useState(chosen);

    // The hint and the message both describe the GROUP, so they hang off the fieldset
    // rather than off each checkbox — repeated on five controls, one sentence about the
    // base currency becomes five announcements of it.
    const describedBy = [hintId, error ? errorId : null]
        .filter(Boolean)
        .join(' ');

    // The base may be missing from `picked` for one submit — somebody can change the
    // base currency above without touching these ticks — so it is added here rather
    // than assumed to be in state.
    const submitted = picked.includes(base) ? picked : [base, ...picked];

    const toggle = (code: string): void =>
        setPicked((current) =>
            current.includes(code)
                ? current.filter((one) => one !== code)
                : [...current, code],
        );

    return (
        <fieldset className="space-y-2" aria-describedby={describedBy}>
            <legend className="font-medium text-sm leading-none">
                {t('business-settings.field.currencies')}
            </legend>

            <div className="space-y-0.5 rounded-md border p-2">
                {options.map((option) => {
                    const locked = option.value === base;

                    return (
                        // Associated by id rather than by wrapping, for the reason
                        // CheckboxFilter gives: Radix renders a button, which is not a
                        // label's implicit control.
                        <div
                            key={option.value}
                            className="flex items-center gap-2 rounded-sm px-2 py-1.5"
                        >
                            <Checkbox
                                id={`${id}-${option.value}`}
                                checked={submitted.includes(option.value)}
                                disabled={locked}
                                onCheckedChange={() => toggle(option.value)}
                            />
                            <Label
                                htmlFor={`${id}-${option.value}`}
                                className={cn(
                                    'flex-1 font-normal text-sm',
                                    locked
                                        ? 'text-muted-foreground'
                                        : 'cursor-pointer',
                                )}
                            >
                                {t(option.label)}
                            </Label>
                        </div>
                    );
                })}
            </div>

            {submitted.map((code) => (
                <input
                    key={code}
                    type="hidden"
                    name="currencies[]"
                    value={code}
                />
            ))}

            <p id={hintId} className="text-muted-foreground text-xs">
                {t('business-settings.field.currencies_hint')}
            </p>

            <InputError id={errorId} role="alert" message={error} />
        </fieldset>
    );
}
