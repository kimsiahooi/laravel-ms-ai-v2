import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A link that has to announce itself.
 *
 * Sitting in a table cell or inside a sentence, surrounded by text that is not
 * clickable — a category name, a warehouse, "3 products". Nothing about the position
 * of these says they can be followed, so the styling has to: the primary colour and a
 * **standing** underline, which is the one convention every reader already knows. An
 * underline that only appears on hover asks somebody to find the link before it will
 * admit to being one, and on a touch screen there is no hover at all.
 *
 * The colour is the `text-link` token rather than `text-primary`. The primary is a
 * *surface* colour — it is contrast-checked against `primary-foreground` sitting on top
 * of it, not against the page behind it, so text in it can fail against the background.
 *
 * **Not {@see TextLink}, which is the opposite trade.** That one is for links in prose
 * on a page that is mostly links already — the auth screens' "Forgot password?" — where
 * standing out is unnecessary and colouring every one of them would make the page a
 * ransom note. This is for the link that would otherwise be invisible.
 *
 * Here rather than beside any one of its callers because there are five, in three
 * modules and in both a table and a dialog. They were five copies of the same
 * two-hundred-character class string, and the fifth is the one that drifted: the
 * warehouse name kept the underline for hover only and so read as plain bold text.
 */
export function InlineLink({
    className,
    children,
    ...props
}: ComponentProps<typeof Link>) {
    return (
        <Link
            className={cn(
                'rounded-sm text-link underline underline-offset-4 ring-offset-background transition-colors hover:text-link-hover focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-offset-2',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
