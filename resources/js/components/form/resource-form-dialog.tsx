import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { ZodType } from 'zod';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/feedback/dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { useZodGate } from '@/hooks/use-zod-gate';
import type { TranslationParams } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';
import type { RouteFormDefinition } from '@/wayfinder';

/** The error bag Inertia hands the render prop, keyed by field name. */
type Errors = Record<string, string>;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** A Wayfinder form variant — `store.form()`, or `update.form({ id })`. */
    action: RouteFormDefinition<'post'>;
    /** The schema that refuses the same values the FormRequest does. */
    schema: ZodType;
    title: TranslationKey;
    description: TranslationKey;
    /**
     * What the heading's `:placeholders` are filled with — the record's own name,
     * where the dialog is about one particular row.
     *
     * One bag for both lines rather than two props: the title and the description are
     * two sentences about the same thing, and a dialog that named a different record
     * in each would be a bug rather than a feature.
     */
    headingParams?: TranslationParams;
    submit: TranslationKey;
    submitting: TranslationKey;
    /**
     * How much room the fields need. `sm` suits two or three; `lg` gives a form with
     * a two-column grid somewhere to put it. Spelled out rather than passed as a class
     * because Tailwind cannot see a class assembled at runtime.
     */
    size?: 'sm' | 'lg';
    children: (state: { processing: boolean; errors: Errors }) => ReactNode;
};

const WIDTH = {
    sm: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
} as const;

/**
 * The dialog every create-or-edit form in the app is built from: the shell, the
 * submission, the zod gate and the footer. The caller supplies only its fields and
 * its words.
 *
 * **Create and edit are the same component, told apart by the `action` handed in.**
 * They validate identically and post to the same controller, so the only real
 * difference is a URL and four strings — and keeping them together is what stops one
 * mode growing a field the other forgot.
 *
 * Fields are expected to be **uncontrolled**, seeded with `defaultValue`. Radix
 * unmounts this content when the dialog closes, so they take their values with them:
 * an abandoned edit cannot reappear on the next open and no reset is needed. The
 * corollary is that a caller must keep the record it is editing in state until the
 * dialog has finished closing — clearing it early swaps the copy mid-animation.
 *
 * Every write is expected to `back()`, so `preserveScroll` is not optional here: the
 * list re-renders in place and the page must not jump to the top on each save.
 */
export function ResourceFormDialog({
    open,
    onOpenChange,
    action,
    schema,
    title,
    description,
    headingParams,
    submit,
    submitting,
    size = 'sm',
    children,
}: Props) {
    const { t } = useTranslation();
    const gate = useZodGate(schema);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {/*
                A column with a ceiling, rather than the primitive's plain grid. A
                seven-field form in Malay on a 375x667 phone is taller than the screen,
                and the primitive centres it with no max-height and no overflow — so it
                hangs off BOTH ends, unscrollable, with the title clipped above and the
                Cancel button unreachable below.

                Capping the height and giving the fields their own scroll region keeps
                the heading and the buttons where they can always be reached. Done here,
                by composition: ui/dialog.tsx is vendored and read-only. The padding is
                moved off the container and onto the three regions so the scrollbar sits
                at the dialog's edge instead of inside a 24px gutter.
            */}
            <DialogContent
                className={cn(
                    'flex max-h-[calc(100dvh-2rem)] flex-col gap-0 p-0',
                    WIDTH[size],
                )}
            >
                <DialogHeader className="border-b p-6">
                    <DialogTitle>{t(title, headingParams)}</DialogTitle>
                    <DialogDescription>
                        {t(description, headingParams)}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...action}
                    {...gate}
                    // Without this the browser's own validation bubble fires first and
                    // the gate never runs.
                    noValidate
                    disableWhileProcessing
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* min-h-0 is what lets a flex child actually shrink and
                                scroll; without it the region grows to fit its content
                                and pushes the footer off the screen again. */}
                            <div className="min-h-0 flex-1 overflow-y-auto p-6">
                                {children({ processing, errors })}
                            </div>

                            <DialogFooter className="border-t p-6">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        {t('common.actions.cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit">
                                    {processing && <Spinner />}
                                    {t(processing ? submitting : submit)}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
