import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { Paginated } from '@/types';

type Props = {
    // Only the counts matter here; the row type is irrelevant.
    page: Pick<
        Paginated<unknown>,
        'current_page' | 'last_page' | 'from' | 'to' | 'total'
    >;
    onPage: (page: number) => void;
};

/**
 * Prev/next paging with a live count. A single page still shows the count — how many
 * results a search returned is the useful part, not the buttons.
 */
export function PaginationBar({ page, onPage }: Props) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-muted-foreground text-sm">
                {page.total === 0
                    ? t('common.pagination.no_results')
                    : t('common.pagination.showing', {
                          from: page.from ?? 0,
                          to: page.to ?? 0,
                          total: page.total,
                      })}
            </p>

            {page.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={page.current_page <= 1}
                        onClick={() => onPage(page.current_page - 1)}
                    >
                        <ChevronLeft className="size-4" />
                        {t('common.pagination.previous')}
                    </Button>
                    <span className="px-1 text-muted-foreground text-sm tabular-nums">
                        {t('common.pagination.page_of', {
                            current: page.current_page,
                            last: page.last_page,
                        })}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={page.current_page >= page.last_page}
                        onClick={() => onPage(page.current_page + 1)}
                    >
                        {t('common.pagination.next')}
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            )}
        </div>
    );
}
