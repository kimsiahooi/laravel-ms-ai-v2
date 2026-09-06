import { useId } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import type { TranslationKey } from '@/types/lang';

/**
 * One money-or-quantity box in a repeating row: labelled, right-aligned, and wired to
 * its own message.
 *
 * **A cell rather than a field, and the difference is who holds the value.** Every
 * `*-field` here is uncontrolled — seeded from `defaultValue`, and the DOM keeps what
 * was typed, which is right for a form nobody reads back. This one reports every
 * keystroke, because the order editor above it is recomputing a total while somebody
 * types. Reach for {@see TextField} everywhere else; giving it a controlled mode would
 * put a second way of writing every form in the app on offer.
 *
 * The label is hidden from `sm` up rather than dropped, because the column header
 * disappears below that breakpoint and the label is then the only thing saying what the
 * box is for.
 *
 * `inputMode="decimal"`, never `type="number"`, for the reason TextField gives — and it
 * matters more here: a wheel passing over a focused number input silently changes it,
 * and in this row that is a price.
 */
export function DecimalCell({
    name,
    label,
    placeholder,
    value,
    onChange,
    error,
}: {
    /** The name the server reads. The error bag keys the failure under it too. */
    name: string;
    label: TranslationKey;
    placeholder: TranslationKey;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;

    return (
        <div className="space-y-2">
            <Label htmlFor={id} className="sm:sr-only">
                {t(label)}
            </Label>
            <Input
                id={id}
                name={name}
                inputMode="decimal"
                autoComplete="off"
                className="text-right tabular-nums"
                placeholder={t(placeholder)}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={!!error}
                aria-describedby={error ? errorId : undefined}
            />
            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
