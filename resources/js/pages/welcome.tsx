import { Head, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Central landing page: a workspace picker.
 *
 * Every application route is prefixed `/{tenant}/`, so there is no single sign-in
 * URL — each workspace has its own at `/{slug}/login`. This page turns a workspace
 * name into that URL. (Linking to `login()` here is what produced a 404: with no
 * tenant in scope the route helper cannot fill in `{tenant}`.)
 *
 * An unknown slug lands on the 404 page, which is the same answer the server gives
 * for any wrong workspace address.
 */
export default function Welcome() {
    const [slug, setSlug] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const workspace = slug
            .trim()
            .toLowerCase()
            .replace(/^\/+|\/+$/g, '');

        if (workspace !== '') {
            router.visit(`/${workspace}/login`);
        }
    };

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6 text-foreground">
                <div className="w-full max-w-sm space-y-6">
                    <div className="space-y-2 text-center">
                        <h1 className="font-semibold text-2xl tracking-tight">
                            Inventory
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Enter your workspace to sign in.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="workspace">Workspace</Label>
                            <Input
                                id="workspace"
                                name="workspace"
                                value={slug}
                                onChange={(event) =>
                                    setSlug(event.target.value)
                                }
                                placeholder="acme"
                                autoComplete="organization"
                                autoCapitalize="none"
                                spellCheck={false}
                                required
                            />
                            <p className="text-muted-foreground text-xs">
                                The name in your address bar, e.g.
                                <span className="font-medium"> /acme</span>
                            </p>
                        </div>

                        <Button type="submit" className="w-full">
                            Continue
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}
