import { SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/use-translation';
import type { FilterApi } from '@/types';

/**
 * One button holding every filter a list has.
 *
 * Built before the lists that need it, deliberately. Raw materials filter by one thing
 * today; stock movements will filter by warehouse, direction and a date range, and
 * orders by customer, status and date. Three or four controls do not fit a toolbar row
 * on a phone — they stack, and push the table they are meant to narrow off the screen.
 * Deciding the shape once means every later list inherits it rather than being
 * retrofitted.
 *
 * **The count on the button is not decoration.** A filter behind a button is a filter
 * you cannot see, and someone arriving from a link or the back button would find rows
 * missing with nothing on screen to say why. The badge is what keeps a narrowed list
 * honest while the panel is shut.
 *
 * A popover on a desk and a sheet on a phone, because a four-field form anchored to a
 * small button has nowhere to go at 375px. Both render the same children; only the
 * container differs. `useIsMobile` reports the server's answer during hydration and
 * re-renders after, so the two sides never disagree — and the panel is shut on first
 * render anyway, where the difference is invisible.
 */
export function FilterPanel({
    filter,
    children,
}: {
    filter: FilterApi;
    /** The controls themselves — one {@see SelectFilter} per thing to narrow by. */
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const isMobile = useIsMobile();
    const [open, setOpen] = useState(false);

    const trigger = (
        <Button variant="outline" size="sm" className="w-full sm:w-auto">
            <SlidersHorizontal className="size-4" />
            {t('common.filter.trigger')}
            {filter.count > 0 && (
                <Badge
                    variant="secondary"
                    className="ml-1 h-5 min-w-5 justify-center px-1 tabular-nums"
                >
                    {filter.count}
                </Badge>
            )}
        </Button>
    );

    const body = (
        <>
            <div className="space-y-4">{children}</div>

            {filter.count > 0 && (
                <>
                    <Separator className="my-4" />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="w-full"
                        onClick={() => {
                            filter.clear();
                            setOpen(false);
                        }}
                    >
                        {t('common.filter.clear_all')}
                    </Button>
                </>
            )}
        </>
    );

    return (
        <div className="flex w-full items-center gap-2 sm:w-auto">
            {isMobile ? (
                <Sheet open={open} onOpenChange={setOpen}>
                    <SheetTrigger asChild>{trigger}</SheetTrigger>
                    <SheetContent side="bottom" className="max-h-[80dvh]">
                        <SheetHeader>
                            <SheetTitle>
                                {t('common.filter.trigger')}
                            </SheetTitle>
                            <SheetDescription>
                                {t('common.filter.description')}
                            </SheetDescription>
                        </SheetHeader>
                        <div className="overflow-y-auto px-4 pb-6">{body}</div>
                    </SheetContent>
                </Sheet>
            ) : (
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>{trigger}</PopoverTrigger>
                    <PopoverContent
                        align="end"
                        // Tall enough for four controls, and scrolls past that rather
                        // than growing off the bottom of a short window.
                        className="max-h-[24rem] w-72 overflow-y-auto"
                    >
                        {body}
                    </PopoverContent>
                </Popover>
            )}

            {/*
                A one-click reset, for the common case of "show me everything again"
                without opening the panel to do it. Only when there is something to
                reset — otherwise it is a button that does nothing.
            */}
            {filter.count > 0 && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={filter.clear}
                >
                    {t('common.filter.clear')}
                </Button>
            )}
        </div>
    );
}
