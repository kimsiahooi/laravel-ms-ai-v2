import { XIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    DialogClose,
    DialogContent as DialogContentPrimitive,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * The app's dialog. **Import dialogs from here, never from `@/components/ui/dialog`** —
 * `bun run check:structure` fails the build if anything does.
 *
 * The reason is one word, and it is `Close`. The vendored `DialogContent` renders its
 * dismiss button with a hard-coded English `sr-only` label, so every dialog in the app
 * announced "Close" to a screen reader in Malay and in Chinese. It is invisible on
 * screen, which is exactly why it survived four modules unnoticed.
 *
 * The primitive is not edited — it is vendored and read-only. It already takes
 * `showCloseButton`, so this turns its button off and renders one that reads its label
 * from `lang/`. Everything else is re-exported untouched, which is what makes the swap a
 * one-line change at each call site and leaves nothing to remember.
 *
 * Two things improve on the way through, neither of them the point but both worth having:
 * the replacement is a real `Button`, so it picks up the same focus ring, hover and
 * disabled behaviour as every other control; and it is a 36px target rather than the
 * primitive's 16px, which was under the 24px minimum.
 *
 * It lives in `feedback/` because that is where the app keeps the surfaces it interrupts
 * somebody with — `ConfirmDialog` is already here.
 */
export {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export function DialogContent({
    className,
    children,
    showCloseButton = true,
    ...props
}: ComponentProps<typeof DialogContentPrimitive>) {
    const { t } = useTranslation();

    return (
        // Always false: the primitive's own button is the one with the English label.
        // Ours is rendered below, under the same `showCloseButton` prop so the component
        // is a drop-in replacement rather than a similarly-named thing.
        <DialogContentPrimitive
            {...props}
            showCloseButton={false}
            className={cn(
                // Keep the title out from under the button. It is absolutely positioned,
                // so a long one runs straight beneath it — "Delete Stainless steel
                // folding step stool, 3-tread, powder coated?" does exactly that at
                // 375px, and Malay wraps to three lines and does it twice. Reserving the
                // room on the title rather than the header leaves the description, which
                // sits below the button and never collides with it, at full width.
                //
                // 32px on top of the header's own 24px clears the button's 49px, and on a
                // phone — where the header centres its text — centring what is left is
                // the correct place for it to be anyway.
                showCloseButton && '**:data-[slot=dialog-title]:pr-8',
                className,
            )}
        >
            {children}

            {showCloseButton && (
                <DialogClose asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        // Positioning cannot live in a token, and the primitive is the
                        // positioning context (it is `fixed`).
                        className="absolute top-3 right-3"
                    >
                        <XIcon />
                        <span className="sr-only">
                            {t('common.actions.close')}
                        </span>
                    </Button>
                </DialogClose>
            )}
        </DialogContentPrimitive>
    );
}
