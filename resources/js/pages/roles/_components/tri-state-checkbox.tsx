import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { CheckIcon, MinusIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A checkbox with three states, where the third one looks like a third state.
 *
 * **The vendored `components/ui/checkbox.tsx` accepts `checked="indeterminate"` and then draws
 * a check mark for it**, because its indicator has `CheckIcon` written inside and props only
 * reach the root. So a group with one of four ticked and a group with four of four ticked
 * render the same glyph, differing only in fill — which is exactly the distinction somebody
 * scanning nineteen cards will not make. The dash is the universal answer to "some of these".
 *
 * Composed from `@radix-ui/react-checkbox` rather than forked from the vendored file, which is
 * the escape hatch `docs/CODING-STANDARDS.md` names for the case where passing props cannot
 * reach what needs changing. The classes are the vendored ones plus the indeterminate fill, so
 * the two controls sit beside each other without looking like different components.
 *
 * Private to the roles module until something else needs a tri-state box — rule of three. Its
 * home on a second use is `components/form/`.
 */
export function TriStateCheckbox({
    className,
    checked,
    ...props
}: ComponentProps<typeof CheckboxPrimitive.Root>) {
    return (
        <CheckboxPrimitive.Root
            data-slot="checkbox"
            checked={checked}
            className={cn(
                'peer size-4 shrink-0 rounded-[4px] border border-input shadow-xs outline-none transition-shadow',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
                // The half-state carries the same fill as the full one: the glyph is what
                // tells them apart, and an unfilled box would read as "off".
                'data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator className="flex items-center justify-center text-current transition-none">
                {checked === 'indeterminate' ? (
                    <MinusIcon className="size-3.5" />
                ) : (
                    <CheckIcon className="size-3.5" />
                )}
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}
