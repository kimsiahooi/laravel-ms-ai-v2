import { type InertiaLinkProps, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

/**
 * The two things that can be done with the form: go back, or save it.
 *
 * **It owns the create-or-edit wording**, which is the only reason it is a component rather
 * than markup on the page. Four labels chosen by two booleans is a nested ternary in the middle
 * of a render, and this is the one place it reads as a table instead.
 *
 * `back` differs between the two paths and the page decides it: cancelling a new return returns
 * to the delivery it would have credited, and cancelling an edit returns to the document.
 */
export function ReturnFormFooter({
    editing,
    processing,
    back,
}: {
    editing: boolean;
    processing: boolean;
    back: NonNullable<InertiaLinkProps['href']>;
}) {
    const { t } = useTranslation();

    const submit = editing
        ? 'purchase-returns.edit.submit'
        : 'purchase-returns.create.submit';
    const submitting = editing
        ? 'purchase-returns.edit.submitting'
        : 'purchase-returns.create.submitting';

    return (
        <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
            <Button variant="outline" asChild>
                <Link href={back}>{t('common.actions.cancel')}</Link>
            </Button>
            <Button type="submit" disabled={processing}>
                {processing && <Spinner />}
                {t(processing ? submitting : submit)}
            </Button>
        </div>
    );
}
