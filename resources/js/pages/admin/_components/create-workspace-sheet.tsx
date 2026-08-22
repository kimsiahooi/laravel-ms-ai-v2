import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { useZodGate } from '@/hooks/use-zod-gate';
import { storeTenantSchema } from '@/lib/validation/schemas/store-tenant';
import { store } from '@/routes/admin/tenants';

/** `Acme Trading Sdn Bhd` → `acme-trading-sdn-bhd`. */
function toSlug(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 50);
}

/**
 * Creating a workspace also creates its database and its first administrator, so the
 * form asks for both in one pass — a workspace nobody can sign into is not useful.
 *
 * The slug tracks the name until it is edited by hand, at which point it stops: a
 * slug is part of every URL the workspace will ever have, so silently rewriting a
 * deliberate choice would be worse than a little duplication.
 */
export function CreateWorkspaceSheet({
    trigger,
}: {
    trigger?: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugEdited, setSlugEdited] = useState(false);
    const gate = useZodGate(storeTenantSchema);

    const reset = () => {
        setName('');
        setSlug('');
        setSlugEdited(false);
    };

    return (
        <Sheet
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                }
            }}
        >
            <SheetTrigger asChild>
                {trigger ?? (
                    <Button>
                        <Plus className="size-4" />
                        New workspace
                    </Button>
                )}
            </SheetTrigger>

            <SheetContent className="flex w-full flex-col gap-0 sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>New workspace</SheetTitle>
                    <SheetDescription>
                        Creates the workspace, its own database, and the first
                        administrator who can sign in to it.
                    </SheetDescription>
                </SheetHeader>

                <Form
                    {...store.form()}
                    {...gate}
                    noValidate
                    disableWhileProcessing
                    onSuccess={() => {
                        setOpen(false);
                        reset();
                    }}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 pb-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Workspace name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        value={name}
                                        onChange={(event) => {
                                            setName(event.target.value);

                                            if (!slugEdited) {
                                                setSlug(
                                                    toSlug(event.target.value),
                                                );
                                            }
                                        }}
                                        placeholder="Acme Trading"
                                        autoFocus
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <InputError
                                        role="alert"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="slug">Address</Label>
                                    <div className="flex items-center rounded-md border border-input focus-within:ring-[3px] focus-within:ring-ring/50">
                                        <span className="pl-3 text-muted-foreground text-sm">
                                            /
                                        </span>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            value={slug}
                                            onChange={(event) => {
                                                setSlugEdited(true);
                                                setSlug(event.target.value);
                                            }}
                                            placeholder="acme-trading"
                                            autoCapitalize="none"
                                            spellCheck={false}
                                            aria-invalid={Boolean(errors.slug)}
                                            className="border-0 pl-1 shadow-none focus-visible:ring-0"
                                        />
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Lowercase letters, numbers and hyphens.
                                        This becomes the workspace's sign-in
                                        address and cannot be changed later.
                                    </p>
                                    <InputError
                                        role="alert"
                                        message={errors.slug}
                                    />
                                </div>

                                <div className="space-y-4 rounded-lg border bg-muted/40 p-4">
                                    <p className="font-medium text-sm">
                                        First administrator
                                    </p>

                                    <div className="space-y-2">
                                        <Label htmlFor="admin_name">Name</Label>
                                        <Input
                                            id="admin_name"
                                            name="admin_name"
                                            placeholder="Jane Tan"
                                            aria-invalid={Boolean(
                                                errors.admin_name,
                                            )}
                                        />
                                        <InputError
                                            role="alert"
                                            message={errors.admin_name}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="admin_email">
                                            Email address
                                        </Label>
                                        <Input
                                            id="admin_email"
                                            name="admin_email"
                                            type="email"
                                            autoComplete="off"
                                            placeholder="jane@acme.test"
                                            aria-invalid={Boolean(
                                                errors.admin_email,
                                            )}
                                        />
                                        <InputError
                                            role="alert"
                                            message={errors.admin_email}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="admin_password">
                                            Temporary password
                                        </Label>
                                        <PasswordInput
                                            id="admin_password"
                                            name="admin_password"
                                            autoComplete="new-password"
                                            placeholder="At least 8 characters"
                                            aria-invalid={Boolean(
                                                errors.admin_password,
                                            )}
                                        />
                                        <InputError
                                            role="alert"
                                            message={errors.admin_password}
                                        />
                                    </div>
                                </div>
                            </div>

                            <SheetFooter className="flex-row justify-end border-t">
                                <SheetClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </SheetClose>
                                <Button type="submit">
                                    {processing && <Spinner />}
                                    {processing
                                        ? 'Creating…'
                                        : 'Create workspace'}
                                </Button>
                            </SheetFooter>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
