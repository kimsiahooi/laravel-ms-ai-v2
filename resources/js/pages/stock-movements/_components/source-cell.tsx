import { InlineLink } from '@/components/inline-link';
import { useTranslation } from '@/hooks/use-translation';
import { show as showStockTake } from '@/routes/stock-takes';

type Source = App.Enums.MovementSource;

/**
 * What caused this row, named in the reader's language and opened where there is
 * something to open.
 *
 * **The words are chosen here, not stored.** Until this column existed the reference was
 * spelled into `notes` at posting time — v1 did it in six Actions by concatenation, and
 * v2's stock takes did it through the translator, which was worse: it froze the poster's
 * language into a column every locale reads. The row now holds `stock_take` and `12`, and
 * the sentence is built at render time out of the asking reader's bundle.
 *
 * **Only some sources have a screen.** A stock take has a count sheet to go and look at; a
 * transfer does not, because transfers are a list with no detail page. Rather than link to
 * something that does not exist, the label renders as plain text — and the day transfers
 * grow a detail screen, this is the one place that changes.
 */
export function SourceCell({
    type,
    id,
}: {
    type: Source | null;
    id: number | null;
}) {
    const { t } = useTranslation();

    // Null for a hand-recorded adjustment — nothing caused it but a person — and for
    // every row written before the column existed. i18n-allow
    if (type === null || id === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    const label = t(`stock-movements.source.${type}`, { id });

    if (type === 'stock_take') {
        return (
            <InlineLink href={showStockTake({ stockTake: id })}>
                {label}
            </InlineLink>
        );
    }

    return <span className="whitespace-nowrap">{label}</span>;
}
