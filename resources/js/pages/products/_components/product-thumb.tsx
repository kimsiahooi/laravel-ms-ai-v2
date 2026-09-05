import { ImageIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

const SIZE = {
    /** In a table cell, beside the product's name. */
    sm: { box: 'size-10', icon: 'size-4' },
    /** In the form dialog, showing what is currently stored or newly picked. */
    lg: { box: 'size-20', icon: 'size-7' },
} as const;

/**
 * A product's photo in a fixed square, or a placeholder in the same square when there
 * is none.
 *
 * The placeholder is the point. A list where only some rows have a picture would
 * otherwise have its names starting at two different offsets, and the eye reads that as
 * two kinds of row. An empty frame keeps the column straight and says "no photo yet"
 * rather than "nothing here".
 *
 * `object-contain` on a neutral ground, never `object-cover`: product photos arrive in
 * whatever shape the supplier's catalog used, and cropping to fill a square is how the
 * label ends up outside the frame.
 */
export function ProductThumb({
    src,
    alt,
    size = 'sm',
}: {
    src?: string | null;
    /**
     * Empty in a list, where the product's name is already the adjacent text and
     * repeating it only makes a screen reader say everything twice. A real sentence
     * where the image stands alone.
     */
    alt: string;
    size?: keyof typeof SIZE;
}) {
    const { box, icon } = SIZE[size];

    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted',
                box,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt={alt}
                    // Twenty-five rows of photographs, most of them below the fold on a
                    // phone.
                    loading="lazy"
                    className="size-full object-contain"
                />
            ) : (
                <ImageIcon
                    className={cn('text-muted-foreground/60', icon)}
                    aria-hidden="true"
                />
            )}
        </div>
    );
}
