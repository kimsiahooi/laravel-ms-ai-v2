import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { useZodGate } from '@/hooks/use-zod-gate';
import { storeTenantSchema } from '@/lib/validation/schemas/store-tenant';
import { WorkspaceAdminFields } from '@/pages/admin/_components/workspace-admin-fields';
import { WorkspaceIdentityFields } from '@/pages/admin/_components/workspace-identity-fields';
import { store } from '@/routes/admin/tenants';

/**
 * Creating a workspace also creates its database and its first administrator, so the
 * form asks for both in one pass. This file owns the sheet and the submission; the
 * two field groups own their own fields.
 *
 * There is no reset: Radix unmounts the sheet's content when it closes, so the field
 * groups take their state with them and reopen empty. That is worth stating rather
 * than assuming — it is the reason a half-filled form abandoned with Escape cannot
 * come back on the next click.
 */
export function CreateWorkspaceSheet({ trigger }: { trigger?: ReactNode }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const gate = useZodGate(storeTenantSchema);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                {trigger ?? (
                    <Button>
                        <Plus className="size-4" />
                        {t('console.create.trigger')}
                    </Button>
                )}
            </SheetTrigger>

            <SheetContent className="flex w-full flex-col gap-0 sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>{t('console.create.title')}</SheetTitle>
                    <SheetDescription>
                        {t('console.create.description')}
                    </SheetDescription>
                </SheetHeader>

                <Form
                    {...store.form()}
                    {...gate}
                    noValidate
                    disableWhileProcessing
                    onSuccess={() => setOpen(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 pb-6">
                                <WorkspaceIdentityFields errors={errors} />
                                <WorkspaceAdminFields errors={errors} />
                            </div>

                            <SheetFooter className="flex-row justify-end border-t">
                                <SheetClose asChild>
                                    <Button type="button" variant="outline">
                                        {t('common.actions.cancel')}
                                    </Button>
                                </SheetClose>
                                <Button type="submit">
                                    {processing && <Spinner />}
                                    {processing
                                        ? t('console.create.submitting')
                                        : t('console.create.submit')}
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
