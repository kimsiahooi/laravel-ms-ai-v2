import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Where in its life each return sits.
 *
 * A `Record` over the whole enum rather than a lookup with a fallback, the trade
 * {@see OrderStatusBadge} makes: a fourth status becomes a compile error here until
 * somebody says what it looks like, instead of a badge that quietly falls back to grey.
 *
 * **The hues are the order statuses', deliberately.** Pending is pending whichever document
 * it is on, and a reader who learned the colours on the orders list has not learned them for
 * one screen. Cancelled is again the one with no hue: it is a record of nothing having
 * happened, and colour would give it a weight its content does not have.
 */
const TONE: Record<App.Enums.ReturnStatus, string> = {
    // Raised, and the only status the return can still be edited in.
    pending: 'border-chart-1/25 bg-chart-1/10 text-chart-1',
    // The goods went back and the ledger was written. Not "success" — a return is a thing
    // that happened, and nobody is pleased about it.
    completed: 'border-chart-2/25 bg-chart-2/10 text-chart-2',
    cancelled: 'border-transparent bg-muted text-muted-foreground',
};

/**
 * What state a return is in, on the list and at the top of the return itself.
 *
 * Shared by both screens rather than written twice, so the list where somebody learns the
 * three words and the document where they act on them cannot spell them differently.
 */
export function ReturnStatusBadge({
    status,
}: {
    status: App.Enums.ReturnStatus;
}) {
    const { t } = useTranslation();

    return (
        <Badge variant="secondary" className={cn('font-normal', TONE[status])}>
            {t(`returns.status.${status}` as const)}
        </Badge>
    );
}
