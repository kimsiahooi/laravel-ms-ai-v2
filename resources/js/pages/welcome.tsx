import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

/**
 * Placeholder landing page.
 *
 * The starter kit's marketing page was removed: it hard-coded 26 colour literals,
 * which the design-token guard rejects, and none of it ships in this app. Phase 1
 * replaces this route with a redirect to the tenant/admin entry points.
 */
export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-foreground">
                <div className="flex flex-col items-center gap-2 text-center">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Inventory
                    </h1>
                    <p className="max-w-sm text-muted-foreground text-sm">
                        Multi-tenant inventory and manufacturing management.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>Go to dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={register()}>Register</Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
