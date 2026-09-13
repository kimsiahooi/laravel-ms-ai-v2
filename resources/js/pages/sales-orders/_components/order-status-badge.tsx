import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Where in its life each status sits.
 *
 * A `Record` over the whole enum rather than a lookup with a fallback, the same trade the
 * purchase-order badge makes: a fourth status becomes a compile error here until somebody
 * says what it looks like, instead of a badge that quietly falls back to grey.
 *
 * **The hues match the purchase-order badge deliberately**, because they mean the same
 * things: `chart-1` is "still open and still editable", `chart-2` is "the stock moved". A
 * reader who has learned them on one order screen has not learned them for one screen.
 * Cancelled is the one with no hue — an order awaiting despatch and an order shipped are
 * both claims about goods, while a cancelled order is a record of nothing having happened,
 * and colour would give it a weight on the page its content does not have.
 */
const TONE: Record<App.Enums.SalesOrderStatus, string> = {
    // Taken, and the only status the order can still be edited in.
    pending: 'border-chart-1/25 bg-chart-1/10 text-chart-1',
    // The goods left and the ledger was written. Not "success" — shipping is a thing that
    // happened, and a short delivery deserves no congratulation.
    fulfilled: 'border-chart-2/25 bg-chart-2/10 text-chart-2',
    cancelled: 'border-transparent bg-muted text-muted-foreground',
};

/**
 * What state a sales order is in, on the list and at the top of the order itself.
 *
 * Shared between the two screens rather than written twice: the list is where somebody
 * learns what the three words mean and the document is where they act on them, and two
 * spellings of "Pending" would make those two different facts.
 *
 * No accessible label of its own — the word is the label, and on the list a screen reader
 * already announces it under the Status column header.
 */
export function OrderStatusBadge({
    status,
}: {
    status: App.Enums.SalesOrderStatus;
}) {
    const { t } = useTranslation();

    return (
        <Badge variant="secondary" className={cn('font-normal', TONE[status])}>
            {t(`sales-orders.status.${status}` as const)}
        </Badge>
    );
}
