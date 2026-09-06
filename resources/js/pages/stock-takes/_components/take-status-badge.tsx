import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * Where in its life each status sits.
 *
 * A `Record` over the whole enum on purpose, the same trade {@see ReasonCell} makes:
 * a fourth status would be a compile error here until somebody says what it looks
 * like, rather than a badge that silently falls back to grey.
 *
 * **Cancelled is the one with no hue, and that is the point.** Draft and posted are
 * both claims about stock — one being made, one already applied — while a cancelled
 * take is a record of nothing having happened, and colouring it would give it a weight
 * on the page that its content does not have.
 */
const TONE: Record<App.Enums.StockTakeStatus, string> = {
    // Underway, and the only status anything can still be done to. The brand hue, the
    // same one transfers use for stock that has not left the business.
    draft: 'border-chart-1/25 bg-chart-1/10 text-chart-1',
    // Applied. Green would be the obvious choice and the wrong one — posting is not a
    // success, it is a thing that happened, and a count that wrote a large correction
    // deserves no congratulation.
    posted: 'border-chart-2/25 bg-chart-2/10 text-chart-2',
    cancelled: 'border-transparent bg-muted text-muted-foreground',
};

/**
 * What state a stock take is in, on the list and at the top of its own sheet.
 *
 * Shared between the two screens rather than written twice, because the list is where
 * somebody learns what the three words mean and the sheet is where they act on them —
 * two spellings of "In progress" would make those two different facts.
 *
 * **A tint and coloured text, not a solid fill.** Twenty-five saturated badges down a
 * page stop being labels and start being the page. And the colour is never the only
 * channel: the badge says the status in words, in the reader's language, and the hue is
 * reinforcement for somebody skimming the column rather than information for somebody
 * reading it.
 *
 * No accessible label of its own. The word is the label, and on the list a screen
 * reader already announces it under the Status column header.
 */
export function TakeStatusBadge({
    status,
}: {
    status: App.Enums.StockTakeStatus;
}) {
    const { t } = useTranslation();

    return (
        <Badge variant="secondary" className={cn('font-normal', TONE[status])}>
            {t(`stock-takes.status.${status}` as const)}
        </Badge>
    );
}
