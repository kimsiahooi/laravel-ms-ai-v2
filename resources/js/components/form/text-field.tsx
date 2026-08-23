import { useId } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import type { TranslationKey } from '@/types/lang';

type Props = {
    /** The name the server reads, and the key the error bag files a failure under. */
    name: string;
    label: TranslationKey;
    /** Whichever of the zod gate's or Laravel's messages arrived for this field. */
    error?: string;
    defaultValue?: string | null;
    placeholder?: TranslationKey;
    /**
     * Marks the field as one the form will accept empty, and drops the `required`
     * attribute. One prop rather than a separate `required`, so the two can never
     * contradict each other — in these forms, not optional means required.
     */
    optional?: boolean;
    autoFocus?: boolean;
    autoComplete?: string;
    /** `email` and `tel` get the right keyboard on a phone. */
    type?: 'text' | 'email' | 'tel';
    /** Render as a textarea this many rows tall instead of a single line. */
    rows?: number;
};

/**
 * A labelled text input with its error underneath, and the accessibility wiring that
 * connects the two.
 *
 * That wiring is the reason this exists. `aria-invalid` on the control,
 * `aria-describedby` pointing at the message, and the message carrying the matching id
 * — three details that have to agree, are invisible when they do not, and are exactly
 * what gets forgotten on the twentieth form. Here they agree by construction, from one
 * `useId`.
 *
 * Uncontrolled by design: the dialog that hosts it is unmounted on close, so
 * `defaultValue` is the whole of state management. See {@see ResourceFormDialog}.
 *
 * Text only. A select, a combobox or a date picker gets its own component rather than
 * this one growing a `kind` prop and a branch per control.
 */
export function TextField({
    name,
    label,
    error,
    defaultValue,
    placeholder,
    optional,
    autoFocus,
    autoComplete = 'off',
    type = 'text',
    rows,
}: Props) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;

    const shared = {
        id,
        name,
        defaultValue: defaultValue ?? '',
        required: !optional,
        autoFocus,
        autoComplete,
        placeholder: placeholder ? t(placeholder) : undefined,
        'aria-invalid': !!error,
        'aria-describedby': error ? errorId : undefined,
    };

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>
                {t(label)}
                {optional && (
                    // An explicit `{' '}`, not a margin. A margin is the obvious fix
                    // and only half of one: it puts a gap on screen, but the accessible
                    // name is built by concatenating text nodes, and nothing is inserted
                    // between two inline elements — so a screen reader announced
                    // "Barcodeoptional". `{' '}` survives (JSX drops whitespace between
                    // lines, not an expression) and fixes both at once.
                    <>
                        {' '}
                        <span className="font-normal text-muted-foreground">
                            {t('common.field.optional')}
                        </span>
                    </>
                )}
            </Label>

            {rows === undefined ? (
                <Input type={type} {...shared} />
            ) : (
                <Textarea rows={rows} {...shared} />
            )}

            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
