import type { ChangeEvent } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { ProductThumb } from '@/pages/products/_components/product-thumb';
import type { TranslationKey } from '@/types/lang';

/**
 * What the file picker offers first. A hint to the operating system, not a check —
 * `accept` is trivially bypassed, and the real answer comes from the zod schema and
 * then from ProductRequest. It exists so that somebody looking for their photo is not
 * shown a folder of spreadsheets.
 */
const ACCEPT = 'image/jpeg,image/png,image/webp';

type Props = {
    /** The field the file is submitted under. */
    name: string;
    /** The flag submitted when a stored photo is removed without replacing it. */
    removeName: string;
    label: TranslationKey;
    hint: TranslationKey;
    removeLabel: TranslationKey;
    /** Describes the preview to a screen reader — the image is the only content here. */
    alt: TranslationKey;
    /** The stored photo, if this product already has one. */
    currentUrl?: string | null;
    error?: string;
};

/**
 * Pick a photo, see it before saving, or take the stored one away.
 *
 * **Three states, and the form has to be able to say which.** No photo; a new file
 * chosen; the stored one removed. A file input alone can only express the first two —
 * "leave it alone" and "replace it" are both "the input is empty" versus "the input has
 * a file". Removal is the third, and it travels as its own flag, rendered only while it
 * is true so that an untouched form sends nothing about the photo at all.
 *
 * The input itself is uncontrolled, like every other field in these dialogs, and for a
 * stronger reason than the rest: a file input's value cannot be set from React at all.
 * What is controlled here is only what is *shown* — which is why pressing Remove has to
 * clear `input.value` by hand rather than by re-rendering.
 *
 * The preview of a newly picked file is an object URL, created in the change handler and
 * released when it is replaced or when the dialog closes. Created there rather than
 * during render on purpose: a URL minted while rendering is minted again on every
 * re-render, and every one of them leaks until the tab is closed.
 */
export function ImageField({
    name,
    removeName,
    label,
    hint,
    removeLabel,
    alt,
    currentUrl,
    error,
}: Props) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;

    const input = useRef<HTMLInputElement>(null);
    // The live object URL, kept in a ref as well as in state so it can be released
    // without the effect that releases it depending on the value it is releasing.
    const objectUrl = useRef<string | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [cleared, setCleared] = useState(false);

    // Only on unmount. Anything picked earlier in the session was already released when
    // it was replaced.
    useEffect(
        () => () => {
            if (objectUrl.current !== null) {
                URL.revokeObjectURL(objectUrl.current);
            }
        },
        [],
    );

    const show = (url: string | null) => {
        if (objectUrl.current !== null) {
            URL.revokeObjectURL(objectUrl.current);
        }

        objectUrl.current = url;
        setPreview(url);
    };

    const onPick = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        // Only preview something the browser can actually draw. Picking a PDF and
        // getting a broken-image glyph reads as "the app is broken" rather than "that is
        // not a photo" — and the message under the field is already saying the second.
        // Anything image-shaped is previewed, including formats the rules refuse: seeing
        // the GIF you chose next to "must be jpg, jpeg, png, webp" explains itself.
        const drawable = file?.type.startsWith('image/') ? file : null;

        show(drawable === null ? null : URL.createObjectURL(drawable));

        // Picking a replacement undoes a removal: somebody who pressed Remove and then
        // chose a file meant the file.
        if (file !== null) {
            setCleared(false);
        }
    };

    const onRemove = () => {
        if (input.current !== null) {
            input.current.value = '';
        }

        show(null);
        setCleared(true);
    };

    const source = preview ?? (cleared ? null : (currentUrl ?? null));
    const hasSomething = source !== null;

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>
                {t(label)}{' '}
                <span className="font-normal text-muted-foreground">
                    {t('common.field.optional')}
                </span>
            </Label>

            <div className="flex items-start gap-4">
                <ProductThumb src={source} alt={t(alt)} size="lg" />

                <div className="min-w-0 flex-1 space-y-2">
                    <Input
                        ref={input}
                        id={id}
                        name={name}
                        type="file"
                        accept={ACCEPT}
                        onChange={onPick}
                        aria-invalid={!!error}
                        aria-describedby={
                            error ? `${hintId} ${errorId}` : hintId
                        }
                    />

                    <p id={hintId} className="text-muted-foreground text-xs">
                        {t(hint)}
                    </p>

                    {hasSomething && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            // Cancels the size's own `px-3` so the label starts on the
                            // same vertical line as the hint above it and the input
                            // above that — a button indented from the column it sits in
                            // reads as belonging to something else. The same trick the
                            // layouts already use on SidebarTrigger. The padding is only
                            // cancelled on the left, so the hover surface still surrounds
                            // the text and the target stays 32px tall.
                            className="-ml-3"
                            onClick={onRemove}
                        >
                            {t(removeLabel)}
                        </Button>
                    )}
                </div>
            </div>

            {/*
                Rendered only while it is true, so a form nobody touched submits no
                opinion about the photo — which is what lets the server treat "absent"
                as "leave it exactly as it is".
            */}
            {cleared && preview === null && (
                <input type="hidden" name={removeName} value="1" />
            )}

            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
