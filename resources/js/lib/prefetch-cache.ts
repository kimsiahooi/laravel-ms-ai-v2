import { router } from '@inertiajs/react';

/**
 * Throw away prefetched pages whenever something is written.
 *
 * Nav links are marked `prefetch`, so Inertia fetches a page on hover and serves that
 * copy when the link is clicked. Nothing invalidates it on its own, which makes a
 * write in between invisible: hover "Archive", archive a workspace, click "Archive" —
 * and the cached, still-empty page is what renders. It looks like the archive lost the
 * row, and a manual refresh "fixes" it, which is the tell.
 *
 * A non-GET visit is the signal that something on the server changed. What changed is
 * not knowable from here, so every prefetched page is dropped rather than guessed at.
 * Inertia can flush by cache tag instead, but that means tagging each link correctly
 * and forever — and a missed tag brings this bug back silently, which is exactly the
 * failure mode worth designing out.
 *
 * Registered once from the client entry, next to the other one-time setup.
 */
export function invalidatePrefetchOnWrite(): void {
    // Never on the server: there is no cache there, and no navigation to invalidate.
    if (typeof window === 'undefined') {
        return;
    }

    router.on('finish', (event) => {
        const visit = (event as CustomEvent).detail?.visit;

        // Reads do not invalidate anything — and prefetches themselves are reads.
        if (!visit || visit.method === 'get') {
            return;
        }

        router.flushAll();
    });
}
